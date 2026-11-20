<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Fixtures;

use Cycle\ORM\Mapper\Mapper;
use Cycle\ORM\SchemaInterface as S;

final class UserSchema
{
    /** Columns for TableBuilder::makeTable(). */
    public const COLUMNS = ['id' => 'primary', 'email' => 'string', 'balance' => 'float'];

    public static function schema(): array
    {
        return [
            'user' => [
                S::ENTITY => User::class,
                S::MAPPER => Mapper::class,
                S::DATABASE => 'default',
                S::TABLE => 'user',
                S::PRIMARY_KEY => 'id',
                S::COLUMNS => ['id', 'email', 'balance'],
                S::TYPECAST => ['id' => 'int', 'balance' => 'float'],
                S::SCHEMA => [],
                S::RELATIONS => [],
            ],
        ];
    }
}
