<?php

declare(strict_types=1);

namespace Cycle\Testing\CaseTemplate\OrmBehaviors;

use Cycle\ORM\Entity\Behavior\EventDrivenCommandGenerator;
use Cycle\ORM\EntityManager;
use Cycle\ORM\Schema;
use Cycle\ORM\Select;
use Cycle\Testing\Container\SimpleContainer;
use Cycle\Testing\ORM\OrmFactory;
use Cycle\Testing\ORM\OrmTestCase;
use Cycle\Testing\ORM\Schema\SchemaCompiler;
use Testo\Assert;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

/**
 * Regression case: attribute-mapped entities with cycle/entity-behavior behaviours, which need the
 * event-driven command generator. Replace the sample entities and tests with the issue's reproduction.
 */
#[Test]
final class CaseTest extends OrmTestCase
{
    #[BeforeTest]
    public function prepare(): void
    {
        $schema = new Schema(SchemaCompiler::compile($this->dbal, [
            Entity\User::class,
            Entity\Post::class,
            Entity\Comment::class,
            Entity\Tag::class,
            Entity\PostTag::class,
        ]));
        $generator = new EventDrivenCommandGenerator($schema, new SimpleContainer());
        $this->orm = (new OrmFactory($generator))($this->dbal, $schema);

        $make = $this->orm->make(...);
        $user = $make(Entity\User::class, ['login' => 'user-1', 'passwordHash' => '123456789']);
        $user->posts = [
            $make(Entity\Post::class, ['title' => 'Title 1', 'public' => true, 'content' => 'Content 1']),
            $make(Entity\Post::class, ['title' => 'Title 2', 'public' => false, 'content' => 'Content 2']),
        ];
        $this->save($user);
        $this->orm->getHeap()->clean();
    }

    public function createdAtIsSetOnInsert(): void
    {
        $user = $this->orm->make(Entity\User::class, ['login' => 'new-user', 'passwordHash' => '987654321']);
        \assert($user instanceof Entity\User);
        $this->save($user);

        Assert::true(isset($user->createdAt));
    }

    public function updateBumpsUpdatedAtAndVersion(): void
    {
        $post = (new Select($this->orm, Entity\Post::class))->where('title', 'Title 1')->fetchOne();
        \assert($post instanceof Entity\Post);
        $version = $post->version;

        $post->content = 'Changed';
        $this->save($post);

        Assert::notNull($post->updatedAt);
        Assert::same($post->version, $version + 1);
    }

    public function deleteIsSoft(): void
    {
        $post = (new Select($this->orm, Entity\Post::class))->where('title', 'Title 2')->fetchOne();
        \assert($post instanceof Entity\Post);
        (new EntityManager($this->orm))->delete($post)->run();
        $this->orm->getHeap()->clean();

        $rows = $this->database->table('post')->select()->where('title', 'Title 2')->fetchAll();
        Assert::count($rows, 1);
        Assert::notNull($rows[0]['deleted_at']);
    }
}
