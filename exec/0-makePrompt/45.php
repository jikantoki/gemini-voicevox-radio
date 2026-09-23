<?php
date_default_timezone_set('Asia/Tokyo');
$hour = (int)date('G'); // 現在の時間を取得（0〜23の整数）

$filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/45-gadget.txt';

if (file_exists($filename)) {
    $prompt = file_get_contents($filename);

    /** 放送開始時刻（分） */
    $liveStartMinute = 45;
    if ($minute >= $liveStartMinute) {
        // 放送開始時刻を過ぎている場合は、次の時間帯のプロンプトを使用
        $hour += 1;
    }
    echo $prompt . "\n\n" . "放送開始時に、時刻は" . $hour . "時45分になりました。を読み上げてください。";
} else {
    echo "Prompt file not found.";
}