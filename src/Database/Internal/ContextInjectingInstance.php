<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Internal;

use Cycle\Testing\Database\DatabaseAware;
use Cycle\Testing\Database\DatabaseContext;
use Testo\Core\Value\CaseInstance;

/**
 * Attaches the database context to the case instance the moment Testo creates it.
 *
 * @internal
 */
final class ContextInjectingInstance implements CaseInstance
{
    private bool $attached = false;

    public function __construct(
        private readonly CaseInstance $inner,
        private readonly DatabaseContext $context,
    ) {}

    public function getInstance(): object
    {
        $instance = $this->inner->getInstance();
        if (!$this->attached && $instance instanceof DatabaseAware) {
            $this->attached = true;
            $instance->attachDatabase($this->context);
        }

        return $instance;
    }

    public function hasInstance(): bool
    {
        return $this->inner->hasInstance();
    }
}
