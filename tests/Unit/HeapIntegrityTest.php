<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\ORM\EntityManager;
use Cycle\Testing\Database\Schema\TableBuilder;
use Cycle\Testing\ORM\HeapIntegrity;
use Cycle\Testing\ORM\Memory;
use Cycle\Testing\Tests\Fixtures\User;
use Cycle\Testing\Tests\Fixtures\UserSchema;
use Testo\Assert;
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

    /**
     * @return array{\Cycle\ORM\ORMInterface, User}
     */
    private static function savedUser(): array
    {
        $orm = Memory::orm(UserSchema::schema());
        $database = $orm->getFactory()->database('default');
        \assert($database instanceof \Cycle\Database\Database);
        (new TableBuilder($database))->makeTable('user', UserSchema::COLUMNS);

        $user = new User();
        $user->email = 'a@example.com';
        (new EntityManager($orm))->persist($user)->run();

        return [$orm, $user];
    }
}
