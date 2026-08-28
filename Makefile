DC = docker compose
DC_PHP= $(DC) exec php

.PHONY: test test-api reset-test-db create-test-db cc

#lint:
#	$(DC_PHP) composer lint:phpstan
#	$(DC_PHP) composer lint:fixer:check
#
#lint-fix:
#	$(DC_PHP) composer lint:fixer

test:
	$(DC_PHP) bin/phpunit

test-api:
	$(DC_PHP) bin/console doctrine:database:create --if-not-exists --env=test
	$(DC_PHP) bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=test
	$(DC_PHP) bin/console doctrine:fixtures:load --no-interaction --env=test
	$(DC_PHP) composer test:api
	$(DC_PHP) composer test:integration

reset-test-db:
	$(DC_PHP) bin/console doctrine:database:drop -f --env=test
	$(DC_PHP) bin/console doctrine:database:create --env=test
	$(DC_PHP) bin/console doctrine:migrations:migrate --allow-no-migration --no-interaction --env=test
	$(DC_PHP) bin/console doctrine:fixtures:load --no-interaction --env=test

create-test-db:
	$(DC_PHP) bin/console doctrine:database:create --env=test

cc:
	$(DC_PHP) bin/console cache:clear
