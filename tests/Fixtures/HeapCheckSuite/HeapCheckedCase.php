<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Fixtures\HeapCheckSuite;

use Cycle\Testing\ORM\Attribute\SkipHeapCheck;
use Cycle\Testing\ORM\OrmTestCase;
use Cycle\Testing\Tests\Fixtures\User;
use Cycle\Testing\Tests\Fixtures\UserSchema;
use Testo\Test;

/**
 * Run by `Unit\OrmPluginTest` in a nested Testo application; no suite of the package locates it.
 */
#[Test]
final class HeapCheckedCase extends OrmTestCase
{
    public function savesWithoutAssertions(): void
    {
        $this->makeTable('user', UserSchema::COLUMNS);
        $this->withSchema(UserSchema::schema());

        $user = new User();
        $user->email = 'a@example.com';
        $this->save($user);
    }

    #[SkipHeapCheck]
    public function skipsTheCheck(): void {}
}
