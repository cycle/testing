<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Testing\ORM\Schema\ClassList;
use Cycle\Testing\Tests\Fixtures\ShadowedParent;
use Cycle\Testing\Tests\Fixtures\ShadowingEntity;
use Cycle\Testing\Tests\Fixtures\User;
use Testo\Assert;
use Testo\Test;

#[Test]
final class ClassListTest
{
    public function listsEveryClassWithoutATarget(): void
    {
        $list = new ClassList(User::class, ShadowingEntity::class);

        Assert::same(\array_keys($list->getClasses()), [User::class, ShadowingEntity::class]);
    }

    public function targetKeepsTheClassAndItsSubclasses(): void
    {
        $list = new ClassList(User::class, ShadowedParent::class, ShadowingEntity::class);

        Assert::same(\array_keys($list->getClasses(ShadowedParent::class)), [ShadowedParent::class, ShadowingEntity::class]);
        Assert::same(\array_keys($list->getClasses(ShadowingEntity::class)), [ShadowingEntity::class]);
    }
}
