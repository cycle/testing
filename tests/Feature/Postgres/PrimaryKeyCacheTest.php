<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Feature\Postgres;

use Cycle\Database\Driver\Postgres\PostgresDriver;
use Cycle\Testing\Database\DatabaseTestCase;
use Testo\Assert;
use Testo\Test;

/**
 * The tests run in declaration order: the second recreates the table the first cached a primary key of.
 */
#[Test]
final class PrimaryKeyCacheTest extends DatabaseTestCase
{
    public function cachesThePrimaryKey(): void
    {
        $this->makeTable('item', ['target' => 'primary']);

        Assert::same($this->postgres()->getPrimaryKey('', 'item'), 'target');
    }

    public function purgeBetweenTestsResetsTheCache(): void
    {
        # Raw DDL: the schema handler would reset the cache itself and hide a stale one.
        $this->postgres()->execute('CREATE TABLE item (id SERIAL PRIMARY KEY)');

        Assert::same($this->postgres()->getPrimaryKey('', 'item'), 'id');
    }

    private function postgres(): PostgresDriver
    {
        $driver = $this->database->getDriver();
        \assert($driver instanceof PostgresDriver);

        return $driver;
    }
}
