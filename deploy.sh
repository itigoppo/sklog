#!/usr/bin/env bash
#
# 本番のサーバーで sklog を新しくする。git pull してから、依存・キャッシュ・マイグレーションを更新する。
#
# 使い方:
#   ./deploy.sh
#   PHP=/path/to/php ./deploy.sh   # cron などとそろえて php を指定するとき
#
# pull で deploy.sh 自体が変わっても、その回から新しい中身で動くよう、pull のあとに自分を起動し直す（--after-pull）。
# 実行中にファイルが書き換わっても途中から読まれないよう、全体を { } で囲んで最初に読み切らせる。

{
set -euo pipefail

cd "$(dirname "$0")"

PHP="${PHP:-php}"

if [ "${1:-}" != "--after-pull" ]; then
  echo "Pull latest..."
  git pull --ff-only
  exec bash "$0" --after-pull
fi

echo "Install composer dependencies..."
cd src
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

echo "Done."
exit 0
}
