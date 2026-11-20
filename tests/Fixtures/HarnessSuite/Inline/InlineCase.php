<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Fixtures\HarnessSuite\Inline;

use Testo\Inline\TestInline;

/**
 * An inline test: a case of another type than `test`, which the driver filters leave alone.
 */
final class InlineCase
{
    #[TestInline([2], 4)]
    public static function double(int $value): int
    {
        return $value * 2;
    }
}
