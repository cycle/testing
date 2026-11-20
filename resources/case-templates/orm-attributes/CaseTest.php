<?php

declare(strict_types=1);

namespace Cycle\Testing\CaseTemplate\OrmAttributes;

use Cycle\ORM\Select;
use Cycle\Testing\ORM\OrmTestCase;
use Cycle\Testing\ORM\Schema\SchemaCompiler;
use Testo\Assert;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

/**
 * Regression case: entities mapped with attributes, schema compiled from them (which also creates the tables).
 * Replace the sample entities and tests with the issue's reproduction.
 */
#[Test]
final class CaseTest extends OrmTestCase
{
    #[BeforeTest]
    public function prepare(): void
    {
        $this->withSchema(SchemaCompiler::compile($this->dbal, [
            Entity\User::class,
            Entity\Post::class,
            Entity\Comment::class,
            Entity\Tag::class,
            Entity\PostTag::class,
        ]));
        $this->save(...$this->fillData());
        $this->orm->getHeap()->clean();
    }

    public function loadsRelations(): void
    {
        $user = (new Select($this->orm, Entity\User::class))
            ->load('posts.tags')
            ->where('login', 'user-2')
            ->fetchOne();

        Assert::instanceOf($user, Entity\User::class);
        Assert::count($user->posts, 2);
    }

    public function updateIsOneWrite(): void
    {
        $user = (new Select($this->orm, Entity\User::class))->where('login', 'user-2')->fetchOne();
        \assert($user instanceof Entity\User);
        $user->passwordHash = 'new-password-hash';

        $this->captureWriteQueries();
        $this->save($user);

        $this->assertNumWrites(1);
    }

    /**
     * @return iterable<object>
     */
    private function fillData(): iterable
    {
        $make = $this->orm->make(...);

        $u1 = $make(Entity\User::class, ['login' => 'user-1', 'passwordHash' => '123456789']);
        $u2 = $make(Entity\User::class, ['login' => 'user-2', 'passwordHash' => '852741963']);

        $p1 = $make(Entity\Post::class, ['title' => 'Title 1', 'public' => true, 'content' => 'Content 1']);
        $p2 = $make(Entity\Post::class, ['title' => 'Title 2', 'public' => true, 'content' => 'Content 2']);
        $p3 = $make(Entity\Post::class, ['title' => 'Title 3', 'public' => false, 'content' => 'Content 3']);
        $u1->posts = [$p1];
        $u2->posts = [$p2, $p3];

        $t1 = $make(Entity\Tag::class, ['label' => 'tag-1']);
        $t2 = $make(Entity\Tag::class, ['label' => 'tag-2']);
        $t1->posts = [$p1, $p2];
        $t2->posts = [$p2, $p3];

        $c1 = $make(Entity\Comment::class, ['post' => $p1, 'user' => $u2, 'content' => 'Comment 1', 'public' => true]);
        $c2 = $make(Entity\Comment::class, ['post' => $p2, 'user' => $u1, 'content' => 'Comment 2', 'public' => true]);

        yield from [$u1, $u2, $p1, $p2, $p3, $t1, $t2, $c1, $c2];
    }
}
