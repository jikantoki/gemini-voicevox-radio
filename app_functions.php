<?php
/**
 * ねるるラジオ (gemini-api) アプリケーション共通関数定義
 *
 * ※ 他リポジトリで共通管理されている functions.php や database 関連と区別・競合回避のため、
 *    本プロジェクト専用の関数は本ファイル (app_functions.php) で一元管理します。
 */

// ドキュメントルートおよび環境設定ファイルの解決（Web環境/CLI環境双方に対応）
$appDocRoot = rtrim(!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : __DIR__, '/\\');
if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = $appDocRoot;
}
if (file_exists($appDocRoot . '/env.php')) {
    require_once $appDocRoot . '/env.php';
} elseif (file_exists(__DIR__ . '/env.php')) {
    require_once __DIR__ . '/env.php';
}

/* ==========================================================================
   1. 汎用・ファイル・通信ユーティリティ関数
   ========================================================================== */

if (!function_exists('getEcho')) {
    /**
     * ローカル内の他PHPファイルの実行関数（HTTP経由）
     *
     * @param string $execPath 実行するPHPファイルのパス
     * @return string 出力内容
     */
    function getEcho(string $execPath = '/index.php'): string
    {
        $httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $url = 'http://' . $httpHost . '/' . ltrim($execPath, '/');

        $output = @file_get_contents($url);
        if ($output === false) {
            return 'Error: 読み込みに失敗しました (' . $url . ')';
        }

        return $output;
    }
}

if (!function_exists('loadFile')) {
    /**
     * ファイルを読み込んで中身を返す
     *
     * @param string $filename ファイル名（相対パスまたは絶対パス）
     * @return string ファイルの内容
     */
    function loadFile(string $filename = ''): string
    {
        $docRoot = rtrim(!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : __DIR__, '/\\');
        $filePath = $docRoot . '/' . ltrim($filename, '/\\');

        if (file_exists($filePath)) {
            return file_get_contents($filePath);
        } else {
            return "File not found: " . $filename;
        }
    }
}

if (!function_exists('outputToFile')) {
    /**
     * テキストファイルのローカル保存
     * 単一ファイル名の場合は自動的に /buffer/ 配下へ保存します。
     *
     * @param string $filename 保存するファイル名または相対パス
     * @param string $content 保存する内容
     * @return int|false 保存に成功した場合は書き込んだバイト数、失敗した場合はfalse
     */
    function outputToFile(string $filename = '', string $content = '')
    {
        $docRoot = rtrim(!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : __DIR__, '/\\');

        // パス区切り文字が含まれていない場合は /buffer/ 配下に保存
        if (strpos($filename, '/') === false && strpos($filename, '\\') === false) {
            $filePath = $docRoot . '/buffer/' . $filename;
        } else {
            $filePath = $docRoot . '/' . ltrim($filename, '/\\');
        }

        // 保存先ディレクトリが存在しない場合は自動作成
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $content = str_replace('\n', "\n", $content);
        return @file_put_contents($filePath, $content);
    }
}

/* ==========================================================================
   2. 天気・気象庁API関連関数
   ========================================================================== */

if (!function_exists('confirmAqc')) {
    /**
     * 品質情報を確認し、データを文字列で返す
     *
     * @param array|null $data
     * @return string
     */
    function confirmAqc(?array $data): string
    {
        if (!is_array($data) || count($data) < 2) {
            return "データなし";
        }

        // 品質情報フラグが 0 (正常) の場合
        if ($data[1] === 0) {
            return (string)$data[0];
        }

        return "品質情報を確認して下さい";
    }
}

if (!function_exists('findIndex')) {
    /**
     * 対象エリアのデータが格納されているインデックス番号を返す
     *
     * @param array $data
     * @param string $code
     * @return int|null
     */
    function findIndex(array $data, string $code): ?int
    {
        foreach ($data as $index => $item) {
            if (isset($item['area']['code']) && $item['area']['code'] === $code) {
                return $index;
            }
        }
        return null;
    }
}

