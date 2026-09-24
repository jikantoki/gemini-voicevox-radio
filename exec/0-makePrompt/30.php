<?php
// リクエストのContent-TypeをJSONに設定
header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('Asia/Tokyo');
$hour = (int)date('G'); // 現在の時間を取得（0〜23の整数）
$minute = (int)date('i'); // 現在の分を取得（0〜59の整数）

// RSSフィードのURLを読み込み
require_once $_SERVER['DOCUMENT_ROOT'] . '/env.php';

/** Jarticデータの取得 */
$jarticJson = getEcho('/getJartic.php');

/** 天気データの取得 */
$weatherJson = getEcho('/getWeather.php');

$randomUrlId = array_rand($RSS_URLs);

/** RSSデータの読み込み */
$xml = simplexml_load_file($RSS_URLs[$randomUrlId]);

// もしRSSの読み込みに失敗した場合はエラーメッセージを返して終了
if ($xml === false) {
    echo "Failed to load RSS feed.";
    exit;
}

/** 整形後のニュースのリスト */
$newsList = [];

// RSSの整形
foreach ($xml->channel->item as $news) {
    $newsList[] = [
        'title' => (string)$news->title,
        'description' => (string)$news->description
    ];
}

/** ニュースリストからランダムな記事をピックアップするindex */
$newsListKeys = array_rand($newsList, 10);

/** 表示個数を絞った後のニュースリスト */
$finalNewsList = [];
foreach ($newsListKeys as $key) {
    $finalNewsList[] = $newsList[$key];
}

$filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/30-news.txt';

if (file_exists($filename)) {
    $prompt = file_get_contents($filename);

    /** 放送開始時刻（分） */
    $liveStartMinute = 30;
    if ($minute >= $liveStartMinute) {
        // 放送開始時刻を過ぎている場合は、次の時間帯のプロンプトを使用
        $hour += 1;
    }
    echo $prompt . "\n\n" . "放送開始時に、時刻は" . $hour . "時30分になりました。を読み上げてください。\n\n" . $jarticJson . "\n\n" . $weatherJson . "\n\n" . json_encode($finalNewsList, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);;
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