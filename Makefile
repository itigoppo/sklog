# php を実行する先の決め方
#   web コンテナが起動している (= main チェックアウトで docker-up 済み): そのコンテナで実行する
#   起動していない (ワークツリー、または main で未起動): web イメージから使い捨てコンテナを起動する
#     テストは sqlite :memory: を使うので DB が無くても動く
#
# 使い捨てコンテナが使うイメージは main チェックアウトの make docker-build が作る sklog-web など
# (名前は docker-compose.yml の image: で固定)。main とワークツリーで共有するので、
# ワークツリーでは docker-build を止めている。Dockerfile を試すときは別名でビルドして WEB_IMAGE= で指定する
WEB_IMAGE ?= sklog-web
DB_IMAGE ?= sklog-mysql
MAIL_IMAGE ?= sklog-mail
WEB_CONTAINER := $(shell docker compose ps -q web 2>/dev/null)
MAIN_CHECKOUT := $(shell cd "$$(git rev-parse --path-format=absolute --git-common-dir 2>/dev/null)/.." 2>/dev/null && pwd)
# docker-up / docker-down / setup などは、main では compose のスタック、ワークツリーでは専用のコンテナを扱う
IS_MAIN := $(if $(filter $(MAIN_CHECKOUT),$(CURDIR)),1,)

# ワークツリー専用の使い捨て MySQL (make db-up / db-down)
# main の DB を汚さずにマイグレーションを流すためのもの。--rm なのでデータは残らない。
WT_NAME := $(shell printf '%s' "$(notdir $(CURDIR))" | tr -c 'A-Za-z0-9_.-' '_')
WT_NET := sklog_wt_$(WT_NAME)
WT_DB := sklog_wt_$(WT_NAME)_db
WT_DB_RUNNING := $(shell docker ps -q -f name=^$(WT_DB)$$ 2>/dev/null)

WT_APP := sklog_wt_$(WT_NAME)_app
WT_MAIL := sklog_wt_$(WT_NAME)_mail
# ワークツリー名からポートを決める (アプリ 19100-19999 / Mailpit 20100-20999)。
# 衝突したら APP_PORT= / MAIL_PORT= で上書きする
# (19 通りだとワークツリー同士で重なりやすかったので広げている。18xxx は main の 18443 と、
#  17xxx は Dropbox などと重なるので避けている)
WT_PORT_OFFSET := $(shell printf '%s' "$(WT_NAME)" | cksum | cut -d' ' -f1 | awk '{print $$1 % 900}')
APP_PORT ?= $(shell expr 19100 + $(WT_PORT_OFFSET))
MAIL_PORT ?= $(shell expr 20100 + $(WT_PORT_OFFSET))
# cookie はポートを区別しないので、ホスト名でワークツリーと main を分ける。
# localhost:18080 と localhost:19xxx のままだとセッションと remember me の cookie を
# 食い合い、片方を開くともう片方がログアウトする。*.localhost は 127.0.0.1 に解決される。
# 解決できない環境では APP_HOST=localhost で上書きする
WT_HOST := $(shell printf '%s' "$(WT_NAME)" | tr 'A-Z' 'a-z' | tr '_.' '--')
APP_HOST ?= $(WT_HOST).localhost

# compose でビルドしたイメージにはプロジェクト名のラベルが付いていて、docker run したコンテナはそれを引き継ぐ。
# そのままだと Docker Desktop でビルドした側（main など）のまとまりに混ざるので、ワークツリーごとのラベルで上書きする
WT_PROJECT := sklog-wt-$(WT_HOST)
WT_LABEL = --label com.docker.compose.project=$(WT_PROJECT) --label com.docker.compose.service=$(1)

WT_RUN = docker run --rm $(call WT_LABEL,php) -v "$(CURDIR)":/home/itigoppo/git/sklog -w /home/itigoppo/git/sklog/src
ifeq ($(WEB_CONTAINER),)
ifeq ($(WT_DB_RUNNING),)
PHP = $(WT_RUN) $(WEB_IMAGE)
else
PHP = $(WT_RUN) --network $(WT_NET) $(WEB_IMAGE)
endif
else
PHP = docker compose exec web
endif

.PHONY: \
	setup setup-fresh setup-worktree \
	docker-up docker-down docker-build docker-rebuild docker-web docker-db docker-mail \
	run build queue queue-once schedule \
	migrate migrate-fresh migrate-fresh-seed seed cache-clear \
	test test-coverage \
	lint format \
	require-main require-web require-app require-php require-db \
	db-up db-down db-prune \
	app-up app-down app-info app-url dev

# Set up the local development environment (worktree: .orca/setup.sh)
ifeq ($(IS_MAIN),1)
setup:
	./setup.sh
else
setup: setup-worktree
endif

