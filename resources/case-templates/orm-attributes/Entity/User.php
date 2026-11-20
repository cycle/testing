<?php

declare(strict_types=1);

namespace Cycle\Testing\CaseTemplate\OrmAttributes\Entity;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\HasMany;

#[Entity(role: User::ROLE, table: 'user')]
class User
{
    public const ROLE = 'user';

    #[Column(type: 'bigPrimary')]
    public ?int $id = null;

    #[Column(type: 'string')]
    public string $login;

    #[Column(type: 'string', name: 'password_hash')]
    public string $passwordHash;

    /** @var iterable<Post> */
    #[HasMany(target: Post::class, innerKey: 'id', outerKey: 'userId', fkCreate: false)]
    public iterable $posts = [];

    /** @var iterable<Comment> */
    #[HasMany(target: Comment::class, innerKey: 'id', outerKey: 'userId', fkCreate: false)]
    public iterable $comments = [];
}
