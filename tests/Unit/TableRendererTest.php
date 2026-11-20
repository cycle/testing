<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Database\Schema\AbstractTable;
use Cycle\Testing\Database\Memory;
use Cycle\Testing\Database\Schema\TableRenderer;
use Testo\Assert;
use Testo\Test;

#[Test]
final class TableRendererTest
{
    public function notNullColumnsGetATypedDefault(): void
    {
        $table = self::render([
            'i' => 'integer',
            'f' => 'float',
            'b' => 'boolean',
            's' => 'string',
            'at' => 'datetime',
            'day' => 'date',
            'e' => 'enum(on, off)',
        ]);

        Assert::same($table->column('i')->getDefaultValue(), 0);
        Assert::same($table->column('f')->getDefaultValue(), 0.0);
        Assert::same($table->column('b')->getDefaultValue(), false);
        Assert::same($table->column('s')->getDefaultValue(), '');
        Assert::instanceOf($table->column('at')->getDefaultValue(), \DateTimeInterface::class);
        Assert::same($table->column('at')->getDefaultValue()->format('Y-m-d H:i:s'), '1970-01-01 00:00:00');
        Assert::same($table->column('day')->getDefaultValue(), '1970-01-01');
        Assert::same($table->column('e')->getDefaultValue(), 'on');
    }

    public function primaryAndRequiredColumnsGetNoDefault(): void
    {
        $table = self::render(['id' => 'primary', 'name' => 'string, required', 'code' => 'string(8), primary']);

        Assert::null($table->column('id')->getDefaultValue());
        Assert::null($table->column('name')->getDefaultValue());
        Assert::null($table->column('code')->getDefaultValue());
    }

    public function primaryFlagMakesTheKey(): void
    {
        $table = self::render(['a' => 'integer, primary', 'b' => 'string(8), primary', 'c' => 'string']);

        Assert::same($table->getPrimaryKeys(), ['a', 'b']);
    }

    public function nullDefaultMakesTheColumnNullable(): void
    {
        $table = self::render(['n' => 'string', 'v' => 'string'], ['n' => null, 'v' => 'x']);

        Assert::true($table->column('n')->isNullable());
        Assert::false($table->column('v')->isNullable());
        Assert::same($table->column('v')->getDefaultValue(), 'x');
    }

    public function typeIsCaseInsensitive(): void
    {
        $table = self::render(['name' => 'STRING(10)']);

        Assert::same($table->column('name')->getSize(), 10);
    }

    public function onlyWholeNumbersBecomeSizes(): void
    {
        $table = self::render(['e' => 'enum(1a, a1, 2)']);

        Assert::same($table->column('e')->getEnumValues(), ['1a', 'a1', '2']);
    }

    /**
     * @param array<non-empty-string, non-empty-string> $columns
     * @param array<non-empty-string, mixed> $defaults
     */
    private static function render(array $columns, array $defaults = []): AbstractTable
    {
        $table = Memory::database()->table('t')->getSchema();
        (new TableRenderer())->renderColumns($table, $columns, $defaults);

        return $table;
    }
}
