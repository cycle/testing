<?php

declare(strict_types=1);

namespace Cycle\Testing\Database;

/**
 * How the state a test leaves in the database is undone.
 *
 * Chosen by the nearest of: `#[Isolate]` on the method, on the class (or a parent or trait), then the
 * `DatabasePlugin` default.
 */
enum Isolation
{
    /**
     * After the test, drop every foreign key and table in every database of the layout. An in-memory
     * SQLite connection is closed instead, which leaves an empty database. Safe for DDL, which MySQL and
     * SQLServer commit implicitly.
     */
    case DropTables;

    /**
     * Begin a transaction on every database before the test and roll it back after. The schema and seed
     * come from the plugin's `prepare` callback, which runs once per suite.
     */
    case Transaction;

    /**
     * Nothing: the test leaves no state behind, or asserts that no transaction is open.
     */
    case None;
}
