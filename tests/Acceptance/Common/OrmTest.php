<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Acceptance\Common;

use Cycle\Testing\Database\Assert\QueryAssert;
use Cycle\Testing\ORM\OrmTestCase;
use Cycle\Testing\Tests\Fixtures\User;
use Cycle\Testing\Tests\Fixtures\UserSchema;
use Testo\Assert;
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

    public function freshOrmPerTest(): void
    {
        Assert::notSame($this->orm, self::$previousOrm);
        Assert::same($this->orm->getRepository(User::class)->findAll(), []);
    }
}
