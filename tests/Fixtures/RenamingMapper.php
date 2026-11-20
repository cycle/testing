<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Fixtures;

use Cycle\ORM\Mapper\Mapper;

/**
 * Extracts `email` as `renamed` whatever the entity holds.
 */
final class RenamingMapper extends Mapper
{
    public function extract(object $entity): array
    {
        return ['email' => 'renamed'] + parent::extract($entity);
    }
}
