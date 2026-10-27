# git worktree で開発する

`git worktree add` でワークツリーを作ったら、まずセットアップスクリプトを実行します。git 管理外のファイル（`src/.env` / `src/vendor` / `src/node_modules`）は新しいワークツリーには無いため、これで揃えます。

```
git worktree add ../sklog-foo -b foo
cd ../sklog-foo
bash .orca/setup.sh
```

スクリプトは `.orca/` に置いていますが、Orca 以外で作ったワークツリーでもそのまま使えます（Orca での使い方は末尾の「Orca で使う場合」を参照）。

- `src/.env` は main チェックアウトからコピーし、`APP_URL` と `SESSION_COOKIE` をワークツリー用に書き換えます（後述の cookie 分離のため）
- `src/node_modules` はホストの `pnpm install` で作ります
- `src/vendor` は使い捨ての `composer:2` コンテナで作ります（ホストに PHP 不要）
- **compose のスタックは起動しません。** `docker-compose.yml` は `container_name` とポートが固定なので、main チェックアウトの環境と衝突します。ワークツリーで `make docker-up` / `make docker-down` / `make setup` を実行すると、compose の代わりにワークツリー専用のコンテナを扱います（後述）
- `vendor` が既にある場合はスキップします。作り直すときは `bash .orca/setup.sh --force`
- `.husky/_` も生成します（git 管理外のため、これが無いとワークツリーでは pre-commit のアセットビルドが黙ってスキップされます）

ワークツリーでも `make test` / `make lint` / `make format` / `make build` は動きます。web コンテナが起動していない場所では、Makefile が `sklog-web` イメージから使い捨てコンテナを起動して実行するためです（テストは sqlite `:memory:` なので DB 不要）。

ただしその `sklog-web` イメージは **main チェックアウトの compose が作るもの**です。したがって **一度は main 側で `make docker-build`（または `./setup.sh`）を通しておく必要があります**。イメージは main とワークツリーで共有するので、ワークツリーでは `make docker-build` は止まります。Dockerfile を試すときは `docker build -t sklog-web-<名前> -f docker/web/Dockerfile .` のように別名でビルドし、`make WEB_IMAGE=sklog-web-<名前> test` で指定してください。イメージが無い場合は Makefile がその旨を出して止まります。

DB はセットアップスクリプトが用意します。ワークツリー専用の MySQL を起動し、マイグレーションとシードまで済ませるので、作成直後から `make migrate` などが使えます。main の DB とは完全に別物なので、何をしても main 側には影響しません。

```
make docker-down  # アプリ・Mailpit・DB を全部止める（--rm なのでコンテナごと消え、データも残らない）
make docker-up    # また使うときに起動する（DB を作り直したときは migrate と seed も流す）
make db-down      # DB だけ止めたいとき（DB を使うアプリも止まる）
make db-up        # DB だけ起動する
make migrate      # 専用 DB に対して実行される
make seed      # シードを流し直す
```

MySQL は既定のままだと 1台 360MB ほど使うため、`docker/db/worktree.cnf` で `performance_schema` とバッファを絞って **約110MB** に抑えています（main の DB には影響しません）。DB が不要なワークツリーでは `make docker-down`、最初から立てたくない場合は `bash .orca/setup.sh --no-db` を使ってください。

DB を止めた状態で `make migrate` を叩くとガードで止まります。

止め忘れたままワークツリーを消すとコンテナとネットワークが残るので、ワークツリーを消す前にそのワークツリーで後始末のスクリプトを実行します。

```
bash .orca/archive.sh
git worktree remove ../sklog-foo
```

実行し忘れて消してしまった分は次で回収できます。

```
make db-prune   # ワークツリーが存在しない sklog_wt_* を削除する
```

## ワークツリーのアプリをブラウザで見る

compose のスタックは main のディレクトリをマウントしているので、ワークツリーの変更は `http://localhost:18080` には出ません。そのため専用のアプリ（Laravel + Mailpit）を**セットアップ時に自動で起動**しています。URL はセットアップの最後に表示されます。

```
make app-info   # URL を再表示
make app-down   # 停止してメモリを空ける
make app-up     # また起動する（起動済みなら何もしません）
make dev        # アプリ + Vite をまとめて起動（Vite はフォアグラウンド）
```

アプリが不要なワークツリーは `bash .orca/setup.sh --no-app`（DB も要らなければ `--no-db`）で立てずに済ませられます。

`php artisan serve` をワークツリー専用ネットワークで動かすので、DB も Mailpit もそのワークツリーのものを使います。ポートはワークツリー名から決まります（アプリ 19100〜19999、Mailpit 20100〜20999）。ポートがすでに使われていると `make app-up` は止まるので、`make APP_PORT=19500 MAIL_PORT=20500 app-up` のように上書きしてください。

**アプリの URL は `http://<ワークツリー名>.localhost:<ポート>` です。** cookie はポートを区別しないので、`localhost:19xxx` で開くと main（`localhost:18080`）とセッションや remember me の cookie を食い合い、片方を開くともう片方がログアウトします。DB は別物なので復元もできません。ホスト名を分けることで cookie を分離しています（`*.localhost` は 127.0.0.1 に解決されます）。`.orca/setup.sh` がワークツリーの `src/.env` の `APP_URL` と `SESSION_COOKIE` をこれに合わせて書き換えます。

ワークツリーの DB にはシードのアカウントしか無いので、`.orca/setup.sh` は `src/.env` の `DEV_LOGIN` も `true` にします。`/dev/login` から Bluesky の認証なしでログインできます（URL は `make app-info` に出ます）。

`*.localhost` が解決できない環境では `make APP_HOST=localhost app-info` のように上書きできます（その場合は main と cookie を食い合います）。

アセットを編集しながら確認するときは、別ターミナルで `make run`（Vite）も起動します。ワークツリーの `public/hot` をそのワークツリーの Laravel が読むので、HMR がそのまま効きます。

**起動順はどちらでも構いません。** Laravel はリクエストのたびに `public/hot` を見るため、後から `make run` しても次のリロードで dev サーバーに切り替わり、Vite を Ctrl-C で止めればビルド済みアセットに戻ります。

ただし Vite を強制終了（`kill -9` など）すると `public/hot` が残り、アプリが死んだ dev サーバーを指し続けてアセットが読めなくなります。`make app-up` は起動時にこれを検出して削除しますが、実行中に起きた場合は `rm src/public/hot` してください。

メモリはアプリ 73MB + Mailpit 10MB です（DB 119MB と合わせて約200MB）。

`src/vendor` や `src/node_modules` はワークツリーごと削除されるので、後始末が要るのはコンテナだけです（上記の `.orca/archive.sh` が担当）。

## Orca で使う場合

Orca の repo 設定に次のスクリプトを登録してあり、ワークツリーの作成時と削除時に自動で実行されます（動作確認済み）。

- Setup script: `bash .orca/setup.sh`
- Archive script: `bash .orca/archive.sh`

ただし **`orca worktree rm` は `--run-hooks` を付けないと archive フックを実行しません**（`orca.yaml archive hook skipped ... pass --run-hooks to run it.` という警告が出ます）。フックが走らずに消えてしまった分は `make db-prune` で回収してください。
