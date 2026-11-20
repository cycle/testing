# cycle/testing API

Namespaces: `Cycle\Testing\Database\*` needs only `cycle/database`; `Cycle\Testing\ORM\*` also needs
`cycle/orm`; `ORM\Schema\SchemaCompiler` also needs `cycle/annotated` and `cycle/schema-builder`.

## `testo.php`

```php
use Cycle\Testing\Config;
use Cycle\Testing\Database\{Isolation, Layout};
use Cycle\Testing\ORM\OrmPlugin;

return Config::forPackage('cycle/orm', root: __DIR__)       // src: ['src'], clover → runtime/clover.xml
    ->unit('tests/Unit')
    ->feature(
        'tests/Feature',                                     // Common/ + <Driver>/ beneath
        isolation: Isolation::DropTables,
        layout: Layout::prefixed(['secondary' => 'secondary_']),
        plugins: [new OrmPlugin()],
    )
    ->acceptance('tests/Acceptance', plugins: [new OrmPlugin()])
    ->regression('tests/Regression', plugins: [new OrmPlugin()])   // Issue<N>/ beneath
    ->build();
```

| Method | Suites | Common part |
|---|---|---|
| `feature($path, …)` | `Feature/<Driver>` | `<path>/Common` |
| `acceptance($path, …)` | `Acceptance/<Driver>` | `<path>/Common` |
| `regression($path, …)` | `Regression/<Driver>` | `<path>` itself (`common: null`) |
| `perDriver($name, $path, …)` | `<name>/<Driver>` | `<path>/Common` |

All four take the same options: `drivers` (default `DB_DRIVERS`), `isolation`, `layout`, `prepare`,
`plugins`, `common` (a folder name, or `null` for the path itself), `folders` (driver value => folder name)
and `exclude`. A driver folder (`<path>/MySQL`) joins only its own suite, also under `common: null`. Missing
folders are left out; a driver with no folder gets no suite. Each kind's options are independent: repeat
`layout`, `plugins` and the rest on every call that needs them.

`suite(SuiteConfig)` adds a hand-made suite; `plugins(...)` adds app-level plugins. Every suite gets
`MockeryPlugin` when `testo/bridge-mockery` and Mockery are installed.

Hand-written equivalent of one driver suite:
`new SuiteConfig('Feature/MySQL', [...dirs], [new DatabasePlugin(Driver::MySQL, Isolation::DropTables, $layout, $prepare)])`.

## Layouts

| Layout | Databases |
|---|---|
| `Layout::single()` (default) | `default` |
| `Layout::prefixed(['secondary' => 'secondary_'])` | extra databases on the suite connection, by table prefix; a `default` key sets its prefix |
| `Layout::withSqlite(['secondary'])` | extra databases, each on its own in-memory SQLite connection |

## `DatabaseTestCase` (implement `DatabaseAware` instead to skip the base class)

| Member | Meaning |
|---|---|
| `$driver` | `Driver` enum of the suite |
| `$dbal`, `$database` | `DatabaseManager` of the layout, its `default` database |
| `$context` | `DatabaseContext`: `pool`, `queryLog()`, `connection()`, `serverVersion()` |
| `db(name, prefix, driverConfig, connectionConfig)` | a `Database` on the suite connection or on a memoised variant: `db(driverConfig: ['options' => ['withDatetimeMicroseconds' => true]])`, Postgres `driverConfig: ['schema' => ['public', 'other']]` |
| `schema($table)`, `fetchSchema($table)` | declared schema; schema as the database reports it |
| `makeTable($t, ['id' => 'primary', 'x' => 'string(32), nullable'], fk:, pk:, defaults:)`, `makeFK`, `makeCompositeFK`, `makeIndex` | DDL from short definitions (`Schema\TableBuilder`) |
| `wrapDriver()` | the instrumented driver; needs `#[WrapDriver]` |
| `serverVersion()` | PDO server version |

A variant of an in-memory SQLite driver is a separate, empty database.

## `Driver` enum

`SQLite`, `MySQL`, `Postgres`, `SQLServer` (values `sqlite`, `mysql`, `postgres`, `sqlserver`).
`pick(default: …, <value>: …)`, `supports(Feature)`, `quote($id)`, `quotes()`, `config(?Env)`,
`suiteName($kind)`, `wrapDriverClass()`. The `Database\Feature` enum (unrelated to the suite kind):
`Returning`, `FullJoin`, `ForUpdate`, `Schemas`, `TransactionalDdl`.

## Attributes (`Cycle\Testing\Database\Attribute\*`)

| Attribute | Target | Effect |
|---|---|---|
| `#[OnlyDriver(Driver::Postgres, ...)]` | class, method | test exists only in those driver suites |
| `#[ExceptDriver(Driver::MySQL, ...)]` | class, method | test is left out of those suites |
| `#[Isolate(Isolation::None)]` | class, method | overrides the suite isolation |
| `#[WrapDriver]` | class | `default` uses the driver's wrap class |
| `#[SkipHeapCheck]` (`ORM\Attribute`) | class, method | no heap check after the test |

The nearest level wins: method attributes over class attributes, a class over its parents.

## Assertions (argument order: actual, expected)

- `SqlAssert::same($queryOrSql, 'SELECT * FROM {users}', ?driver)`, `sameParameters($query, [1])`,
  `sameWithParameters($query, $sql, $params)`. Without a driver and outside a suite, quotes are stripped.
- `QueryAssert::reads(1, fn)`, `writes(2, fn)`, `count($reads, $writes, fn)`, `none(fn)` — return the
  callback's result. Writes are `INSERT`/`UPDATE`/`DELETE`; Postgres catalog queries are not counted.
- `SchemaAssert::sameAsInDb($schema)` — declared table equals the database's; `sameColumn`, `sameIndex`,
  `sameForeignKey`.

## Environment

| Variable | Default | Effect |
|---|---|---|
| `DB_DRIVERS` | `sqlite,mysql,postgres,sqlserver` | driver suites `Config` registers (`DB` is a deprecated alias) |
| `DB_REQUIRED` | `0` | `1`: unreachable database fails instead of skipping |
| `DB_DEBUG` | `0` | `1`: echo queries to stdout (they are always attached to the test report) |
| `DB_SQLITE_FILE` | empty (in-memory) | SQLite file for debugging |
| `DB_<DRIVER>_{HOST,PORT,DATABASE,USER,PASSWORD,IMAGE}` | see `resources/docker/.env` | connection and docker image |
