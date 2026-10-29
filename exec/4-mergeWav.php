<?php
ini_set('display_errors', '1');
ini_set('error_reporting', E_ALL);
header('Content-Type: application/json; charset=utf-8');
set_time_limit(0); // 無制限に実行できるようにする

// 環境変数の読み込み
$docRoot = rtrim(!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : dirname(__DIR__), '/\\');
if (file_exists($docRoot . '/env.php')) {
    require_once $docRoot . '/env.php';
}
if (file_exists($docRoot . '/exec/func/makePodcastRssFunc.php')) {
    require_once $docRoot . '/exec/func/makePodcastRssFunc.php';
}

/**
 * オープニング、トーク(Wav)、BGM(自動ループ/カット)、エンディングを合成して1つのMP3を出力する
 */
function mixAudioWithOpEd(string $opMp3, string $talkWav, string $bgmMp3, string $edMp3, string $outputMp3, float $bgmVolume = 0.15, string $songTitle = '', string $songArtist = 'ねるるラジオ', string $thumbnailPath = '/assets/img/default.jpg', string $comment = ''): bool
{
    // サーバーのドキュメントルートを取得
    $docRoot = rtrim(!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : dirname(__DIR__), '/\\');

    // パスを絶対パスに変換するクロージャ
    $getAbsPath = function($path) use ($docRoot) {
        return $docRoot . '/' . ltrim($path, '/');
    };

    $absOp     = $getAbsPath($opMp3);
    $absTalk   = $getAbsPath($talkWav);
    $absBgm    = $getAbsPath($bgmMp3);
    $absEd     = $getAbsPath($edMp3);
    $absOutput = $getAbsPath($outputMp3);
    $absImage  = $getAbsPath($thumbnailPath);

    // 1. ファイルの存在チェック
    $files = [$absOp, $absTalk, $absBgm, $absEd];
    foreach ($files as $file) {
        if (!file_exists($file)) {
            echo "エラー: ファイルが見つかりません -> {$file}\n";
            getEcho("/sendMattermost.php?sendMessage=エラー: ファイルが見つかりません -> {$file}");
            return false;
        }
    }

    // 2. トークファイルの長さを取得（フォーマット指定を修正）
    $durationCmd = sprintf('ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>&1', escapeshellarg($absTalk));
    $durationOutput = trim(shell_exec($durationCmd));

    // 取得した文字列を数値に変換
    $talkDuration = floatval($durationOutput);

    // 【デバッグ用確認ログ】不要になったら削除して構いません
    echo "--- デバッグ情報 ---\n";
    echo "解析された秒数: {$talkDuration} 秒\n";
    echo "--------------------\n";

    // トーク用フィルターの初期化
    $talkFilter = 'volume=9.5,aresample=48000,aformat=channel_layouts=stereo';

    // 11分（660秒）を超えている場合は早回しフィルターを追加
    $limitSeconds = 660.0;
    if ($talkDuration > $limitSeconds) {
        $speedRatio = $talkDuration / $limitSeconds;
        $talkFilter .= sprintf(',atempo=%f', $speedRatio);
        echo "【判定】トークが11分を超えています。ピッチを維持したまま " . round($speedRatio, 4) . " 倍速に調整します。\n";
    } else {
        echo "【判定】トークは11分以内です。速度調整はスキップします。\n";
    }

    // 3. FFmpegコマンドの組み立て
    // - -f lavfi -i anullsrc=r=44100:cl=stereo : 2秒用の無音ソース（44.1kHz/ステレオ）を生成して5番目の入力（[4:a]）とする
    // - [4:a]atrim=end=2[silence] : 生成した無音ソースを2秒でカットして共通パーツ化
    // - concat=n=5 : 「2秒無音」→「OP」→「BGM付きトーク」→「ED」→「2秒無音」の5つを繋ぐ
    $cmd = sprintf(
        'ffmpeg -y -i %s -i %s -stream_loop -1 -i %s -i %s -f lavfi -i anullsrc=r=48000:cl=stereo -i %s -filter_complex ' .
        '"[4:a]atrim=end=2,asplit=2[silence1][silence2];' .
        '[1:a]%s[talk_vol];' .
        '[2:a]volume=%f[bgm_vol];' .
        '[talk_vol][bgm_vol]amix=inputs=2:duration=first[talk_bgm];' .
        '[silence1][0:a][talk_bgm][3:a][silence2]concat=n=5:v=0:a=1[outa]" ' .
        '-map "[outa]" -map 5:v ' .
        '-c:a libmp3lame -q:a 2 -c:v mjpeg -disposition:v attached_pic ' .
        '-metadata title=%s -metadata artist=%s -metadata comment=%s -metadata description=%s -id3v2_version 3 %s 2>&1',
        escapeshellarg($absOp),      // 0: OP
        escapeshellarg($absTalk),    // 1: Talk
        escapeshellarg($absBgm),     // 2: BGM
        escapeshellarg($absEd),      // 3: ED
        escapeshellarg($absImage),   // 5: 画像
        $talkFilter,
        $bgmVolume,
        escapeshellarg($songTitle),
        escapeshellarg($songArtist),
        escapeshellarg($comment),
        escapeshellarg($comment),
        escapeshellarg($absOutput)
    );

    // 4. コマンドの実行
    echo "音声の合成・結合処理を開始します...\n";
    exec($cmd, $execOutput, $execReturnCode);

    if ($execReturnCode === 0) {
        echo "成功: 処理が完了しました -> {$absOutput}\n";
        return true;
    } else {
        echo "エラー: 合成に失敗しました（コード: {$execReturnCode}）。\n";
        getEcho("/sendMattermost.php?sendMessage=エラー: 合成に失敗しました（コード: {$execReturnCode}）。");
        echo "詳細ログ:\n" . implode("\n", $execOutput) . "\n";
        return false;
    }
}

