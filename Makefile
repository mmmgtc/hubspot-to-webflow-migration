.PHONY: up
up:
	./vendor/bin/sail up --build

.PHONY: in
in:
	docker exec -it hubspot-to-webflow-migration-laravel.test-1 /bin/bash

.PHONY: go
go:
	./vendor/bin/sail artisan go

