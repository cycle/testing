<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\ORM\EntityManager;
use Cycle\ORM\Heap\HeapInterface;
use Cycle\ORM\Heap\Node;
use Cycle\ORM\Mapper\Mapper;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Reference\Reference;
use Cycle\ORM\Relation;
use Cycle\ORM\SchemaInterface as S;
use Cycle\Testing\Database\Schema\TableBuilder;
use Cycle\Testing\ORM\HeapIntegrity;
use Cycle\Testing\ORM\Memory;
use Cycle\Testing\Tests\Fixtures\FailingMapper;
use Cycle\Testing\Tests\Fixtures\RenamingMapper;
use Cycle\Testing\Tests\Fixtures\ShadowingEntity;
use Cycle\Testing\Tests\Fixtures\TypedEntity;
use Cycle\Testing\Tests\Fixtures\User;
use Cycle\Testing\Tests\Fixtures\UserSchema;
use Doctrine\Common\Collections\ArrayCollection;
use Testo\Assert;
use Testo\Core\Exception\SkipTest;
use Testo\Expect;
use Testo\Test;

#[Test]
final class HeapIntegrityTest
{
    public function syncedHeapHasNoViolations(): void
    {
        [$orm, $user] = self::savedUser();

        Assert::same(HeapIntegrity::violations($orm), []);
        Assert::notNull($user->id);
    }

    public function unsavedChangeIsReported(): void
    {
        [$orm, $user] = self::savedUser();
        $user->email = 'changed@example.com';

        Assert::same(HeapIntegrity::violations($orm), ['Entity and State are not in sync `user`.`email`']);
    }

    public function everyUnsavedChangeIsReported(): void
    {
        [$orm, $user] = self::savedUser();
        $user->email = 'changed@example.com';
        $user->balance = 10.0;

        Assert::same(HeapIntegrity::violations($orm), [
            'Entity and State are not in sync `user`.`email`',
            'Entity and State are not in sync `user`.`balance`',
        ]);
    }

    public function entityIsReadThroughItsMapper(): void
    {
        $schema = UserSchema::schema();
        $schema['user'][S::MAPPER] = RenamingMapper::class;
        [$orm] = self::savedUser($schema);

        Assert::same(HeapIntegrity::violations($orm), ['Entity and State are not in sync `user`.`email`']);
    }

    public function mapperErrorsOtherThanUninitializedPropertiesPropagate(): never
    {
        $schema = UserSchema::schema();
        $schema['user'][S::MAPPER] = FailingMapper::class;
        [$orm] = self::savedUser($schema);

        Expect::exception(\Error::class)->withMessage('mapper failed');

        HeapIntegrity::violations($orm);
    }

    public function uninitializedTypedPropertiesAreSkipped(): void
    {
        $orm = Memory::orm(TypedEntity::schema());
        $entity = $orm->make('typed');
        Assert::false((new \ReflectionProperty(TypedEntity::class, 'id'))->isInitialized($entity));

        Assert::same(HeapIntegrity::violations($orm), []);
    }

    public function initializedPropertiesOfPartlyInitializedEntityAreCompared(): void
    {
        $orm = Memory::orm(TypedEntity::schema());
        $entity = $orm->make('typed', ['name' => 'a', 'note' => 'n']);
        $entity->name = 'b';
        $entity->note = 'm';

        Assert::same(HeapIntegrity::violations($orm), [
            'Entity and State are not in sync `typed`.`name`',
            'Entity and State are not in sync `typed`.`note`',
        ]);
    }

    public function propertyShadowingAParentPrivateOneIsReadFromTheEntityClass(): void
    {
        $orm = Memory::orm(ShadowingEntity::schema());
        $entity = $orm->make('shadowing', ['name' => 'a']);
        $entity->name = 'b';

        Assert::same(HeapIntegrity::violations($orm), ['Entity and State are not in sync `shadowing`.`name`']);
    }

    public function fieldsAreComparedLoosely(): void
    {
        $orm = self::relationOrm();

        Assert::same(HeapIntegrity::entityViolations($orm, 'post', ['a' => '1', 'b' => [1, '2']], ['a' => 1, 'b' => ['1', 2]], []), []);
    }

    public function arraysDifferInLengthKeysOrValues(): void
    {
        $orm = self::relationOrm();

        $violations = HeapIntegrity::entityViolations(
            $orm,
            'post',
            ['a' => [1], 'b' => ['x' => 1], 'c' => [1, 2], 'd' => [1], 'e' => 'x'],
            ['a' => [1, 2], 'b' => ['y' => 1], 'c' => [1, 3], 'd' => 'x', 'e' => [1]],
            [],
        );

        Assert::same($violations, [
            'Entity and State are not in sync `post`.`a`',
            'Entity and State are not in sync `post`.`b`',
            'Entity and State are not in sync `post`.`c`',
            'Entity and State are not in sync `post`.`d`',
            'Entity and State are not in sync `post`.`e`',
        ]);
    }

