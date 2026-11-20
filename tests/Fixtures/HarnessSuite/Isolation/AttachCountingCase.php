<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Fixtures\HarnessSuite\Isolation;

use Cycle\Testing\Database\DatabaseAware;
use Cycle\Testing\Database\DatabaseContext;
use Testo\Assert;
use Testo\Test;

final class AttachCountingCase implements DatabaseAware
{
    private int $attached = 0;

    public function attachDatabase(DatabaseContext $context): void
    {
        ++$this->attached;
    }

    #[Test]
    public function first(): void
    {
        Assert::same($this->attached, 1);
    }

    #[Test]
    public function second(): void
    {
        Assert::same($this->attached, 1);
    }
}
