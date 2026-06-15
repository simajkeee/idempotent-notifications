SAIL := vendor/bin/sail

.PHONY: help up down restart build logs shell migrate fresh seed test test-integration worker-stop worker-start

help:
	@printf '%s\n' \
		'make up                Start all Docker services' \
		'make down              Stop all Docker services' \
		'make restart           Restart all Docker services' \
		'make build             Build Docker images' \
		'make logs              Follow Docker service logs' \
		'make shell             Open a shell in the application container' \
		'make migrate           Run database migrations' \
		'make fresh             Rebuild and seed the development database' \
		'make seed              Run database seeders' \
		'make test              Run the regular test suite' \
		'make test-integration  Run the real RabbitMQ integration suite' \
		'make worker-stop       Stop the queue worker' \
		'make worker-start      Start the queue worker'

up:
	$(SAIL) up -d

down:
	$(SAIL) down

restart: down up

build:
	$(SAIL) build

logs:
	$(SAIL) logs -f

shell:
	$(SAIL) shell

migrate:
	$(SAIL) artisan migrate

fresh:
	$(SAIL) artisan migrate:fresh --seed

seed:
	$(SAIL) artisan db:seed

test:
	$(SAIL) artisan test

test-integration:
	$(SAIL) stop queue.worker
	$(SAIL) php vendor/bin/phpunit --configuration phpunit.integration.xml
	$(SAIL) up -d queue.worker

worker-stop:
	$(SAIL) stop queue.worker

worker-start:
	$(SAIL) up -d queue.worker
