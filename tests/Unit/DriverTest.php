<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Database\Config\MySQLDriverConfig;
use Cycle\Database\Config\SQLite\FileConnectionConfig;
use Cycle\Database\Config\SQLite\MemoryConnectionConfig;
use Cycle\Testing\Database\Driver;
use Cycle\Testing\Database\Env;
use Cycle\Testing\Database\Feature;
use Cycle\Testing\Database\Mock\MySQLWrapDriver;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

#[Test]
final class DriverTest
{
    public function pickPrefersOwnVariant(): void
    {
        Assert::same(Driver::SQLServer->pick(default: 'LIMIT', sqlserver: 'TOP'), 'TOP');
        Assert::same(Driver::MySQL->pick(default: 'LIMIT', sqlserver: 'TOP'), 'LIMIT');
    }

    public function pickWithoutVariantThrows(): never
    {
        Expect::exception(\LogicException::class);
        Driver::MySQL->pick(postgres: 'x');
    }

    public function pickRejectsUnknownKeys(): never
    {
        Expect::exception(\InvalidArgumentException::class);
        Driver::MySQL->pick(mssql: 'x');
    }

    public function quotesLikeCompilers(): void
    {
        Assert::same(Driver::MySQL->quote('t'), '`t`');
        Assert::same(Driver::SQLServer->quote('t'), '[t]');
        Assert::same(Driver::Postgres->quote('t'), '"t"');
    }

    public function namesSuiteAndFolder(): void
    {
        Assert::same(Driver::Postgres->suiteName(), 'Acceptance/Postgres');
        Assert::same(Driver::SQLServer->folder(), 'SQLServer');
    }

    public function supportsFeatures(): void
    {
        Assert::true(Driver::Postgres->supports(Feature::Returning));
        Assert::false(Driver::MySQL->supports(Feature::Returning));
        Assert::false(Driver::MySQL->supports(Feature::TransactionalDdl));
    }

    public function configReadsEnv(): void
    {
        $env = new Env(['DB_MYSQL_HOST' => 'db', 'DB_MYSQL_PORT' => '3333', 'DB_MYSQL_DATABASE' => 'x', 'DB_MYSQL_USER' => 'u', 'DB_MYSQL_PASSWORD' => 'p']);
        $config = Driver::MySQL->config($env, MySQLWrapDriver::class);

        Assert::instanceOf($config, MySQLDriverConfig::class);
        Assert::same($config->driver, MySQLWrapDriver::class);
        Assert::string($config->connection->getDsn())->contains('host=db');
        Assert::true($config->options['logInterpolatedQueries']);
    }

    public function sqliteFileIsOptional(): void
    {
        Assert::instanceOf(Driver::SQLite->config(new Env())->connection, MemoryConnectionConfig::class);
        Assert::instanceOf(
            Driver::SQLite->config((new Env())->with(['DB_SQLITE_FILE' => 'runtime/test.db']))->connection,
            FileConnectionConfig::class,
        );
    }
}
