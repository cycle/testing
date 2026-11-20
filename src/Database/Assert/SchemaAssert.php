<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Assert;

use Cycle\Database\Database;
use Cycle\Database\Schema\AbstractColumn;
use Cycle\Database\Schema\AbstractForeignKey;
use Cycle\Database\Schema\AbstractIndex;
use Cycle\Database\Schema\AbstractTable;
use Testo\Assert;
use Testo\Common\Attribute\AssertMethod;

/**
 * Compares a declared table schema with another one, by default with the table as the database reports it.
 */
final class SchemaAssert
{
    #[AssertMethod]
    public static function sameAsInDb(AbstractTable $current, ?AbstractTable $target = null): void
    {
        $target ??= (new Database('schema-assert', '', $current->getDriver()))
            ->table($current->getFullName())
            ->getSchema();
        $source = $current->getState();
        $target = $target->getState();

        Assert::same($target->getName(), $source->getName(), 'Table name changed');
        Assert::same($target->getPrimaryKeys(), $source->getPrimaryKeys(), 'Primary keys changed');
        Assert::same(\count($target->getColumns()), \count($source->getColumns()), 'Column number has changed');
        Assert::same(\count($target->getIndexes()), \count($source->getIndexes()), 'Index number has changed');
        Assert::same(\count($target->getForeignKeys()), \count($source->getForeignKeys()), 'FK number has changed');

        foreach ([[$source, $target, 'removed'], [$target, $source, 'added']] as [$from, $to, $verb]) {
            foreach ($from->getColumns() as $column) {
                Assert::true($to->hasColumn($column->getName()), "Column {$column} has been {$verb}");
                self::sameColumn($column, $to->findColumn($column->getName()));
            }

            foreach ($from->getIndexes() as $index) {
                Assert::true($to->hasIndex($index->getColumnsWithSort()), "Index {$index->getName()} has been {$verb}");
                self::sameIndex($index, $to->findIndex($index->getColumnsWithSort()));
            }

            foreach ($from->getForeignKeys() as $key) {
                Assert::true($to->hasForeignKey($key->getColumns()), "FK {$key->getName()} has been {$verb}");
                self::sameForeignKey($key, $to->findForeignKey($key->getColumns()));
            }
        }
    }

    #[AssertMethod]
    public static function sameColumn(AbstractColumn $a, ?AbstractColumn $b): void
    {
        Assert::notNull($b, "Column {$a} is missing");
        Assert::same($b->getInternalType(), $a->getInternalType(), "Column {$a} type has been changed");
        Assert::same($b->getScale(), $a->getScale(), "Column {$a} scale has been changed");
        Assert::same($b->getPrecision(), $a->getPrecision(), "Column {$a} precision has been changed");
        Assert::same($b->getEnumValues(), $a->getEnumValues(), "Column {$a} enum values have been changed");
        Assert::true($a->compare($b), "Column {$a} has been changed");
    }

    #[AssertMethod]
    public static function sameIndex(AbstractIndex $a, ?AbstractIndex $b): void
    {
        Assert::notNull($b, "Index {$a->getName()} is missing");
        Assert::same($b->getColumns(), $a->getColumns(), "Index {$a->getName()} columns have been changed");
        Assert::same($b->isUnique(), $a->isUnique(), "Index {$a->getName()} uniqueness has been changed");
        Assert::true($a->compare($b), "Index {$a->getName()} has been changed");
    }

    #[AssertMethod]
    public static function sameForeignKey(AbstractForeignKey $a, ?AbstractForeignKey $b): void
    {
        Assert::notNull($b, "FK {$a->getName()} is missing");
        Assert::same($b->getColumns(), $a->getColumns(), "FK {$a->getName()} columns have been changed");
        Assert::same($b->getForeignTable(), $a->getForeignTable(), "FK {$a->getName()} table has been changed");
        Assert::same($b->getForeignKeys(), $a->getForeignKeys(), "FK {$a->getName()} keys have been changed");
        Assert::same($b->getDeleteRule(), $a->getDeleteRule(), "FK {$a->getName()} delete rule has been changed");
        Assert::same($b->getUpdateRule(), $a->getUpdateRule(), "FK {$a->getName()} update rule has been changed");
        Assert::true($a->compare($b), "FK {$a->getName()} has been changed");
    }
}
