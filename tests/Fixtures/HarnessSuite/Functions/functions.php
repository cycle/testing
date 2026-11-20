<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Fixtures\HarnessSuite\Functions;

use Testo\Assert;
use Testo\Test;

/**
 * A function test has no case class: no reflection and no instance.
 */
#[Test]
function runsWithoutACaseClass(): void
{
    Assert::true(true);
}
