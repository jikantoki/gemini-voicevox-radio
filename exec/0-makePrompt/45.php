<?php
date_default_timezone_set('Asia/Tokyo');
$hour = (int)date('G'); // 現在の時間を取得（0〜23の整数）

$filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/45-gadget.txt';
if (file_exists($filename)) {
    $prompt = file_get_contents($filename);
    echo $prompt . "放送開始時に、時刻は" . ($hour + 1) . "時45分になりました。を読み上げてください。\n\n";
} else {
    echo "Prompt file not found.";
}