# Set up the local development environment from scratch (recreate DB)
setup-fresh: require-main
	./setup.sh --fresh

# Set up a git worktree (copy .env from main, install deps; no Docker)
setup-worktree:
	bash .orca/setup.sh

# Fail early when a target is only for the main checkout
# イメージは main とワークツリーで共有するので、ワークツリーでビルドすると全員のイメージが書き換わる。
# setup-fresh は main の DB を作り直すためのもの
require-main:
	@if [ "$(CURDIR)" = "$(MAIN_CHECKOUT)" ]; then exit 0; fi; \
	echo "ここはワークツリーです。この操作は main チェックアウト ($(MAIN_CHECKOUT)) で実行してください。"; \
	echo "  ワークツリーでは make setup / make docker-up / make docker-down を使います。"; \
	if [ -n "$(filter docker-build,$(MAKECMDGOALS))" ]; then \
		echo "  Dockerfile を試すときは別名でビルドしてください:"; \
		echo "    docker build -t sklog-web-$(WT_HOST) -f docker/web/Dockerfile ."; \
		echo "    make WEB_IMAGE=sklog-web-$(WT_HOST) test"; \
	fi; \
	exit 1

# Fail early when a target needs the running stack (DB, long-running processes)
require-web:
	@if [ -n "$(WEB_CONTAINER)" ]; then exit 0; fi; \
	if [ "$(CURDIR)" = "$(MAIN_CHECKOUT)" ]; then \
		echo "web コンテナが起動していません。make docker-up してください。"; \
	else \
		echo "ここはワークツリーです。この操作は main チェックアウト ($(MAIN_CHECKOUT)) で実行してください。"; \
	fi; \
	exit 1

# Fail early when this worktree's app is not running
require-app:
	@if [ -n "$$(docker ps -q -f name=^$(WT_APP)$$)" ]; then exit 0; fi; \
	echo "ワークツリーのアプリが起動していません。make docker-up してください。"; \
	exit 1

# Fail early when the throwaway container cannot be started
require-php:
	@if [ -n "$(WEB_CONTAINER)" ]; then exit 0; fi; \
	if ! docker info >/dev/null 2>&1; then \
		echo "Docker が起動していません。"; \
		exit 1; \
	fi; \
	if ! docker image inspect $(WEB_IMAGE) >/dev/null 2>&1; then \
		echo "$(WEB_IMAGE) イメージがありません。main チェックアウト ($(MAIN_CHECKOUT)) で make docker-build してください。"; \
		echo "別名のイメージを使う場合: make WEB_IMAGE=<image> <target>"; \
		exit 1; \
	fi

# Fail early when a target needs a database
require-db:
	@if [ -n "$(WEB_CONTAINER)" ] || [ -n "$(WT_DB_RUNNING)" ]; then exit 0; fi; \
	if [ "$(CURDIR)" = "$(MAIN_CHECKOUT)" ]; then \
		echo "web コンテナが起動していません。make docker-up してください。"; \
	else \
		echo "ワークツリー用の DB が起動していません。make docker-up してください。"; \
	fi; \
	exit 1

# Start a throwaway MySQL for this worktree (data is discarded on db-down)
db-up: require-php
	@if [ "$(CURDIR)" = "$(MAIN_CHECKOUT)" ]; then \
		echo "main チェックアウトでは make docker-up を使ってください。"; \
		exit 1; \
	fi
	@docker image inspect $(DB_IMAGE) >/dev/null 2>&1 || { \
		echo "$(DB_IMAGE) イメージがありません。main チェックアウト ($(MAIN_CHECKOUT)) で make docker-build してください。"; \
		exit 1; \
	}
	@if [ -n "$(WT_DB_RUNNING)" ]; then echo "$(WT_DB) は既に起動しています。"; exit 0; fi; \
	docker network inspect $(WT_NET) >/dev/null 2>&1 || docker network create $(WT_NET) >/dev/null; \
	docker run -d --rm --name $(WT_DB) $(call WT_LABEL,mysql) --network $(WT_NET) --network-alias mysql \
		-v "$(CURDIR)/docker/db/worktree.cnf":/etc/mysql/conf.d/worktree.cnf:ro \
		-e TZ=Asia/Tokyo -e MYSQL_DATABASE=sklog -e MYSQL_ROOT_PASSWORD=root \
		-e MYSQL_USER=user -e MYSQL_PASSWORD=password $(DB_IMAGE) >/dev/null; \
	printf "MySQL の起動を待っています"; \
	for i in $$(seq 1 60); do \
		if docker exec $(WT_DB) mysqladmin ping -h 127.0.0.1 -uuser -ppassword --silent >/dev/null 2>&1; then \
			echo " OK"; \
			echo "make migrate / make queue などがこのワークツリーの DB に向きます。"; \
			exit 0; \
		fi; \
		printf "."; \
		sleep 2; \
	done; \
	echo " タイムアウトしました。docker logs $(WT_DB) を確認してください。"; \
	exit 1

