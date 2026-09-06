# php を実行する先の決め方
#   web コンテナが起動している (= main チェックアウトで docker-up 済み): そのコンテナで実行する
#   起動していない (ワークツリー、または main で未起動): web イメージから使い捨てコンテナを起動する
#     テストは sqlite :memory: を使うので DB が無くても動く
#
# 使い捨てコンテナが使うイメージは main チェックアウトの compose が作ったもの
# (プロジェクト名 sklog → sklog-web)。ワークツリーで compose build しても
# 別名 (<ディレクトリ名>-web) になるため、一度は main で make docker-build が要る。
WEB_IMAGE ?= sklog-web
WEB_CONTAINER := $(shell docker compose ps -q web 2>/dev/null)
MAIN_CHECKOUT := $(shell cd "$$(git rev-parse --path-format=absolute --git-common-dir 2>/dev/null)/.." 2>/dev/null && pwd)
ifeq ($(WEB_CONTAINER),)
PHP = docker run --rm -v "$(CURDIR)":/home/itigoppo/git/sklog -w /home/itigoppo/git/sklog/src $(WEB_IMAGE)
else
PHP = docker compose exec web
endif

.PHONY: \
	setup setup-fresh setup-worktree \
	docker-up docker-down docker-build docker-rebuild docker-web docker-db docker-mail \
	run build queue queue-once schedule \
	migrate migrate-fresh migrate-fresh-seed cache-clear \
	test test-coverage \
	lint format \
	require-web require-php

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
docker-db: require-web
	docker compose exec mysql mysql -u user -ppassword sklog

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
queue: require-web
	docker compose exec web php artisan queue:work

# Drain the queue and exit (same as the production cron)
queue-once: require-web
	docker compose exec web php artisan queue:work --stop-when-empty

# Run the scheduler (bluesky:sync-all is scheduled hourly)
schedule: require-web
	docker compose exec web php artisan schedule:work

# Migrate the database
migrate: require-web
	docker compose exec web php artisan migrate

# Fresh migrate the database
migrate-fresh: require-web
	docker compose exec web php artisan migrate:fresh

# Fresh migrate the database with seed
migrate-fresh-seed: require-web
	docker compose exec web php artisan migrate:fresh --seed

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