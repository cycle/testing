<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Fixtures\HarnessSuite\Isolation;

use Cycle\Testing\Database\Attribute\Isolate;
use Cycle\Testing\Database\DatabaseTestCase;
use Cycle\Testing\Database\Isolation;
use Testo\Assert;
use Testo\Test;

/**
 * The tests run in declaration order: each later one checks what the earlier left behind.
 */
#[Test]
#[Isolate(Isolation::None)]
final class NoIsolationCase extends DatabaseTestCase
{
    public function leavesATransactionOpen(): void
    {
        $this->makeTable('kept', ['id' => 'primary']);
        $this->database->begin();

        Assert::same($this->database->getDriver()->getTransactionLevel(), 1);
    }

    public function startsOutsideATransactionWithTheTableKept(): void
    {
        Assert::same($this->database->getDriver()->getTransactionLevel(), 0);
        Assert::true($this->database->hasTable('kept'));
    }
}
