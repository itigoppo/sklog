# SkLoG

Bluesky の自分の投稿を自動で取り込み、カレンダー形式のログとして保存・公開する Web サービスです。

https://sklog.nono150.com/

## 主な機能

- **自動同期**: Bluesky のアプリパスワードで登録すると、自分の投稿（テキスト・画像・ハッシュタグ）とリポストを1時間ごとに取り込みます
- **カレンダー表示**: 年・月・日ごとのアーカイブ、タグ一覧、月めくりのカレンダーで過去の投稿をたどれます
- **公開範囲の連動**: Bluesky 側でログアウト時の公開範囲を制限していると、カレンダーも本人以外には見えなくなります
- **削除モード**: 投稿やリポストをまとめて選び、カレンダーから非表示にするか、Bluesky 側からも削除（リポストは取り消し）できます
- **マルチアカウント**: 複数の Bluesky アカウントを1つのセッションで切り替えて使えます
- **招待制**: 参加申請（ウェイトリスト）を受け付け、管理画面から招待を送ります。管理画面は二要素認証（TOTP）に対応しています

## 技術スタック

- PHP 8.3 / Laravel 13
- MySQL
- Tailwind CSS 4 / Vite
- Bluesky（AT Protocol）の XRPC API を SDK を使わずに直接呼び出しています
- Docker Compose でのローカル開発環境、GitHub Actions でのテスト

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

## ワークツリーでの開発

git worktree で並行して開発するときの手順は [docs/worktree.md](docs/worktree.md) にまとめています（Orca での使い方も含みます）。

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

DB と Mail の接続先は `setup.sh` が書き込む値と同じものが最初から入っているので、書き換えは不要です。

#### APP_KEY生成

```
docker compose exec web php artisan key:generate
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
make queue-once   # 溜まっている分だけ消化して終了（本番でスケジューラが5分ごとに動かすのと同じ）
make schedule     # スケジューラ（bluesky:sync-all は1時間ごと）
```

## デプロイ

アセットはローカルでビルドします。

```
cd src
pnpm build
```

サーバーでは `deploy.sh` を実行します。コードの最新化（`git pull --ff-only`）、依存関係のインストール（`composer install --no-dev`）、キャッシュの作り直し（`optimize`）、マイグレーション（`migrate --force`）を行います。pull で `deploy.sh` 自体が変わったときは、新しい中身で起動し直してから続けます。

```
./deploy.sh
```

cron と php のバージョンをそろえたいときなどは、`PHP=/path/to/php ./deploy.sh` のように使う php を指定できます（composer もその php で動かします）。

本番の `src/.env` では、DB（MySQL）とメール送信（SMTP）の接続先を本番のものに設定してください。メールが届かないと、招待コードやウェイトリストの通知が送れません。`APP_KEY` は Bluesky のアプリパスワードの暗号化に使っているので、変えたりなくしたりしないでください。

本番では cron で `php artisan schedule:run` を5分ごとに実行してください。キューの消化（`queue:work --stop-when-empty`）もスケジューラから動くので、cron に `queue:work` を別に登録する必要はありません。

本番のサーバーへの設置・更新・cron は nono150com-server-ops の [docs/sklog.md](https://github.com/itigoppo/nono150com-server-ops/blob/main/docs/sklog.md) で管理しています。

## ライセンス

All rights reserved. 無断での利用・再配布はできません。