# Serve this worktree's app (main checkout keeps using docker compose)
# 「既に起動している」はコンテナが指定のポートで公開されているかまで見る。ポートの応答だけだと別のアプリを拾う
# ポートの確認は docker rm -f より前に行う。後だと別のアプリのポートで止まったときに自分のアプリまで消える
app-up: require-php require-db
	@if [ "$(CURDIR)" = "$(MAIN_CHECKOUT)" ]; then \
		echo "main チェックアウトでは make docker-up を使ってください。"; \
		exit 1; \
	fi; \
	if docker port $(WT_APP) 8000 2>/dev/null | grep -q ":$(APP_PORT)$$" \
		&& docker port $(WT_MAIL) 8025 2>/dev/null | grep -q ":$(MAIL_PORT)$$" \
		&& curl -s -o /dev/null --max-time 2 http://localhost:$(APP_PORT)/ 2>/dev/null; then \
		echo "アプリは既に起動しています。"; \
		$(MAKE) -s app-info; \
		exit 0; \
	fi; \
	own=$$( { docker port $(WT_APP) 8000; docker port $(WT_MAIL) 8025; } 2>/dev/null); \
	for p in $(APP_PORT) $(MAIL_PORT); do \
		printf '%s\n' "$$own" | grep -q ":$$p$$" && continue; \
		if curl -s -o /dev/null --max-time 2 http://localhost:$$p/ 2>/dev/null; then \
			echo "ポート $$p は別のアプリが使っています（他のワークツリーと重なった可能性があります）。"; \
			echo "  空いているポートを指定してください: make app-up APP_PORT=<ポート> MAIL_PORT=<ポート>"; \
			exit 1; \
		fi; \
	done; \
	docker rm -f $(WT_APP) $(WT_MAIL) >/dev/null 2>&1 || true; \
	if [ -f src/public/hot ] && ! curl -s -o /dev/null --max-time 2 "$$(cat src/public/hot)/@vite/client"; then \
		rm -f src/public/hot; \
		echo "死んでいる public/hot を削除しました（Vite が強制終了された跡）。"; \
	fi; \
	docker run -d --rm --name $(WT_MAIL) $(call WT_LABEL,mail) --network $(WT_NET) --network-alias mail \
		-p $(MAIL_PORT):8025 $(MAIL_IMAGE) >/dev/null \
		|| { echo "Mailpit のコンテナを起動できませんでした。"; exit 1; }; \
	docker run -d --rm --name $(WT_APP) $(call WT_LABEL,web) --network $(WT_NET) \
		-v "$(CURDIR)":/home/itigoppo/git/sklog -w /home/itigoppo/git/sklog/src \
		-p $(APP_PORT):8000 $(WEB_IMAGE) \
		php artisan serve --host=0.0.0.0 --port=8000 >/dev/null \
		|| { echo "アプリのコンテナを起動できませんでした。"; docker rm -f $(WT_MAIL) >/dev/null 2>&1; exit 1; }; \
	printf "起動を待っています"; \
	for i in $$(seq 1 30); do \
		if curl -s -o /dev/null http://localhost:$(APP_PORT)/ 2>/dev/null; then \
			echo " OK"; \
			echo ""; \
			$(MAKE) -s app-info; \
			echo ""; \
			echo "  CSS/JS を触るときは別ターミナルで make run (Vite) も起動してください。"; \
			exit 0; \
		fi; \
		printf "."; \
		sleep 1; \
	done; \
	echo " タイムアウトしました。docker logs $(WT_APP) を確認してください。"; \
	exit 1

# Show this worktree's app URLs
app-info:
	@echo "  アプリ    : http://$(APP_HOST):$(APP_PORT)"
	@echo "  Mailpit   : http://localhost:$(MAIL_PORT)"
	@if grep -qx 'APP_ENV=local' src/.env 2>/dev/null && grep -qx 'DEV_LOGIN=true' src/.env; then \
		echo "  開発ログイン: http://$(APP_HOST):$(APP_PORT)/dev/login"; \
	else \
		echo "  開発ログイン: src/.env を APP_ENV=local・DEV_LOGIN=true にすると /dev/login が使えます"; \
	fi

# Print this worktree's app URL (used by .orca/setup.sh)
app-url:
	@echo "http://$(APP_HOST):$(APP_PORT)"

# Start the app and the Vite dev server together (Vite runs in the foreground)
dev: app-up
	@echo ""
	cd src && pnpm dev

# Stop this worktree's app
app-down:
	@docker rm -f $(WT_APP) $(WT_MAIL) >/dev/null 2>&1 && echo "アプリを停止しました。" || echo "アプリは起動していません。"

