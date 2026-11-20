<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Mock;

use Cycle\Database\Driver\DriverInterface;

/**
 * A driver instrumented with {@see WrapDriverTrait}.
 */
interface WrapDriverInterface extends DriverInterface
{
    /**
     * @param \Closure(\PDOStatement, ?array): bool $callback Runs instead of every statement's `execute()`.
     */
    public function setQueryCallback(\Closure $callback): void;

    /**
     * Restore the knobs: no disconnects counted, no failing begins, no query callback.
     */
    public function setDefaults(): void;
}
