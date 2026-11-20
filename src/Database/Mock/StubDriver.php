<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Mock;

use Cycle\Database\Config\DriverConfig;
use Cycle\Database\Config\SQLiteDriverConfig;
use Cycle\Database\Driver\Driver;
use Cycle\Database\Driver\HandlerInterface;
use Cycle\Database\Driver\PDOInterface;
use Cycle\Database\Driver\SQLite\SQLiteCompiler;
use Cycle\Database\Driver\SQLite\SQLiteHandler;
use Cycle\Database\Exception\StatementException;
use Cycle\Database\Query\BuilderInterface;
use Cycle\Database\Query\QueryBuilder;

/**
 * A driver for unit tests: the SQLite handler and compiler over a PDO you supply (a {@see FakePDO} or a
 * mock), or an in-memory SQLite PDO when none is given. Exceptions are rethrown unmapped.
 */
class StubDriver extends Driver
{
    protected ?PDOInterface $pdoOverride = null;

    public static function create(DriverConfig $config): static
    {
        return new static($config, new SQLiteHandler(), new SQLiteCompiler('""'), QueryBuilder::defaultBuilder());
    }

    public static function make(
        ?PDOInterface $pdo = null,
        ?HandlerInterface $handler = null,
        ?BuilderInterface $builder = null,
        ?DriverConfig $config = null,
    ): static {
        $driver = new static(
            $config ?? new SQLiteDriverConfig(driver: static::class),
            $handler ?? new SQLiteHandler(),
            new SQLiteCompiler('""'),
            $builder ?? QueryBuilder::defaultBuilder(),
        );
        $driver->pdoOverride = $pdo;

        return $driver;
    }

    public function getType(): string
    {
        return 'test';
    }

    protected function mapException(\Throwable $exception, string $query): StatementException
    {
        throw $exception;
    }

    protected function getPDO(): \PDO|PDOInterface
    {
        return $this->pdoOverride ?? parent::getPDO();
    }
}
