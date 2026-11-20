<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Fixtures;

use Cycle\ORM\Mapper\Mapper;

final class FailingMapper extends Mapper
{
    public function extract(object $entity): array
    {
        throw new \Error('mapper failed');
    }
}
