<?php

declare(strict_types=1);

namespace Cycle\Testing\ORM;

use Cycle\ORM\EntityManager;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Schema;
use Cycle\ORM\SchemaInterface;
use Cycle\Testing\Database\DatabaseTestCase;
use Testo\Assert;
use Testo\Common\Attribute\AssertMethod;

/**
 * Base for ORM tests in an `Acceptance/<Driver>` suite with `OrmPlugin`.
 *
 * `$this->orm` is a fresh ORM before every test, on the plugin's schema; {@see self::withSchema()} swaps
 * the schema for the rest of the test.
 */
abstract class OrmTestCase extends DatabaseTestCase implements OrmAware
{
    protected ORMInterface $orm;

    public function attachOrm(ORMInterface $orm): void
    {
        $this->orm = $orm;
    }

    public function currentOrm(): ORMInterface
    {
        return $this->orm;
    }

    /**
     * @param SchemaInterface|array<non-empty-string, array<int, mixed>> $schema
     */
    protected function withSchema(SchemaInterface|array $schema): ORMInterface
    {
        return $this->orm = $this->orm->with(schema: \is_array($schema) ? new Schema($schema) : $schema);
    }

    /**
     * @param string $file A PHP file that returns the schema array.
     */
    protected function loadSchema(string $file): ORMInterface
    {
        return $this->withSchema(require $file);
    }

    /**
     * Persist the entities and run the unit of work.
     */
    protected function save(object ...$entities): void
    {
        $em = new EntityManager($this->orm);
        foreach ($entities as $entity) {
            $em->persist($entity);
        }

        $em->run();
    }

    /**
     * @return array<string, mixed> Field => value, as the mapper extracts it.
     */
    protected function extractEntity(object $entity): array
    {
        return $this->orm->getMapper($entity)->extract($entity);
    }

    /**
     * Every entity in the heap matches its node; the plugin also checks this after each passed test.
     */
    #[AssertMethod]
    protected function assertClearState(?ORMInterface $orm = null): void
    {
        Assert::same(HeapIntegrity::violations($orm ?? $this->orm), [], 'The heap is out of sync with the entities.');
    }

    /**
     * @param array<string, mixed> $entity
     * @param array<string, mixed> $state
     * @param array<string, mixed> $relations
     */
    #[AssertMethod]
    protected function assertEntitySynced(string $role, array $entity, array $state, array $relations = []): void
    {
        Assert::same(HeapIntegrity::entityViolations($this->orm, $role, $entity, $state, $relations), []);
    }
}
