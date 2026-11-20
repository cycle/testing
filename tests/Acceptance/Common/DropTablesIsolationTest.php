<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Acceptance\Common;

use Cycle\Testing\Database\DatabaseTestCase;
use Testo\Assert;
use Testo\Test;

/**
 * The tests run in declaration order: the second sees what the first left.
 */
#[Test]
final class DropTablesIsolationTest extends DatabaseTestCase
{
    public function createsTable(): void
    {
        $this->makeTable('leftover', ['id' => 'primary']);
        $this->database->table('leftover')->insertOne([]);

        Assert::true($this->database->hasTable('leftover'));
    }

    public function tableIsGone(): void
    {
        Assert::false($this->database->hasTable('leftover'));
        Assert::same($this->database->getDriver()->getTransactionLevel(), 0);
    }
}
