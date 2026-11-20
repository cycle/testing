<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Fixtures;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;

#[Entity(table: 'note', database: 'secondary')]
class Note
{
    #[Column(type: 'primary')]
    public ?int $id = null;

    #[Column(type: 'string')]
    public string $text = '';
}
