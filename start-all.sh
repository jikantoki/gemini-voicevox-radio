#!/bin/bash

# ==========================================
# 設定: 接続先のホスト名やIPアドレスを指定してください
# ==========================================
HOST="gemini-api.enoki.local"

# コピー先のディレクトリパス
DEST_DIR="/mnt/ncdata/admin/files/シェアハウス共有/ラジオ配信リスト/定期実行させるもの/"

echo "ラジオ配信スクリプト"
echo "===================="
echo "このスクリプトでは、台本の考案からミックス後の音声出力までを自動化します。"

# HTTP経由で各PHPスクリプトを実行
curl -s "http://${HOST}/exec/1-makeText.php"
echo "台本の作成が完了しました。"

curl -s "http://${HOST}/exec/2-makeVoiceVoxRequest.php"
echo "音声生成リクエストが送信されました。"

curl -s "http://${HOST}/exec/3-makeWav.php"
echo "音声ファイルの作成が完了しました。"

curl -s "http://${HOST}/exec/4-mergeWav.php"
echo "音声の合成が完了しました。"

echo "ラジオ配信スクリプトが完了しました。"

# ==========================================
# 音声ファイルのコピー処理
# ==========================================
echo "作成された音声ファイルを 00.mp3 として共有ディレクトリへコピーしています..."

if [ -f "./output/final_podcast_00.mp3" ]; then
    # コピー先ディレクトリが存在しない場合は自動作成
    mkdir -p "${DEST_DIR}"

    # ファイル名を「00.mp3」に変更してコピー
    cp "./output/final_podcast_00.mp3" "${DEST_DIR}00.mp3"
    echo "コピーとリネームが完了しました！ -> ${DEST_DIR}00.mp3"
else
    echo "【エラー】コピー元のファイル (./output/final_podcast_00.mp3) が見つかりませんでした。"
fi

if [ -f "./output/final_podcast_15.mp3" ]; then
    # コピー先ディレクトリが存在しない場合は自動作成
    mkdir -p "${DEST_DIR}"

    # ファイル名を「15.mp3」に変更してコピー
    cp "./output/final_podcast_15.mp3" "${DEST_DIR}15.mp3"
    echo "コピーとリネームが完了しました！ -> ${DEST_DIR}15.mp3"
else
    echo "【エラー】コピー元のファイル (./output/final_podcast_15.mp3) が見つかりませんでした。"
fi

if [ -f "./output/final_podcast_30.mp3" ]; then
    # コピー先ディレクトリが存在しない場合は自動作成
    mkdir -p "${DEST_DIR}"

    # ファイル名を「30.mp3」に変更してコピー
    cp "./output/final_podcast_30.mp3" "${DEST_DIR}30.mp3"
    echo "コピーとリネームが完了しました！ -> ${DEST_DIR}30.mp3"
else
    echo "【エラー】コピー元のファイル (./output/final_podcast_30.mp3) が見つかりませんでした。"
fi

if [ -f "./output/final_podcast_45.mp3" ]; then
    # コピー先ディレクトリが存在しない場合は自動作成
    mkdir -p "${DEST_DIR}"

    # ファイル名を「45.mp3」に変更してコピー
    cp "./output/final_podcast_45.mp3" "${DEST_DIR}45.mp3"
    echo "コピーとリネームが完了しました！ -> ${DEST_DIR}45.mp3"
else
    echo "【エラー】コピー元のファイル (./output/final_podcast_45.mp3) が見つかりませんでした。"
fi

echo "ラジオ配信スクリプトが完了しました。"