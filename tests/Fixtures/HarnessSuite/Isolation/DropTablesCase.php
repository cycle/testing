<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Fixtures\HarnessSuite\Isolation;

use Cycle\Testing\Database\DatabaseTestCase;
use Testo\Assert;
use Testo\Test;

/**
 * The tests run in declaration order: the second checks what the first left behind, a transaction included.
 */
#[Test]
final class DropTablesCase extends DatabaseTestCase
{
    public function leavesTablesBehind(): void
    {
        $this->makeTable('left_behind', ['id' => 'primary']);
        $this->database->begin();

        Assert::true($this->database->hasTable('left_behind'));
    }

    public function startsWithoutThem(): void
    {
        Assert::false($this->database->hasTable('left_behind'));
    }
}
