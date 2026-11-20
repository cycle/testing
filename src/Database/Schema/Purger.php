<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Schema;

use Cycle\Database\Database;
use Cycle\Database\Driver\DriverInterface;
use Cycle\Database\Driver\HandlerInterface;
use Cycle\Database\Driver\MySQL\MySQLDriver;
use Cycle\Database\Driver\Postgres\PostgresDriver;
use Cycle\Database\Driver\SQLite\SQLiteDriver;
use Cycle\Database\Driver\SQLServer\SQLServerDriver;

/**
 * Empties a database by dropping its tables.
 */
final class Purger
{
    /**
     * Drop every table of the database (tables outside its prefix stay).
     *
     * All foreign keys go first, so tables can then be dropped in any order.
     */
    public static function dropAll(Database $database): void
    {
        foreach ($database->getTables() as $table) {
            $schema = $table->getSchema();
            foreach ($schema->getForeignKeys() as $foreign) {
                $schema->dropForeignKey($foreign->getColumns());
            }

            $schema->save(HandlerInterface::DROP_FOREIGN_KEYS);
        }

        foreach ($database->getTables() as $table) {
            $schema = $table->getSchema();
            $schema->declareDropped();
            $schema->save();
        }
    }

    /**
     * Drop every table the connection lists, whatever its prefix.
     *
     * One query when there is nothing to drop, a few when there is: no table is introspected, unlike
     * {@see self::dropAll()}, which reads every column, index and foreign key first.
     */
    public static function dropTables(DriverInterface $driver): void
    {
        # The schema handler resets this cache when it drops a table; the raw DROP below does not.
        $driver instanceof PostgresDriver and $driver->resetPrimaryKeys();
        $tables = $driver->getSchemaHandler()->getTableNames();
        if ($tables === []) {
            return;
        }

        $list = \implode(', ', \array_map(static fn(string $t): string => self::quote($driver, $t), $tables));
        match (true) {
            $driver instanceof MySQLDriver => self::withoutChecks(
                $driver,
                'SET FOREIGN_KEY_CHECKS = 0',
                "DROP TABLE {$list}",
                'SET FOREIGN_KEY_CHECKS = 1',
            ),
            $driver instanceof PostgresDriver => $driver->execute("DROP TABLE {$list} CASCADE"),
            $driver instanceof SQLServerDriver => self::dropSqlServer($driver, $list),
            $driver instanceof SQLiteDriver => self::withoutChecks(
                $driver,
                'PRAGMA foreign_keys = OFF',
                ...\array_map(static fn(string $t): string => 'DROP TABLE ' . self::quote($driver, $t), $tables),
                ...['PRAGMA foreign_keys = ON'],
            ),
            default => self::dropAll(new Database('purge', '', $driver)),
        };
    }

    /**
     * The first and last statements switch the checks off and back on, even when a drop fails.
     */
    private static function withoutChecks(DriverInterface $driver, string $off, string ...$statements): void
    {
        $on = \array_pop($statements);
        $driver->execute($off);
        try {
            foreach ($statements as $statement) {
                $driver->execute($statement);
            }
        } finally {
            $driver->execute($on);
        }
    }

    /**
     * SQL Server has no way to skip foreign key checks on DROP: the keys go first.
     */
    private static function dropSqlServer(DriverInterface $driver, string $list): void
    {
        $keys = $driver->query(
            'SELECT OBJECT_SCHEMA_NAME(parent_object_id), OBJECT_NAME(parent_object_id), name FROM sys.foreign_keys',
        )->fetchAll(\PDO::FETCH_NUM);
        foreach ($keys as [$schema, $table, $key]) {
            $driver->execute(\sprintf(
                'ALTER TABLE %s DROP CONSTRAINT %s',
                self::quote($driver, "{$schema}.{$table}"),
                self::quote($driver, $key),
            ));
        }

        $driver->execute("DROP TABLE {$list}");
    }

    /**
     * `schema.table` quoted part by part: the driver's own quoting treats the dot as part of the name.
     */
    private static function quote(DriverInterface $driver, string $name): string
    {
        $compiler = $driver->getQueryCompiler();

        return \implode('.', \array_map($compiler->quoteIdentifier(...), \explode('.', $name)));
    }
}
