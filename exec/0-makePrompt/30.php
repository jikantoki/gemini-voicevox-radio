<?php
// RSSフィードのURLを読み込み
require_once $_SERVER['DOCUMENT_ROOT'] . '/env.php';

/** Jarticデータの取得 */
$jarticJson = getEcho('/getJartic.php');

$randomUrlId = array_rand($RSS_URLs);

/** RSSデータの読み込み */
$xml = simplexml_load_file($RSS_URLs[$randomUrlId]);

// もしRSSの読み込みに失敗した場合はエラーメッセージを返して終了
if ($xml === false) {
    echo "Failed to load RSS feed.";
    exit;
}

$filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/30-news.txt';
if (file_exists($filename)) {
    $prompt = file_get_contents($filename);
    echo $prompt. "\n\n" . $jarticJson . "\n\n" . json_encode($xml, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo "Prompt file not found.";
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