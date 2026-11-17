<?php
ini_set('display_errors', '1');
ini_set('error_reporting', E_ALL);
header('Content-Type: application/json; charset=utf-8');
set_time_limit(0); // 無制限に実行できるようにする

// 共通関数の読み込み
require_once dirname(__DIR__) . '/app_functions.php';

date_default_timezone_set('Asia/Tokyo');
$hour = (int)date('G'); // 現在の時間を取得（0〜23の整数）
$minute = (int)date('i'); // 現在の分を取得（0〜59の整数）

// 時間帯に応じて適切なBGMを選択
if ($hour >= 0 && $hour < 4) {
    $bgm = '/assets/bgm/ノクターンop9-2.mp3';
} elseif ($hour >= 4 && $hour < 23) {
    $bgm = '/assets/bgm/ねるるミュージックアカデミーBGM.mp3';
} elseif ($hour >= 23 && $hour < 24) {
    $bgm = '/assets/bgm/ノクターンop9-2.mp3';
} else {
    $bgm = '/assets/bgm/bgm1.mp3';
}

// 00分放送
$opening   = '/assets/jingle/ねるるミュージックアカデミー.mp3';
$talkWav   = '/output/00.wav'; // 5〜10分のトークWav
$ending    = '/assets/jingle/ネルルラジオ_lofi.mp3';
$thumbnail = '/assets/img/ねるるミュージックアカデミー.jpg';
$outputMp3 = '/output/final_podcast_00.mp3';
$title = 'ねるるミュージックアカデミー';
$comment00 = getTopicsAsText('/buffer/00-pickup.txt');

// 実行
$res00 = mixAudioWithOpEd($opening, $talkWav, $bgm, $ending, $outputMp3, 0.5, $title, 'ねるるラジオ', $thumbnail, $comment00);
if ($res00) {
    saveToPodcasts($outputMp3, $title, 0);
    if (function_exists('generatePodcastXml')) {
        generatePodcastXml('musicacademy');
    }
}

// 15分放送
/** 放送開始時刻（分） */
$hour = (int)date('G'); // 現在の時間を取得（0〜23の整数）
$minute = (int)date('i'); // 現在の分を取得（0〜59の整数）
$liveStartMinute = 15;
if ($minute >= $liveStartMinute) {
    // 放送開始時刻を過ぎている場合は、次の時間帯のプロンプトを使用
    $hour += 1;
}

// 時間帯に応じて適切なプロンプトファイルを選択
if ($hour >= 0 && $hour < 4) {
    $opening = '/assets/jingle/ねるるの眠れない話_lofi.mp3';
    $title = 'ねるるの眠れない話';
    $thumbnail = '/assets/img/ねるるの眠れない話.jpg';
    $bgm = '/assets/bgm/ねるるの眠れない話BGM.mp3';
} elseif ($hour >= 4 && $hour < 10) {
    $opening = '/assets/jingle/ネルルラジオ.mp3';
    $title = 'オハラジオ';
    $thumbnail = '/assets/img/オハラジオ.jpg';
    $bgm = '/assets/bgm/オハラジオBGM.mp3';
} elseif ($hour >= 10 && $hour < 23) {
    $opening = '/assets/jingle/エンカウント・チバ.mp3';
    $title = 'サボさんの千葉来ません？';
    $thumbnail = '/assets/img/サボ千葉.jpg';
    $bgm = '/assets/bgm/ウチボウ Go!.mp3';
} elseif ($hour >= 23 && $hour < 24) {
    $opening = '/assets/jingle/ねるるの眠れない話_lofi.mp3';
    $title = 'ねるるの眠れない話';
    $thumbnail = '/assets/img/ねるるの眠れない話.jpg';
    $bgm = '/assets/bgm/ねるるの眠れない話BGM.mp3';
} else {
    $opening = '/assets/jingle/ネルルラジオ.mp3';
    $title = 'フリートーク';
    $thumbnail = '/assets/img/default.jpg';
    $bgm = '/assets/bgm/bgm1.mp3';
}

