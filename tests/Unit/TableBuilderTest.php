<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Database\Exception\SchemaException;
use Cycle\Testing\Database\Memory;
use Cycle\Testing\Database\Schema\Purger;
use Cycle\Testing\Database\Schema\TableBuilder;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

#[Test]
final class TableBuilderTest
{
    public function rendersColumnsFlagsAndDefaults(): void
    {
        $db = Memory::database();
        (new TableBuilder($db))->makeTable('t', [
            'id' => 'primary',
            'name' => 'string(32)',
            'note' => 'text, nullable',
            'status' => 'enum(active, disabled)',
            'amount' => 'decimal(10, 2)',
            'label' => 'string',
        ], defaults: ['label' => 'none']);

        $schema = $db->table('t')->getSchema();
        Assert::same($schema->getPrimaryKeys(), ['id']);
        Assert::same($schema->column('name')->getSize(), 32);
        Assert::true($schema->column('note')->isNullable());
        Assert::false($schema->column('name')->isNullable());
        Assert::same($schema->column('status')->getDefaultValue(), 'active');
        Assert::same($schema->column('label')->getDefaultValue(), 'none');
    }

    public function foreignKeysAndPurge(): void
    {
        $db = Memory::database();
        $builder = new TableBuilder($db);
        $builder->makeTable('parent', ['id' => 'primary']);
        $builder->makeTable('child', ['id' => 'primary', 'parent_id' => 'integer'], fk: [
            'parent_id' => ['table' => 'parent', 'column' => 'id'],
        ]);
        $builder->makeIndex('child', ['parent_id'], unique: true);

        Assert::true($db->table('child')->getSchema()->hasForeignKey(['parent_id']));
        Assert::true($db->table('child')->getSchema()->index(['parent_id'])->isUnique());

        Purger::dropAll($db);
        Assert::same($db->getTables(), []);
    }

    public function invalidTypeFails(): never
    {
        Expect::exception(SchemaException::class);
        (new TableBuilder(Memory::database()))->makeTable('t', ['id' => 'nosuchtype']);
    }
}