# Remove throwaway MySQL containers whose worktree no longer exists
# (archive フックが走らずに削除されたワークツリーの取りこぼし用)
db-prune:
	@names=$$(docker ps -a --filter "name=^sklog_wt_" --format '{{.Names}}'); \
	if [ -z "$$names" ]; then echo "対象のコンテナはありません。"; exit 0; fi; \
	existing=$$(git worktree list --porcelain | awk '/^worktree /{n=split($$2,a,"/"); print a[n]}' \
		| while IFS= read -r b; do printf '%s\n' "$$(printf '%s' "$$b" | tr -c 'A-Za-z0-9_.-' '_')"; done); \
	printf '%s\n' "$$names" | while IFS= read -r c; do \
		n=$${c#sklog_wt_}; \
		case "$$n" in *_db) n=$${n%_db};; *_app) n=$${n%_app};; *_mail) n=$${n%_mail};; esac; \
		if printf '%s\n' "$$existing" | grep -qx "$$n"; then \
			echo "$$c: ワークツリーが存在するので残します"; \
		else \
			docker rm -f "$$c" >/dev/null 2>&1 && echo "$$c を削除しました"; \
			docker network rm "sklog_wt_$$n" >/dev/null 2>&1 || true; \
		fi; \
	done

# Stop the throwaway MySQL (the container and its data are removed)
db-down: app-down
	@docker rm -f $(WT_DB) >/dev/null 2>&1 && echo "$(WT_DB) を停止しました。" || echo "$(WT_DB) は起動していません。"
	@docker network rm $(WT_NET) >/dev/null 2>&1 || true

# docker-compose.yml は container_name とポートが固定で、カレントディレクトリをマウントするので、
# ワークツリーで compose のスタックを立てると main とぶつかる。ワークツリーでは専用のコンテナを扱う
ifeq ($(IS_MAIN),1)
# Start Docker containers
docker-up:
	docker compose up -d

# Stop Docker containers
docker-down:
	docker compose down
else
# Start this worktree's DB and app (migrate and seed when the DB is newly created)
docker-up:
	@$(MAKE) -s db-up || exit 1; \
	if [ -z "$(WT_DB_RUNNING)" ]; then \
		$(MAKE) -s migrate && $(MAKE) -s seed || exit 1; \
	fi; \
	$(MAKE) -s app-up

# Stop this worktree's app, Mailpit and DB (the DB data is removed)
docker-down:
	@echo "ワークツリーのアプリ・Mailpit・DB を止めます（DB のデータも消えます）。"
	@$(MAKE) -s db-down
endif

# Build Docker containers
docker-build: require-main
	docker compose build

# Rebuild Docker containers
docker-rebuild: require-main
	docker compose up --build -d

# Get into the web container
ifeq ($(IS_MAIN),1)
docker-web: require-web
	docker compose exec web bash
else
docker-web: require-app
	docker exec -it $(WT_APP) bash
endif

# Get into the mysql container
docker-db: require-db
	@if [ -n "$(WEB_CONTAINER)" ]; then \
		docker compose exec mysql mysql -u user -ppassword sklog; \
	else \
		docker exec -it $(WT_DB) mysql -u user -ppassword sklog; \
	fi

# Get into the mailpit container
ifeq ($(IS_MAIN),1)
docker-mail: require-web
	docker compose exec mail sh
else
docker-mail: require-app
	docker exec -it $(WT_MAIL) sh
endif

# Run the development server
run:
	cd src && pnpm dev

# Build the project
build:
	cd src && pnpm build

# Run the queue worker (jobs are dispatched to the database queue)
queue: require-php require-db
	$(PHP) php artisan queue:work

# Drain the queue and exit (same as the production cron)
queue-once: require-php require-db
	$(PHP) php artisan queue:work --stop-when-empty

# Run the scheduler (bluesky:sync-all is scheduled hourly)
schedule: require-php require-db
	$(PHP) php artisan schedule:work

# Migrate the database
migrate: require-php require-db
	$(PHP) php artisan migrate

# Fresh migrate the database
migrate-fresh: require-php require-db
	$(PHP) php artisan migrate:fresh

# Fresh migrate the database with seed
migrate-fresh-seed: require-php require-db
	$(PHP) php artisan migrate:fresh --seed

# Run the database seeders
seed: require-php require-db
	$(PHP) php artisan db:seed

# Clear cache
cache-clear: require-php
	$(PHP) php artisan optimize:clear

# Check code style (Laravel Pint)
lint: require-php
	$(PHP) php vendor/bin/pint --test

# Fix code style
format: require-php
	$(PHP) php vendor/bin/pint

# Run unit tests
test: require-php
	$(PHP) php artisan test

# Run unit tests with coverage
test-coverage: require-php
	$(PHP) php artisan test --coverage