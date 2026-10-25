<?php
require_once './env.php';
// リクエストのContent-TypeをJSONに設定
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tokyo');

$categoryText = '&categories=';
// カテゴリ絞る配列が存在する場合、カテゴリを絞る文章を作成
if (count($WORDPRESS_CATEGORY_ID)) {
  foreach ($WORDPRESS_CATEGORY_ID as $categoryId) {
    $categoryText = "{$categoryText}{$categoryId}+";
  }
  // 最後のプラス記号は削除
  $categoryText = substr($categoryText, 0, -1);
}

$api_endpoint = "{$WORDPRESS_URL}/wp-json/wp/v2/posts?per_page=100{$categoryText}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

/** APIからデータを取得 */
$responseJson = curl_exec($ch);
if ($responseJson === false) {
    echo "Error fetching data: " . curl_error($ch);
    exit;
}

/** 取得したデータをデコード */
$responseData = json_decode($responseJson, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Error decoding JSON: " . json_last_error_msg();
    exit;
}

// 将来的にリストで複数記事を渡す想定で、アウトプットは配列にする
$pickupData = [];

/** pickupDataを整形したもの */
$outputData = [];

$randomKey = array_rand($responseData);
$pickupData[] = $responseData[$randomKey];

foreach ($pickupData as $pickup) {
  $title = $pickup['title']['rendered'];
  $content = $pickup['content']['rendered'];
  $content = strip_tags($content);
  $content = str_replace("\n", "", $content);
  $mappedData = [
    'title' => $title,
    'content' => $content,
  ];
  $outputData[] = $mappedData;
}

echo json_encode($outputData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
