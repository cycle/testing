<?php

declare(strict_types=1);

namespace Cycle\Testing\Database;

use Cycle\Database\Database;
use Cycle\Database\DatabaseManager;
use Cycle\Database\Driver\DriverInterface;
use Cycle\Testing\Database\Mock\WrapDriverTrait;

/**
 * The database services of one test case, handed to {@see DatabaseAware::attachDatabase()}.
 */
final class DatabaseContext
{
    public function __construct(
        public readonly Driver $driver,
        public readonly ConnectionPool $pool,
        private readonly bool $wrap = false,
    ) {}

    public function dbal(): DatabaseManager
    {
        return $this->pool->manager($this->wrap);
    }

    public function database(?string $name = null): Database
    {
        $database = $this->dbal()->database($name);
        \assert($database instanceof Database);

        return $database;
    }

    /**
     * @return class-string<DriverInterface>|null The driver class replacing the default one, if any.
     */
    public function driverClass(): ?string
    {
        return $this->wrap ? $this->driver->wrapDriverClass() : null;
    }

    public function queryLog(): QueryLog
    {
        return $this->pool->queryLog;
    }

    /**
     * The driver of `default`; under `#[WrapDriver]` it is the instrumented one (see {@see WrapDriverTrait}).
     */
    public function connection(): DriverInterface
    {
        return $this->database()->getDriver();
    }

    /**
     * The server version reported by PDO, e.g. `8.0.36` for MySQL.
     */
    public function serverVersion(): string
    {
        $driver = $this->connection();
        $driver->connect();
        $pdo = (new \ReflectionMethod($driver, 'getPDO'))->invoke($driver);

        return (string) $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }
}
