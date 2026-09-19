<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

set_time_limit(0); // 無制限に実行できるようにする
ini_set('memory_limit', '512M'); // メモリ制限を増やす
sleep(1); // 1秒待機

require_once $_SERVER['DOCUMENT_ROOT'] . '/env.php'; // 環境変数の読み込み
require_once $_SERVER['DOCUMENT_ROOT'] . '/exec/func/makeWavFunc.php'; // 音声生成関数の読み込み

$inputText00 = loadFile('/buffer/00-2.txt');
$inputText15 = loadFile('/buffer/15-2.txt');
$inputText30 = loadFile('/buffer/30-2.txt');
$inputText45 = loadFile('/buffer/45-2.txt');

makeWav($inputText00, '00.wav');
makeWav($inputText15, '15.wav');
makeWav($inputText30, '30.wav');
makeWav($inputText45, '45.wav');

/**
 * ファイルを読み込んで中身を返す
 *
 * @param $filename ファイル名
 * @return string ファイルの内容
 */
function loadFile($filename = '') {
  if (file_exists($_SERVER['DOCUMENT_ROOT'] . $filename)) {
      return file_get_contents($_SERVER['DOCUMENT_ROOT'] . $filename);
  } else {
      return "File not found: " . $filename;
  }
}

/**
 * Waveファイルの製造
 *
 * @param string $playListText
 * @param string $outputFilename
 * @return void
 */
function makeWav($playListText = '', $outputFilename = 'output.wav'){
  if (empty($playListText)) {
      echo json_encode([
          'errorFlg' => true,
          'result' => 'Error: No playlist provided.'
      ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
      exit;
  }

  // 先ほどのスクリプトで変換したと想定したプレイリストデータ
  $playlist = json_decode($playListText, true);
  if (!is_array($playlist)) {
      echo json_encode([
          'errorFlg' => true,
          'result' => 'Error: Invalid playlist format.'
      ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
      exit;
  }

  // 保存先ディレクトリの作成（なければ）
  $outputDir = $_SERVER['DOCUMENT_ROOT'] . '/output/buffer';
  $finalOutputDir = $_SERVER['DOCUMENT_ROOT'] . '/output';
  if (!is_dir($outputDir)) {
      mkdir($outputDir, 0777, true);
  }
  if (!is_dir($finalOutputDir)) {
      mkdir($finalOutputDir, 0777, true);
  }
  /**
   * 生成した行単位のwavファイルの一時保存先
   */
  $audioParts = [];

  // ループ処理で順番に音声を生成
  foreach ($playlist as $index => $item) {
      // ファイル名（例: voice_0.wav, voice_1.wav）
      $fileName = sprintf("voice_%d.wav", $index);
      $filePath = $outputDir . '/' . $fileName;

      echo "「{$item['text']}」の音声を生成中...\n";

      if (generateVoiceWithVoicevox($item['text'], $item['speaker'], $filePath)) {
          $audioParts[] = file_get_contents($filePath);
          echo "成功: {$fileName} を保存しました。\n";
      } else {
          echo "失敗: {$fileName} の生成に失敗しました。\n";
      }
  }

  if (!empty($audioParts)) {
      // wave結合処理

      // 1. 最初のファイルのヘッダー（先頭44バイト）を取得
      $firstHeader = substr($audioParts[0], 0, 44);

      // 2. 全てファイルの「データ部分（44バイト目以降）」だけを抽出して連結
      $combinedData = '';
      foreach ($audioParts as $audioData) {
          $combinedData .= substr($audioData, 44);
      }

      // 3. 結合後のデータサイズから、WAVヘッダーのサイズ情報を再計算して上書き
      $dataSize = strlen($combinedData);
      $fileSize = $dataSize + 36; // 全体サイズ - 8バイト

      // 4バイト目からの「全ファイルサイズ」を書き換え（リトルエンディアン形式）
      $firstHeader = substr_replace($firstHeader, pack('V', $fileSize), 4, 4);
      // 40バイト目からの「データサイズ」を書き換え
      $firstHeader = substr_replace($firstHeader, pack('V', $dataSize), 40, 4);

      // 4. ヘッダーとデータ部分を合わせて、1つのファイルとして保存
      file_put_contents($finalOutputDir . '/' . $outputFilename, $firstHeader . $combinedData);

      echo "音声ファイルが生成されました: /output/{$outputFilename}\n";
  } else {
      echo "音声ファイルが生成されませんでした。\n";
  }
}