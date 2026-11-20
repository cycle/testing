<?php

declare(strict_types=1);

namespace Cycle\Testing\ORM;

use Cycle\ORM\Collection\Pivoted\PivotedStorage;
use Cycle\ORM\Heap\Node;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Reference\ReferenceInterface;
use Cycle\ORM\Relation;
use Cycle\ORM\SchemaInterface;
use Doctrine\Common\Collections\Collection;

/**
 * Checks that every entity in the heap matches its node: the data the ORM believes is stored and the
 * relations it tracks.
 *
 * A mismatch means the ORM would write wrong data on the next sync, even when the test itself passed.
 */
final class HeapIntegrity
{
    /**
     * @return list<string> One message per mismatch; empty when the heap is in sync.
     */
    public static function violations(ORMInterface $orm): array
    {
        $relations = new \ReflectionProperty(Node::class, 'relations');
        $heap = $orm->getHeap();
        $violations = [];

        foreach ($heap as $entity) {
            $node = $heap->get($entity);
            if ($node === null) {
                $violations[] = \sprintf('No node for a heap entity of class %s.', $entity::class);
                continue;
            }

            \array_push($violations, ...self::entityViolations(
                $orm,
                $node->getRole(),
                $orm->getMapper($entity)->extract($entity),
                $node->getData(),
                $relations->getValue($node),
            ));
        }

        return $violations;
    }

    /**
     * @param array<string, mixed> $entity Field => value, as the mapper extracts it.
     * @param array<string, mixed> $state Field => value, as the node stores it.
     * @param array<string, mixed> $relations Relation => value, as the node tracks it.
     * @return list<string>
     */
    public static function entityViolations(
        ORMInterface $orm,
        string $role,
        array $entity,
        array $state,
        array $relations,
    ): array {
        $violations = [];
        foreach ($entity as $name => $eValue) {
            if (\array_key_exists($name, $state)) {
                self::equals($eValue, $state[$name])
                    or $violations[] = "Entity and State are not in sync `{$role}`.`{$name}`";
                continue;
            }

            if (!\array_key_exists($name, $relations)) {
                continue;
            }

            $relation = self::findRelation($orm, $role, $name);
            if ($relation === null) {
                $violations[] = "Relation `{$name}` is not found among the roles of `{$role}` and its parents.";
                continue;
            }

            if ($relation[Relation::TYPE] === Relation::EMBEDDED) {
                continue;
            }

            $rValue = $relations[$name];
            if ($rValue === $eValue) {
                # Ends the whole entity, not only this field: orm's own check always has, and its suite relies on it.
                return $violations;
            }

            $rValue = self::unwrap($rValue);
            $eValue = $eValue instanceof ReferenceInterface && $eValue->hasValue() ? $eValue->getValue() : $eValue;
            if ($eValue instanceof \Traversable) {
                $eValue = \iterator_to_array($eValue);
                $rValue ??= [];
            }

            if ($rValue instanceof ReferenceInterface && $eValue instanceof ReferenceInterface) {
                $rValue->getScope() == $eValue->getScope()
                    or $violations[] = "Entity and State are not in sync `{$role}`.`{$name}` (Reference scope)";
                $rValue->getRole() === $eValue->getRole()
                    or $violations[] = "Entity and State are not in sync `{$role}`.`{$name}` (Reference role)";
                continue;
            }

            self::equals($rValue, $eValue) or $violations[] = "Entity and State are not in sync `{$role}`.`{$name}`";
        }

        return $violations;
    }

    private static function findRelation(ORMInterface $orm, string $role, string $name): ?array
    {
        $schema = $orm->getSchema();
        for ($parent = $role; $parent; $parent = $schema->define($parent, SchemaInterface::PARENT)) {
            $relations = (array) $schema->define($parent, SchemaInterface::RELATIONS);
            if (\array_key_exists($name, $relations)) {
                return $relations[$name];
            }
        }

        return null;
    }

    private static function unwrap(mixed $value): mixed
    {
        $value instanceof ReferenceInterface && $value->hasValue() and $value = $value->getValue();
        $value instanceof PivotedStorage and $value = $value->getElements();
        $value instanceof Collection and $value = \array_values($value->toArray());

        return $value;
    }

    /**
     * Loose equality, like PHPUnit's `assertEquals`, that does not recurse into entity graphs.
     */
    private static function equals(mixed $a, mixed $b): bool
    {
        if (\is_array($a) && \is_array($b)) {
            if (\count($a) !== \count($b)) {
                return false;
            }

            foreach ($a as $key => $value) {
                if (!\array_key_exists($key, $b) || !self::equals($value, $b[$key])) {
                    return false;
                }
            }

            return true;
        }

        if (\is_object($a) || \is_object($b)) {
            try {
                return $a === $b || (\is_object($a) && \is_object($b) && $a::class === $b::class && $a == $b);
            } catch (\Error) {
                # `==` throws on a recursive graph; distinct instances of such a graph are not equal here.
                return false;
            }
        }

        return $a == $b;
    }
}
