<?php
// リクエストのContent-TypeをJSONに設定
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tokyo');

/**
 * 品質情報を確認し、データを文字列で返す
 *
 * @param array|null $data
 * @return string
 */
function confirmAqc(?array $data)
{
    if (!is_array($data) || count($data) < 2) {
        return "データなし";
    }

    // 品質情報フラグが 0 (正常) の場合
    if ($data[1] === 0) {
        return (string)$data[0];
    }

    return "品質情報を確認して下さい";
}

/**
 * 対象エリアのデータが格納されているインデックス番号を返す
 *
 * @param array $data
 * @param string $code
 * @return int|null
 */
function findIndex(array $data, string $code): ?int
{
    foreach ($data as $index => $item) {
        if (isset($item['area']['code']) && $item['area']['code'] === $code) {
            return $index;
        }
    }
    return null;
}

/**
 * 気象庁APIより天気情報を取得し、整形した文字列を返す
 *
 * @param string $area エリア番号（初期値: 東京都 "130000"）
 * @param string $detailArea 詳細予報エリア番号（初期値: 東京地方 "130010"）
 * @param string $stnid 観測所番号（初期値: 北の丸公園 "44132"）
 * @return
 */
function getWeatherReport(
    string $area = "130000",
    string $detailArea = "130010",
    string $stnid = "44132"
) {
    // 1. 最新時刻の取得と日時解析
    $latestTimeUrl = "https://www.jma.go.jp/bosai/amedas/data/latest_time.txt";
    $latestTimeRaw = @file_get_contents($latestTimeUrl);

    if ($latestTimeRaw === false) {
        return "エラー: 最新時刻データの取得に失敗しました。";
    }

    try {
        $dateTime = new DateTime(trim($latestTimeRaw));
    } catch (Exception $e) {
        return "エラー: 日時フォーマットの解析に失敗しました。";
    }

    $yyyymmdd = $dateTime->format('Ymd');
    $hour = (int)$dateTime->format('H');
    $h3 = sprintf('%02d', floor($hour / 3) * 3);

    // 2. 天気概況の取得
    $overviewUrl = "https://www.jma.go.jp/bosai/forecast/data/overview_forecast/{$area}.json";
    $overviewJson = @file_get_contents($overviewUrl);
    $overviewText = "取得失敗";

    if ($overviewJson !== false) {
        $overviewData = json_decode($overviewJson, true);
        if (isset($overviewData['text'])) {
            // 空白・改行の正規化
            $lines = preg_split('/\s+/u', trim($overviewData['text']));
            $overviewText = implode("\n", $lines);
        }
    }

    // 3. 天気予報の取得
    $forecastUrl = "https://www.jma.go.jp/bosai/forecast/data/forecast/{$area}.json";
    $forecastJson = @file_get_contents($forecastUrl);
    $tomorrowWeather = "取得失敗";

    if ($forecastJson !== false) {
        $forecastData = json_decode($forecastJson, true);
        if (isset($forecastData[0]['timeSeries'][0]['areas'])) {
            $areas = $forecastData[0]['timeSeries'][0]['areas'];
            $targetIndex = findIndex($areas, $detailArea);

            if ($targetIndex !== null && isset($areas[$targetIndex]['weathers'][1])) {
                $words = preg_split('/\s+/u', trim($areas[$targetIndex]['weathers'][1]));
                $tomorrowWeather = implode(' ', $words);
            }
        }
    }

    // 4. アメダスデータの取得
    $amedasUrl = "https://www.jma.go.jp/bosai/amedas/data/point/{$stnid}/{$yyyymmdd}_{$h3}.json";
    $amedasJson = @file_get_contents($amedasUrl);
    $latestTemp = "データなし";
    $latestPrecipitation10m = "データなし";

    if ($amedasJson !== false) {
        $amedasData = json_decode($amedasJson, true);
        if (is_array($amedasData) && !empty($amedasData)) {
            $keys = array_keys($amedasData);
            $latestKey = max($keys);

            if (isset($amedasData[$latestKey])) {
                $pointData = $amedasData[$latestKey];
                $latestTemp = confirmAqc($pointData['temp'] ?? null);
                $latestPrecipitation10m = confirmAqc($pointData['precipitation10m'] ?? null);
            }
        }
    }

    // 5. 出力文字列の結合
    $output = [
        "現在の気温" => "{$latestTemp} 度",
        "現在の降水量(10分あたり)" => "{$latestPrecipitation10m} mm",
        "翌日の天気" => "{$tomorrowWeather}",
        "天気概況" => "{$overviewText}"
    ];

    return $output;
}

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