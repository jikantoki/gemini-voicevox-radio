<?php

/**
 * オープニング、トーク(Wav)、BGM(自動ループ/カット)、エンディングを合成して1つのMP3を出力する
 */
function mixAudioWithOpEd(string $opMp3, string $talkWav, string $bgmMp3, string $edMp3, string $outputMp3, float $bgmVolume = 0.15): bool 
{
    // サーバーのドキュメントルートを取得
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/');

    // パスを絶対パスに変換するクロージャ
    $getAbsPath = function($path) use ($docRoot) {
        return $docRoot . '/' . ltrim($path, '/');
    };

    $absOp     = $getAbsPath($opMp3);
    $absTalk   = $getAbsPath($talkWav);
    $absBgm    = $getAbsPath($bgmMp3);
    $absEd     = $getAbsPath($edMp3);
    $absOutput = $getAbsPath($outputMp3);

    // 1. ファイルの存在チェック
    $files = [$absOp, $absTalk, $absBgm, $absEd];
    foreach ($files as $file) {
        if (!file_exists($file)) {
            echo "エラー: ファイルが見つかりません -> {$file}\n";
            return false;
        }
    }

    // 2. FFmpegコマンドの組み立て
    // [ポイント]
    // - -stream_loop -1 : BGM入力を無限ループさせる
    // - amix=duration=first : 1番目の入力（トーク）の長さに合わせてBGMをカットする
    // - concat=n=3 : 「OP」→「BGM付きトーク」→「ED」の3つを順番に1本に繋ぐ
    $cmd = sprintf(
        'ffmpeg -y -i %s -i %s -stream_loop -1 -i %s -i %s -filter_complex ' .
        '"[1:a]volume=3.5[talk_vol];' .
        '[2:a]volume=%f[bgm_vol];' .
        '[talk_vol][bgm_vol]amix=inputs=2:duration=first[talk_bgm];' .
        '[0:a][talk_bgm][3:a]concat=n=3:v=0:a=1" ' .
        '-c:a libmp3lame -q:a 2 %s 2>&1',
        escapeshellarg($absOp),
        escapeshellarg($absTalk),
        escapeshellarg($absBgm),
        escapeshellarg($absEd),
        $bgmVolume,
        escapeshellarg($absOutput)
    );

    // 3. コマンドの実行
    echo "音声の合成・結合処理を開始します...\n";
    exec($cmd, $execOutput, $execReturnCode);

    if ($execReturnCode === 0) {
        echo "成功: 処理が完了しました -> {$absOutput}\n";
        return true;
    } else {
        echo "エラー: 合成に失敗しました（コード: {$execReturnCode}）。\n";
        echo "詳細ログ:\n" . implode("\n", $execOutput) . "\n";
        return false;
    }
}


// 00分放送
$opening   = '/assets/jingle/ネルルラジオ.mp3';
$talkWav   = '/output/00.wav'; // 5〜10分のトークWav
$bgm       = '/assets/bgm/bgm1.mp3';   // 短くてもループし、長くても自動カットされます
$ending    = '/assets/jingle/ネルルラジオ.mp3';
$outputMp3 = '/output/final_podcast_00.mp3';

// 実行
mixAudioWithOpEd($opening, $talkWav, $bgm, $ending, $outputMp3, 0.15);

// 15分放送
$opening   = '/assets/jingle/ネルルラジオ.mp3';
$talkWav   = '/output/15.wav'; // 5〜10分のトークWav
$bgm       = '/assets/bgm/bgm2.mp3';   // 短くてもループし、長くても自動カットされます
$ending    = '/assets/jingle/ネルルラジオ.mp3';
$outputMp3 = '/output/final_podcast_15.mp3';

// 実行
mixAudioWithOpEd($opening, $talkWav, $bgm, $ending, $outputMp3, 0.15);

// 30分放送
$opening   = '/assets/jingle/ネルルラジオ.mp3';
$talkWav   = '/output/30.wav'; // 5〜10分のトークWav
$bgm       = '/assets/bgm/bgm3.mp3';   // 短くてもループし、長くても自動カットされます
$ending    = '/assets/jingle/ネルルラジオ.mp3';
$outputMp3 = '/output/final_podcast_30.mp3';

// 実行
mixAudioWithOpEd($opening, $talkWav, $bgm, $ending, $outputMp3, 0.15);

// 45分放送
$opening   = '/assets/jingle/ネルルラジオ.mp3';
$talkWav   = '/output/45.wav'; // 5〜10分のトークWav
$bgm       = '/assets/bgm/bgm4.mp3';   // 短くてもループし、長くても自動カットされます
$ending    = '/assets/jingle/ネルルラジオ.mp3';
$outputMp3 = '/output/final_podcast_45.mp3';

// 実行
mixAudioWithOpEd($opening, $talkWav, $bgm, $ending, $outputMp3, 0.15);