    public function objectsAreEqualOnlyWhenOfOneClassAndEqualState(): void
    {
        $orm = self::relationOrm();
        $user = self::user('a');

        $violations = HeapIntegrity::entityViolations(
            $orm,
            'post',
            ['same' => $user, 'equal' => self::user('a'), 'differ' => self::user('a'), 'class' => new \ArrayObject(), 'scalar' => $user],
            ['same' => $user, 'equal' => self::user('a'), 'differ' => self::user('b'), 'class' => new \ArrayIterator(), 'scalar' => 1],
            [],
        );

        Assert::same($violations, [
            'Entity and State are not in sync `post`.`differ`',
            'Entity and State are not in sync `post`.`class`',
            'Entity and State are not in sync `post`.`scalar`',
        ]);
    }

    public function recursiveGraphsAreNotEqual(): void
    {
        if (\PHP_VERSION_ID < 80400) {
            # Before PHP 8.4 `==` on a recursive graph is a fatal error, which equals() cannot catch.
            throw new SkipTest('Comparing recursive graphs is fatal before PHP 8.4.');
        }

        $orm = self::relationOrm();
        [$a, $b] = [new \stdClass(), new \stdClass()];
        $a->self = $a;
        $b->self = $b;

        Assert::same(HeapIntegrity::entityViolations($orm, 'post', ['a' => $a], ['a' => $b], []), [
            'Entity and State are not in sync `post`.`a`',
        ]);
    }

    public function fieldsOutsideStateAndRelationsAreSkipped(): void
    {
        $orm = self::relationOrm();

        $violations = HeapIntegrity::entityViolations($orm, 'post', ['extra' => 1, 'title' => 'a'], ['title' => 'b'], []);

        Assert::same($violations, ['Entity and State are not in sync `post`.`title`']);
    }

    public function relationMissingFromSchemaIsReported(): void
    {
        $orm = self::relationOrm();

        $violations = HeapIntegrity::entityViolations($orm, 'bare', ['tags' => [], 'title' => 'a'], ['title' => 'b'], ['tags' => []]);

        Assert::same($violations, [
            'Relation `tags` is not found among the roles of `bare` and its parents.',
            'Entity and State are not in sync `bare`.`title`',
        ]);
    }

    public function relationsAreFoundOnParentRoles(): void
    {
        $orm = self::relationOrm();

        $violations = HeapIntegrity::entityViolations($orm, 'child', ['author' => self::user('a')], [], ['author' => self::user('b')]);

        Assert::same($violations, ['Entity and State are not in sync `child`.`author`']);
    }

    public function embeddedRelationsAreSkipped(): void
    {
        $orm = self::relationOrm();

        $violations = HeapIntegrity::entityViolations(
            $orm,
            'post',
            ['address' => self::user('a'), 'author' => self::user('a')],
            [],
            ['address' => self::user('b'), 'author' => self::user('b')],
        );

        Assert::same($violations, ['Entity and State are not in sync `post`.`author`']);
    }

    public function identicalRelationValueEndsTheEntityCheck(): void
    {
        $orm = self::relationOrm();
        $user = self::user('a');

        $violations = HeapIntegrity::entityViolations(
            $orm,
            'post',
            ['title' => 'a', 'body' => 'a', 'author' => $user, 'subtitle' => 'a'],
            ['title' => 'b', 'body' => 'b', 'subtitle' => 'b'],
            ['author' => $user],
        );

        Assert::same($violations, [
            'Entity and State are not in sync `post`.`title`',
            'Entity and State are not in sync `post`.`body`',
        ]);
    }

    public function loadedReferencesAreComparedByValue(): void
    {
        $orm = self::relationOrm();
        $user = self::user('a');

        $violations = HeapIntegrity::entityViolations(
            $orm,
            'post',
            ['author' => self::reference('user', ['id' => 1], $user), 'tags' => [self::user('a')]],
            [],
            ['author' => self::reference('user', ['id' => 2], self::user('a')), 'tags' => self::reference('user', [], [self::user('b')])],
        );

        Assert::same($violations, ['Entity and State are not in sync `post`.`tags`']);
    }