if (!function_exists('getWeatherReport')) {
    /**
     * 気象庁APIより天気情報を取得し、整形した配列を返す
     *
     * @param string $area エリア番号（初期値: 東京都 "130000"）
     * @param string $detailArea 詳細予報エリア番号（初期値: 東京地方 "130010"）
     * @param string $stnid 観測所番号（初期値: 北の丸公園 "44132"）
     * @return array
     */
    function getWeatherReport(
        string $area = "130000",
        string $detailArea = "130010",
        string $stnid = "44132"
    ): array
    {
        // 1. 最新時刻の取得と日時解析
        $latestTimeUrl = "https://www.jma.go.jp/bosai/amedas/data/latest_time.txt";
        $latestTimeRaw = @file_get_contents($latestTimeUrl);

        if ($latestTimeRaw === false) {
            return ["エラー" => "最新時刻データの取得に失敗しました。"];
        }

        try {
            $dateTime = new DateTime(trim($latestTimeRaw));
        } catch (Exception $e) {
            return ["エラー" => "日時フォーマットの解析に失敗しました。"];
        }

        $yyyymmdd = $dateTime->format('Ymd');
        $hour = (int)$dateTime->format('H');
        $h3 = sprintf('%02d', floor($hour / 3) * 3);

        // 2. 天気概況の取得
        $overviewUrl = "https://www.jma.go.jp/bosai/forecast/data/overview_forecast/{$area}.json";
        $overviewJson = @file_get_contents($overviewUrl);
        $overviewText = "取得失敗";

        if ($overviewJson !== false) {
            $overviewData = json_decode($overviewJson, true);
            if (isset($overviewData['text'])) {
                // 空白・改行の正規化
                $lines = preg_split('/\s+/u', trim($overviewData['text']));
                $overviewText = implode("\n", $lines);
            }
        }

        // 3. 天気予報の取得
        $forecastUrl = "https://www.jma.go.jp/bosai/forecast/data/forecast/{$area}.json";
        $forecastJson = @file_get_contents($forecastUrl);
        $tomorrowWeather = "取得失敗";

        if ($forecastJson !== false) {
            $forecastData = json_decode($forecastJson, true);
            if (isset($forecastData[0]['timeSeries'][0]['areas'])) {
                $areas = $forecastData[0]['timeSeries'][0]['areas'];
                $targetIndex = findIndex($areas, $detailArea);

                if ($targetIndex !== null && isset($areas[$targetIndex]['weathers'][1])) {
                    $words = preg_split('/\s+/u', trim($areas[$targetIndex]['weathers'][1]));
                    $tomorrowWeather = implode(' ', $words);
                }
            }
        }

        // 4. アメダスデータの取得
        $amedasUrl = "https://www.jma.go.jp/bosai/amedas/data/point/{$stnid}/{$yyyymmdd}_{$h3}.json";
        $amedasJson = @file_get_contents($amedasUrl);
        $latestTemp = "データなし";
        $latestPrecipitation10m = "データなし";

        if ($amedasJson !== false) {
            $amedasData = json_decode($amedasJson, true);
            if (is_array($amedasData) && !empty($amedasData)) {
                $keys = array_keys($amedasData);
                $latestKey = max($keys);

                if (isset($amedasData[$latestKey])) {
                    $pointData = $amedasData[$latestKey];
                    $latestTemp = confirmAqc($pointData['temp'] ?? null);
                    $latestPrecipitation10m = confirmAqc($pointData['precipitation10m'] ?? null);
                }
            }
        }

        // 5. 出力配列の結合
        return [
            "現在の気温" => "{$latestTemp} 度",
            "現在の降水量(10分あたり)" => "{$latestPrecipitation10m} mm",
            "翌日の天気" => "{$tomorrowWeather}",
            "天気概況" => "{$overviewText}"
        ];
    }
}

/* ==========================================================================
   3. Mattermost連携関数
   ========================================================================== */

if (!function_exists('getMattermostPostData')) {
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
}

if (!function_exists('sendMattermostMessage')) {
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

        if (empty($INCOMING_WEBHOOK_URL)) {
            return '';
        }

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

        return (string)$result;
    }
}

/* ==========================================================================
   4. AI・台本生成関連関数 (Gemini & Ollama/Open WebUI)
   ========================================================================== */

