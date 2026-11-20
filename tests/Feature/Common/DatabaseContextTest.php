<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Feature\Common;

use Cycle\Testing\Database\Assert\QueryAssert;
use Cycle\Testing\Database\Assert\SchemaAssert;
use Cycle\Testing\Database\Assert\SqlAssert;
use Cycle\Testing\Database\Attribute\ExceptDriver;
use Cycle\Testing\Database\Attribute\OnlyDriver;
use Cycle\Testing\Database\DatabaseTestCase;
use Cycle\Testing\Database\Driver;
use Testo\Assert;
use Testo\Test;

#[Test]
final class DatabaseContextTest extends DatabaseTestCase
{
    public function contextIsAttached(): void
    {
        Assert::same($this->database->getName(), 'default');
        Assert::same($this->dbal->database()->getDriver(), $this->database->getDriver());
        Assert::same($this->context->driver, $this->driver);
    }

    public function connectsToServer(): void
    {
        Assert::notSame($this->serverVersion(), '');
    }

    public function sqlAssertUsesDriverQuotes(): void
    {
        SqlAssert::same(
            $this->database->select('id')->from('users')->where('id', 1),
            'SELECT {id} FROM {users} WHERE {id} = ?',
        );
        SqlAssert::sameParameters($this->database->select()->from('users')->where('id', 1), [1]);
    }

    public function queryLogCountsReadsAndWrites(): void
    {
        $this->makeTable('counted', ['id' => 'primary', 'name' => 'string']);
        $table = $this->database->table('counted');

        QueryAssert::writes(2, static function () use ($table): void {
            $table->insertOne(['name' => 'a']);
            $table->insertOne(['name' => 'b']);
        });
        $rows = QueryAssert::reads(1, static fn(): array => $table->select()->fetchAll());

        Assert::count($rows, 2);
    }

    public function tableBuilderMatchesDatabase(): void
    {
        $this->makeTable('parent', ['id' => 'primary', 'name' => 'string(64)']);
        $this->makeTable(
            'child',
            ['id' => 'primary', 'parent_id' => 'integer', 'note' => 'string(64), nullable'],
            fk: ['parent_id' => ['table' => 'parent', 'column' => 'id']],
        );
        $this->makeIndex('child', ['note'], unique: false);

        $schema = $this->schema('child');
        Assert::true($schema->exists());
        Assert::true($schema->hasForeignKey(['parent_id']));
        SchemaAssert::sameAsInDb($schema);
    }

    public function dbVariantSharesServer(): void
    {
        $this->makeTable('shared', ['id' => 'primary']);

        Assert::true($this->db()->hasTable('shared'));
        # A variant is another connection: the same server database, but a separate in-memory SQLite one.
        $variant = $this->db(driverConfig: ['options' => ['withDatetimeMicroseconds' => true]]);
        Assert::same($variant->hasTable('shared'), $this->driver !== Driver::SQLite);
    }

    #[OnlyDriver(Driver::Postgres, Driver::SQLServer)]
    public function onlyOnServersWithSchemas(): void
    {
        Assert::true(\in_array($this->driver, [Driver::Postgres, Driver::SQLServer], true));
    }

    #[ExceptDriver(Driver::SQLite)]
    public function neverOnSqlite(): void
    {
        Assert::notSame($this->driver, Driver::SQLite);
    }
}
