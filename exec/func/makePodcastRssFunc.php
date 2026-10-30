<?php
/**
 * Podcast RSS (XML) 自動生成関数
 */

// 環境変数の読み込み
$docRoot = rtrim(!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : dirname(dirname(__DIR__)), '/\\');
if (file_exists($docRoot . '/env.php')) {
    require_once $docRoot . '/env.php';
}

/**
 * 番組定義
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

    // 保存先ディレクトリの作成（なければ）
    if (!is_dir($RSS_PATH)) {
        if (!mkdir($RSS_PATH, 0777, true) && !is_dir($RSS_PATH)) {
            echo "【RSS生成エラー】RSS保存先ディレクトリの作成に失敗しました -> {$RSS_PATH}\n";
            return false;
        }
    }

    // 小文字/大文字互換シンボリックリンクの確認・作成
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

    // URLの基準を決定
    $podcastBaseUrl = rtrim($PODCAST_URL ?? ($PAGES_URL . '/' . basename($PODCAST_PATH)), '/');
    $feedBaseUrl    = rtrim($PAGES_URL ?? 'https://radio.enoki.xyz/pages', '/') . '/' . basename($RSS_PATH);

    foreach ($targetList as $key => $prog) {
        $subDir = $prog['subDir'];
        $folderPath = rtrim($PODCAST_PATH, '/') . '/' . $subDir;

        // 1400x1400のArtwork画像を確認・準備
        $dstImagePath = rtrim($RSS_PATH, '/') . '/' . $prog['imageName'];
        ensurePodcastArtwork($prog['imageSrc'], $dstImagePath);
        $imageUrl = $feedBaseUrl . '/' . $prog['imageName'];

        $items = [];
        if (is_dir($folderPath)) {
            $files = glob($folderPath . '/*.mp3');
            if ($files !== false) {
                // ファイル名で降順ソート（新しいエピソード順）
                rsort($files);

                foreach ($files as $filePath) {
                    $fileName = basename($filePath);

                    // 試験運用ファイル（ファイル名にトピックが付いている旧ファイル）は自動削除して除外
                    if (preg_match('/_\d{12}_.*\.mp3$/', $fileName)) {
                        @unlink($filePath);
                        continue;
                    }

                    // 正しい命名規則（番組名_YYYYMMDDHHMM.mp3）でないものはスキップ
                    if (!preg_match('/_(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})\.mp3$/', $fileName, $matches)) {
                        continue;
                    }

                    $parsedTime = strtotime("{$matches[1]}-{$matches[2]}-{$matches[3]} {$matches[4]}:{$matches[5]}:00");
                    $timestamp = ($parsedTime !== false) ? $parsedTime : filemtime($filePath);
                    $displayTime = sprintf('%04d年%02d月%02d日 %02d:%02d', $matches[1], $matches[2], $matches[3], $matches[4], $matches[5]);

                    $pubDate = date('r', $timestamp);
                    $fileSize = filesize($filePath);

                    // ffprobe によるメタデータ（ID3タグ内のトピック、再生時間）取得
                    $cmd = sprintf('ffprobe -v quiet -print_format json -show_format %s 2>&1', escapeshellarg($filePath));
                    $probeOutput = shell_exec($cmd);
                    $meta = json_decode($probeOutput, true);

                    $duration = (int)round(floatval($meta['format']['duration'] ?? 0));
                    $tags = $meta['format']['tags'] ?? [];
                    $topicText = trim($tags['comment'] ?? $tags['description'] ?? '');

                    // 不可視文字や制御文字のサニタイズ（UTF-8バリデーション対策）
                    $topicText = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $topicText);
                    $topicText = str_replace(["\r\n", "\r"], "\n", $topicText);

                    // エピソード詳細説明文: 基本説明文 + 今日のトピック（ID3タグより取得）
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

        // XML の構築
        $xmlContent = buildPodcastRssXml($prog, $items, $feedBaseUrl . '/' . $prog['xmlFilename'], $imageUrl);

        // XML ファイルの保存
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
