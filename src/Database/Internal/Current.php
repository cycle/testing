<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Internal;

use Cycle\Testing\Database\ConnectionPool;

/**
 * The pool of the suite that is running, for the static assertion helpers.
 *
 * Testo runs suites one after another in one process, so there is at most one.
 *
 * @internal
 */
final class Current
{
    public static ?ConnectionPool $pool = null;
}
