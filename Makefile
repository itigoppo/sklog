# php を実行する先の決め方
#   web コンテナが起動している (= main チェックアウトで docker-up 済み): そのコンテナで実行する
#   起動していない (ワークツリー、または main で未起動): web イメージから使い捨てコンテナを起動する
#     テストは sqlite :memory: を使うので DB が無くても動く
#
# 使い捨てコンテナが使うイメージは main チェックアウトの compose が作ったもの
# (プロジェクト名 sklog → sklog-web)。ワークツリーで compose build しても
# 別名 (<ディレクトリ名>-web) になるため、一度は main で make docker-build が要る。
WEB_IMAGE ?= sklog-web
DB_IMAGE ?= sklog-mysql
WEB_CONTAINER := $(shell docker compose ps -q web 2>/dev/null)
MAIN_CHECKOUT := $(shell cd "$$(git rev-parse --path-format=absolute --git-common-dir 2>/dev/null)/.." 2>/dev/null && pwd)

# ワークツリー専用の使い捨て MySQL (make db-up / db-down)
# main の DB を汚さずにマイグレーションを流すためのもの。--rm なのでデータは残らない。
WT_NAME := $(shell printf '%s' "$(notdir $(CURDIR))" | tr -c 'A-Za-z0-9_.-' '_')
WT_NET := sklog_wt_$(WT_NAME)
WT_DB := sklog_wt_$(WT_NAME)_db
WT_DB_RUNNING := $(shell docker ps -q -f name=^$(WT_DB)$$ 2>/dev/null)

WT_APP := sklog_wt_$(WT_NAME)_app
WT_MAIL := sklog_wt_$(WT_NAME)_mail
# ワークツリー名からポートを決める (18081-18099 / 18025 系)。衝突したら APP_PORT= で上書きする
WT_PORT_OFFSET := $(shell printf '%s' "$(WT_NAME)" | cksum | cut -d' ' -f1 | awk '{print $$1 % 19}')
APP_PORT ?= $(shell expr 18081 + $(WT_PORT_OFFSET))
MAIL_PORT ?= $(shell expr 18026 + $(WT_PORT_OFFSET))

WT_RUN = docker run --rm -v "$(CURDIR)":/home/itigoppo/git/sklog -w /home/itigoppo/git/sklog/src
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
	require-web require-php require-db \
	db-up db-down db-prune \
	app-up app-down app-info dev

# Set up the local development environment
setup:
	./setup.sh

# Set up the local development environment from scratch (recreate DB)
setup-fresh:
	./setup.sh --fresh

# Set up a git worktree (copy .env from main, install deps; no Docker)
setup-worktree:
	bash .orca/setup.sh

# Fail early when a target needs the running stack (DB, long-running processes)
require-web:
	@if [ -n "$(WEB_CONTAINER)" ]; then exit 0; fi; \
	if [ "$(CURDIR)" = "$(MAIN_CHECKOUT)" ]; then \
		echo "web コンテナが起動していません。make docker-up してください。"; \
	else \
		echo "ここはワークツリーです。この操作は main チェックアウト ($(MAIN_CHECKOUT)) で実行してください。"; \
	fi; \
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
		echo "ワークツリー用の DB が起動していません。make db-up してください。"; \
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
	docker run -d --rm --name $(WT_DB) --network $(WT_NET) --network-alias mysql \
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
app-up: require-php require-db
	@if [ "$(CURDIR)" = "$(MAIN_CHECKOUT)" ]; then \
		echo "main チェックアウトでは make docker-up を使ってください。"; \
		exit 1; \
	fi; \
	if [ -n "$$(docker ps -q -f name=^$(WT_APP)$$)" ] \
		&& curl -s -o /dev/null --max-time 2 http://localhost:$(APP_PORT)/ 2>/dev/null; then \
		echo "アプリは既に起動しています。"; \
		$(MAKE) -s app-info; \
		exit 0; \
	fi; \
	docker rm -f $(WT_APP) $(WT_MAIL) >/dev/null 2>&1 || true; \
	if [ -f src/public/hot ] && ! curl -s -o /dev/null --max-time 2 "$$(cat src/public/hot)/@vite/client"; then \
		rm -f src/public/hot; \
		echo "死んでいる public/hot を削除しました（Vite が強制終了された跡）。"; \
	fi; \
	docker run -d --rm --name $(WT_MAIL) --network $(WT_NET) --network-alias mail \
		-p $(MAIL_PORT):8025 sklog-mail >/dev/null; \
	docker run -d --rm --name $(WT_APP) --network $(WT_NET) \
		-v "$(CURDIR)":/home/itigoppo/git/sklog -w /home/itigoppo/git/sklog/src \
		-p $(APP_PORT):8000 $(WEB_IMAGE) \
		php artisan serve --host=0.0.0.0 --port=8000 >/dev/null; \
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
	@echo "  アプリ    : http://localhost:$(APP_PORT)"
	@echo "  Mailpit   : http://localhost:$(MAIL_PORT)"

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

# Start Docker containers
docker-up:
	docker compose up -d

# Stop Docker containers
docker-down:
	docker compose down

# Build Docker containers
docker-build:
	docker compose build

# Rebuild Docker containers
docker-rebuild:
	docker compose up --build -d

# Get into the web container
docker-web: require-web
	docker compose exec web bash

# Get into the mysql container
docker-db: require-db
	@if [ -n "$(WEB_CONTAINER)" ]; then \
		docker compose exec mysql mysql -u user -ppassword sklog; \
	else \
		docker exec -it $(WT_DB) mysql -u user -ppassword sklog; \
	fi

# Get into the mailpit container
docker-mail: require-web
	docker compose exec mail sh

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