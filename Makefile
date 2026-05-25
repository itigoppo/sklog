.PHONY: \
	docker-up docker-down docker-build docker-rebuild docker-web docker-db docker-mail \
	run build \
	migrate migrate-fresh migrate-fresh-seed cache-clear \
	test test-coverage

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

# Run unit tests
test:
	docker compose exec web php artisan test

# Run unit tests with coverage
test-coverage:
	docker compose exec web php artisan test --coverage