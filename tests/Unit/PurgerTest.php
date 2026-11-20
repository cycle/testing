<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Database\Database;
use Cycle\Testing\Database\Memory;
use Cycle\Testing\Database\Mock\StubDriver;
use Cycle\Testing\Database\QueryLog;
use Cycle\Testing\Database\Schema\Purger;
use Cycle\Testing\Database\Schema\TableBuilder;
use Testo\Assert;
use Testo\Test;

#[Test]
final class PurgerTest
{
    public function dropAllDropsTheKeysBeforeTheTables(): void
    {
        $database = self::referencedRows(Memory::database());

        Purger::dropAll($database);

        Assert::same($database->getTables(), []);
    }

    public function dropTablesDropsEachTableWithTheChecksOff(): void
    {
        $log = new QueryLog();
        $database = self::referencedRows(Memory::manager(log: $log)->database());

        $run = $log->capture(static fn() => Purger::dropTables($database->getDriver()));

        Assert::same(\array_slice($run['queries'], 1), [
            'PRAGMA foreign_keys = OFF',
            'DROP TABLE "a_parent"',
            'DROP TABLE "b_child"',
            'PRAGMA foreign_keys = ON',
        ]);
        Assert::same($database->getTables(), []);
        Assert::same($database->query('PRAGMA foreign_keys')->fetchColumn(), 1);
    }

    public function dropTablesOnAnEmptyDatabaseOnlyLists(): void
    {
        $log = new QueryLog();
        $database = Memory::manager(log: $log)->database();

        $run = $log->capture(static fn() => Purger::dropTables($database->getDriver()));

        Assert::count($run['queries'], 1);
    }

    public function dropTablesOfAnotherDriverFallsBackToDropAll(): void
    {
        $database = self::referencedRows(new Database('default', '', StubDriver::make()));

        Purger::dropTables($database->getDriver());

        Assert::same($database->getTables(), []);
    }

    /**
     * `b_child` references `a_parent`, both have a row, and foreign keys are enforced.
     */
    private static function referencedRows(Database $database): Database
    {
        $database->execute('PRAGMA foreign_keys = ON');
        $builder = new TableBuilder($database);
        $builder->makeTable('a_parent', ['id' => 'primary']);
        $builder->makeTable('b_child', ['id' => 'primary', 'parent_id' => 'integer'], fk: [
            'parent_id' => ['table' => 'a_parent', 'column' => 'id', 'onDelete' => 'NO ACTION'],
        ]);
        $database->insert('a_parent')->values(['id' => 1])->run();
        $database->insert('b_child')->values(['id' => 1, 'parent_id' => 1])->run();

        return $database;
    }
}
