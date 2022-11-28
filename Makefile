.PHONY: up
up:
	./vendor/bin/sail up

.PHONY: in
in:
	docker exec -it laravel.test /bin/bash
