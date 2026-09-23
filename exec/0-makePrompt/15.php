<?php
date_default_timezone_set('Asia/Tokyo');
$hour = (int)date('G'); // 現在の時間を取得（0〜23の整数）
$minute = (int)date('i'); // 現在の分を取得（0〜59の整数）

// 時間帯に応じて適切なプロンプトファイルを選択
if ($hour >= 0 && $hour < 4) {
    $filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/15-freetalk-shinya.txt';
} elseif ($hour >= 4 && $hour < 10) {
    $filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/15-freetalk-asa.txt';
} elseif ($hour >= 10 && $hour < 20) {
    $filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/15-freetalk-hiru.txt';
} elseif ($hour >= 20 && $hour < 24) {
    $filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/15-freetalk-shinya.txt';
} else {
    $filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/15-freetalk-hiru.txt';
}

if (file_exists($filename)) {
    $prompt = file_get_contents($filename);

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