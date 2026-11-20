<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Acceptance\Common;

use Cycle\Testing\Database\Assert\QueryAssert;
use Cycle\Testing\ORM\OrmTestCase;
use Cycle\Testing\Tests\Fixtures\User;
use Cycle\Testing\Tests\Fixtures\UserSchema;
use Testo\Assert;
use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class OrmTest extends OrmTestCase
{
    private static ?object $previousOrm = null;

    #[BeforeTest]
    public function defineSchema(): void
    {
        $this->makeTable('user', UserSchema::COLUMNS);
        $this->withSchema(UserSchema::schema());
    }

    public function savesAndLoads(): void
    {
        $user = new User();
        $user->email = 'a@example.com';
        $user->balance = 1.5;

        QueryAssert::writes(1, fn() => $this->save($user));
        Assert::notNull($user->id);

        $this->orm->getHeap()->clean();
        $loaded = QueryAssert::reads(1, fn(): ?User => $this->orm->getRepository(User::class)->findByPK($user->id));

        Assert::same($loaded?->email, 'a@example.com');
        $this->assertClearState();
        self::$previousOrm = $this->orm;
    }

    public function savedEntityIsInSyncWithItsNode(): void
    {
        $user = new User();
        $user->email = 'b@example.com';
        $this->save($user);

        $extracted = $this->extractEntity($user);
        $state = $this->orm->getHeap()->get($user)->getData();

        Assert::same($extracted['email'], 'b@example.com');
        $this->assertEntitySynced('user', $extracted, $state);
    }

    public function changedEntityIsOutOfSync(): never
    {
        $user = new User();
        $user->email = 'c@example.com';
        $this->save($user);
        $state = $this->orm->getHeap()->get($user)->getData();
        # The plugin checks the heap after the test: keep the out-of-sync entity out of it.
        $this->orm->getHeap()->clean();
        $user->email = 'changed@example.com';

        Expect::exception(ComparisonFailure::class);
        $this->assertEntitySynced('user', $this->extractEntity($user), $state);
    }

    public function freshOrmPerTest(): void
    {
        Assert::notSame($this->orm, self::$previousOrm);
        Assert::same($this->orm->getRepository(User::class)->findAll(), []);
    }
}