if (!function_exists('askLocalAI')) {
    /**
     * Open WebUI (Ollama互換) APIに質問を投げる (履歴維持対応版)
     *
     * @param string $prompt 最新の質問
     * @param string $chatId ユーザーIDやチャンネルIDなど、チャットを一意に識別する文字列
     * @return string 生成された文字列
     */
    function askLocalAI(string $prompt = '', string $chatId = ''): string
    {
        global $LLM_BASE_URL, $LLM_API_KEY, $MODEL_NAME;

        $url = rtrim($LLM_BASE_URL, '/') . '/api/chat/completions';

        // --- 履歴ファイルの準備 ---
        $historyFile = '';
        if ($chatId !== '') {
            $historyFile = sys_get_temp_dir() . '/chat_' . md5($chatId) . '.json';
        }
        $messages = [];

        if ($chatId !== '') {
            // 既存の履歴があれば読み込む
            if (file_exists($historyFile)) {
                $historyData = file_get_contents($historyFile);
                $messages = json_decode($historyData, true) ?? [];
            }
        }

        // 履歴が長くなりすぎた場合は古いものを古い順に削除 (トークン節約・最大10往復程度)
        if (count($messages) > 20) {
            $messages = array_slice($messages, -20);
        }

        // 新しいユーザーの発言を追加
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $data = [
            'model' => $MODEL_NAME,
            'messages' => $messages,
            'temperature' => 0.7,
            'stream' => true,
            'features' => [
                'web_search' => true
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $LLM_API_KEY
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            getEcho("/sendMattermost.php?sendMessage=LLM接続エラー: {$error}");
            echo "LLM接続エラー: " . $error . "\n";
            return "LLM接続エラー: " . $error;
        }

        // --- ストリーミングデータの解析処理 ---
        $lines = explode("\n", (string)$response);
        $fullText = "";

        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'data: ') === 0) {
                $jsonStr = substr($line, 6);
                if (trim($jsonStr) === '[DONE]') {
                    break;
                }
                $json = json_decode($jsonStr, true);
                if (isset($json['choices'][0]['delta']['content'])) {
                    $fullText .= $json['choices'][0]['delta']['content'];
                }
            }
        }

        // 結果を返す & 履歴の保存
        if (!empty($fullText)) {
            $messages[] = ['role' => 'assistant', 'content' => $fullText];
            if ($chatId !== '') {
                file_put_contents($historyFile, json_encode($messages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }

            echo "LLMでのトーク生成に成功しました\n";
            getEcho("/sendMattermost.php?sendMessage=Geminiの代わりに、LLMでトークを生成しました");
            return $fullText;
        } else {
            echo "LLMの回答の解析に失敗しました。レスポンスの冒頭: " . substr((string)$response, 0, 100) . "\n";
            getEcho("/sendMattermost.php?sendMessage=LLMの回答の解析に失敗しました。レスポンスの冒頭:" . substr((string)$response, 0, 100));
            return "LLMの回答の解析に失敗しました。レスポンスの冒頭: " . substr((string)$response, 0, 100);
        }
    }
}

if (!function_exists('makeText')) {
    /**
     * Geminiに投げる
     *
     * @param string $prompt AIに投げるプロンプト
     * @param integer $min 放送時刻（分）
     * @return string ニュース原稿
     */
    function makeText($prompt = '', $min = 0)
    {
        global $API_KEYs;

        $httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $URL = 'http://' . $httpHost . '/request.php';

        $requestData = [
            'prompt' => $prompt
        ];

        $requestCnt = 0;

        while (true) {
            echo "Geminiに質問しています…\n";
            $ch = curl_init($URL);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . $API_KEYs[$requestCnt]
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($requestData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            if ($response === false) {
                echo 'Failed to send request to ' . $URL . "\n";
                break;
            }

            $json = json_decode($response, true);

            $errCode = 200;
            if (isset($json['result']) && isset($json['result']['error']['code']) && $json['result']['error']['code']) {
                $errCode = $json['result']['error']['code'];
            }
            if ($errCode == 503) {
                echo "{$errCode}エラー（アクセス過多）のため、LLMでの生成に切り替えます\n";
                echo "次の{$min}分に放送する原稿作成は、GeminiがビジーなためLLMでの生成に切り替えます\n";
                return askLocalAI($prompt);
            } else if ($errCode == 429) {
                echo "{$requestCnt}番のキーは{$errCode}エラーのため、別のキーで再実行します\n";
                if (count($API_KEYs) - 1 <= $requestCnt) {
                    echo "次の{$min}分に放送する原稿作成について、どのAPIキーでもリクエストを実行できませんでした\n";
                    getEcho("/sendMattermost.php?sendMessage=次の{$min}分に放送する原稿作成について、どのAPIキーでもリクエストを実行できませんでした");
                    return json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                sleep(3);
                $requestCnt++;
                continue;
            }
            echo "Geminiからの返答あり。APIキーは環境変数の" . ($requestCnt + 1) . "番を使用しました\n";
            return $json['result']['candidates'][0]['content']['parts'][0]['text'] ?? $response ?? 'Error: No response from Gemini.';
        }
    }
}

/* ==========================================================================
   5. VOICEVOX・音声合成関連関数
   ========================================================================== */

if (!function_exists('getTalkScript')) {
    /**
     * Geminiが投げた台本をAPIスクリプト形式に変換
     *
     * @param string $inputText 台本
     * @return array スクリプト
     */
    function getTalkScript($inputText = ''): array
    {
        // キャラクター名とVOICEVOXのspeaker IDの対応マッピング
        $speakerMap = [
            'めたん'   => 2,  // 四国めたん（ノーマル）
            'ずんだもん' => 3,  // ずんだもん（ノーマル）
            'つむぎ'   => 8,  // 春日部つむぎ（ノーマル）
            'りつ'     => 9,  // 波音リツ（ノーマル）
            'はう'     => 10, // 雨晴はう（ノーマル）
            'そら'     => 16, // 九州そら（ノーマル）
        ];

        /** 前の発言者のspeaker ID */
        $lastSpeaker = '';

        // テキストを行ごとに分解
        $lines = explode("\n", $inputText);
        $voicevoxPlayList = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // 「名前:発言内容」の形式にマッチング
            if (preg_match('/^([^:]+):(.*)$/', $line, $matches)) {
                $name = trim($matches[1]);
                $text = trim($matches[2]);

                if (isset($speakerMap[$name])) {
                    $voicevoxPlayList[] = [
                        'speaker' => $speakerMap[$name],
                        'text'    => $text,
                    ];
                    $lastSpeaker = $speakerMap[$name];
                } else {
                    if (!empty($lastSpeaker)) {
                        $voicevoxPlayList[] = [
                            'speaker' => $lastSpeaker,
                            'text'    => $line,
                        ];
                    }
                }
            }
        }

        echo json_encode($voicevoxPlayList, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return $voicevoxPlayList;
    }
}

if (!function_exists('generateVoiceWithVoicevox')) {
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
        global $VOICEVOX_HOST;
        $baseUrl = $VOICEVOX_HOST;

        // ステップ1: /audio_query (音声合成用クエリの作成)
        $queryUrl = $baseUrl . '/audio_query?' . http_build_query([
            'text'    => $text,
            'speaker' => $speaker
        ]);

        $ch = curl_init($queryUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '');

        $queryJson = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$queryJson) {
            error_log("Failed to get audio_query. HTTP Code: " . $httpCode);
            return false;
        }

        // ステップ2: /synthesis (音声合成の実行)
        $synthesisUrl = $baseUrl . '/synthesis?' . http_build_query([
            'speaker' => $speaker
        ]);

        $ch = curl_init($synthesisUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $queryJson);
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

        // ステップ3: ファイルへの保存
        return file_put_contents($outputPath, $audioData) !== false;
    }
}

