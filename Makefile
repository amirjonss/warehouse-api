DC = docker compose
DC_PHP= $(DC) exec php

.PHONY: lint lint-fix test test-api reset-test-db

#lint:
#	$(DC_PHP) composer lint:phpstan
#	$(DC_PHP) composer lint:fixer:check
#
#lint-fix:
#	$(DC_PHP) composer lint:fixer
#
#test:
#	$(DC_PHP) bin/phpunit
#
#test-api:
#	bin/console doctrine:database:create --if-not-exists --env=test
#	bin/console doctrine:migrations:migrate --no-interaction --env=test
#	bin/console doctrine:fixtures:load --no-interaction --env=test
#	bin/console ask:install
#	composer test:api
#
#reset-test-db:
#	$(DC_PHP) bin/console doctrine:database:drop -f --env=test
#	$(DC_PHP) bin/console doctrine:database:create --env=test
#	$(DC_PHP) bin/console doctrine:migrations:migrate --allow-no-migration --no-interaction --env=test
#	$(DC_PHP) bin/console doctrine:fixtures:load --no-interaction --env=test
#
#create-test-db:
#	$(DC_PHP) bin/console doctrine:database:create --env=test
cc:
	$(DC_PHP) bin/console cache:clear
