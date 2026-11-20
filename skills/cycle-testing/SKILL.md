---
name: cycle-testing
description: 'Tests of a Cycle ORM package (cycle/*) run on Testo through the cycle/testing harness. Use when writing, running or debugging such tests: anything under `tests/Unit` or `tests/Acceptance`, a per-driver failure (SQLite, MySQL, Postgres, SQLServer), `testo.php`, the test databases in docker, or porting a PHPUnit test.'
---

# Testing a Cycle package with cycle/testing

The harness turns one test tree into one Testo suite per database driver. Tests stay driver-agnostic; the
suite decides the driver, the connection, and the cleanup between tests. Load `testo-write-tests` for the
Testo API itself (`#[Test]`, `Assert`, `Expect`, lifecycle hooks); this skill covers what the harness adds.
When a name here disagrees with `vendor/cycle/testing/src`, the source wins.

## The test tree

```
tests/
  Unit/                    suite "Unit": no database server
  Acceptance/
    Common/                joins every Acceptance/<Driver> suite
    SQLite/ MySQL/ Postgres/ SQLServer/   each joins only its own driver's suite
  Fixtures/                entities, schemas, seeds — no tests
```

`testo.php` declares this with `Cycle\Testing\Config`; read it before adding a folder. Suites are
`Unit`, `Acceptance/SQLite`, `Acceptance/MySQL`, `Acceptance/Postgres`, `Acceptance/SQLServer`.

## Running

```
vendor/bin/testo --suite=Unit
vendor/bin/testo --suite=Acceptance/SQLite                     # needs no docker
composer db:up -- mysql                                       # shared compose, waits for healthchecks
vendor/bin/testo --suite=Acceptance/MySQL --filter=SelectTest::limit
DB_DRIVERS=sqlite vendor/bin/testo                            # register only these driver suites
```

A driver whose server is down is checked once per suite and its tests are reported **skipped**. CI sets
`DB_REQUIRED=1`, which turns that into a failure. Ports, credentials and images come from
`vendor/cycle/testing/resources/docker/.env`; any exported `DB_*` variable overrides them. Add `--json` when
you parse the result.

## Writing an acceptance test

```php
#[Test]
final class SelectTest extends DatabaseTestCase          // ORM tests: extends OrmTestCase
{
    #[BeforeTest]
    public function createTables(): void
    {
        $this->makeTable('users', ['id' => 'primary', 'email' => 'string(255), nullable']);
    }

    public function selectsById(): void
    {
        SqlAssert::same($this->database->select()->from('users'), 'SELECT * FROM {users}');
    }

    #[ExceptDriver(Driver::SQLite)]
    public function locksForUpdate(): void { /* … */ }
}
```

- Put it in `Acceptance/Common` unless it is meaningful on one driver only; then put it in that driver's
  folder, with no attribute.
- Per-driver differences, finest first: `$this->driver->pick(default: '…', sqlserver: '…')`,
  `$this->driver->supports(Feature::Returning)`, `#[OnlyDriver(...)]` / `#[ExceptDriver(...)]` on a method
  or class, a separate class in the driver folder plus `#[ExceptDriver]` on the common one. An excluded test
  vanishes from that suite instead of showing as skipped. One class serves every driver: per-driver
  subclasses that only set a driver are the pattern this harness replaces.
- `{name}` in expected SQL becomes the driver's identifier quotes; whitespace is ignored.
- The base class owns `schema()`, `db()`, `makeTable()` and friends; name your hooks differently.

## One instance per class

Testo creates the test object **once per class**, not per test. Properties survive from one test to the
next. Set per-test state in `#[BeforeTest]`, keep entities and queries in local variables, and treat
properties set in `#[BeforeClass]` as immutable. The harness resets what it owns before every test: the
query log, the wrap-driver knobs, `$this->orm`.

## Isolation

The suite default is `DropTables`: after each test every table is dropped (in-memory SQLite is simply
reconnected). Create the tables a test needs inside the test or its `#[BeforeTest]`. Switch per class or
method with `#[Isolate(Isolation::Transaction)]` or `#[Isolate(Isolation::None)]`; details and the
`prepare` hook are in [references/isolation.md](references/isolation.md).

## References

- [references/api.md](references/api.md) — every class a test touches: base classes, attributes, assertions,
  `Config`, env vars.
- [references/isolation.md](references/isolation.md) — modes, ordering against hooks, seeding once per suite.
- [references/orm.md](references/orm.md) — `OrmTestCase`, `OrmPlugin`, the heap check, compiled schemas.
- [references/mocks.md](references/mocks.md) — wrap drivers, `StubDriver`, `FakePDO`, Mockery, in-memory helpers.
- [references/migrate-from-phpunit.md](references/migrate-from-phpunit.md) — porting a PHPUnit suite with per-driver stubs.