/**
 * ピックアップファイルからトピックを読み込み、箇条書きテキストとして取得する
 *
 * @param string $pickupFileRelPath ピックアップファイルの相対パス
 * @return string 改行区切りのトピックテキスト
 */
function getTopicsAsText(string $pickupFileRelPath): string
{
    $docRoot = rtrim(!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : dirname(__DIR__), '/\\');
    $pickupAbsPath = $docRoot . '/' . ltrim($pickupFileRelPath, '/');

    if (!file_exists($pickupAbsPath)) {
        return '';
    }

    $content = file_get_contents($pickupAbsPath);
    $topics = json_decode($content, true);
    if (!is_array($topics) || empty($topics)) {
        return '';
    }

    $lines = ["【本日のトピック】"];
    foreach ($topics as $t) {
        $lines[] = '・' . trim($t);
    }

    return implode("\n", $lines);
}

/**
 * Podcast保存用フォルダに音声ファイルを保存する
 *
 * @param string $sourceMp3RelPath 合成されたMP3の相対パス（例: '/output/final_podcast_00.mp3'）
 * @param string $title 番組タイトル（例: 'ねるるミュージックアカデミー'）
 * @param int $targetMinute 放送開始分（0, 15, 30, 45）
 * @return bool 保存成功ならtrue、対象外または失敗ならfalse
 */
function saveToPodcasts(string $sourceMp3RelPath, string $title, int $targetMinute): bool
{
    global $PODCAST_PATH;

    // 対象番組と保存先サブディレクトリのマッピング
    $targetDirMap = [
        'サボさんの千葉来ません？'       => 'sabochiba',
        'ガジェットマニア'               => 'gadgetmania',
        'ねるるミュージックアカデミー' => 'musicacademy',
    ];

    // 対象番組でない場合は何もしない
    if (!isset($targetDirMap[$title])) {
        echo "【Podcast】番組「{$title}」はPodcast保存の対象外です。スキップします。\n";
        return false;
    }

    if (empty($PODCAST_PATH)) {
        echo "【Podcastエラー】env.php の \$PODCAST_PATH が設定されていません。\n";
        getEcho("/sendMattermost.php?sendMessage=【Podcastエラー】env.php の \$PODCAST_PATH が設定されていません。");
        return false;
    }

    $docRoot = rtrim(!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : dirname(__DIR__), '/\\');
    $sourceAbsPath = $docRoot . '/' . ltrim($sourceMp3RelPath, '/');

    if (!file_exists($sourceAbsPath)) {
        echo "【Podcastエラー】コピー元のファイルが見つかりません -> {$sourceAbsPath}\n";
        getEcho("/sendMattermost.php?sendMessage=【Podcastエラー】コピー元のファイルが見つかりません -> {$sourceAbsPath}");
        return false;
    }

    // 保存先ディレクトリの準備
    $subDir = $targetDirMap[$title];
    $destDir = rtrim($PODCAST_PATH, '/') . '/' . $subDir;
    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0777, true) && !is_dir($destDir)) {
            echo "【Podcastエラー】保存先ディレクトリを作成できませんでした -> {$destDir}\n";
            getEcho("/sendMattermost.php?sendMessage=【Podcastエラー】保存先ディレクトリを作成できませんでした -> {$destDir}");
            return false;
        }
    }

    // 放送予定日時の計算 (YYYYMMDDHHMM)
    $now = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
    $currentMinute = (int)$now->format('i');
    if ($currentMinute >= $targetMinute) {
        $now->modify('+1 hour');
    }
    $now->setTime((int)$now->format('H'), $targetMinute, 0);
    $formattedDate = $now->format('YmdHi');

    // ファイル名の生成: 番組名_YYYYMMDDHHMM.mp3
    $destFileName = sprintf('%s_%s.mp3', $title, $formattedDate);
    $destFilePath = $destDir . '/' . $destFileName;

    // ファイルコピー
    if (copy($sourceAbsPath, $destFilePath)) {
        echo "【Podcast】保存成功 -> {$destFilePath}\n";
        return true;
    } else {
        echo "【Podcastエラー】ファイルのコピーに失敗しました -> {$destFilePath}\n";
        getEcho("/sendMattermost.php?sendMessage=【Podcastエラー】ファイルのコピーに失敗しました -> {$destFilePath}");
        return false;
    }
}

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

/**
 * ローカル内の他PHPファイルの実行関数
 *
 * @param $execPath 実行するPHPファイルのパス
 * @return string 出力内容
 */
function getEcho ($execPath = '/index.php') {
  $httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';

  // http://localhost/exec/0-makePrompt/00.php のようなURLを組み立てる
  $url = 'http://' . $httpHost . $execPath;

  // HTTP経由でファイルを取得（別リクエストとして実行される）
  $output = @file_get_contents($url);

  if ($output === false) {
    return 'Error: 読み込みに失敗しました (' . $url . ')';
  }

  return $output;
}
