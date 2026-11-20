# cycle/testing

Shared [Testo](https://github.com/php-testo/testo) test harness for the Cycle ORM packages. One test tree runs
as one suite per database driver; the harness owns the connections, the cleanup between tests, the test
databases in docker, and the helpers every package used to copy around.

```
composer require --dev cycle/testing
```

Requires PHP 8.2+, `testo/testo` and `cycle/database`. The ORM helpers additionally need `cycle/orm`;
`SchemaCompiler` needs `cycle/annotated` and `cycle/schema-builder`. With `testo/bridge-mockery` installed,
Mockery expectations are verified in every suite.

## Quick start

`testo.php`:

```php
<?php

use Cycle\Testing\Config;
use Cycle\Testing\Database\Layout;
use Cycle\Testing\ORM\OrmPlugin;

return Config::forPackage('cycle/orm', root: __DIR__)
    ->unit('tests/Unit')
    ->feature('tests/Feature', layout: Layout::prefixed(['secondary' => 'secondary_']), plugins: [new OrmPlugin()])
    ->regression('tests/Regression', plugins: [new OrmPlugin()])
    ->build();
```

Test tree — four kinds of tests, each a suite or one suite per driver:

```
tests/
  Unit/                    suite Unit: no database, no IO
  Feature/                 Feature/<Driver>: one feature against a real database (most tests)
    Common/                  joins every driver's suite
    SQLite/ MySQL/ Postgres/ SQLServer/   joins only its own driver's suite
  Acceptance/              Acceptance/<Driver>: end-to-end scenarios through the public API; same layout
  Regression/              Regression/<Driver>: reproductions of reported issues
    Issue123/                test, entities and schema of one issue; runs on every driver
```

`feature()`, `acceptance()` and `regression()` take the same options (drivers, isolation, layout, `prepare`,
plugins, folders); `perDriver($name, $path, …)` builds any other kind.

A test:

```php
#[Test]
final class SelectTest extends DatabaseTestCase
{
    #[BeforeTest]
    public function createTables(): void
    {
        $this->makeTable('users', ['id' => 'primary', 'email' => 'string(255), nullable']);
    }

    public function selectsAll(): void
    {
        SqlAssert::same($this->database->select()->from('users'), 'SELECT * FROM {users}');
    }

    #[ExceptDriver(Driver::SQLite)]
    public function locksForUpdate(): void { /* … */ }
}
```

Testo creates **one instance per test class**, not per test: keep per-test state in `#[BeforeTest]` and
local variables, never in properties carried between tests.

## Running

```
composer db:up                                            # all servers; or: composer db:up -- mysql
vendor/bin/testo                                          # Unit + every driver suite; down servers are skipped
vendor/bin/testo --suite=Feature/Postgres --filter=SelectTest
DB_DRIVERS=sqlite vendor/bin/testo                        # register only the SQLite suites
DB_REQUIRED=1 vendor/bin/testo --suite=Feature/MySQL --suite=Regression/MySQL   # CI: an unreachable server fails
composer db:down
```

Consumer `composer.json` scripts:

```json
"db:up": "docker compose -f vendor/cycle/testing/resources/docker/compose.yaml up -d --wait",
"db:down": "docker compose -f vendor/cycle/testing/resources/docker/compose.yaml down -v"
```

## Configuration

[`resources/docker/.env`](resources/docker/.env) is the single source of truth: docker compose interpolates
it and the harness reads connection defaults from it. Exported variables override it.

| Variable | Default |
|---|---|
| `DB_DRIVERS` | `sqlite,mysql,postgres,sqlserver` |
| `DB_REQUIRED` | `0` |
| `DB_DEBUG` | `0` (`1` echoes every query) |
| `DB_SQLITE_FILE` | empty: in-memory |
| `DB_MYSQL_*` | `127.0.0.1:13306`, database `spiral`, `root`, image `mysql:8.0` |
| `DB_POSTGRES_*` | `127.0.0.1:15432`, database `spiral`, `postgres`, image `postgres:15` |
| `DB_SQLSERVER_*` | `127.0.0.1:11433`, database `tempdb`, `SA`, image `mssql/server:2022-latest` |

The password is `YourStrong!Passw0rd` for every server.

## What is inside

- **Suites:** `Config`, `Database\DatabasePlugin` (one per driver suite), `ORM\OrmPlugin`.
- **Isolation:** `DropTables` (default), `Transaction`, `None`; `#[Isolate]` per class or method.
- **Driver differences:** `#[OnlyDriver]`, `#[ExceptDriver]`, `Driver::pick()`, `Driver::supports(Feature)`.
- **Base classes:** `Database\DatabaseTestCase` (`makeTable()`, `db()`, `schema()`), `ORM\OrmTestCase`
  (fresh ORM per test, `withSchema()`, `save()`, heap check).
- **Assertions:** `SqlAssert`, `QueryAssert`, `SchemaAssert`.
- **Mocks:** wrap drivers (`#[WrapDriver]`), `StubDriver` + `FakePDO`, in-memory `Memory` helpers.

The full reference is the agent skill [`skills/cycle-testing`](skills/cycle-testing/SKILL.md), which
[`llm/skills`](https://github.com/roxblnfk/skills) installs into consumer projects together with Testo's own
skills.

## License

MIT, see [LICENSE.md](LICENSE.md).
