<?php
header('Content-Type: application/json; charset=utf-8');
set_time_limit(0); // 無制限に実行できるようにする
// 環境変数の読み込み
require_once $_SERVER['DOCUMENT_ROOT'] . '/env.php';

set_time_limit(0); // 無制限に実行できるようにする

/** 00分のニュース原稿プロンプト */
$prompt00 = getEcho('/exec/0-makePrompt/00.php');
/** 15分のニュース原稿プロンプト */
$prompt15 = getEcho('/exec/0-makePrompt/15.php');
/** 30分のニュース原稿プロンプト */
$prompt30 = getEcho('/exec/0-makePrompt/30.php');
/** 45分のニュース原稿プロンプト */
$prompt45 = getEcho('/exec/0-makePrompt/45.php');

$text00 = makeText($prompt00, 0);
outputToFile('00.txt', $text00);

$text15 = makeText($prompt15, 15);
outputToFile('15.txt', $text15);

$text30 = makeText($prompt30, 30);
outputToFile('30.txt', $text30);

$text45 = makeText($prompt45, 45);
outputToFile('45.txt', $text45);

echo "00.txt, 15.txt, 30.txt, 45.txt にニュース原稿を保存しました。\n";

/**
 * Geminiに投げる
 *
 * @param string $prompt AIに投げるプロンプト
 * @param integer $min 放送時刻（分）
 * @return string ニュース原稿
 */
function makeText ($prompt = '', $min = 0) {
  global $API_KEYs;

  /** HTTPホスト */
  $httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
  /**
   * Gemini用curl
   */
  $URL = 'http://' . $httpHost . '/request.php';


  $requestData = [
    'prompt' => $prompt
  ];

  /** 試行回数 */
  $requestCnt = 0;
  $err503cnt = 0;

  while (true) {
    echo "Geminiに質問しています…\n";
    $ch = curl_init($URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'apikey: ' . $API_KEYs[$requestCnt]
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($requestData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    if ($response === false) {
      echo 'Failed to send request to ' . $URL;
      break;
    }

    $json = json_decode($response, true);

    $errCode = 200;
    if(isset($json['result']) && isset($json['result']['error']['code']) && $json['result']['error']['code']) {
      $errCode = $json['result']['error']['code'];
    }
    if($errCode == 503) {
      echo "{$errCode}エラー（アクセス過多）のため、LLMでの生成に切り替えます\n";
      echo "次の{$min}分に放送する原稿作成は、GeminiがビジーなためLLMでの生成に切り替えます\n";
      return askLocalAI($prompt);
    } else if ($errCode == 429) {
      echo "{$requestCnt}番のキーは{$errCode}エラーのため、別のキーで再実行します\n";
      // 3秒待って、APIキーを変えて再試行
      if(count($API_KEYs) - 1 <= $requestCnt) {
        echo "次の{$min}分に放送する原稿作成について、どのAPIキーでもリクエストを実行できませんでした\n";
        getEcho("/sendMattermost.php?sendMessage=次の{$min}分に放送する原稿作成について、どのAPIキーでもリクエストを実行できませんでした");
        return json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
      }
      sleep(3);
      $requestCnt++;
      continue;
    }
    echo "Geminiからの返答あり。APIキーは環境変数の" . ($requestCnt + 1) . "番を使用しました\n";
    return $json['result']['candidates'][0]['content']['parts'][0]['text'] ?? $response ?? 'Error: No response from Gemini.';
  }
}

/**
 * ローカル内の他PHPファイルの実行関数
 *
 * @param $execPath 実行するPHPファイルのパス
 * @return string 出力内容
 */
function getEcho ($execPath = '/index.php') {
  $httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';

  // http://localhost/exec/0-makePrompt/00.php のようなURLを組み立てる
  $url = 'http://' . $httpHost . $execPath;

  // HTTP経由でファイルを取得（別リクエストとして実行される）
  $output = @file_get_contents($url);

  if ($output === false) {
    return 'Error: 読み込みに失敗しました (' . $url . ')';
  }

  return $output;
}

/**
 * テキストファイルのローカル保存
 *
 * @param string $filename 保存するファイル名
 * @param string $content 保存する内容
 * @return int|false 保存に成功した場合は書き込んだバイト数、失敗した場合はfalse
 */
function outputToFile ($filename = '', $content = '') {
  $filePath = $_SERVER['DOCUMENT_ROOT'] . '/buffer/' . $filename;
  return file_put_contents($filePath, $content);
}

/**
 * Open WebUI (Ollama互換) APIに質問を投げる (履歴維持対応版)
 *
 * @param string $prompt 最新の質問
 * @param string $chatId ユーザーIDやチャンネルIDなど、チャットを一意に識別する文字列
 * @return string 生成された文字列
 */
function askLocalAI(string $prompt = '', string $chatId = ''): string
{
    global $LLM_BASE_URL, $LLM_API_KEY, $MODEL_NAME;

    $url = rtrim($LLM_BASE_URL, '/') . '/api/chat/completions';

    // --- 履歴ファイルの準備 ---
    // 一時保存ディレクトリに chat_userA.json のような名前で保存
    $historyFile = '';
    if($chatId != '') {
        $historyFile = sys_get_temp_dir() . '/chat_' . md5($chatId) . '.json';
    }
    $messages = [];

    if($chatId != '') {
        // 既存の履歴があれば読み込む
        if (file_exists($historyFile)) {
            $historyData = file_get_contents($historyFile);
            $messages = json_decode($historyData, true) ?? [];
        }
    }

    // 履歴が長くなりすぎた場合は古いものを古い順に削除 (トークン節約・最大10往復程度)
    if (count($messages) > 20) {
        $messages = array_slice($messages, -20);
    }

    // 新しいユーザーの発言を追加
    $messages[] = ['role' => 'user', 'content' => $prompt];

    $data = [
        'model' => $MODEL_NAME,
        'messages' => $messages, // 過去の履歴を含めた配列を渡す
        'temperature' => 0.7,
        'stream' => true,
        'features' => [
            'web_search' => true
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $LLM_API_KEY
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);

    if ($error) {
        getEcho("/sendMattermost.php?sendMessage=LLM接続エラー: {$error}");
        echo "LLM接続エラー: " . $error . "\n";
        return "LLM接続エラー: " . $error;
    }

    // --- ストリーミングデータの解析処理 ---
    $lines = explode("\n", $response);
    $fullText = "";

    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, 'data: ') === 0) {
            $jsonStr = substr($line, 6);
            if (trim($jsonStr) === '[DONE]') {
                break;
            }
            $json = json_decode($jsonStr, true);
            if (isset($json['choices'][0]['delta']['content'])) {
                $fullText .= $json['choices'][0]['delta']['content'];
            }
        }
    }

    // 結果を返す & 履歴の保存
    if (!empty($fullText)) {
        // AIの回答も履歴に追加して保存
        $messages[] = ['role' => 'assistant', 'content' => $fullText];
        if($chatId != ''){
            file_put_contents($historyFile, json_encode($messages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        echo "LLMでのトーク生成に成功しました\n";
        getEcho("/sendMattermost.php?sendMessage=Geminiの代わりに、LLMでトークを生成しました");
        return $fullText;
    } else {
        echo "LLMの回答の解析に失敗しました。レスポンスの冒頭: " . substr($response, 0, 100) . "\n";
        getEcho("/sendMattermost.php?sendMessage=LLMの回答の解析に失敗しました。レスポンスの冒頭:" . substr($response, 0, 100));
        return "LLMの回答の解析に失敗しました。レスポンスの冒頭: " . substr($response, 0, 100);
    }
}
