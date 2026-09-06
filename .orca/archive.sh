#!/usr/bin/env bash
#
# Orca ワークツリー削除時のクリーンアップスクリプト
#
# make db-up / make app-up で立てたワークツリー専用のコンテナを止めます。
# 止め忘れたままワークツリーを消すとコンテナとネットワークが残るため、
# Orca の archive フックから実行される想定です。
#
# vendor / node_modules / .env はワークツリーのディレクトリごと消えるので
# ここでは何もしません。
#
# 使い方:
#   bash .orca/archive.sh

set -uo pipefail

cd "$(dirname "$0")/.."

WT_NAME="$(printf '%s' "$(basename "$PWD")" | tr -c 'A-Za-z0-9_.-' '_')"
WT_NET="sklog_wt_${WT_NAME}"

if ! docker info >/dev/null 2>&1; then
  echo "Docker が起動していないため何もしません。"
  exit 0
fi

removed=0
for suffix in db app mail; do
  c="sklog_wt_${WT_NAME}_${suffix}"
  if [ -n "$(docker ps -aq -f "name=^${c}$")" ]; then
    docker rm -f "$c" >/dev/null 2>&1
    echo "$c を削除しました。"
    removed=$((removed + 1))
  fi
done
if [ "$removed" -eq 0 ]; then
  echo "このワークツリーのコンテナはありません。"
fi

if docker network rm "$WT_NET" >/dev/null 2>&1; then
  echo "$WT_NET を削除しました。"
fi

echo "クリーンアップが完了しました。"
