# php を実行する先の決め方
#   main チェックアウト: 起動中の web コンテナ (docker compose exec)
#   ワークツリー       : compose プロジェクトが無いので web イメージから使い捨てコンテナを起動する
#                        テストは sqlite :memory: を使うので DB が無くても動く
WEB_IMAGE ?= sklog-web
WEB_CONTAINER := $(shell docker compose ps -q web 2>/dev/null)
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
	require-web

# Set up the local development environment
setup:
	./setup.sh

# Set up the local development environment from scratch (recreate DB)
setup-fresh:
	./setup.sh --fresh

# Set up a git worktree (copy .env from main, install deps; no Docker)
setup-worktree:
	bash .orca/setup.sh

# Fail early when a target needs the running stack (main checkout only)
require-web:
	@if [ -z "$(WEB_CONTAINER)" ]; then \
		echo "web コンテナが起動していません。main チェックアウト (~/git/sklog) で make docker-up してください。"; \
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
cache-clear:
	$(PHP) php artisan optimize:clear

# Check code style (Laravel Pint)
lint:
	$(PHP) php vendor/bin/pint --test

# Fix code style
format:
	$(PHP) php vendor/bin/pint

# Run unit tests
test:
	$(PHP) php artisan test

# Run unit tests with coverage
test-coverage:
	$(PHP) php artisan test --coverage