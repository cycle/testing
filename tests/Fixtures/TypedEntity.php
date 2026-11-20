<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Fixtures;

use Cycle\ORM\SchemaInterface as S;

class TypedEntity
{
    public int $id;
    public string $name;
    public ?string $note = null;

    /**
     * Mapped by {@see StrictMapper}, which throws on an uninitialized property.
     */
    public static function schema(): array
    {
        return [
            'typed' => [
                S::ENTITY => self::class,
                S::MAPPER => StrictMapper::class,
                S::DATABASE => 'default',
                S::TABLE => 'typed',
                S::PRIMARY_KEY => 'id',
                S::COLUMNS => ['id', 'name', 'note'],
                S::SCHEMA => [],
                S::RELATIONS => [],
            ],
        ];
    }
}
