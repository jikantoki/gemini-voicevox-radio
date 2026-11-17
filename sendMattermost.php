<?php

// 共通関数の読み込み（env.php も内部で読み込まれます）
require_once __DIR__ . '/app_functions.php';

if (!isset($MATTERMOST_TOKEN) || $MATTERMOST_TOKEN == '' || !isset($INCOMING_WEBHOOK_URL) || $INCOMING_WEBHOOK_URL == '') {
  echo 'Mattermost通知設定がされていないため、通知はスキップします';
  exit;
}

$sendMessage = $_POST['sendMessage'] ?? $_GET['sendMessage'] ?? null;
if (!isset($sendMessage)) {
  echo '通知文が設定されていないため、通知はスキップします';
  exit;
}

$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
sendMattermostMessage("{$sendMessage}", 'ねるるラジオログ', "http://{$httpHost}/logo.jpg");
echo "通知を送信しました: {$sendMessage}";
