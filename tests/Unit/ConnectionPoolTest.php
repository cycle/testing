<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Database\DatabaseManager;
use Cycle\Testing\Database\ConnectionPool;
use Cycle\Testing\Database\Driver;
use Cycle\Testing\Database\Layout;
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

    private static function driverName(DatabaseManager $manager, string $database): string
    {
        return $manager->database($database)->getDriver()->getName();
    }
}
