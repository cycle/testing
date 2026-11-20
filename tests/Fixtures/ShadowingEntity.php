<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Fixtures;

use Cycle\ORM\SchemaInterface as S;

/**
 * Redeclares the private `name` of its parent; `id` stays uninitialized until saved.
 */
class ShadowingEntity extends ShadowedParent
{
    public int $id;
    public string $name;

    public static function schema(): array
    {
        return [
            'shadowing' => [
                S::ENTITY => self::class,
                S::MAPPER => StrictMapper::class,
                S::DATABASE => 'default',
                S::TABLE => 'shadowing',
                S::PRIMARY_KEY => 'id',
                S::COLUMNS => ['id', 'name'],
                S::SCHEMA => [],
                S::RELATIONS => [],
            ],
        ];
    }
}
