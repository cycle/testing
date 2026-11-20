<?php

declare(strict_types=1);

namespace Cycle\Testing\ORM;

use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\SchemaInterface;
use Cycle\Testing\Database\Layout;
use Cycle\Testing\Database\Memory as DatabaseMemory;

/**
 * An ORM over in-memory SQLite for unit tests, outside any driver suite.
 */
final class Memory
{
    /**
     * @param (callable(DatabaseProviderInterface, SchemaInterface): ORMInterface)|null $factory
     */
    public static function orm(
        SchemaSource|SchemaInterface|array $schema = [],
        ?Layout $layout = null,
        ?callable $factory = null,
    ): ORMInterface {
        return ($factory ?? new OrmFactory())(DatabaseMemory::manager($layout), SchemaSource::of($schema)->resolve($layout));
    }
}