    public function unloadedReferencesAreComparedByScopeAndRole(): void
    {
        $orm = self::relationOrm();

        $violations = HeapIntegrity::entityViolations(
            $orm,
            'post',
            ['author' => new Reference('user', ['id' => 1]), 'tags' => new Reference('user', ['id' => 1]), 'other' => new Reference('user', ['id' => '1'])],
            [],
            ['author' => new Reference('user', ['id' => 2]), 'tags' => new Reference('tag', ['id' => 1]), 'other' => new Reference('user', ['id' => 1])],
        );

        Assert::same($violations, [
            'Entity and State are not in sync `post`.`author` (Reference scope)',
            'Entity and State are not in sync `post`.`tags` (Reference role)',
        ]);
    }

    public function referenceAgainstEntityIsAMismatch(): void
    {
        $orm = self::relationOrm();

        $violations = HeapIntegrity::entityViolations(
            $orm,
            'post',
            ['author' => self::user('a')],
            [],
            ['author' => new Reference('user', ['id' => 1])],
        );

        Assert::same($violations, ['Entity and State are not in sync `post`.`author`']);
    }

    public function collectionsAreComparedAsLists(): void
    {
        $orm = self::relationOrm();

        $violations = HeapIntegrity::entityViolations(
            $orm,
            'post',
            ['tags' => [self::user('a')], 'other' => new \ArrayIterator([]), 'items' => new \ArrayIterator([self::user('a')]), 'author' => new \ArrayIterator(['k' => self::user('a')])],
            [],
            ['tags' => new ArrayCollection(['k' => self::user('a')]), 'other' => null, 'items' => [self::user('a')], 'author' => [self::user('a')]],
        );

        Assert::same($violations, ['Entity and State are not in sync `post`.`author`']);
    }

    public function heapEntityWithoutANodeIsReported(): void
    {
        $heap = new class implements HeapInterface, \IteratorAggregate {
            public function getIterator(): \Iterator
            {
                return new \ArrayIterator([new User()]);
            }

            public function has(object $entity): bool
            {
                return false;
            }

            public function get(object $entity): ?Node
            {
                return null;
            }

            public function find(string $role, array $scope): ?object
            {
                return null;
            }

            public function attach(object $entity, Node $node, array $index = []): void {}

            public function detach(object $entity): void {}

            public function clean(): void {}
        };
        $orm = Memory::orm(UserSchema::schema())->with(heap: $heap);

        Assert::same(HeapIntegrity::violations($orm), ['No node for a heap entity of class ' . User::class . '.']);
    }

    /**
     * @return array{ORMInterface, User}
     */
    private static function savedUser(?array $schema = null): array
    {
        $orm = Memory::orm($schema ?? UserSchema::schema());
        $database = $orm->getFactory()->database('default');
        \assert($database instanceof \Cycle\Database\Database);
        (new TableBuilder($database))->makeTable('user', UserSchema::COLUMNS);

        $user = new User();
        $user->email = 'a@example.com';
        (new EntityManager($orm))->persist($user)->run();

        return [$orm, $user];
    }

    /**
     * An ORM whose schema is only read, never queried: `post` has a belongs-to, a has-many and an embedded
     * relation, `child` inherits them, `bare` defines no relations at all.
     */
    private static function relationOrm(): ORMInterface
    {
        $relation = static fn(int $type, string $target): array => [
            Relation::TYPE => $type,
            Relation::TARGET => $target,
            Relation::LOAD => Relation::LOAD_PROMISE,
            Relation::SCHEMA => [],
        ];
        $role = static fn(string $role): array => [
            S::ENTITY => $role,
            S::MAPPER => Mapper::class,
            S::DATABASE => 'default',
            S::TABLE => $role,
            S::PRIMARY_KEY => 'id',
            S::COLUMNS => ['id'],
        ];

        return Memory::orm([
            'user' => $role('user') + [S::RELATIONS => []],
            'post' => $role('post') + [S::RELATIONS => [
                'author' => $relation(Relation::BELONGS_TO, 'user'),
                'tags' => $relation(Relation::HAS_MANY, 'user'),
                'other' => $relation(Relation::HAS_MANY, 'user'),
                'items' => $relation(Relation::HAS_MANY, 'user'),
                'address' => $relation(Relation::EMBEDDED, 'user'),
            ]],
            'child' => $role('child') + [S::PARENT => 'post', S::RELATIONS => []],
            'bare' => $role('bare'),
        ]);
    }

    private static function user(string $email): User
    {
        $user = new User();
        $user->email = $email;

        return $user;
    }

    private static function reference(string $role, array $scope, mixed $value): Reference
    {
        $reference = new Reference($role, $scope);
        $reference->setValue($value);

        return $reference;
    }
}
