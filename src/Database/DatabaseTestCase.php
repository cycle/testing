<?php

declare(strict_types=1);

namespace Cycle\Testing\Database;

use Cycle\Database\Database;
use Cycle\Database\DatabaseManager;
use Cycle\Database\Schema\AbstractTable;
use Cycle\Testing\Database\Attribute\WrapDriver;
use Cycle\Testing\Database\Mock\MySQLWrapDriver;
use Cycle\Testing\Database\Mock\PostgresWrapDriver;
use Cycle\Testing\Database\Mock\SQLiteWrapDriver;
use Cycle\Testing\Database\Mock\SQLServerWrapDriver;
use Cycle\Testing\Database\Mock\WrapDriverInterface;
use Cycle\Testing\Database\Schema\TableTrait;

/**
 * Base for tests that run in a per-driver suite (`Feature/<Driver>` and the like).
 *
 * The properties are set by {@see self::attachDatabase()} before the first hook of the case runs.
 */
abstract class DatabaseTestCase implements DatabaseAware
{
    use TableTrait;

    protected Driver $driver;
    protected DatabaseManager $dbal;
    protected Database $database;
    protected DatabaseContext $context;

    public function attachDatabase(DatabaseContext $context): void
    {
        $this->context = $context;
        $this->driver = $context->driver;
        $this->dbal = $context->dbal();
        $this->database = $context->database();
    }

    /**
     * A database on the suite's connection, or on a driver variant when options are given.
     *
     * @param array<non-empty-string, mixed> $driverConfig See {@see ConnectionPool::driver()}.
     * @param array<non-empty-string, mixed> $connectionConfig
     */
    protected function db(
        string $name = 'default',
        string $prefix = '',
        array $driverConfig = [],
        array $connectionConfig = [],
    ): Database {
        return new Database($name, $prefix, $this->context->pool->driver(
            $driverConfig,
            $connectionConfig,
            $this->context->driverClass(),
        ));
    }

    /**
     * The schema of a table in `default` (or a prefixed or reconfigured variant of it).
     *
     * @param array<non-empty-string, mixed> $driverConfig
     */
    protected function schema(string $table, string $prefix = '', array $driverConfig = []): AbstractTable
    {
        return $this->db('default', $prefix, $driverConfig)->table($table)->getSchema();
    }

    /**
     * The table's schema as the database reports it now.
     */
    protected function fetchSchema(AbstractTable $table): AbstractTable
    {
        return $this->schema($table->getFullName());
    }

    protected function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * The instrumented driver of `default`; the case needs `#[WrapDriver]`.
     *
     * @return SQLiteWrapDriver|MySQLWrapDriver|PostgresWrapDriver|SQLServerWrapDriver
     */
    protected function wrapDriver(): WrapDriverInterface
    {
        $driver = $this->context->connection();
        $driver instanceof WrapDriverInterface or throw new \LogicException(
            \sprintf('Add #[%s] to %s to use the wrap driver.', WrapDriver::class, static::class),
        );

        /** @var SQLiteWrapDriver|MySQLWrapDriver|PostgresWrapDriver|SQLServerWrapDriver */
        return $driver;
    }

    protected function serverVersion(): string
    {
        return $this->context->serverVersion();
    }
}
