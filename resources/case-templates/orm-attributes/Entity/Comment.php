<?php

declare(strict_types=1);

namespace Cycle\Testing\CaseTemplate\OrmAttributes\Entity;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;

#[Entity(role: Comment::ROLE, table: 'comment')]
class Comment
{
    public const ROLE = 'comment';

    #[Column(type: 'bigPrimary')]
    public ?int $id = null;

    #[Column(type: 'boolean')]
    public bool $public = false;

    #[Column(type: 'text')]
    public string $content = '';

    #[BelongsTo(target: User::class, innerKey: 'userId')]
    public User $user;

    #[BelongsTo(target: Post::class, innerKey: 'postId')]
    public ?Post $post = null;

    #[Column(type: 'bigInteger', name: 'user_id')]
    public ?int $userId = null;

    #[Column(type: 'bigInteger', name: 'post_id')]
    public ?int $postId = null;
}
