<div align="center">
    <a href="https://cycle-orm.dev" target="_blank">
        <picture>
            <source media="(prefers-color-scheme: dark)" srcset="https://github.com/cycle/.github/blob/main/logo/words-vector-dark.svg?raw=true">
            <img width="50%" align="center" src="https://github.com/cycle/.github/blob/main/logo/words-vector-light.svg?raw=true" alt="CycleORM Logo">
        </picture>
    </a>
</div>

<p align="center">Shared Testo test harness for the Cycle ORM packages</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://cycle-orm.dev/docs)
[![Sponsor](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsor&logo=githubsponsors&logoColor=white&color=%23EA4AAA)](https://github.com/sponsors/cycle)
[![Discord](https://img.shields.io/discord/538114875570913290?style=for-the-badge&logo=discord&labelColor=7289d9&logoColor=white&color=39456d)](https://discord.gg/spiralphp)

[![Psalm Level](https://shepherd.dev/github/cycle/testing/level.svg)](https://shepherd.dev/github/cycle/testing)
[![Type Coverage](https://shepherd.dev/github/cycle/testing/coverage.svg)](https://shepherd.dev/github/cycle/testing)
[![Codecov](https://img.shields.io/codecov/c/github/cycle/testing/1.x?style=flat-square&logo=codecov)](https://app.codecov.io/gh/cycle/testing/tree/1.x)

</div>

<br />

A [Testo](https://github.com/php-testo/testo) test harness shared by the Cycle ORM packages. One test tree runs
as one suite per database driver; the harness owns the connections, the cleanup between tests, the test
databases in docker, and the helpers every package used to copy around.

## Get Started

### Installation

The package requires PHP 8.2+, `testo/testo` and `cycle/database`.

```bash
composer require --dev cycle/testing
```

[![PHP](https://img.shields.io/packagist/php-v/cycle/testing.svg?style=flat-square&logo=php)](https://packagist.org/packages/cycle/testing)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/cycle/testing.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/cycle/testing)
[![License](https://img.shields.io/packagist/l/cycle/testing.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/cycle/testing.svg?style=flat-square)](https://packagist.org/packages/cycle/testing/stats)

The ORM helpers additionally need `cycle/orm`; `SchemaCompiler` needs `cycle/annotated` and
`cycle/schema-builder`. With `testo/bridge-mockery` installed, Mockery expectations are verified in every suite.

### Quick start

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
