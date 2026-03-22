.PHONY: help install run lint check

help:
	@echo "Targets:"
	@echo "  install  Install PHP dependencies"
	@echo "  run      Start local server on 127.0.0.1:8000"
	@echo "  lint     Lint PHP, YAML and container"
	@echo "  check    Alias of lint"

install:
	composer install

run:
	php -S 127.0.0.1:8000 -t public

lint:
	php -l src/Security/CasAuthenticator.php
	php -l src/Security/CasLogoutListener.php
	php -l src/Security/User.php
	php -l src/Security/UserProvider.php
	php -l src/Controller/HomeController.php
	php bin/console lint:yaml config/packages/security.yaml config/services.yaml
	php bin/console lint:container

check: lint
