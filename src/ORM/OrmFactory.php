<?php

declare(strict_types=1);

namespace Cycle\Testing\ORM;

use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\Collection\ArrayCollectionFactory;
use Cycle\ORM\Collection\DoctrineCollectionFactory;
use Cycle\ORM\Config\RelationConfig;
use Cycle\ORM\Factory;
use Cycle\ORM\Options;
use Cycle\ORM\ORM;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\SchemaInterface;
use Cycle\ORM\Transaction\CommandGeneratorInterface;

/**
 * The default ORM build of the test harness: Doctrine collections when installed (`array` stays
 * available by name), uninitialized relations ignored, `GROUP BY` deduplication.
 */
final class OrmFactory
{
    public function __construct(
        private readonly ?CommandGeneratorInterface $commandGenerator = null,
        private readonly ?Options $options = null,
    ) {}

    public function __invoke(DatabaseProviderInterface $dbal, SchemaInterface $schema): ORMInterface
    {
        $doctrine = \interface_exists(\Doctrine\Common\Collections\Collection::class);
        $factory = (new Factory(
            $dbal,
            RelationConfig::getDefault(),
            null,
            $doctrine ? new DoctrineCollectionFactory() : new ArrayCollectionFactory(),
        ))->withCollectionFactory('array', new ArrayCollectionFactory());

        return new ORM(
            $factory,
            $schema,
            $this->commandGenerator,
            options: $this->options ?? (new Options())
                ->withIgnoreUninitializedRelations(true)
                ->withGroupByToDeduplicate(true),
        );
    }
}
