<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Acceptance\Common;

use Cycle\Testing\Database\Attribute\Isolate;
use Cycle\Testing\Database\DatabaseTestCase;
use Cycle\Testing\Database\Isolation;
use Cycle\Testing\Database\Schema\Purger;
use Testo\Assert;
use Testo\Lifecycle\AfterClass;
use Testo\Lifecycle\BeforeClass;
use Testo\Test;

/**
 * The table lives for the whole case; only the rows of each test are rolled back.
 */
#[Test]
#[Isolate(Isolation::Transaction)]
final class TransactionIsolationTest extends DatabaseTestCase
{
    #[BeforeClass]
    public function createTable(): void
    {
        $this->makeTable('tx_rows', ['id' => 'primary', 'value' => 'string']);
    }

    #[AfterClass]
    public function dropTable(): void
    {
        Purger::dropAll($this->database);
    }

    public function insertsInsideTransaction(): void
    {
        Assert::same($this->database->getDriver()->getTransactionLevel(), 1);

        $this->database->table('tx_rows')->insertOne(['value' => 'rolled back']);

        Assert::same($this->database->table('tx_rows')->count(), 1);
    }

    public function previousInsertIsRolledBack(): void
    {
        Assert::same($this->database->table('tx_rows')->count(), 0);
    }

    #[Isolate(Isolation::None)]
    public function methodLevelWins(): void
    {
        Assert::same($this->database->getDriver()->getTransactionLevel(), 0);
    }
}
