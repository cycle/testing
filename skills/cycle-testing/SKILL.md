---
name: cycle-testing
description: 'Tests of a Cycle ORM package (cycle/*) run on Testo through the cycle/testing harness. Use when writing, running or debugging such tests: anything under `tests/Unit`, `tests/Feature`, `tests/Acceptance` or `tests/Regression`, deciding where a new test goes, a per-driver failure (SQLite, MySQL, Postgres, SQLServer), `testo.php`, the test databases in docker, or porting a PHPUnit test.'
---

# Testing a Cycle package with cycle/testing

The harness turns one test tree into one Testo suite per database driver. Tests stay driver-agnostic; the
suite decides the driver, the connection, and the cleanup between tests. Load `testo-write-tests` for the
Testo API itself (`#[Test]`, `Assert`, `Expect`, lifecycle hooks); this skill covers what the harness adds.
When a name here disagrees with `vendor/cycle/testing/src`, the source wins.

## The test tree

```
tests/
  Unit/                    suite "Unit": no database server, no IO
  Feature/                 suites "Feature/<Driver>"
    Common/                joins every Feature/<Driver> suite
    SQLite/ MySQL/ Postgres/ SQLServer/   each joins only its own driver's suite
  Acceptance/              suites "Acceptance/<Driver>", same layout as Feature/
  Regression/              suites "Regression/<Driver>"
    Issue123/              one folder per reported issue; every folder joins every driver suite
  Fixtures/                entities, schemas, seeds shared across tests — no tests
```

`testo.php` declares this with `Cycle\Testing\Config` (`unit()`, `feature()`, `acceptance()`,
`regression()`); read it before adding a folder — a repo registers only the kinds it has, and some keep the
tree under a package folder such as `tests/ORM/`.

## Where a test goes

Walk the list; the first match wins.

1. It reproduces a user-reported issue → `Regression/Issue<N>/`, where `<N>` is the issue number.
2. It needs no database server and no files → `Unit/` (StubDriver, `FakePDO` and in-memory SQLite are fine).
3. It drives a whole scenario through the public API — configure, persist, query, check the outcome —
   the way a user's application would → `Acceptance/Common/`.
4. Otherwise it checks one feature against a real database → `Feature/Common/`. Most tests land here.

Inside `Feature/` and `Acceptance/`, a test meaningful on one driver only goes to that driver's folder, with
no attribute.

## Running

```
vendor/bin/testo --suite=Unit
vendor/bin/testo --suite=Feature/SQLite                        # needs no docker
composer db:up -- mysql                                       # shared compose, waits for healthchecks
vendor/bin/testo --suite=Feature/MySQL --filter=SelectTest::limit
vendor/bin/testo --suite=Feature/MySQL --suite=Regression/MySQL   # --suite repeats; unknown names are ignored
DB_DRIVERS=sqlite vendor/bin/testo                            # register only these driver suites
```

A driver whose server is down is checked once per suite and its tests are reported **skipped**. CI sets
`DB_REQUIRED=1`, which turns that into a failure. Ports, credentials and images come from
`vendor/cycle/testing/resources/docker/.env`; any exported `DB_*` variable overrides them. Add `--json` when
you parse the result. A test's SQL is in the `query.sql` channel: printed with a failed test's output, and for
every test in the HTML report (`--log-html`).

## Writing a database test

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

- Per-driver differences, finest first: `$this->driver->pick(default: '…', sqlserver: '…')`,
  `$this->driver->supports(Feature::Returning)`, `#[OnlyDriver(...)]` / `#[ExceptDriver(...)]` on a method
  or class, a separate class in the driver folder plus `#[ExceptDriver]` on the common one. An excluded test
  vanishes from that suite instead of showing as skipped. One class serves every driver: per-driver
  subclasses that only set a driver are the pattern this harness replaces.
- `{name}` in expected SQL becomes the driver's identifier quotes; whitespace is ignored.
- The base class owns `schema()`, `db()`, `makeTable()` and friends; name your hooks differently.

## Writing a regression test

An issue folder is self-contained: the test class, its entities and its schema live inside it, in the
namespace `…\Regression\Issue<N>`, so cases never share fixtures and one can be deleted whole.

Generate the folder, then edit it; the generated case already passes:

```
vendor/bin/cycle-testing new-case 123 --template=orm                       # tests/Regression/Issue123/
vendor/bin/cycle-testing new-case 123 --template=orm --dir=tests/ORM/Regression
vendor/bin/cycle-testing templates                                          # list the templates
```

| Template | Case |
|---|---|
| `database` | `DatabaseTestCase`, tables made with `makeTable()` (cycle/database) |
| `orm` | `OrmTestCase`, entities plus a hand-written `schema.php` |
| `orm-attributes` | entities mapped with cycle/annotated attributes, schema compiled by `SchemaCompiler` |
| `orm-behaviors` | `orm-attributes` with cycle/entity-behavior behaviours and the event-driven command generator |

The namespace comes from `autoload-dev` PSR-4 of `composer.json`; `--namespace=` overrides it, `--force`
writes into an existing folder. Prefer the repo's composer script when it has one (e.g. `composer
test:new-case -- 123`), which fixes the template and folder.

- Replace the sample entities, tables and tests with the issue's reproduction; keep what the issue needs.
- The test fails on the release that has the bug: assert the behaviour the issue asked for, and name the
  methods after it.
- A driver-only issue stays in its folder with `#[OnlyDriver(...)]` on the class.
- Testo loads every PHP file of the folder at discovery, `schema.php` included: keep files free of side
  effects and read them with `require` (as `loadSchema()` does), since a later `require_once` returns `true`.

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
- [references/mocks.md](references/mocks.md) — wrap drivers, `StubDriver`, `FakePDO`, Mockery, in-memory helpers,
  `SimpleContainer` (PSR-11).
- [references/migrate-from-phpunit.md](references/migrate-from-phpunit.md) — porting a PHPUnit suite with per-driver stubs.
