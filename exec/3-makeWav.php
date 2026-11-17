<?php
header('Content-Type: application/json; charset=utf-8');
set_time_limit(0); // 無制限に実行できるようにする
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '512M'); // メモリ制限を増やす
sleep(1); // 1秒待機

// 共通関数の読み込み
require_once dirname(__DIR__) . '/app_functions.php';

$inputText00 = loadFile('/buffer/00-2.txt');
$inputText15 = loadFile('/buffer/15-2.txt');
$inputText30 = loadFile('/buffer/30-2.txt');
$inputText45 = loadFile('/buffer/45-2.txt');

makeWav($inputText00, '00.wav');
makeWav($inputText15, '15.wav');
makeWav($inputText30, '30.wav');
makeWav($inputText45, '45.wav');
