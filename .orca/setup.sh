#!/usr/bin/env bash
#
# Orca ワークツリー用セットアップスクリプト
#
# ワークツリーを作成したときに Orca の setup フックから実行される想定です。
# git 管理外のファイル（src/.env, vendor, node_modules）は新しいワークツリーには
# 存在しないため、ここで揃えます。
#
# Docker はワークツリーでは起動しません。docker-compose.yml は container_name と
# ポートが固定のため、main チェックアウトの環境と衝突します。
# アプリの動作確認は main チェックアウト側 (~/git/sklog) の Docker 環境で行ってください。
#
# 使い方:
#   bash .orca/setup.sh           # 通常（vendor が既にあれば composer install はスキップ）
#   bash .orca/setup.sh --force   # vendor があっても composer install をやり直す

set -uo pipefail

cd "$(dirname "$0")/.."
ROOT="$PWD"

FORCE=0
for arg in "$@"; do
  case "$arg" in
    --force) FORCE=1 ;;
    *) echo "Unknown option: $arg" >&2; exit 1 ;;
  esac
done

step() { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }
info() { printf '    %s\n' "$1"; }
warn() { printf '\033[1;33m[warn] %s\033[0m\n' "$1" >&2; }
fail() { printf '\033[1;31m[error] %s\033[0m\n' "$1" >&2; exit 1; }

# ---------- main チェックアウトを特定する ----------
MAIN="$(cd "$(git rev-parse --path-format=absolute --git-common-dir)/.." && pwd)"

# ---------- git 管理外の .env を main からコピーする ----------
# .env.example は git 管理下なのでコピー不要
step ".env を main チェックアウトから引き継ぎます"
if [ "$MAIN" = "$ROOT" ]; then
  info "ここは main チェックアウトなのでコピーは不要です。"
else
  copied=0
  while read -r rel; do
    [ -n "$rel" ] || continue
    [ -e "$rel" ] && continue
    mkdir -p "$(dirname "$rel")"
    cp "$MAIN/$rel" "$rel"
    info "copied: $rel"
    copied=$((copied + 1))
  done < <(
    git -C "$MAIN" ls-files --others --ignored --exclude-standard -- '.env*' '*/.env*' \
      | grep -vE '(^|/)node_modules/|(^|/)vendor/|^\.claude/worktrees/'
  )
  if [ "$copied" -eq 0 ]; then
    info "コピーするものはありませんでした。"
  fi
fi

if [ ! -f src/.env ]; then
  warn "src/.env がありません。main チェックアウトにも無いようです。"
  warn "  cp src/.env.example src/.env してから設定してください。"
fi

# ---------- フロントエンド (ホスト側) ----------
step "フロントエンドの依存関係をインストールします (pnpm install)"
if command -v pnpm >/dev/null 2>&1; then
  (cd src && pnpm install) || fail "pnpm install に失敗しました。"

  # .husky/_ は git 管理外なのでワークツリーには存在しない。
  # core.hooksPath=.husky/_ が無いと pre-commit (アセットビルド) が黙ってスキップされる。
  # .npmrc の ignore-scripts=true により prepare は自動実行されないため明示的に叩く。
  (cd src && pnpm run prepare) || fail "husky のセットアップに失敗しました。"
  info "husky (pre-commit) を有効にしました。"
else
  warn "pnpm が見つからないためスキップします (npm install -g pnpm / corepack enable pnpm)。"
fi

# ---------- PHP (使い捨てコンテナ) ----------
# ホストに php / composer が無いため、composer イメージで vendor を作る。
# 静的解析や IDE 補完のために vendor があると都合がよい。
step "PHP の依存関係をインストールします (composer install)"
if [ -f src/vendor/autoload.php ] && [ "$FORCE" -eq 0 ]; then
  info "src/vendor は既にあるためスキップします (--force でやり直せます)。"
elif ! docker info >/dev/null 2>&1; then
  warn "Docker が起動していないためスキップします。"
  warn "  あとで実行する場合: bash .orca/setup.sh --force"
else
  COMPOSER_CACHE="${HOME}/.cache/composer"
  mkdir -p "$COMPOSER_CACHE"
  docker run --rm \
    --user "$(id -u):$(id -g)" \
    -e COMPOSER_HOME=/tmp/composer \
    -e COMPOSER_CACHE_DIR=/tmp/composer/cache \
    -v "$ROOT/src":/app \
    -v "$COMPOSER_CACHE":/tmp/composer/cache \
    -w /app \
    composer:2 install \
      --ignore-platform-reqs \
      --no-interaction \
      --no-progress \
    || fail "composer install に失敗しました。"
fi

# ---------- web イメージの確認 ----------
# make test / lint はこのイメージから使い捨てコンテナを起動する。
# イメージは main チェックアウトの compose が作るので、一度も起動していないと存在しない。
step "web イメージを確認します"
if docker image inspect sklog-web >/dev/null 2>&1; then
  info "sklog-web: OK"
else
  warn "sklog-web イメージがありません。make test / make lint はこのままでは動きません。"
  warn "  main チェックアウトで一度ビルドしてください: cd ~/git/sklog && make docker-build"
fi

# ---------- 完了 ----------
cat <<'MSG'

ワークツリーのセットアップが完了しました。

このワークツリーでも動くもの（web イメージの使い捨てコンテナで実行されます）:

  make test     # テスト（sqlite :memory: なので DB 不要）
  make lint     # Pint のチェック
  make format   # Pint の自動修正
  make build    # Vite ビルド
  make run      # Vite 開発サーバー（ポートが空いていれば）

DB が要るもの（make migrate / queue / schedule など）は、このワークツリー専用の
使い捨て MySQL を立てれば動きます。main の DB は汚しません。

  make db-up     # 専用 MySQL を起動
  make migrate   # このワークツリーの DB に対して実行される
  make db-down   # 停止（コンテナごと消えるのでデータも残りません）

アプリをブラウザで確認したいときは main チェックアウト側で行ってください
（コンテナは main のディレクトリをマウントしています）。

  cd ~/git/sklog && make docker-up
MSG
