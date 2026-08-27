# Интеграционные тесты

Тесты идут через настоящий HTTP-стек API Platform против настоящей PostgreSQL —
никаких моков, никакого sqlite. Подход скопирован с `kh-agency-crm-api`.

## Запуск

```bash
docker compose up -d
make test-api        # создать БД + миграции + фикстуры + прогнать сьют
make reset-test-db   # пересоздать тестовую БД с нуля
```

Точечно:

```bash
docker compose exec php bin/phpunit tests/ApiTest/Sale/
docker compose exec php bin/phpunit --filter testSuccessPostSaleWritesAllLedgers
```

Всё выполняется внутри контейнера `php`: `DATABASE_URL` указывает на хост `db`,
который резолвится только внутри compose-сети.

## Как это устроено

- **База** — `warehouse-api_test`. Имя получается само: `.env.test` не переопределяет
  `DATABASE_URL`, а `when@test` в `config/packages/doctrine.yaml` добавляет суффикс `_test`.
- **Схема** — настоящие миграции Doctrine, не `schema:create`.
- **Фикстуры** (`src/DataFixtures/`) грузятся один раз перед прогоном. `StockFixtures` и
  `SaleFixtures` идут через боевые фабрики и сервисы (черновик → позиции → проведение),
  поэтому партии, FIFO и все четыре журнала выходят такими же, как при реальной работе.
- **Изоляция** — `dama/doctrine-test-bundle`: каждый тест выполняется в транзакции и
  откатывается. Работает благодаря `use_savepoints: true` в `config/packages/doctrine.yaml`
  (**не выключать** — бизнес-логика использует `wrapInTransaction` + пессимистичные блокировки).
  Проверка: прогнать сьют дважды подряд без `reset-test-db` — состояние БД не меняется.

⚠️ Откат работает **только внутри PHPUnit**. Ручные проверки в обход него — `php -r` с
  собственным бутстрапом ядра, `bin/console ... --env=test`, `psql` — пишут в тестовую БД
  по-настоящему и портят фикстурное состояние. После такого обязателен `make reset-test-db`,
  иначе посыплются тесты, которые считают `totalItems` по коллекции.

## Соглашения

- Сущности ищутся по бизнес-ключу (`findIriBy(Client::class, ['name' => 'Test Client 1'])`),
  **никогда по id**: фикстуры чистятся через DELETE, а sequence'ы Postgres не сбрасываются.
- Сайд-эффекты проверяются **через API**, а не через `EntityManager`.
- Роли: иерархия — `ROLE_ADMIN: [ROLE_SALES]`, ниже `ROLE_SALES` ничего нет. Поэтому
  негативный кейс для admin-only эндпоинта — sales-клиент (**403**), а для sales-эндпоинта —
  анонимный клиент (**401**).
- Кастомные POST-операции-отчёты (`/clients/summary`, `/products/summary`,
  `/profits/summary`, `/expenses/daily`, ...) отвечают **201**, а не 200.
- `assertResponseStatusCodeSame()` смотрит на **последний созданный** клиент, а не на тот,
  у которого вызвали `request()`. Отсюда два следствия:
  - приватные хелперы должны **принимать** `Client` параметром, а не звать
    `createAdminClientWithCredentials()` внутри себя (см. `Category/DeleteApiTest`,
    `Supplier/DeleteApiTest`);
  - если в тесте всё же появляется второй клиент, статус проверяйте на возвращённом объекте
    ответа — `$response->getStatusCode()`, см. `User/DeleteApiTest`.

## Удаление справочников

`Category`, `Client` и `Supplier` защищены от удаления, если на них кто-то ссылается:
FK в схеме стоят как `NO ACTION`, поэтому без guard'а получался бы 500 с текстом SQL наружу.
Проверки живут в `CategoryDeleteService` / `ClientDeleteService` / `SupplierDeleteService`,
ответ — **422** с перечислением того, что мешает:

```
Client "Test Client 2" still has 1 sale(s), 1 debt record(s) and cannot be deleted.
```

`Product` и `User` удаляются мягко (`deletedAt`), их это не касается.

## Известные отклонения

- В `composer.json` в `config.policy.advisories.ignore-id` внесён `PKSA-5r1g-c7b7-y1zg`
  (CVE-2026-45071, low): XXE в `DomCrawler::addXmlContent()` при `validateOnParse = true`.
  Исправлено в `symfony/dom-crawler` 7.4.12+, но `extra.symfony.require` пиннит все
  symfony-пакеты к `7.3.*`, поэтому пропатченная версия недостижима без апгрейда фреймворка.
  Пакет только в `require-dev` (тянется `symfony/browser-kit` ради тестового клиента),
  в прод не уезжает, и ни тестовый клиент, ни наши тесты `addXmlContent()` не вызывают.
  **Удалить эту запись при апгрейде на symfony 7.4.**
- `phpunit.dist.xml`: `ignoreDirectDeprecations="true"`. В проекте есть unfixable
  deprecation'ы из vendor (API Platform `ApiTestCase`, symfony/property-info,
  security-http), которые при `failOnDeprecation` роняли бы сборку.
  `ignoreSelfDeprecations` намеренно оставлен выключенным — deprecation'ы в нашем
  собственном `src/` по-прежнему валят прогон.
