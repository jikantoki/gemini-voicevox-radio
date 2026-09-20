<?php
// リクエストのContent-TypeをJSONに設定
header('Content-Type: application/json; charset=utf-8');

/** 最新の更新時刻を取得するURL */
$timeUrl = 'https://www.jartic.or.jp/d/traffic_info/r1/target.json';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $timeUrl);
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

/** 最新の更新時刻（yyyymmddhhmm） */
$lastUpdateTime = $responseData['target'] ?? null;
if ($lastUpdateTime === null) {
    echo "Error: lastUpdateTime not found in response.";
    exit;
}

$date = DateTime::createFromFormat('YmdHi', $lastUpdateTime);

/** 出力用のJSONデータ */
$outputJson = [
  "time" => '',
  "datas" => []
];

$outputJson['time'] = $date->format('Y年m月d日 H時i分') . "時点での関東の交通情報";

/** 最新のデータを取得するURL */
$bodyUrl = 'https://www.jartic.or.jp/d/traffic_info/r1/' . $lastUpdateTime . '/s/301/C01.json';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $bodyUrl);
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

/** 交通情報のパースと日本語化処理 */
$features = $responseData['features'] ?? [];
$messages = [];
$uniqueRegulations = [];

foreach ($features as $feature) {
    $props = $feature['properties'] ?? null;
    if (!$props) {
        continue;
    }

    // 規制を一意に特定するキー（rn: 規制番号）
    // 万が一 rn がない場合は、道路名＋区間＋方向＋事象で代用
    $rn = $props['rn'] ?? ($props['r'] . ($props['i'] ?? '') . ($props['d'] ?? '') . ($props['c'] ?? ''));

    // すでに同じ規制を処理済みの場合はスキップ（重複排除）
    if (isset($uniqueRegulations[$rn])) {
        continue;
    }
    $uniqueRegulations[$rn] = true;

    // 各項目の抽出（存在しない場合のデフォルト値を設定）
    $road    = $props['r'] ?? '';       // 道路名
    $section = $props['i'] ?? '';       // 区間・場所
    $direction = $props['d'] ?? '';     // 方向（上り、下りなど）
    $cause   = $props['c'] ?? '';       // 原因（工事、雨、衝突事故など）
    $state   = $props['rd'] ?? '';      // 規制内容（通行止、１車線規制など）
    $status  = $props['a'] ?? '';       // 状況（処理中など）

    // 道路名が「高速八重洲線　西銀座　竹橋ＪＣＴ方面」のように冗長な場合があるため、
    // pd（主要道路名配列）があればそちらをベースにすっきりさせる
    if (!empty($props['pd'][0])) {
        $road = $props['pd'][0];
    }

    // 自然な文章の組み立て
    // 例: 【関越道】（下り）川越ＩＣ付近で衝突事故のため、第１走行規制（処理中）
    $msg = "【{$road}】";
    if ($direction) {
        $msg .= "（{$direction}）";
    }
    if ($section) {
        $msg .= " {$section}で";
    }
    if ($cause) {
        $msg .= "{$cause}のため、";
    } else {
        $msg .= "影響により、";
    }

    $msg .= $state;

    if ($status) {
        $msg .= "（{$status}）";
    }

    $messages[] = $msg;
}

// 画面へ出力（各行を個別のJSON文字列に変換して出力）
if (empty($messages)) {
    $outputJson['datas'][] = ["message" => "現在、該当する規制情報はありません。"];
} else {
    foreach ($messages as $message) {
        $outputJson['datas'][] = $message;
    }
}

echo json_encode($outputJson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";