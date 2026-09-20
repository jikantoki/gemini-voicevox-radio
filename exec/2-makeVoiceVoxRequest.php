<?php
header('Content-Type: application/json; charset=utf-8');
set_time_limit(0); // 無制限に実行できるようにする
sleep(1); // 1秒待機

$inputText00 = loadFile('/buffer/00.txt');
$inputText15 = loadFile('/buffer/15.txt');
$inputText30 = loadFile('/buffer/30.txt');
$inputText45 = loadFile('/buffer/45.txt');

$outputText00 = getTalkScript($inputText00);
$outputText15 = getTalkScript($inputText15);
$outputText30 = getTalkScript($inputText30);
$outputText45 = getTalkScript($inputText45);

outputToFile('/buffer/00-2.txt', json_encode($outputText00, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
outputToFile('/buffer/15-2.txt', json_encode($outputText15, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
outputToFile('/buffer/30-2.txt', json_encode($outputText30, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
outputToFile('/buffer/45-2.txt', json_encode($outputText45, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

date_default_timezone_set('Asia/Tokyo');
$now = date('Y-m-d-Hi');

outputToFile('/talkHistory/' . $now . '-00.txt', json_encode($outputText00, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
outputToFile('/talkHistory/' . $now . '-15.txt', json_encode($outputText15, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
outputToFile('/talkHistory/' . $now . '-30.txt', json_encode($outputText30, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
outputToFile('/talkHistory/' . $now . '-45.txt', json_encode($outputText45, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

/**
 * ファイルを読み込んで中身を返す
 *
 * @param $filename ファイル名
 * @return string ファイルの内容
 */
function loadFile($filename = '') {
  if (file_exists($_SERVER['DOCUMENT_ROOT'] . $filename)) {
      return file_get_contents($_SERVER['DOCUMENT_ROOT'] . $filename);
  } else {
      return "File not found: " . $filename;
  }
}

/**
 * Geminiが投げた台本をAPIスクリプト形式に変換
 *
 * @param string $inputText 台本
 * @return array スクリプト
 */
function getTalkScript($inputText = '') {
  // キャラクター名とVOICEVOXのspeaker IDの対応マッピング
  $speakerMap = [
      'めたん' => 2, // 四国めたん（ノーマル）
      'ずんだもん' => 3, // ずんだもん（ノーマル）
      'つむぎ' => 8, // 春日部つむぎ（ノーマル）
      'りつ' => 9, // 波音リツ（ノーマル）
      'はう' => 10, // 雨晴はう（ノーマル）
      'そら' => 16, // 九州そら（ノーマル）
  ];

  // テキストを行ごとに分解
  $lines = explode("\n", $inputText);
  $voicevoxPlayList = [];

  foreach ($lines as $line) {
      $line = trim($line);
      if (empty($line)) {
          continue;
      }

      // 「名前:発言内容」の形式にマッチング
      if (preg_match('/^([^:]+):(.*)$/', $line, $matches)) {
          $name = trim($matches[1]);
          $text = trim($matches[2]);

          // 定義されたキャラクターの場合のみAPI用配列に追加
          if (isset($speakerMap[$name])) {
              $voicevoxPlayList[] = [
                  'speaker' => $speakerMap[$name],
                  'text'    => $text,
              ];
          }
      }
  }

  // デバッグ用に生成されたスクリプトをJSON形式で出力
  echo json_encode($voicevoxPlayList, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  return $voicevoxPlayList;
}

/**
 * テキストファイルのローカル保存
 *
 * @param string $filename 保存するファイル名
 * @param string $content 保存する内容
 * @return int|false 保存に成功した場合は書き込んだバイト数、失敗した場合はfalse
 */
function outputToFile ($filename = '', $content = '') {
  $filePath = $_SERVER['DOCUMENT_ROOT'] . $filename;
  return file_put_contents($filePath, $content);
}