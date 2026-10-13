<?php

require_once './env.php';

if (!isset($MATTERMOST_TOKEN) || $MATTERMOST_TOKEN == '' || !isset($INCOMING_WEBHOOK_URL) || $INCOMING_WEBHOOK_URL == '') {
  echo 'Mattermost通知設定がされていないため、通知はスキップします';
  exit;
}

$sendMessage = $_POST['sendMessage'];
if (!isset($sendMessage)) {
  $sendMessage = $_GET['sendMessage'];
  if (!isset($sendMessage)) {
    echo '通知文が設定されていないため、通知はスキップします';
    exit;
  }
}

/**
 * MattermostからのPOSTリクエストを受け取り、検証してデータを返す
 *
 * @return array|null 受信データ（配列）、または検証失敗時はnull
 */
function getMattermostPostData(): ?array
{
    global $MATTERMOST_TOKEN;

    // トークンの検証
    $token = $_POST['token'] ?? '';
    if ($token !== $MATTERMOST_TOKEN) {
        header('HTTP/1.0 403 Forbidden');
        exit('Invalid token');
    }

    // データの取得
    return [
        'channel' => $_POST['channel_name'] ?? '',
        'user'    => $_POST['user_name'] ?? '',
        'text'    => $_POST['text'] ?? '',
    ];
}

/**
 * MattermostのIncoming Webhookへメッセージを送信する
 *
 * @param string $message 送信したいテキスト
 * @param string $username 表示されるユーザー名
 * @param string|null $icon_url アイコンのURL
 * @return string cURLの実行結果
 */
function sendMattermostMessage(string $message, string $username = 'Bot', ?string $icon_url = null): string
{
    global $INCOMING_WEBHOOK_URL;

    $payload = [
        'text'     => $message,
        'username' => $username,
    ];

    if ($icon_url) {
        $payload['icon_url'] = $icon_url;
    }

    $ch = curl_init($INCOMING_WEBHOOK_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

sendMattermostMessage("@all {$sendMessage}", 'ねるるラジオログ', "http://{$_SERVER['HTTP_HOST']}/logo.jpg");
echo "通知を送信しました: {$sendMessage}";
