<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tokyo');
$hour = (int)date('G'); // 現在の時間を取得（0〜23の整数）
$minute = (int)date('i'); // 現在の分を取得（0〜59の整数）

$noon = 'hiru';

// 時間帯に応じて適切なプロンプトファイルを選択
if ($hour >= 0 && $hour < 4) {
    $noon = 'shinya';
    $filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/15-freetalk-shinya.txt';
} elseif ($hour >= 4 && $hour < 10) {
    $noon = 'asa';
    $filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/15-freetalk-asa.txt';
} elseif ($hour >= 10 && $hour < 20) {
    $noon = 'hiru';
    $filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/15-freetalk-hiru.txt';
} elseif ($hour >= 20 && $hour < 24) {
    $noon = 'shinya';
    $filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/15-freetalk-shinya.txt';
} else {
    $noon = 'hiru';
    $filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/15-freetalk-hiru.txt';
}

$pickupList = [
    "千葉駅",
    "本千葉駅",
    "千葉みなと駅",
    "蘇我駅",
    "浜野駅",
    "八幡宿駅",
    "五井駅",
    "姉ヶ崎駅",
    "長浦駅",
    "袖ヶ浦駅",
    "巌根駅",
    "木更津駅",
    "君津駅",
    "青堀駅",
    "大貫駅",
    "佐貫町駅",
    "上総湊駅",
    "竹岡駅",
    "浜金谷駅",
    "安房勝山駅",
    "岩井駅",
    "冨浦駅",
    "那古船形駅",
    "館山駅",
    "九重駅",
    "千倉駅",
    "和田浦駅",
    "江見駅",
    "太海駅",
    "安房鴨川駅",
    "安房小湊駅",
    "安房天津駅",
    "行川アイランド駅",
    "上総興津駅",
    "勝浦駅",
    "御宿駅",
    "大原駅",
    "上総一ノ宮駅",
    "茂原駅",
    "大網駅",
    "海浜幕張駅",
    "本八幡駅",
    "船橋駅",
    "成田湯川駅",
    "津田沼駅",
    "稲毛駅",
    "ちはら台駅",
    "千城台駅",
    "海士有木駅",
    "光風台駅",
    "馬立駅",
    "上総牛久駅",
    "高滝駅",
    "上総中野駅",
    "大多喜駅",
    "東金駅",
    "上総清川駅",
    "横田駅",
    "馬来田駅",
    "久留里駅",
    "上総亀山駅",
    "銚子駅",
    "犬吠駅",
    "成田駅",
    "ユーカリが丘駅",
    "千葉ニュータウン中央駅",
    "印旛日本医大駅",
    "新鎌ヶ谷駅",
    "松戸駅",
    "市川駅",
    "舞浜駅",
    "新浦安駅",
    "南船橋駅",
    "千葉中央駅",
];
$randomKeys = array_rand($pickupList, 3);
$pickup = '';
foreach ($randomKeys as $key) {
    $pickup .= "'{$pickupList[$key]}', ";
}

if (file_exists($filename)) {
    $prompt = file_get_contents($filename);
    if($noon == 'hiru') {
        $prompt = str_replace("<!-- 今日のお題 -->", $pickup, $prompt);
    }

    /** 放送開始時刻（分） */
    $liveStartMinute = 15;
    if ($minute >= $liveStartMinute) {
        // 放送開始時刻を過ぎている場合は、次の時間帯のプロンプトを使用
        $hour += 1;
    }
    echo $prompt . "\n\n" . "放送開始時に、時刻は" . $hour . "時15分になりました。を読み上げてください。";
} else {
    echo "Prompt file not found.";
}