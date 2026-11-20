# Isolation

| Mode | Around each test | Fits |
|---|---|---|
| `Isolation::DropTables` (default) | after: drop every FK and table on every connection of the layout; in-memory SQLite is reconnected instead | tests that create their tables (DDL). MySQL and SQLServer commit DDL implicitly, so a transaction cannot undo it. |
| `Isolation::Transaction` | before: begin on every connection; after: roll back | a fixed schema created once by `prepare` |
| `Isolation::None` | nothing | tests that assert no transaction is open, or leave no state |

Pick the suite default in `testo.php` (`feature(isolation: …)`, likewise for every per-driver kind),
override with `#[Isolate(...)]` on a class or method.

## What always happens

- Suite start: one connection probe per database; leftover tables from a crashed run are dropped; then
  `prepare` runs once with a `DatabaseContext`.
- Before each test: a transaction left open by the previous test is rolled back.
- After each test: the query log and the wrap-driver knobs are reset.
- Suite end: every pooled connection is closed.

## Order against lifecycle hooks

`#[BeforeTest]`, the test, and `#[AfterTest]` run **inside** the isolation step: tables made in
`#[BeforeTest]` are dropped after the test, rows inserted there are rolled back. The ORM heap check runs
after `#[AfterTest]` and before the rollback or drop.

`#[BeforeClass]` / `#[AfterClass]` run outside any test's isolation. A table created in `#[BeforeClass]`
survives `Transaction` isolation for the whole class; drop it in `#[AfterClass]`
(`Schema\Purger::dropAll($this->database)`). Under `DropTables` it is gone after the first test, so create
per-test tables in `#[BeforeTest]`.

## Seeding once per suite

```php
->feature('tests/Feature',
    isolation: Isolation::Transaction,
    prepare: static function (DatabaseContext $ctx): void {
        SchemaCompiler::compile($ctx->dbal(), [User::class, Post::class]);   // creates the tables
        $ctx->database()->table('user')->insertMultiple(['name'], [['Antony'], ['John']]);
    },
)
```

A failing `prepare` marks every test of the suite skipped (or failed with `DB_REQUIRED=1`) with its message.
