<?php
// RSSフィードのURLを読み込み
require_once $_SERVER['DOCUMENT_ROOT'] . '/env.php';

/** RSSデータの読み込み */
$xml = simplexml_load_file($RSS_URL);

// もしRSSの読み込みに失敗した場合はエラーメッセージを返して終了
if ($xml === false) {
    echo "Failed to load RSS feed.";
    exit;
}

$filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/30-news.txt';
if (file_exists($filename)) {
    $prompt = file_get_contents($filename);
    echo $prompt. "\n\n" . json_encode($xml, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo "Prompt file not found.";
}