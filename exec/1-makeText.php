<?php
ini_set('display_errors', 'On');
ini_set('display_startup_errors', 'On');
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

$text00 = makeText($prompt00);
outputToFile('00.txt', $text00);

$text15 = makeText($prompt15);
outputToFile('15.txt', $text15);

$text30 = makeText($prompt30);
outputToFile('30.txt', $text30);

$text45 = makeText($prompt45);
outputToFile('45.txt', $text45);

echo "00.txt, 15.txt, 30.txt, 45.txt にニュース原稿を保存しました。\n";

/**
 * Geminiに投げる
 *
 * @param string $prompt AIに投げるプロンプト
 * @return string ニュース原稿
 */
function makeText ($prompt = '') {
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

    if(isset($json['result']) && isset($json['result']['error']['code']) && $json['result']['error']['code'] == 503) {
      echo "503エラー（アクセス過多）のため、3秒待って再実行します\n";
      // 503エラーの場合は3秒待って再試行
      if($err503cnt < 3){
        $err503cnt++;
        sleep(3);
        continue;
      } else {
        echo "3回連続で失敗したため、中断しました\n";
        break;
      }
    } else if (isset($json['result']) && isset($json['result']['error']['code']) && $json['result']['error']['code'] == 429) {
      echo "429エラー（リクエスト超過）のため、別のキーで再実行します\n";
      // 429エラーの場合はAPIキーを変えて再試行
      if(count($API_KEYs) - 1 <= $requestCnt) {
        echo "どのAPIキーでもリクエストを実行できませんでした\n";
        return json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
      }
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