<?php
set_time_limit(0); // 無制限に実行できるようにする
ini_set('memory_limit', '512M'); // メモリ制限を増やす
// リクエストのContent-TypeをJSONに設定
header('Content-Type: application/json; charset=utf-8');

// 環境変数の読み込み
require_once 'env.php';

/**
 * @var bool $errorFlg エラーが発生したかどうかのフラグ
 */
$errorFlg = false;

/**
 * @var string $PROMPT AIに投げる本文（POSTパラメータ）
 */
$PROMPT = $_POST['prompt'] ?? $_GET['prompt'] ?? '';

// ヘッダーに 'apikey' または 'Apikey' があればそれを使う。なければ env.php の1つ目を使う
$API_KEY = $_SERVER['HTTP_APIKEY'] ?? $_SERVER['HTTP_X_APIKEY'] ?? $API_KEYs[0];

if (empty($PROMPT)) {
  $errorFlg = true;
  echo json_encode([
    'errorFlg' => $errorFlg,
    'result' => 'Error: No prompt provided.'
  ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

$requestData = [
  "contents" => [
    [
      "parts" => [
        ["text" => $PROMPT]
      ]
    ]
  ]
];

$ch = curl_init($URL);

curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Content-Type: application/json',
  'x-goog-api-key: ' . $API_KEY
]);

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);

if ($response === false) {
  $errorFlg = true;
}

$result = json_decode($response, true);

echo json_encode([
  'errorFlg' => $errorFlg,
  'result' => $result
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
