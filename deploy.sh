#!/usr/bin/env bash
#
# 本番のサーバーで sklog を新しくする。git pull してから、src/ の依存・キャッシュ・マイグレーションを更新する。
#
# 使い方:
#   ./deploy.sh
#   PHP=/path/to/php ./deploy.sh   # cron などとそろえて php を指定するとき
#
# pull で deploy.sh 自体が変わっても、その回から新しい中身で動くよう、pull のあとに自分を起動し直す（--after-pull）。
# 実行中にファイルが書き換わっても途中から読まれないよう、全体を { } で囲んで最初に読み切らせる。
# src/.env がまだないとき（初回のセットアップの途中）は src/ を飛ばす。
#
# 更新の途中の、新しいコードと古い DB・部品が混ざった状態のページを出さないよう、
# composer install・キャッシュの作り直し・マイグレーションのあいだは php artisan down でメンテナンス中（errors/503）にする。
# 終わるとき（途中で失敗したときも）は、必ず php artisan up をする。
# マイグレーションは、古いコードでも動く形（空でもいい列を足すなど）にする。列を消す・名前を変えるときは、先にコードで使わなくしてから次の回で消す

{
set -euo pipefail

cd "$(dirname "$0")"
ROOT="$PWD"

PHP="${PHP:-php}"

if [ "${1:-}" != "--after-pull" ]; then
  echo "Pull latest..."
  git pull --ff-only
  exec bash "$0" --after-pull
fi

if [ ! -f src/.env ]; then
  echo "src/.env がないので src/ の更新を飛ばします。"
  exit 0
fi

src_down=0
finish() {
  if [ "$src_down" = 1 ]; then
    # 失敗したときは src/ に cd したあとなので、ルートからの絶対パスで入る
    (cd "$ROOT/src" && "$PHP" artisan up) || echo "php artisan up に失敗しました。src/ で php artisan up を実行してください。"
  fi
}
trap finish EXIT

cd src
# 更新のあいだはメンテナンス中にする（部品やキャッシュを入れ替えている途中のページを出さないよう、先に書き出したページを出す。1分ごとに開き直す）。
# 初回のセットアップ（vendor/ がまだない）は artisan が動かず、まだ公開もしていないので飛ばす
if [ -f vendor/autoload.php ]; then
  echo "Maintenance mode..."
  "$PHP" artisan down --render="errors::503" --retry=60
  src_down=1
fi

echo "Install composer dependencies..."
# PHP を指定したときは composer もその php で動かす（composer.phar を PATH の php で動かすと、別のバージョンになることがある）
if [ "$PHP" = "php" ]; then
  composer install --no-dev --optimize-autoloader
else
  "$PHP" "$(command -v composer)" install --no-dev --optimize-autoloader
fi

# 前の回の設定のキャッシュのまま migrate しないよう、先にキャッシュを作り直してから migrate する
echo "Cache config..."
"$PHP" artisan optimize

echo "Run migrations..."
"$PHP" artisan migrate --force

if [ "$src_down" = 1 ]; then
  echo "Back to live..."
  "$PHP" artisan up
  src_down=0
fi

echo "Done."
exit 0
}
