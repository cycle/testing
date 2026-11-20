<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Feature\Common;

use Cycle\Database\ForeignKeyInterface;
use Cycle\Testing\Database\DatabaseTestCase;
use Cycle\Testing\Database\Schema\Purger;
use Testo\Assert;
use Testo\Test;

#[Test]
final class PurgerTest extends DatabaseTestCase
{
    public function dropsTablesLinkedByForeignKeysBothWays(): void
    {
        $this->makeTable('author', ['id' => 'primary', 'book_id' => 'integer, nullable']);
        $this->makeTable('book', ['id' => 'primary', 'author_id' => 'integer']);
        $this->makeFK('book', 'author_id', 'author', 'id');
        $this->makeFK('author', 'book_id', 'book', 'id', ForeignKeyInterface::NO_ACTION, ForeignKeyInterface::NO_ACTION);
        $this->database->table('author')->insertOne(['book_id' => null]);
        $this->database->table('book')->insertOne(['author_id' => 1]);
        Assert::true($this->schema('book')->hasForeignKey(['author_id']));
        Assert::true($this->schema('author')->hasForeignKey(['book_id']));

        Purger::dropTables($this->database->getDriver());

        Assert::same($this->database->getDriver()->getSchemaHandler()->getTableNames(), []);
    }

    public function nothingToDropRunsNoDdl(): void
    {
        $capture = $this->context->queryLog()->capture(fn() => Purger::dropTables($this->database->getDriver()));

        $ddl = \array_filter(
            $capture['queries'],
            static fn(string $query): bool => \preg_match('~^\s*(SELECT|SHOW)\b~i', $query) !== 1,
        );
        Assert::same($ddl, []);
    }

    public function dropAllKeepsTablesOutsideThePrefix(): void
    {
        $this->makeTable('kept', ['id' => 'primary']);
        $this->makeTable('gone_x', ['id' => 'primary']);

        Purger::dropAll($this->db(prefix: 'gone_'));

        Assert::true($this->database->hasTable('kept'));
        Assert::false($this->database->hasTable('gone_x'));
    }
}