if (!function_exists('makeWav')) {
    /**
     * Waveファイルの製造
     *
     * @param string $playListText
     * @param string $outputFilename
     * @return void
     */
    function makeWav(string $playListText = '', string $outputFilename = 'output.wav'): void
    {
        if (empty($playListText)) {
            echo json_encode([
                'errorFlg' => true,
                'result'   => 'Error: No playlist provided.'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        $playlist = json_decode($playListText, true);
        if (!is_array($playlist)) {
            echo json_encode([
                'errorFlg' => true,
                'result'   => 'Error: Invalid playlist format.'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        $docRoot = rtrim(!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : __DIR__, '/\\');
        $outputDir = $docRoot . '/output/buffer';
        $finalOutputDir = $docRoot . '/output';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }
        if (!is_dir($finalOutputDir)) {
            mkdir($finalOutputDir, 0777, true);
        }

        $audioParts = [];

        foreach ($playlist as $index => $item) {
            $fileName = sprintf("voice_%d.wav", $index);
            $filePath = $outputDir . '/' . $fileName;

            echo "「{$item['text']}」の音声を生成中...\n";

            if (generateVoiceWithVoicevox($item['text'], $item['speaker'], $filePath)) {
                $audioParts[] = file_get_contents($filePath);
                echo "成功: {$fileName} を保存しました。\n";
            } else {
                echo "失敗: {$fileName} の生成に失敗しました。\n";
                getEcho("/sendMattermost.php?sendMessage=失敗: {$fileName} の生成に失敗しました。");
            }
        }

        if (!empty($audioParts)) {
            // 1. 最初のファイルのヘッダー（先頭44バイト）を取得
            $firstHeader = substr($audioParts[0], 0, 44);

            // 2. 全ファイルの「データ部分（44バイト目以降）」だけを抽出して連結
            $combinedData = '';
            foreach ($audioParts as $audioData) {
                $combinedData .= substr($audioData, 44);
            }

            // 3. 結合後のデータサイズからWAVヘッダーのサイズ情報を再計算
            $dataSize = strlen($combinedData);
            $fileSize = $dataSize + 36;

            $firstHeader = substr_replace($firstHeader, pack('V', $fileSize), 4, 4);
            $firstHeader = substr_replace($firstHeader, pack('V', $dataSize), 40, 4);

            // 4. ヘッダーとデータ部分を合わせて保存
            file_put_contents($finalOutputDir . '/' . $outputFilename, $firstHeader . $combinedData);

            echo "音声ファイルが生成されました: /output/{$outputFilename}\n";
        } else {
            echo "次の音声ファイルは生成されませんでした。: /output/{$outputFilename}\n";
            getEcho("/sendMattermost.php?sendMessage=次の音声ファイルは生成されませんでした。: /output/{$outputFilename}");
        }
    }
}

/* ==========================================================================
   6. 音声合成ミキシング・Podcast関連関数
   ========================================================================== */

if (!function_exists('mixAudioWithOpEd')) {
    /**
     * オープニング、トーク(Wav)、BGM(自動ループ/カット)、エンディングを合成して1つのMP3を出力する
     */
    function mixAudioWithOpEd(
        string $opMp3,
        string $talkWav,
        string $bgmMp3,
        string $edMp3,
        string $outputMp3,
        float $bgmVolume = 0.15,
        string $songTitle = '',
        string $songArtist = 'ねるるラジオ',
        string $thumbnailPath = '/assets/img/default.jpg',
        string $comment = ''
    ): bool {
        $docRoot = rtrim(!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : __DIR__, '/\\');

        $getAbsPath = function($path) use ($docRoot) {
            return $docRoot . '/' . ltrim($path, '/\\');
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

        // 2. トークファイルの長さを取得
        $durationCmd = sprintf('ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>&1', escapeshellarg($absTalk));
        $durationOutput = trim((string)shell_exec($durationCmd));
        $talkDuration = floatval($durationOutput);

        echo "--- デバッグ情報 ---\n";
        echo "解析された秒数: {$talkDuration} 秒\n";
        echo "--------------------\n";

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
            escapeshellarg($absOp),
            escapeshellarg($absTalk),
            escapeshellarg($absBgm),
            escapeshellarg($absEd),
            escapeshellarg($absImage),
            $talkFilter,
            $bgmVolume,
            escapeshellarg($songTitle),
            escapeshellarg($songArtist),
            escapeshellarg($comment),
            escapeshellarg($comment),
            escapeshellarg($absOutput)
        );

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
}

if (!function_exists('getTopicsAsText')) {
    /**
     * ピックアップファイルからトピックを読み込み、箇条書きテキストとして取得する
     *
     * @param string $pickupFileRelPath ピックアップファイルの相対パス
     * @return string 改行区切りのトピックテキスト
     */
    function getTopicsAsText(string $pickupFileRelPath): string
    {
        $docRoot = rtrim(!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : __DIR__, '/\\');
        $pickupAbsPath = $docRoot . '/' . ltrim($pickupFileRelPath, '/\\');

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
}

if (!function_exists('saveToPodcasts')) {
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

        $targetDirMap = [
            'サボさんの千葉来ません？'       => 'sabochiba',
            'ガジェットマニア'               => 'gadgetmania',
            'ねるるミュージックアカデミー' => 'musicacademy',
        ];

        if (!isset($targetDirMap[$title])) {
            echo "【Podcast】番組「{$title}」はPodcast保存の対象外です。スキップします。\n";
            return false;
        }

        if (empty($PODCAST_PATH)) {
            echo "【Podcastエラー】env.php の \$PODCAST_PATH が設定されていません。\n";
            getEcho("/sendMattermost.php?sendMessage=【Podcastエラー】env.php の \$PODCAST_PATH が設定されていません。");
            return false;
        }

        $docRoot = rtrim(!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : __DIR__, '/\\');
        $sourceAbsPath = $docRoot . '/' . ltrim($sourceMp3RelPath, '/\\');

        if (!file_exists($sourceAbsPath)) {
            echo "【Podcastエラー】コピー元のファイルが見つかりません -> {$sourceAbsPath}\n";
            getEcho("/sendMattermost.php?sendMessage=【Podcastエラー】コピー元のファイルが見つかりません -> {$sourceAbsPath}");
            return false;
        }

        $subDir = $targetDirMap[$title];
        $destDir = rtrim($PODCAST_PATH, '/') . '/' . $subDir;
        if (!is_dir($destDir)) {
            if (!mkdir($destDir, 0777, true) && !is_dir($destDir)) {
                echo "【Podcastエラー】保存先ディレクトリを作成できませんでした -> {$destDir}\n";
                getEcho("/sendMattermost.php?sendMessage=【Podcastエラー】保存先ディレクトリを作成できませんでした -> {$destDir}");
                return false;
            }
        }

        $now = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
        $currentMinute = (int)$now->format('i');
        if ($currentMinute >= $targetMinute) {
            $now->modify('+1 hour');
        }
        $now->setTime((int)$now->format('H'), $targetMinute, 0);
        $formattedDate = $now->format('YmdHi');

        $destFileName = sprintf('%s_%s.mp3', $title, $formattedDate);
        $destFilePath = $destDir . '/' . $destFileName;

        if (copy($sourceAbsPath, $destFilePath)) {
            echo "【Podcast】保存成功 -> {$destFilePath}\n";
            return true;
        } else {
            echo "【Podcastエラー】ファイルのコピーに失敗しました -> {$destFilePath}\n";
            getEcho("/sendMattermost.php?sendMessage=【Podcastエラー】ファイルのコピーに失敗しました -> {$destFilePath}");
            return false;
        }
    }
}

if (!function_exists('getPodcastProgramDefinitions')) {
    /**
     * Podcast番組定義
     */
    function getPodcastProgramDefinitions(): array
    {
        return [
            'sabochiba' => [
                'title'       => 'サボさんの千葉来ません？',
                'subDir'      => 'sabochiba',
                'xmlFilename' => 'sabochiba.xml',
                'imageSrc'    => '/var/www/html/vhosts/radio/img/programs/サボ千葉.jpg',
                'imageName'   => 'sabochiba_1400.jpg',
                'description' => '千葉を愛し、千葉に愛された男「サボさん」が、千葉県の隠れた名所や絶品グルメ、ローカルな魅力を熱く語り尽くす観光勝手に応援バラエティ。',
                'category'    => 'Society & Culture',
                'guid'        => 'b457e799-6e3e-526f-9982-f5651c6b541a',
            ],
            'gadgetmania' => [
                'title'       => 'ガジェットマニア',
                'subDir'      => 'gadgetmania',
                'xmlFilename' => 'gadgetmania.xml',
                'imageSrc'    => '/var/www/html/vhosts/radio/img/programs/ガジェットマニア.jpg',
                'imageName'   => 'gadgetmania_1400.jpg',
                'description' => '最新のスマートフォン、PC周りの周辺機器、自作PCパーツから便利な家電まで、ガジェットを愛するすべての人のための、愛と物欲に溢れた11分間。',
                'category'    => 'Technology',
                'guid'        => 'a21b44d8-7954-5d51-87a2-c74b88eb342b',
            ],
            'musicacademy' => [
                'title'       => 'ねるるミュージックアカデミー',
                'subDir'      => 'musicacademy',
                'xmlFilename' => 'musicacademy.xml',
                'imageSrc'    => '/var/www/html/vhosts/radio/img/programs/ねるるミュージックアカデミー.jpg',
                'imageName'   => 'musicacademy_1400.jpg',
                'description' => '音楽理論や打ち込み楽曲の極意について、初心者でもわかりやすく解説する音楽学習番組。音楽制作に興味がある方は必聴です。',
                'category'    => 'Music',
                'guid'        => 'c902d3f1-4389-51a7-8911-3e4cb49ec813',
            ],
        ];
    }
}

if (!function_exists('ensurePodcastArtwork')) {
    /**
     * 1400x1400のPodcast用アートワーク画像が存在することを確認し、なければFFmpegで生成する
     *
     * @param string $srcPath 元画像パス
     * @param string $dstPath 出力先パス
     * @return bool 成功時true
     */
    function ensurePodcastArtwork(string $srcPath, string $dstPath): bool
    {
        if (file_exists($dstPath) && filesize($dstPath) > 0) {
            return true;
        }
        if (!file_exists($srcPath)) {
            return false;
        }

        $cmd = sprintf(
            'ffmpeg -y -i %s -vf "scale=1400:1400:flags=lanczos" -frames:v 1 -update 1 -q:v 2 %s 2>&1',
            escapeshellarg($srcPath),
            escapeshellarg($dstPath)
        );
        @exec($cmd, $out, $ret);
        return ($ret === 0 && file_exists($dstPath));
    }
}

if (!function_exists('generatePodcastXml')) {
    /**
     * 指定した番組（または全番組）のPodcast RSS XMLを生成して保存する
     *
     * @param string $targetProgramKey 'sabochiba', 'gadgetmania', 'musicacademy', または ''（全番組）
     * @return bool 成功時true
     */
    function generatePodcastXml(string $targetProgramKey = ''): bool
    {
        global $PODCAST_PATH, $RSS_PATH, $PAGES_URL, $PODCAST_URL, $RADIO_META;

        if (empty($PODCAST_PATH) || empty($RSS_PATH)) {
            echo "【RSS生成エラー】env.php の \$PODCAST_PATH または \$RSS_PATH が未設定です。\n";
            return false;
        }

        if (!is_dir($RSS_PATH)) {
            if (!mkdir($RSS_PATH, 0777, true) && !is_dir($RSS_PATH)) {
                echo "【RSS生成エラー】RSS保存先ディレクトリの作成に失敗しました -> {$RSS_PATH}\n";
                return false;
            }
        }

        $lowerSymlink = '/var/www/html/vhosts/radio/podcasts';
        if (!file_exists($lowerSymlink) && !is_link($lowerSymlink) && is_dir($PODCAST_PATH)) {
            @symlink($PODCAST_PATH, $lowerSymlink);
        }

        $programs = getPodcastProgramDefinitions();

        if (!empty($targetProgramKey)) {
            if (!isset($programs[$targetProgramKey])) {
                echo "【RSS生成エラー】無効な番組キーです -> {$targetProgramKey}\n";
                return false;
            }
            $targetList = [$targetProgramKey => $programs[$targetProgramKey]];
        } else {
            $targetList = $programs;
        }

        $podcastBaseUrl = rtrim($PODCAST_URL ?? ($PAGES_URL . '/' . basename($PODCAST_PATH)), '/');
        $feedBaseUrl    = rtrim($PAGES_URL ?? 'https://radio.enoki.xyz/pages', '/') . '/' . basename($RSS_PATH);

        foreach ($targetList as $key => $prog) {
            $subDir = $prog['subDir'];
            $folderPath = rtrim($PODCAST_PATH, '/') . '/' . $subDir;

            $dstImagePath = rtrim($RSS_PATH, '/') . '/' . $prog['imageName'];
            ensurePodcastArtwork($prog['imageSrc'], $dstImagePath);
            $imageUrl = $feedBaseUrl . '/' . $prog['imageName'];

            $items = [];
            if (is_dir($folderPath)) {
                $files = glob($folderPath . '/*.mp3');
                if ($files !== false) {
                    rsort($files);

                    foreach ($files as $filePath) {
                        $fileName = basename($filePath);

                        if (preg_match('/_\d{12}_.*\.mp3$/', $fileName)) {
                            @unlink($filePath);
                            continue;
                        }

                        if (!preg_match('/_(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})\.mp3$/', $fileName, $matches)) {
                            continue;
                        }

                        $parsedTime = strtotime("{$matches[1]}-{$matches[2]}-{$matches[3]} {$matches[4]}:{$matches[5]}:00");
                        $timestamp = ($parsedTime !== false) ? $parsedTime : filemtime($filePath);
                        $displayTime = sprintf('%04d年%02d月%02d日 %02d:%02d', $matches[1], $matches[2], $matches[3], $matches[4], $matches[5]);

                        $pubDate = date('r', $timestamp);
                        $fileSize = filesize($filePath);

                        $cmd = sprintf('ffprobe -v quiet -print_format json -show_format %s 2>&1', escapeshellarg($filePath));
                        $probeOutput = shell_exec($cmd);
                        $meta = json_decode((string)$probeOutput, true);

                        $duration = (int)round(floatval($meta['format']['duration'] ?? 0));
                        $tags = $meta['format']['tags'] ?? [];
                        $topicText = trim($tags['comment'] ?? $tags['description'] ?? '');

                        $topicText = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $topicText);
                        $topicText = str_replace(["\r\n", "\r"], "\n", $topicText);

                        $itemDescription = $prog['description'];
                        if (!empty($topicText)) {
                            $itemDescription .= "\n\n" . $topicText;
                        }

                        $enclosureUrl = $podcastBaseUrl . '/' . $subDir . '/' . rawurlencode($fileName);
                        $guid = sprintf('%s-%s%s%s%s%s', $subDir, $matches[1], $matches[2], $matches[3], $matches[4], $matches[5]);
                        $itemTitle = sprintf('%s (%s放送回)', $prog['title'], $displayTime);

                        $items[] = [
                            'title'       => $itemTitle,
                            'description' => $itemDescription,
                            'pubDate'     => $pubDate,
                            'enclosure'   => [
                                'url'    => $enclosureUrl,
                                'length' => $fileSize,
                                'type'   => 'audio/mpeg',
                            ],
                            'guid'        => $guid,
                            'duration'    => $duration,
                            'image'       => $imageUrl,
                        ];
                    }
                }
            }

            $xmlContent = buildPodcastRssXml($prog, $items, $feedBaseUrl . '/' . $prog['xmlFilename'], $imageUrl);

            $outputXmlPath = rtrim($RSS_PATH, '/') . '/' . $prog['xmlFilename'];
            if (file_put_contents($outputXmlPath, $xmlContent) !== false) {
                echo "【RSS生成成功】{$prog['title']} -> {$outputXmlPath}\n";
            } else {
                echo "【RSS生成エラー】ファイル書き込みに失敗しました -> {$outputXmlPath}\n";
                if (function_exists('getEcho')) {
                    getEcho("/sendMattermost.php?sendMessage=【RSS生成エラー】ファイル書き込みに失敗しました -> {$outputXmlPath}");
                }
            }
        }

        return true;
    }
}

if (!function_exists('buildPodcastRssXml')) {
    /**
     * 番組情報とエピソード一覧からPodcast RSS XML文字列を生成する
     */
    function buildPodcastRssXml(array $program, array $items, string $feedUrl, string $imageUrl): string
    {
        global $RADIO_META;

        $siteUrl  = $RADIO_META['url'] ?? 'https://radio.enoki.xyz';
        $author   = $RADIO_META['author'] ?? 'ねるるラジオ';
        $language = $RADIO_META['language'] ?? 'ja';
        $mail     = $RADIO_META['mail'] ?? 'info@enoki.xyz';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0"' . "\n";
        $xml .= '    xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"' . "\n";
        $xml .= '    xmlns:podcast="https://podcastindex.org/namespace/1.0"' . "\n";
        $xml .= '    xmlns:content="http://purl.org/rss/1.0/modules/content/"' . "\n";
        $xml .= '    xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= '  <channel>' . "\n";
        $xml .= '    <title>' . htmlspecialchars($program['title'], ENT_XML1, 'UTF-8') . '</title>' . "\n";
        $xml .= '    <link>' . htmlspecialchars($siteUrl, ENT_XML1, 'UTF-8') . '</link>' . "\n";
        $xml .= '    <language>' . htmlspecialchars($language, ENT_XML1, 'UTF-8') . '</language>' . "\n";
        $xml .= '    <description><![CDATA[' . $program['description'] . ']]></description>' . "\n";
        $xml .= '    <itunes:author>' . htmlspecialchars($author, ENT_XML1, 'UTF-8') . '</itunes:author>' . "\n";
        $xml .= '    <itunes:summary>' . htmlspecialchars($program['description'], ENT_XML1, 'UTF-8') . '</itunes:summary>' . "\n";
        $xml .= '    <itunes:owner>' . "\n";
        $xml .= '      <itunes:name>' . htmlspecialchars($author, ENT_XML1, 'UTF-8') . '</itunes:name>' . "\n";
        $xml .= '      <itunes:email>' . htmlspecialchars($mail, ENT_XML1, 'UTF-8') . '</itunes:email>' . "\n";
        $xml .= '    </itunes:owner>' . "\n";
        $xml .= '    <itunes:image href="' . htmlspecialchars($imageUrl, ENT_XML1, 'UTF-8') . '" />' . "\n";
        $xml .= '    <image>' . "\n";
        $xml .= '      <url>' . htmlspecialchars($imageUrl, ENT_XML1, 'UTF-8') . '</url>' . "\n";
        $xml .= '      <title>' . htmlspecialchars($program['title'], ENT_XML1, 'UTF-8') . '</title>' . "\n";
        $xml .= '      <link>' . htmlspecialchars($siteUrl, ENT_XML1, 'UTF-8') . '</link>' . "\n";
        $xml .= '    </image>' . "\n";
        $xml .= '    <itunes:category text="' . htmlspecialchars($program['category'], ENT_XML1, 'UTF-8') . '" />' . "\n";
        $xml .= '    <itunes:explicit>false</itunes:explicit>' . "\n";
        $xml .= '    <podcast:locked>no</podcast:locked>' . "\n";
        if (!empty($program['guid'])) {
            $xml .= '    <podcast:guid>' . htmlspecialchars($program['guid'], ENT_XML1, 'UTF-8') . '</podcast:guid>' . "\n";
        }
        $xml .= '    <atom:link href="' . htmlspecialchars($feedUrl, ENT_XML1, 'UTF-8') . '" rel="self" type="application/rss+xml" />' . "\n";

        foreach ($items as $item) {
            $xml .= '    <item>' . "\n";
            $xml .= '      <title>' . htmlspecialchars($item['title'], ENT_XML1, 'UTF-8') . '</title>' . "\n";
            $xml .= '      <link>' . htmlspecialchars($siteUrl, ENT_XML1, 'UTF-8') . '</link>' . "\n";
            $xml .= '      <description><![CDATA[' . $item['description'] . ']]></description>' . "\n";
            $xml .= '      <itunes:summary>' . htmlspecialchars($item['description'], ENT_XML1, 'UTF-8') . '</itunes:summary>' . "\n";
            $xml .= '      <itunes:author>' . htmlspecialchars($author, ENT_XML1, 'UTF-8') . '</itunes:author>' . "\n";
            $xml .= '      <pubDate>' . $item['pubDate'] . '</pubDate>' . "\n";
            $xml .= '      <enclosure url="' . htmlspecialchars($item['enclosure']['url'], ENT_XML1, 'UTF-8') . '" length="' . $item['enclosure']['length'] . '" type="' . $item['enclosure']['type'] . '" />' . "\n";
            $xml .= '      <guid isPermaLink="false">' . htmlspecialchars($item['guid'], ENT_XML1, 'UTF-8') . '</guid>' . "\n";
            if ($item['duration'] > 0) {
                $xml .= '      <itunes:duration>' . $item['duration'] . '</itunes:duration>' . "\n";
            }
            $xml .= '      <itunes:image href="' . htmlspecialchars($item['image'], ENT_XML1, 'UTF-8') . '" />' . "\n";
            $xml .= '      <itunes:explicit>false</itunes:explicit>' . "\n";
            $xml .= '    </item>' . "\n";
        }

        $xml .= '  </channel>' . "\n";
        $xml .= '</rss>' . "\n";

        return $xml;
    }
}
