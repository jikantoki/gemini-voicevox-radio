<?php
date_default_timezone_set('Asia/Tokyo');
$hour = (int)date('G'); // 現在の時間を取得（0〜23の整数）
$minute = (int)date('i'); // 現在の分を取得（0〜59の整数）

// 時間帯に応じて適切なプロンプトファイルを選択
if ($hour >= 0 && $hour < 5) {
    $filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/00-world-shinya.txt';
} elseif ($hour >= 5 && $hour < 23) {
    $filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/00-ogiri-hiru.txt';
} elseif ($hour >= 23 && $hour < 24) {
    $filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/00-world-shinya.txt';
} else {
    $filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/00-ogiri-hiru.txt';
}

if (file_exists($filename)) {
    $prompt = file_get_contents($filename);

    /** 放送開始時刻（分） */
    $liveStartMinute = 0;
    if ($minute >= $liveStartMinute) {
        // 放送開始時刻を過ぎている場合は、次の時間帯のプロンプトを使用
        $hour += 1;
    }
    echo $prompt . "\n\n" . "放送開始時に、時刻は" . $hour . "時になりました。を読み上げてください。";
} else {
    echo "Prompt file not found.";
}