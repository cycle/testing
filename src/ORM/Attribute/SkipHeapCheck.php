<?php

declare(strict_types=1);

namespace Cycle\Testing\ORM\Attribute;

/**
 * Do not check the heap after the test (see {@see \Cycle\Testing\ORM\HeapIntegrity}).
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class SkipHeapCheck {}
