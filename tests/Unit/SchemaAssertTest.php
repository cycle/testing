<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Database\Database;
use Cycle\Database\Driver\Postgres\Schema\PostgresForeignKey;
use Cycle\Database\ForeignKeyInterface;
use Cycle\Database\Schema\AbstractTable;
use Cycle\Testing\Database\Assert\SchemaAssert;
use Cycle\Testing\Database\Memory;
use Cycle\Testing\Database\Schema\TableBuilder;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
final class SchemaAssertTest
{
    private const COLUMNS = ['id' => 'primary', 'parent_id' => 'integer', 'amount' => 'decimal(10, 2)', 'status' => 'enum(a, b)'];

    public static function declaredChanges(): iterable
    {
        yield 'primary key' => [static function (AbstractTable $t): void {
            $t->setPrimaryKeys(['id', 'parent_id']);
        }, 'Primary keys changed'];
        yield 'column added' => [static function (AbstractTable $t): void {
            $t->string('extra');
        }, 'Column number has changed'];
        yield 'index added' => [static function (AbstractTable $t): void {
            $t->index(['status']);
        }, 'Index number has changed'];
        yield 'column renamed' => [static function (AbstractTable $t): void {
            $t->renameColumn('status', 'state');
        }, 'Column t.state has been removed'];
        yield 'index replaced' => [static function (AbstractTable $t): void {
            $t->dropIndex(['amount']);
            $t->index(['status'])->unique();
        }, 'has been removed'];
        yield 'FK replaced' => [static function (AbstractTable $t): void {
            $t->dropForeignKey(['parent_id']);
            $t->foreignKey(['status'])->references('parent', ['id']);
            $t->dropIndex(['status']);
        }, 'FK t_status_fk has been removed'];
        yield 'column type' => [static function (AbstractTable $t): void {
            $t->string('status');
        }, 'Column t.status type has been changed'];
        yield 'column scale' => [static function (AbstractTable $t): void {
            $t->decimal('amount', 10, 3);
        }, 'Column t.amount scale has been changed'];
        yield 'column precision' => [static function (AbstractTable $t): void {
            $t->decimal('amount', 9, 2);
        }, 'Column t.amount precision has been changed'];
        yield 'enum values' => [static function (AbstractTable $t): void {
            $t->enum('status', ['a', 'c']);
        }, 'Column t.status enum values have been changed'];
        yield 'nullability' => [static function (AbstractTable $t): void {
            $t->column('parent_id')->nullable(true);
        }, 'Column t.parent_id has been changed'];
        yield 'index uniqueness' => [static function (AbstractTable $t): void {
            $t->index(['amount'])->unique(false);
        }, 'uniqueness has been changed'];
        yield 'FK delete rule' => [static function (AbstractTable $t): void {
            $t->foreignKey(['parent_id'])->onDelete(ForeignKeyInterface::NO_ACTION);
        }, 'delete rule has been changed'];
        yield 'FK update rule' => [static function (AbstractTable $t): void {
            $t->foreignKey(['parent_id'])->onUpdate(ForeignKeyInterface::NO_ACTION);
        }, 'update rule has been changed'];
    }

    public static function foreignKeyChanges(): iterable
    {
        yield 'columns' => [static function (AbstractTable $t): mixed {
            return $t->foreignKey(['status'])->references('parent', ['id']);
        }, 'columns have been changed'];
        yield 'foreign table' => [static function (AbstractTable $t): mixed {
            return $t->foreignKey(['parent_id'])->references('t', ['id']);
        }, 'table has been changed'];
        yield 'foreign keys' => [static function (AbstractTable $t): mixed {
            return $t->foreignKey(['parent_id'])->references('parent', ['code']);
        }, 'keys have been changed'];
    }

    public function savedTableMatchesTheDatabase(): void
    {
        $table = self::saved()->table('t')->getSchema();

        SchemaAssert::sameAsInDb($table);
    }

    public function comparesWithTheGivenTarget(): never
    {
        $database = self::saved();
        (new TableBuilder($database))->makeTable('other', self::COLUMNS, fk: ['parent_id' => ['table' => 'parent', 'column' => 'id']]);

        Expect::exception(AssertionException::class)->withMessageContaining('Table name changed');

        SchemaAssert::sameAsInDb($database->table('t')->getSchema(), $database->table('other')->getSchema());
    }

