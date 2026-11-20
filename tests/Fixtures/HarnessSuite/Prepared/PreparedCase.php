<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Fixtures\HarnessSuite\Prepared;

use Cycle\Testing\Database\DatabaseTestCase;
use Testo\Assert;
use Testo\Lifecycle\BeforeClass;
use Testo\Test;

/**
 * Run with a `prepare` callback that creates `prepared` with one row; a leftover table was there before.
 */
#[Test]
final class PreparedCase extends DatabaseTestCase
{
    /**
     * Fails on an unreachable database, where the harness must keep it from running.
     */
    #[BeforeClass]
    public function connect(): void
    {
        $this->database->getDriver()->connect();
    }

    public function seesThePreparedRowsOnly(): void
    {
        Assert::same($this->database->table('prepared')->count(), 1);
        Assert::false($this->database->hasTable('leftover'));
    }
}
