<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Fixtures;

use Cycle\ORM\Mapper\Mapper;

/**
 * Reads every property like a reflection hydrator, so an uninitialized typed property throws.
 */
final class StrictMapper extends Mapper
{
    public function extract(object $entity): array
    {
        $values = [];
        foreach ((new \ReflectionObject($entity))->getProperties() as $property) {
            $values[$property->getName()] = $property->getValue($entity);
        }

        return $values;
    }
}
