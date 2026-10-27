#!/usr/bin/env bash
#
# Orca ワークツリー用セットアップスクリプト
#
# ワークツリーを作成したときに Orca の setup フックから実行される想定です。
# git 管理外のファイル（src/.env, vendor, node_modules）は新しいワークツリーには
# 存在しないため、ここで揃えます。
#
# Docker はワークツリー専用の MySQL・アプリ・Mailpit を docker run で起動します（make db-up / app-up）。
# docker-compose.yml は container_name とポートが固定で main チェックアウトの環境と衝突するので、
# compose のスタックは使いません。あとから起動・停止するときは make docker-up / docker-down です。
#
# 使い方:
#   bash .orca/setup.sh           # 通常（vendor が既にあれば composer install はスキップ）
#   bash .orca/setup.sh --force   # vendor があっても composer install をやり直す
#   bash .orca/setup.sh --no-db   # DB もアプリも起動しない
#   bash .orca/setup.sh --no-app  # DB は起動するがアプリは起動しない

set -uo pipefail

cd "$(dirname "$0")/.."
ROOT="$PWD"

FORCE=0
WITH_DB=1
WITH_APP=1
for arg in "$@"; do
  case "$arg" in
    --force) FORCE=1 ;;
    --no-db) WITH_DB=0; WITH_APP=0 ;;
    --no-app) WITH_APP=0 ;;
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

# ---------- ワークツリー用に .env を調整する ----------
# cookie はポートを区別しない。main (localhost:18080) とワークツリー (localhost:19xxx) を
# 同じホスト名で開くと、セッションと remember me の cookie を共有してしまい、片方を
# 開いたらもう片方がログアウトする。DB は別物なので、復元もできない。
# ホスト名を分けて cookie を分離し、セッション cookie の名前もワークツリー名にする。
# ワークツリーの DB はシードのアカウントしか無いので、/dev/login でログインできるようにしておく。
env_set() { # key value -> 変更したら 0
  key="$1"
  value="$2"
  file="src/.env"

  if grep -qE "^${key}=" "$file"; then
    [ "$(grep -E "^${key}=" "$file" | head -1 | cut -d= -f2-)" = "$value" ] && return 1
    awk -v k="$key" -v v="$value" '
      BEGIN { FS = "=" }
      $1 == k { print k "=" v; next }
      { print }
    ' "$file" > "$file.tmp" && mv "$file.tmp" "$file"
  else
    # 末尾に改行が無いと次の行とくっつく
    [ -n "$(tail -c1 "$file")" ] && printf '\n' >> "$file"
    printf '%s=%s\n' "$key" "$value" >> "$file"
  fi

  return 0
}

step "ワークツリー用に src/.env を調整します"
if [ "$MAIN" = "$ROOT" ]; then
  info "ここは main チェックアウトなので調整は不要です。"
elif [ ! -f src/.env ]; then
  warn "src/.env が無いのでスキップします。"
else
  APP_URL_VALUE="$(make -s app-url 2>/dev/null)"
  # printf で改行を落とさないと、tr -c が改行も '-' に変換してしまう
  SESSION_COOKIE_VALUE="$(printf '%s' "$(basename "$ROOT")" | tr 'A-Z' 'a-z' | tr -c 'a-z0-9-' '-')-session"

  if [ -z "$APP_URL_VALUE" ]; then
    warn "make app-url が取れなかったのでスキップします。"
  else
    changed=0
    env_set APP_URL "$APP_URL_VALUE" && changed=1
    env_set SESSION_COOKIE "$SESSION_COOKIE_VALUE" && changed=1
    env_set DEV_LOGIN true && changed=1
    if [ "$changed" -eq 1 ]; then
      info "APP_URL=$APP_URL_VALUE"
      info "SESSION_COOKIE=$SESSION_COOKIE_VALUE"
      info "DEV_LOGIN=true"
    else
      info "すでに調整済みです。"
    fi
  fi
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
DOCKER_UP=1
docker info >/dev/null 2>&1 || DOCKER_UP=0

step "PHP の依存関係をインストールします (composer install)"
if [ -f src/vendor/autoload.php ] && [ "$FORCE" -eq 0 ]; then
  info "src/vendor は既にあるためスキップします (--force でやり直せます)。"