$talkWav   = '/output/15.wav'; // 5〜10分のトークWav
$ending    = '/assets/jingle/ネルルラジオ_lofi.mp3';
$outputMp3 = '/output/final_podcast_15.mp3';
$comment15 = ($title === 'サボさんの千葉来ません？') ? getTopicsAsText('/buffer/15-sabochiba-pickup.txt') : '';

// 実行
$res15 = mixAudioWithOpEd($opening, $talkWav, $bgm, $ending, $outputMp3, 0.5, $title, 'ねるるラジオ', $thumbnail, $comment15);
if ($res15) {
    if (saveToPodcasts($outputMp3, $title, 15)) {
        if (function_exists('generatePodcastXml')) {
            generatePodcastXml('sabochiba');
        }
    }
}

// 30分放送
/** 放送開始時刻（分） */
$liveStartMinute = 30;
$hour = (int)date('G'); // 現在の時間を取得（0〜23の整数）
$minute = (int)date('i'); // 現在の分を取得（0〜59の整数）
if ($minute >= $liveStartMinute) {
    // 放送開始時刻を過ぎている場合は、次の時間帯のプロンプトを使用
    $hour += 1;
}

// 時間帯に応じて適切なBGMを選択
if ($hour >= 0 && $hour < 4) {
    $bgm = '/assets/bgm/ネルラジニュースBGM-LoFi.mp3';
} elseif ($hour >= 4 && $hour < 23) {
    $bgm = '/assets/bgm/ネルラジニュースBGM.mp3';
} elseif ($hour >= 23 && $hour < 24) {
    $bgm = '/assets/bgm/ネルラジニュースBGM-LoFi.mp3';
} else {
    $bgm = '/assets/bgm/bgm1.mp3';
}

$opening   = '/assets/jingle/ネルルラジオ.mp3';
$talkWav   = '/output/30.wav'; // 5〜10分のトークWav
$ending    = '/assets/jingle/ネルルラジオ_lofi.mp3';
$thumbnail = '/assets/img/ネルラジニュース.jpg';
$outputMp3 = '/output/final_podcast_30.mp3';
$title = 'ネルラジニュース';

// 実行
$res30 = mixAudioWithOpEd($opening, $talkWav, $bgm, $ending, $outputMp3, 0.5, $title, 'ねるるラジオ', $thumbnail, '');
if ($res30) {
    saveToPodcasts($outputMp3, $title, 30);
}

// 45分放送
/** 放送開始時刻（分） */
$liveStartMinute = 45;
$hour = (int)date('G'); // 現在の時間を取得（0〜23の整数）
$minute = (int)date('i'); // 現在の分を取得（0〜59の整数）
if ($minute >= $liveStartMinute) {
    // 放送開始時刻を過ぎている場合は、次の時間帯のプロンプトを使用
    $hour += 1;
}

// 時間帯に応じて適切なBGMを選択
if ($hour >= 0 && $hour < 4) {
    $bgm = '/assets/bgm/ジムノペディ第一曲.mp3';
} elseif ($hour >= 4 && $hour < 23) {
    $bgm = '/assets/bgm/ガジェットマニアBGM.mp3';
} elseif ($hour >= 23 && $hour < 24) {
    $bgm = '/assets/bgm/ジムノペディ第一曲.mp3';
} else {
    $bgm = '/assets/bgm/bgm1.mp3';
}

$opening   = '/assets/jingle/ガジェットマニア.mp3';
$talkWav   = '/output/45.wav'; // 5〜10分のトークWav
$ending    = '/assets/jingle/ネルルラジオ_lofi.mp3';
$thumbnail = '/assets/img/ガジェットマニア.jpg';
$outputMp3 = '/output/final_podcast_45.mp3';
$title = 'ガジェットマニア';
$comment45 = getTopicsAsText('/buffer/45-pickup.txt');

// 実行
$res45 = mixAudioWithOpEd($opening, $talkWav, $bgm, $ending, $outputMp3, 0.5, $title, 'ねるるラジオ', $thumbnail, $comment45);
if ($res45) {
    saveToPodcasts($outputMp3, $title, 45);
    if (function_exists('generatePodcastXml')) {
        generatePodcastXml('gadgetmania');
    }
}
