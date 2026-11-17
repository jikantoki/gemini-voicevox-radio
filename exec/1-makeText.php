<?php
header('Content-Type: application/json; charset=utf-8');
set_time_limit(0); // 無制限に実行できるようにする

// 共通関数の読み込み
require_once dirname(__DIR__) . '/app_functions.php';

/** 00分のニュース原稿プロンプト */
$prompt00 = getEcho('/exec/0-makePrompt/00.php');
/** 15分のニュース原稿プロンプト */
$prompt15 = getEcho('/exec/0-makePrompt/15.php');
/** 30分のニュース原稿プロンプト */
$prompt30 = getEcho('/exec/0-makePrompt/30.php');
/** 45分のニュース原稿プロンプト */
$prompt45 = getEcho('/exec/0-makePrompt/45.php');

$text00 = makeText($prompt00, 0);
outputToFile('00.txt', $text00);

$text15 = makeText($prompt15, 15);
outputToFile('15.txt', $text15);

$text30 = makeText($prompt30, 30);
outputToFile('30.txt', $text30);

$text45 = makeText($prompt45, 45);
outputToFile('45.txt', $text45);

echo "00.txt, 15.txt, 30.txt, 45.txt にニュース原稿を保存しました。\n";
