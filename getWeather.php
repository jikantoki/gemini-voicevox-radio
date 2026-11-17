<?php
// リクエストのContent-TypeをJSONに設定
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tokyo');

// 共通関数の読み込み
require_once __DIR__ . '/app_functions.php';

$outputs = [];

$outputs['取得日'] = date('Y年m月d日 H時i分');

// エリア番号は以下から取得
// https://www.jma.go.jp/bosai/common/const/area.json

// 観測所番号は以下から取得
// https://www.jma.go.jp/bosai/amedas/const/amedastable.json
$outputs['datas']['東京'] = getWeatherReport("130000", "130010", "44132");
$outputs['datas']['埼玉'] = getWeatherReport("110000", "110010", "43241");
$outputs['datas']['千葉'] = getWeatherReport("120000", "120010", "45212");

echo json_encode($outputs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
