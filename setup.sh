#!/usr/bin/env bash
#
# SKLOG ローカル開発環境セットアップスクリプト
#
# README の「初回セットアップ」を自動化します。
# 何度実行しても安全（冪等）です。
#
# 使い方:
#   ./setup.sh              # 通常のセットアップ
#   ./setup.sh --fresh      # DBを作り直す (migrate:fresh --seed)
#   ./setup.sh --no-node    # ホスト側の pnpm install をスキップ
#   ./setup.sh --no-build   # docker compose のイメージビルドをスキップ

set -euo pipefail

cd "$(dirname "$0")"

ENV_FILE="src/.env"
ENV_EXAMPLE="src/.env.example"

FRESH=0
RUN_NODE=1
DO_BUILD=1

for arg in "$@"; do
  case "$arg" in
    --fresh)    FRESH=1 ;;
    --no-node)  RUN_NODE=0 ;;
    --no-build) DO_BUILD=0 ;;
    -h|--help)
      awk 'NR>1 { if ($0 !~ /^#/) exit; sub(/^# ?/, ""); print }' "$0"
      exit 0
      ;;
    *)
      echo "Unknown option: $arg" >&2
      exit 1
      ;;
  esac
done

# ---------- 出力ヘルパー ----------
step() { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }
info() { printf '    %s\n' "$1"; }
warn() { printf '\033[1;33m[warn] %s\033[0m\n' "$1" >&2; }
fail() { printf '\033[1;31m[error] %s\033[0m\n' "$1" >&2; exit 1; }

# ---------- .env のキーを書き換える ----------
# 既存行（コメントアウトされている行も含む）があれば置換、なければ追記する
set_env() {
  local key="$1" value="$2"
  if grep -qE "^[[:space:]]*#?[[:space:]]*${key}=" "$ENV_FILE"; then
    awk -v key="$key" -v val="$value" '
      !replaced && $0 ~ ("^[[:space:]]*#?[[:space:]]*" key "=") {
        print key "=" val
        replaced = 1
        next
      }
      { print }
    ' "$ENV_FILE" > "$ENV_FILE.tmp" && mv "$ENV_FILE.tmp" "$ENV_FILE"
  else
    printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
  fi
}

# ---------- 事前チェック ----------
step "必要なコマンドを確認します"
command -v docker >/dev/null 2>&1 || fail "docker が見つかりません。Docker Desktop をインストールしてください。"
docker compose version >/dev/null 2>&1 || fail "docker compose (v2) が見つかりません。"
docker info >/dev/null 2>&1 || fail "Docker デーモンが起動していません。Docker Desktop を起動してください。"
info "docker: OK"

if [ "$RUN_NODE" -eq 1 ] && ! command -v pnpm >/dev/null 2>&1; then
  warn "pnpm が見つからないため、Vite(Node) のセットアップをスキップします。"
  warn "  インストール例: npm install -g pnpm  /  corepack enable pnpm"
  RUN_NODE=0
fi

# ---------- .env ----------
step ".env を用意します"
if [ -f "$ENV_FILE" ]; then
  info "$ENV_FILE は既に存在するため、必要なキーのみ更新します。"
else
  cp "$ENV_EXAMPLE" "$ENV_FILE"
  info "$ENV_EXAMPLE をコピーして $ENV_FILE を作成しました。"
fi

# アプリ設定（docker-compose.yml のポートに合わせる）
set_env APP_URL "http://localhost:18080"

# DB設定（docker-compose.yml の mysql サービスに合わせる）
set_env DB_CONNECTION mysql
set_env DB_HOST mysql
set_env DB_PORT 3306
set_env DB_DATABASE sklog
set_env DB_USERNAME user
set_env DB_PASSWORD password

# Mail設定（docker-compose.yml の mail = Mailpit に合わせる）
set_env MAIL_MAILER smtp
set_env MAIL_HOST mail
set_env MAIL_PORT 1025
set_env MAIL_USERNAME null
set_env MAIL_PASSWORD null
set_env MAIL_SCHEME null
info "DB / Mail の接続先を Docker 用の設定にしました。"

# ---------- コンテナ起動 ----------
step "Docker コンテナを起動します"
if [ "$DO_BUILD" -eq 1 ]; then
  docker compose up -d --build
else
  docker compose up -d
fi

step "MySQL の起動を待ちます"
for i in $(seq 1 60); do
  if docker compose exec -T mysql mysqladmin ping -h 127.0.0.1 -uuser -ppassword --silent >/dev/null 2>&1; then
    info "MySQL の準備ができました。"
    break
  fi
  if [ "$i" -eq 60 ]; then
    fail "MySQL が起動しませんでした。'docker compose logs mysql' を確認してください。"
  fi
  sleep 2
done

# ---------- PHP ----------
step "PHP の依存関係をインストールします (composer install)"
docker compose exec -T web composer install

step "APP_KEY を確認します"
if grep -qE '^APP_KEY=.+' "$ENV_FILE"; then
  info "APP_KEY は設定済みのためスキップします。"
else
  docker compose exec -T web php artisan key:generate
fi

step "マイグレーションを実行します"
if [ "$FRESH" -eq 1 ]; then
  docker compose exec -T web php artisan migrate:fresh --seed --force
else
  docker compose exec -T web php artisan migrate --force
fi

step "キャッシュをクリアします"
docker compose exec -T web php artisan optimize:clear

# ---------- Node ----------
if [ "$RUN_NODE" -eq 1 ]; then
  step "フロントエンドの依存関係をインストールします (pnpm install)"
  info "※ Vite は Docker 内ではなくホスト側で動かします"
  (cd src && pnpm install)

  # .npmrc の ignore-scripts=true により prepare は自動実行されないため明示的に叩く
  (cd src && pnpm run prepare)
  info "husky (pre-commit) を有効にしました。"
fi

# ---------- 完了 ----------
cat <<'MSG'

セットアップが完了しました。

  Laravel : http://localhost:18080  /  https://localhost:18443
  Mailpit : http://localhost:18025
  MySQL   : localhost:13306 (user / password)

よく使うコマンド:
  make run            # Vite 開発サーバー起動 (cd src && pnpm dev)
  make docker-web     # web コンテナに入る
  make docker-db      # MySQL クライアントに接続
  make migrate        # マイグレーション
  make test           # テスト実行

必要に応じて src/.env の管理画面・Bluesky まわりの設定
(ADMIN_PASSWORD, ADMIN_NOTIFICATION_EMAIL, BLUESKY_DEMO_HANDLE など) を編集してください。
MSG
