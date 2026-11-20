<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Attribute;

use Cycle\Testing\Database\Isolation;

/**
 * Override the suite's isolation mode for a test or a class; the method level wins over the class.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class Isolate
{
    public function __construct(
        public readonly Isolation $mode,
    ) {}
}
