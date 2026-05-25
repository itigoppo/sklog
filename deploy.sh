#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")"

echo "Pull latest..."
git pull

echo "Install composer dependencies..."
cd src
composer install --no-dev --optimize-autoloader

echo "Run migrations..."
php artisan migrate --force

echo "Clear and cache config..."
php artisan optimize:clear
php artisan optimize

echo "Done."