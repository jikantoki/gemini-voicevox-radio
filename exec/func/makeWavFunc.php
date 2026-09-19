<?php
set_time_limit(0); // 無制限に実行できるようにする
ini_set('memory_limit', '512M'); // メモリ制限を増やす
require_once $_SERVER['DOCUMENT_ROOT'] . '/env.php'; // 環境変数の読み込み

/**
 * VOICEVOX APIを利用してテキストから音声ファイル(WAV)を生成する関数（一行単位）
 *
 * @param string $text 合成したいテキスト
 * @param int $speaker スピーカーID（例: 2=四国めたん, 8=春日部つむぎ）
 * @param string $outputPath 保存先のファイルパス
 * @return bool 成功したらtrue、失敗したらfalse
 */
function generateVoiceWithVoicevox(string $text, int $speaker, string $outputPath): bool
{
    global $VOICEVOX_HOST; // env.phpで定義されたVOICEVOXのURLを使用
    // VOICEVOXのローカルサーバーURL
    $baseUrl = $VOICEVOX_HOST;

    // ----------------------------------------------------
    // ステップ1: /audio_query (音声合成用クエリの作成)
    // ----------------------------------------------------
    $queryUrl = $baseUrl . '/audio_query?' . http_build_query([
        'text' => $text,
        'speaker' => $speaker
    ]);

    $ch = curl_init($queryUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // 空のPOSTデータを送信するために必要
    curl_setopt($ch, CURLOPT_POSTFIELDS, '');

    $queryJson = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$queryJson) {
        error_log("Failed to get audio_query. HTTP Code: " . $httpCode);
        return false;
    }

    // ----------------------------------------------------
    // ステップ2: /synthesis (音声合成の実行)
    // ----------------------------------------------------
    $synthesisUrl = $baseUrl . '/synthesis?' . http_build_query([
        'speaker' => $speaker
    ]);

    $ch = curl_init($synthesisUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $queryJson); // ステップ1で得たJSONをそのまま渡す
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: audio/wav'
    ]);

    $audioData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$audioData) {
        error_log("Failed to synthesize audio. HTTP Code: " . $httpCode);
        return false;
    }

    // ----------------------------------------------------
    // ステップ3: ファイルへの保存
    // ----------------------------------------------------
    return file_put_contents($outputPath, $audioData) !== false;
}