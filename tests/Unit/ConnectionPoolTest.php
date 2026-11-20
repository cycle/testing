<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Database\DatabaseManager;
use Cycle\Testing\Database\ConnectionPool;
use Cycle\Testing\Database\Driver;
use Cycle\Testing\Database\Env;
use Cycle\Testing\Database\Layout;
use Cycle\Testing\Database\Mock\SQLiteWrapDriver;
use Cycle\Testing\Database\QueryLog;
use Testo\Assert;
use Testo\Test;

#[Test]
final class ConnectionPoolTest
{
    public function driversAreNamedAfterTheirConnection(): void
    {
        $pool = new ConnectionPool(Driver::SQLite, Layout::withSqlite(['secondary']));

        try {
            foreach ([$pool->manager(), $pool->manager(wrap: true)] as $manager) {
                Assert::same(self::driverName($manager, 'default'), 'default');
                Assert::same(self::driverName($manager, 'secondary'), 'secondary');
            }
            $variant = $pool->driver(driverOptions: ['options' => ['withDatetimeMicroseconds' => true]]);
            Assert::same($variant->getName(), 'default');
        } finally {
            $pool->disconnect();
        }
    }

    public function databasesOnOneConnectionShareTheName(): void
    {
        $pool = new ConnectionPool(Driver::SQLite, Layout::prefixed(['secondary' => 'secondary_']));

        Assert::same(self::driverName($pool->manager(), 'secondary'), 'default');
    }

    public function driversAreMemoisedByTheirOptions(): void
    {
        $pool = new ConnectionPool(Driver::SQLite);

        Assert::same($pool->driver(), $pool->driver());
        Assert::notSame($pool->driver(connectionOptions: ['options' => [\PDO::ATTR_TIMEOUT => 5]]), $pool->driver());
        Assert::array($pool->drivers())->isList()->hasCount(2);
    }

    public function managersAreMemoisedPerWrapMode(): void
    {
        $pool = new ConnectionPool(Driver::SQLite, Layout::withSqlite(['secondary']));

        $plain = $pool->manager();
        $wrapped = $pool->manager(wrap: true);

        Assert::same($pool->manager(), $plain);
        Assert::same($pool->managers(), [$plain, $wrapped]);
        Assert::instanceOf($wrapped->database()->getDriver(), SQLiteWrapDriver::class);
        Assert::false($plain->database()->getDriver() instanceof SQLiteWrapDriver);
        Assert::same($wrapped->database('secondary')->getDriver(), $plain->database('secondary')->getDriver());
    }

    public function ownSqliteDatabasesLogInterpolatedQueries(): void
    {
        $log = new QueryLog();
        $database = (new ConnectionPool(Driver::SQLite, Layout::withSqlite(['secondary']), queryLog: $log))->manager()->database('secondary');
        $database->execute('CREATE TABLE t (id INTEGER)');

        $database->select()->from('t')->where('id', 42)->fetchAll();

        Assert::string(\implode("\n", $log->queries()))->contains('42');
    }

    public function driverOptionsMergeIntoTheDefaults(): void
    {
        $log = new QueryLog();
        $driver = (new ConnectionPool(Driver::SQLite, queryLog: $log))->driver(driverOptions: [
            'options' => ['withDatetimeMicroseconds' => true],
            'readonly' => true,
        ]);

        $driver->query('SELECT ? AS at', [new \DateTimeImmutable('2024-01-02 03:04:05.123456', new \DateTimeZone('UTC'))])->fetchAll();

        Assert::true($driver->isReadonly());
        Assert::string(\implode("\n", $log->queries()))->contains("'2024-01-02 03:04:05.123456'");
    }

    public function connectionOptionsReplaceTheConnectionSettings(): void
    {
        $file = \sys_get_temp_dir() . '/cycle-testing-pool-' . \bin2hex(\random_bytes(4)) . '.sqlite';
        $pool = new ConnectionPool(Driver::SQLite);

        try {
            $pool->driver(connectionOptions: ['database' => $file])->execute('CREATE TABLE t (id INTEGER)');

            Assert::true(\is_file($file));
            Assert::false($pool->driver()->getSchemaHandler()->hasTable('t'));
        } finally {
            $pool->disconnect();
            @\unlink($file);
        }
    }

    public function memoryOnlyForInMemorySqlite(): void
    {
        Assert::true((new ConnectionPool(Driver::SQLite, env: new Env(['DB_SQLITE_FILE' => ''])))->isMemory());
        Assert::false((new ConnectionPool(Driver::SQLite, env: new Env(['DB_SQLITE_FILE' => 'runtime/x.db'])))->isMemory());
        Assert::false((new ConnectionPool(Driver::MySQL, env: Env::load()))->isMemory());
    }

    public function envIsTheGivenOne(): void
    {
        $env = new Env();

        Assert::same((new ConnectionPool(Driver::SQLite, env: $env))->env, $env);
    }

    private static function driverName(DatabaseManager $manager, string $database): string
    {
        return $manager->database($database)->getDriver()->getName();
    }
}