elif [ "$DOCKER_UP" -eq 0 ]; then
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
# イメージは main チェックアウトの compose が作る（名前は docker-compose.yml の image: で固定）ので、一度もビルドしていないと存在しない。
step "web イメージを確認します"
if [ "$DOCKER_UP" -eq 0 ]; then
  warn "Docker が起動していないため確認できません。make test / make lint には Docker が要ります。"
elif docker image inspect sklog-web >/dev/null 2>&1; then
  info "sklog-web: OK"
else
  warn "sklog-web イメージがありません。make test / make lint はこのままでは動きません。"
  warn "  main チェックアウトで一度ビルドしてください: cd $MAIN && make docker-build"
fi

# ---------- ワークツリー専用 DB ----------
# main の DB とは別の、このワークツリー専用の MySQL を立てる。
# 軽量設定 (docker/db/worktree.cnf) を効かせて 1台あたり約 110MB に抑えている。
step "ワークツリー専用の MySQL を起動します"
DB_OK=0
APP_OK=0
if [ "$WITH_DB" -eq 0 ]; then
  info "--no-db が指定されたのでスキップします（あとで make docker-up で起動できます）。"
elif [ "$MAIN" = "$ROOT" ]; then
  info "ここは main チェックアウトなのでスキップします。"
elif [ "$DOCKER_UP" -eq 0 ]; then
  warn "Docker が起動していないためスキップします。"
  warn "  Docker を起動してから make docker-up してください（DB・マイグレーション・シード・アプリまで起動します）。"
  WITH_APP=0
elif ! docker image inspect sklog-mysql >/dev/null 2>&1; then
  warn "sklog-mysql イメージがないためスキップします。"
  warn "  main チェックアウトで一度ビルドしてください: cd $MAIN && make docker-build"
  WITH_APP=0
elif make db-up && make migrate && make seed; then
  info "マイグレーションとシードまで完了しました。"
  DB_OK=1
else
  warn "DB のセットアップに失敗しました。make db-up / make migrate を手で試してください。"
  WITH_APP=0
fi

# ---------- ワークツリー専用アプリ ----------
# compose のスタックは main のディレクトリをマウントしているので、
# ワークツリーの変更を画面で見るには専用のアプリが要る。
step "ワークツリー専用のアプリを起動します"
if [ "$WITH_APP" -eq 0 ]; then
  info "スキップします（あとで make docker-up で起動できます）。"
elif [ "$MAIN" = "$ROOT" ]; then
  info "ここは main チェックアウトなのでスキップします。"
elif make app-up; then
  APP_OK=1
else
  warn "アプリの起動に失敗しました。make app-up を手で試してください。"
fi

# ---------- 完了 ----------
cat <<'MSG'

ワークツリーのセットアップが完了しました。

コードを直してテストを回すだけなら、これだけで足ります:

  make test     # テスト（sqlite :memory:）
  make lint     # Pint のチェック
  make format   # Pint の自動修正
MSG

if [ "$DB_OK" -eq 1 ]; then
  cat <<'MSG'

専用の MySQL（約110MB）が起動していて、マイグレーションとシードも済んでいます。
main の DB とは別物なので、いくら壊しても main には影響しません。

  make migrate  # このワークツリーの DB に対して実行される
  make seed     # シードを流し直す
MSG
else
  cat <<'MSG'

専用の MySQL は起動していません。DB を使うときは make docker-up してください。
MSG
fi

if [ "$APP_OK" -eq 1 ]; then
  echo ""
  echo "専用のアプリ（Laravel + Mailpit）も起動済みです。"
  echo ""
  make -s app-info
  cat <<'MSG'

  make run          # CSS/JS を触るときに別ターミナルで（Vite。HMR が効きます）
  make dev          # アプリと Vite をまとめて起動したいとき
  make app-down     # アプリを止めてメモリを空ける（DB は残る）
  make docker-down  # アプリ・Mailpit・DB を全部止める（DB のデータも消える）

main の compose スタック (http://localhost:18080) は main のディレクトリを
見ているので、ワークツリーの変更はそちらには出ません。

アプリの URL が localhost ではなく <ワークツリー名>.localhost なのは、cookie が
ポートを区別しないためです。localhost:<ポート> で開くと main とセッションや
remember me の cookie を食い合い、片方を開くともう片方がログアウトします。
MSG
else
  cat <<'MSG'

アプリは起動していません。画面を確認するときは make docker-up してください。
MSG
fi

echo ""
echo "止めたものは make docker-up で戻せます（DB を作り直したときは migrate と seed も流します）。"
