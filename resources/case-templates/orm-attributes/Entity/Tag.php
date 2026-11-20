<?php

declare(strict_types=1);

namespace Cycle\Testing\CaseTemplate\OrmAttributes\Entity;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\ManyToMany;

#[Entity(role: Tag::ROLE, table: 'tag')]
class Tag
{
    public const ROLE = 'tag';

    #[Column(type: 'bigPrimary')]
    public ?int $id = null;

    #[Column(type: 'string')]
    public string $label;

    /** @var iterable<Post> */
    #[ManyToMany(target: Post::class, throughInnerKey: 'tagId', throughOuterKey: 'postId', through: PostTag::class)]
    public iterable $posts = [];
}
