# SKLOG

## 初回セットアップ

### Docker起動

```
docker compose up -d --build
```

### 依存関係のインストール (PHP)

```
docker compose exec web composer install
```

## Laravel初期設定

### .env作成

```
cp src/.env.example src/.env
```

### APP_KEY生成

```
docker compose exec web php artisan key:generate
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
docker compose exec web php artisan migrate
```

### アクセス先

Laravel: http://localhost:18080 , https://localhost:18443

Mailpit: http://localhost:18025

## Vite(Node)

docker内じゃないよ

```
cd src
pnpm install
```

## デプロイ

at local

```
cd src
pnpm build
```

at server

### 初回デプロイ

1. リポジトリをクローンします。

```
cd ~/git
git clone <リポジトリのURL> sklog
```

2. `.env` ファイルを作成し、本番環境の各種設定（DB接続、Blueskyアカウントなど）を編集します。

```
cd ~/git/sklog
cp src/.env.example src/.env
# エディタ等で src/.env を編集
```

3. 依存関係のインストール、マイグレーション、キャッシュ初期設定を行います。

```
chmod +x deploy.sh
./deploy.sh
```

4. アプリケーションキーを生成します（初回のみ）。

```
cd src
php artisan key:generate
```

5. 公開ディレクトリへ公開用ディレクトリのシンボリックリンクを作成します。

```
cd ~/www
ln -s ../git/sklog/src/public sklog
```

### 2回目以降のデプロイ

`deploy.sh` を実行するだけで、コードの最新化（`git pull`）、依存関係のインストール、**マイグレーションの実行（`migrate --force`）**、およびキャッシュの最適化までが自動で行われます。

```
cd ~/git/sklog
./deploy.sh
```

### 自動同期ジョブ（Cron）の設定

Blueskyからの自動同期ジョブ（1時間に1回の自動同期など）を定期的に稼働させるため、本番サーバーの `cron` に以下のタスクスケジュール登録を行います。

1. サーバー側で `crontab -e` を実行して編集画面を開きます。
2. 以下の行を追加して保存します（ユーザーのホームディレクトリのパス等に合わせて適宜調整してください）。

```cron
* * * * * cd ~/git/sklog/src && php artisan schedule:run >> ~/git/sklog/cron.log 2>&1
```
