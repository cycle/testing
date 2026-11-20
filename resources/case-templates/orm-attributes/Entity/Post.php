<?php

declare(strict_types=1);

namespace Cycle\Testing\CaseTemplate\OrmAttributes\Entity;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\Annotated\Annotation\Relation\HasMany;
use Cycle\Annotated\Annotation\Relation\ManyToMany;

#[Entity(role: Post::ROLE, table: 'post')]
class Post
{
    public const ROLE = 'post';

    #[Column(type: 'bigPrimary')]
    public ?int $id = null;

    #[Column(type: 'string')]
    public string $title = '';

    #[Column(type: 'boolean')]
    public bool $public = false;

    #[Column(type: 'text')]
    public string $content = '';

    #[Column(type: 'datetime', name: 'published_at', nullable: true)]
    public ?\DateTimeImmutable $publishedAt = null;

    #[BelongsTo(target: User::class, innerKey: 'userId', fkAction: 'NO ACTION')]
    public User $user;

    #[Column(type: 'bigInteger', name: 'user_id')]
    public ?int $userId = null;

    /** @var iterable<Tag> */
    #[ManyToMany(target: Tag::class, throughInnerKey: 'postId', throughOuterKey: 'tagId', through: PostTag::class)]
    public iterable $tags = [];

    /** @var iterable<Comment> */
    #[HasMany(target: Comment::class, innerKey: 'id', outerKey: 'postId', fkCreate: false)]
    public iterable $comments = [];
}
