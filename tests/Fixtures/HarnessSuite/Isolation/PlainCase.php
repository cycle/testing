<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Fixtures\HarnessSuite\Isolation;

use Testo\Assert;
use Testo\Test;

/**
 * Not database-aware: the harness leaves the instance alone.
 */
#[Test]
final class PlainCase
{
    public function runs(): void
    {
        Assert::true(true);
    }
}
