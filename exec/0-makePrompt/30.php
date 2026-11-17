<?php
// リクエストのContent-TypeをJSONに設定
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tokyo');
$hour = (int)date('G'); // 現在の時間を取得（0〜23の整数）
$minute = (int)date('i'); // 現在の分を取得（0〜59の整数）

/** 放送開始時刻（分） */
$liveStartMinute = 30;
if ($minute >= $liveStartMinute) {
    // 放送開始時刻を過ぎている場合は、次の時間帯のプロンプトを使用
    $hour += 1;
}

// 共通関数および環境変数の読み込み
require_once dirname(dirname(__DIR__)) . '/app_functions.php';

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

$greeting = ($hour >= 5 && $hour < 11) ? 'おはようございます'
          : (($hour >= 11 && $hour < 18) ? 'こんにちは' : 'こんばんは');

$filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/30-news.txt';

if (file_exists($filename)) {
    $prompt = file_get_contents($filename);
    $prompt = str_replace("<!-- 時刻 -->", "{$hour}時30分", $prompt);
    $prompt = str_replace("<!-- 挨拶 -->", $greeting, $prompt);
    $prompt = str_replace("<!-- ニュース情報 -->", json_encode($finalNewsList, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), $prompt);
    $prompt = str_replace("<!-- 交通情報 -->", $jarticJson, $prompt);
    $prompt = str_replace("<!-- 天気情報 -->", $weatherJson, $prompt);

    echo $prompt;
} else {
    echo "Prompt file not found.";
}