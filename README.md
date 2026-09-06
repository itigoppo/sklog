# SKLOG

## セットアップ（推奨）

`setup.sh` が下記の「手動セットアップ」の内容（Docker 起動 → `.env` 作成 → composer install → APP_KEY 生成 → マイグレーション → pnpm install）を自動で行います。何度実行しても安全です。

```
./setup.sh
```

または

```
make setup
```

| オプション | 内容 |
| --- | --- |
| `--fresh` | DB を作り直す（`migrate:fresh --seed`） |
| `--no-node` | ホスト側の `pnpm install` をスキップ |
| `--no-build` | Docker イメージのビルドをスキップ |

完了後、必要に応じて `src/.env` の管理画面・Bluesky まわりの設定（`ADMIN_PASSWORD`、`ADMIN_NOTIFICATION_EMAIL`、`BLUESKY_DEMO_HANDLE` など）を編集してください。

## ワークツリー用セットアップ

Orca でワークツリーを作成したときに走らせるスクリプトです。git 管理外のファイル（`src/.env` / `src/vendor` / `src/node_modules`）は新しいワークツリーには無いため、これで揃えます。

```
bash .orca/setup.sh
```

Orca の repo 設定 → Setup script に `bash .orca/setup.sh` が登録済みで、ワークツリー作成時に自動実行されます（動作確認済み）。

- `src/.env` は main チェックアウトからコピーします
- `src/node_modules` はホストの `pnpm install` で作ります
- `src/vendor` は使い捨ての `composer:2` コンテナで作ります（ホストに PHP 不要）
- **アプリのスタック（compose）は起動しません。** `docker-compose.yml` は `container_name` とポートが固定なので、main チェックアウトの環境と衝突します。ブラウザでの確認は main 側（`cd ~/git/sklog && make docker-up`）で行ってください
- `vendor` が既にある場合はスキップします。作り直すときは `bash .orca/setup.sh --force`
- `.husky/_` も生成します（git 管理外のため、これが無いとワークツリーでは pre-commit のアセットビルドが黙ってスキップされます）

ワークツリーでも `make test` / `make lint` / `make format` / `make build` は動きます。web コンテナが起動していない場所では、Makefile が `sklog-web` イメージから使い捨てコンテナを起動して実行するためです（テストは sqlite `:memory:` なので DB 不要）。

ただしその `sklog-web` イメージは **main チェックアウトの compose が作るもの**です（ワークツリーで `compose build` しても `<ディレクトリ名>-web` という別名になります）。したがって **一度は main 側で `make docker-build`（または `./setup.sh`）を通しておく必要があります**。無い場合は Makefile がその旨を出して止まります。別名のイメージを使いたいときは `make WEB_IMAGE=<image> test` で上書きできます。

DB が要るもの（`make migrate` / `make queue` / `make schedule` など）は、ワークツリー専用の使い捨て MySQL を立てれば動きます。

```
make db-up     # このワークツリー専用の MySQL を起動（ネットワークも専用）
make migrate   # 専用 DB に対して実行される
make db-down   # 停止（--rm なのでコンテナごと消え、データも残らない）
```

main の DB とは完全に別で、main 側の中身には触りません。`make db-up` せずに `make migrate` を叩くとガードで止まります。

止め忘れたままワークツリーを消すとコンテナとネットワークが残るので、Orca の repo 設定 → Archive script に `bash .orca/archive.sh` を登録してあります。

ただし **`orca worktree rm` は `--run-hooks` を付けないと archive フックを実行しません**（`orca.yaml archive hook skipped ... pass --run-hooks to run it.` という警告が出ます）。フックが走らずに消えてしまった分は次で回収できます。

```
make db-prune   # ワークツリーが存在しない sklog_wt_* を削除する
```

なお、アプリをブラウザで確認したいときは main チェックアウト側で行ってください（コンテナは main のディレクトリをマウントしているため、ワークツリーの変更はマイグレーションに限らず反映されません）。

`src/vendor` や `src/node_modules` は `orca worktree rm`（`--force` なし）でワークツリーごと削除されるので、後始末は `make db-up` した DB コンテナだけです（上記の Archive script が担当）。

## 手動セットアップ

### 初回セットアップ

#### Docker起動

```
docker compose up -d --build
```

#### 依存関係のインストール (PHP)

```
docker compose exec web composer install
```

### Laravel初期設定

#### .env作成

```
cp src/.env.example src/.env
```

#### APP_KEY生成

```
docker compose exec web php artisan key:generate
```

#### DB設定

src/.env

```
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=sklog
DB_USERNAME=user
DB_PASSWORD=password
```

#### Mail設定

src/.env

```
MAIL_MAILER=smtp
MAIL_HOST=mail
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_SCHEME=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

#### マイグレーション

```
docker compose exec web php artisan migrate
```

#### アクセス先

Laravel: http://localhost:18080 , https://localhost:18443

Mailpit: http://localhost:18025

### Vite(Node)

docker内じゃないよ

```
cd src
pnpm install
```

### コードスタイル

Laravel Pint を使います。このリポジトリは PHP を **2スペースインデント**で書いているため、`pint.json` でインデント系のルール（`statement_indentation` など）を無効にしています。

```
make lint     # チェックのみ
make format   # 自動修正
```

### キュー / スケジューラ（ローカル）

`QUEUE_CONNECTION=database` で、`bluesky:sync-all` はジョブをキューに積むだけです。コンテナには常駐ワーカーがいないので、消化するには自分で動かします。

```
make queue        # ワーカーを常駐させる
make queue-once   # 溜まっている分だけ消化して終了（本番の cron と同じ）
make schedule     # スケジューラ（bluesky:sync-all は1時間ごと）
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

Blueskyからの自動同期をスケジュールどおりに起動させ、かつ非同期のキュータスク（同期処理）を本番で消化させるため、本番サーバーの `cron` に以下の2つのジョブを登録します。

1. サーバー側で `crontab -e` を実行して編集画面を開きます。
2. 以下の2行を追加して保存します。

```cron
# 定期スケジュールジョブのトリガー (5分毎にチェック)
*/5 * * * * cd ~/git/sklog/src && php artisan schedule:run >> ~/git/sklog/cron.log 2>&1

# キューに溜まった同期タスクの実行 (5分毎にチェック・空になったら自動終了)
*/5 * * * * cd ~/git/sklog/src && php artisan queue:work --stop-when-empty >> ~/git/sklog/cron.log 2>&1
```
