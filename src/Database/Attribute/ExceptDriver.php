<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Attribute;

use Cycle\Testing\Database\Driver;

/**
 * Keep the test, or every test of the class, out of the given drivers' suites.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class ExceptDriver implements DriverFilter
{
    /** @var non-empty-list<Driver> */
    public readonly array $drivers;

    public function __construct(Driver $driver, Driver ...$more)
    {
        $this->drivers = [$driver, ...\array_values($more)];
    }

    public function allows(Driver $driver): bool
    {
        return !\in_array($driver, $this->drivers, true);
    }
}
