.PHONY: \
	setup setup-fresh setup-worktree \
	docker-up docker-down docker-build docker-rebuild docker-web docker-db docker-mail \
	run build queue queue-once schedule \
	migrate migrate-fresh migrate-fresh-seed cache-clear \
	test test-coverage \
	lint format

# Set up the local development environment
setup:
	./setup.sh

# Set up the local development environment from scratch (recreate DB)
setup-fresh:
	./setup.sh --fresh

# Set up a git worktree (copy .env from main, install deps; no Docker)
setup-worktree:
	bash .orca/setup.sh

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
docker-web:
	docker compose exec web bash

# Get into the mysql container
docker-db:
	docker compose exec mysql mysql -u user -ppassword sklog

# Get into the mailpit container
docker-mail:
	docker compose exec mailpit bash

# Run the development server
run:
	cd src && pnpm dev

# Build the project
build:
	cd src && pnpm build

# Run the queue worker (jobs are dispatched to the database queue)
queue:
	docker compose exec web php artisan queue:work

# Drain the queue and exit (same as the production cron)
queue-once:
	docker compose exec web php artisan queue:work --stop-when-empty

# Run the scheduler (bluesky:sync-all is scheduled hourly)
schedule:
	docker compose exec web php artisan schedule:work

# Migrate the database
migrate:
	docker compose exec web php artisan migrate

# Fresh migrate the database
migrate-fresh:
	docker compose exec web php artisan migrate:fresh

# Fresh migrate the database with seed
migrate-fresh-seed:
	docker compose exec web php artisan migrate:fresh --seed

# Clear cache
cache-clear:
	docker compose exec web php artisan optimize:clear

# Check code style (Laravel Pint)
lint:
	docker compose exec web php vendor/bin/pint --test

# Fix code style
format:
	docker compose exec web php vendor/bin/pint

# Run unit tests
test:
	docker compose exec web php artisan test

# Run unit tests with coverage
test-coverage:
	docker compose exec web php artisan test --coverage