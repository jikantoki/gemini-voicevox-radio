<?php
header('Content-Type: application/json; charset=utf-8');
set_time_limit(0); // 無制限に実行できるようにする
sleep(1); // 1秒待機

// 共通関数の読み込み
require_once dirname(__DIR__) . '/app_functions.php';

$inputText00 = loadFile('/buffer/00.txt');
$inputText15 = loadFile('/buffer/15.txt');
$inputText30 = loadFile('/buffer/30.txt');
$inputText45 = loadFile('/buffer/45.txt');

$outputText00 = getTalkScript($inputText00);
$outputText15 = getTalkScript($inputText15);
$outputText30 = getTalkScript($inputText30);
$outputText45 = getTalkScript($inputText45);

outputToFile('/buffer/00-2.txt', json_encode($outputText00, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
outputToFile('/buffer/15-2.txt', json_encode($outputText15, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
outputToFile('/buffer/30-2.txt', json_encode($outputText30, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
outputToFile('/buffer/45-2.txt', json_encode($outputText45, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

date_default_timezone_set('Asia/Tokyo');
$now = date('Y-m-d-Hi');

outputToFile('/talkHistory/' . $now . '-00.txt', json_encode($inputText00, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
outputToFile('/talkHistory/' . $now . '-15.txt', json_encode($inputText15, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
outputToFile('/talkHistory/' . $now . '-30.txt', json_encode($inputText30, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
outputToFile('/talkHistory/' . $now . '-45.txt', json_encode($inputText45, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