    public function foreignKeyMissingFromTheTargetFails(): never
    {
        $target = Memory::database();
        $builder = new TableBuilder($target);
        $builder->makeTable('parent', ['id' => 'primary', 'code' => 'integer']);
        $builder->makeTable('t', self::COLUMNS);
        $builder->makeIndex('t', ['parent_id']);
        $builder->makeIndex('t', ['amount'], unique: true);

        Expect::exception(AssertionException::class)->withMessageContaining('FK number has changed');

        SchemaAssert::sameAsInDb(self::saved()->table('t')->getSchema(), $target->table('t')->getSchema());
    }

    /**
     * @param \Closure(AbstractTable): void $change
     */
    #[DataProvider('declaredChanges')]
    public function declaredChangeIsReported(\Closure $change, string $message): never
    {
        $table = self::saved()->table('t')->getSchema();
        $change($table);

        Expect::exception(AssertionException::class)->withMessageContaining($message);

        SchemaAssert::sameAsInDb($table);
    }

    public function missingColumnFails(): never
    {
        $column = self::saved()->table('t')->getSchema()->column('amount');

        Expect::exception(AssertionException::class)->withMessageContaining('Column t.amount is missing');

        SchemaAssert::sameColumn($column, null);
    }

    public function missingIndexFails(): never
    {
        $index = self::saved()->table('t')->getSchema()->index(['amount']);

        Expect::exception(AssertionException::class)->withMessageContaining("Index {$index->getName()} is missing");

        SchemaAssert::sameIndex($index, null);
    }

    public function indexOnOtherColumnsFails(): never
    {
        $table = self::saved()->table('t')->getSchema();

        Expect::exception(AssertionException::class)->withMessageContaining('columns have been changed');

        SchemaAssert::sameIndex($table->index(['amount']), $table->index(['status']));
    }

    public function indexUnderAnotherNameFails(): never
    {
        $table = self::saved()->table('t')->getSchema();
        $renamed = clone $table->index(['amount']);
        $renamed->setName('renamed');

        Expect::exception(AssertionException::class)->withMessageContaining('has been changed');

        SchemaAssert::sameIndex($table->index(['amount']), $renamed);
    }

    public function missingForeignKeyFails(): never
    {
        $key = self::saved()->table('t')->getSchema()->foreignKey(['parent_id']);

        Expect::exception(AssertionException::class)->withMessageContaining("FK {$key->getName()} is missing");

        SchemaAssert::sameForeignKey($key, null);
    }

    /**
     * @param \Closure(AbstractTable): mixed $declare
     */
    #[DataProvider('foreignKeyChanges')]
    public function foreignKeyChangeIsReported(\Closure $declare, string $message): never
    {
        $table = self::saved()->table('t')->getSchema();
        $saved = clone $table->foreignKey(['parent_id']);
        $declared = $declare($table);

        Expect::exception(AssertionException::class)->withMessageContaining($message);

        SchemaAssert::sameForeignKey($saved, $declared);
    }

    public function foreignKeyUnderAnotherNameFails(): never
    {
        # Unlike SQLite's, a Postgres key compares its name too.
        $key = (new PostgresForeignKey('t', '', 'fk_t_parent'))->columns(['parent_id'])->references('parent', ['id']);
        $renamed = clone $key;
        $renamed->setName('renamed');

        Expect::exception(AssertionException::class)->withMessageContaining("FK {$key->getName()} has been changed");

        SchemaAssert::sameForeignKey($key, $renamed);
    }

    /**
     * A database with `parent` and `t`: a unique index on `t.amount` and a cascading FK `t.parent_id`.
     */
    private static function saved(): Database
    {
        $database = Memory::database();
        $builder = new TableBuilder($database);
        $builder->makeTable('parent', ['id' => 'primary', 'code' => 'integer']);
        $builder->makeTable('t', self::COLUMNS, fk: ['parent_id' => ['table' => 'parent', 'column' => 'id']]);
        $builder->makeIndex('t', ['amount'], unique: true);

        return $database;
    }
}
