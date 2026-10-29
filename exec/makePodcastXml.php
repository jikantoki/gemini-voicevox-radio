<?php
/**
 * Podcast RSS XML 一括生成実行スクリプト
 */
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

$docRoot = rtrim(!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : dirname(__DIR__), '/\\');
require_once $docRoot . '/env.php';
require_once $docRoot . '/exec/func/makePodcastRssFunc.php';

echo "Podcast RSS (XML) の生成を開始します...\n";
generatePodcastXml(); // 全番組生成
echo "すべてのPodcast RSS (XML) の生成が完了しました。\n";
