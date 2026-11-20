<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Attribute;

use Cycle\Testing\Database\Driver;

/**
 * An attribute that keeps a test or a case out of some driver suites.
 *
 * The nearest level that declares any filter decides: the method (or the method it overrides), else the
 * class, its parents and traits. All filters on that level must allow the driver. An excluded test is
 * deactivated before the run, so it is not reported at all.
 */
interface DriverFilter
{
    public function allows(Driver $driver): bool;
}
