<?php

declare(strict_types=1);

namespace Cycle\Testing\Database;

/**
 * A capability that differs between drivers; gate a test on it with {@see Driver::supports()}.
 */
enum Feature
{
    /**
     * `INSERT … RETURNING` (SQLServer: `OUTPUT`).
     */
    case Returning;
    case FullJoin;

    /**
     * `SELECT … FOR UPDATE` (SQLServer: `WITH (UPDLOCK)`).
     */
    case ForUpdate;

    /**
     * Several schemas in one database (`schema.table`).
     */
    case Schemas;

    /**
     * DDL inside a transaction is rolled back with it; MySQL and SQLServer commit DDL implicitly.
     */
    case TransactionalDdl;

    /**
     * @return non-empty-list<Driver>
     */
    public function drivers(): array
    {
        return match ($this) {
            self::Returning => [Driver::SQLite, Driver::Postgres, Driver::SQLServer],
            self::FullJoin, self::Schemas => [Driver::Postgres, Driver::SQLServer],
            self::ForUpdate => [Driver::MySQL, Driver::Postgres, Driver::SQLServer],
            self::TransactionalDdl => [Driver::SQLite, Driver::Postgres],
        };
    }
}
