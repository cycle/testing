<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Acceptance\SQLite;

use Cycle\Testing\Database\DatabaseTestCase;
use Cycle\Testing\Database\Driver;
use Testo\Assert;
use Testo\Test;

/**
 * A driver folder joins only its own driver's suite.
 */
#[Test]
final class SqliteFolderTest extends DatabaseTestCase
{
    public function runsOnSqliteOnly(): void
    {
        Assert::same($this->driver, Driver::SQLite);
    }
}
