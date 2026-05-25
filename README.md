# SKLOG

## 初回セットアップ

### Docker起動

```
docker compose up -d --build

```

### Laravelインストール

```
docker compose exec -w /home/itigoppo/git/sklog php composer create-project laravel/laravel src
```

## Laravel初期設定

### .env作成

```
cp src/.env.example src/.env
```

### APP_KEY生成

```
docker compose exec php php artisan key:generate
```

### DB設定

src/.env

```
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=sklog
DB_USERNAME=user
DB_PASSWORD=password
```

### Mail設定

src/.env

```
MAIL_MAILER=smtp
MAIL_HOST=mail
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### マイグレーション

```
docker compose exec php php artisan migrate
```

### アクセス先

Laravel: http://localhost:18080 , https://localhost:18443

Mailpit: http://localhost:18025

## Vite(Node)

docker内じゃないよ

```
cd src
npm install
```

## デプロイ

at local

```
cd src
npm run build
```

at server

```
cd ~/git/sklog
git pull
cd src
composer install --no-dev --optimize-autoloader
php artisan optimize
cd ~/www
ln -s ../git/sklog/src/public sklog

# 次から
cd ~/git/sklog
./deploy.sh
```
