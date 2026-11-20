<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Fixtures\HarnessSuite\Orm;

use Cycle\Testing\ORM\OrmTestCase;
use Cycle\Testing\Tests\Fixtures\User;
use Cycle\Testing\Tests\Fixtures\UserSchema;
use Testo\Assert;
use Testo\Test;

/**
 * Ends with a heap out of sync, which only a heap check reports.
 */
#[Test]
final class UnsyncedHeapCase extends OrmTestCase
{
    public function changesASavedEntity(): void
    {
        $this->makeTable('user', UserSchema::COLUMNS);
        $this->withSchema(UserSchema::schema());
        $user = new User();
        $this->save($user);

        $user->email = 'changed@example.com';

        Assert::notNull($user->id);
    }
}
