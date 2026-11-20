<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Feature\Common;

use Cycle\Database\Exception\StatementException;
use Cycle\Database\Exception\StatementException\ConnectionException;
use Cycle\Testing\Database\Attribute\WrapDriver;
use Cycle\Testing\Database\DatabaseTestCase;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

#[Test]
#[WrapDriver]
final class WrapDriverTest extends DatabaseTestCase
{
    public function countsDisconnects(): void
    {
        $driver = $this->wrapDriver();
        Assert::same($driver, $this->database->getDriver());

        $driver->connect();
        $driver->disconnect();

        Assert::same($driver->disconnectCalls, 1);
        Assert::false($driver->isConnected());
    }

    public function oneFailedBeginIsRetried(): void
    {
        $this->wrapDriver()->exceptionOnTransactionBegin = 1;

        $this->database->begin();

        Assert::same($this->database->getDriver()->getTransactionLevel(), 1);
        $this->database->rollback();
    }

    public function knobsAreResetBetweenTests(): void
    {
        Assert::same($this->wrapDriver()->disconnectCalls, 0);
    }

    public function queriesRunThroughTheWrappedPdo(): void
    {
        $this->makeTable('wrapped', ['id' => 'primary', 'name' => 'string(32)']);
        $table = $this->database->table('wrapped');

        $table->insertMultiple(['name'], [['a'], ['b']]);

        Assert::same($table->select('name')->orderBy('id')->fetchAll(), [['name' => 'a'], ['name' => 'b']]);
        Assert::same($table->count(), 2);
    }

    public function queryCallbackExecutesTheStatements(): void
    {
        $this->makeTable('called', ['id' => 'primary', 'name' => 'string(32)']);
        $seen = [];
        $this->wrapDriver()->setQueryCallback(static function (\PDOStatement $statement, ?array $params) use (&$seen): bool {
            $seen[] = $statement->queryString;
            return $statement->execute($params);
        });

        $this->database->table('called')->insertOne(['name' => 'a']);
        $rows = $this->database->query('SELECT name FROM called WHERE name = ?', ['a'])->fetchAll();

        Assert::count(\preg_grep('~^\s*INSERT INTO~i', $seen), 1);
        Assert::string(\end($seen))->contains('WHERE name = ?');
        Assert::same($rows, [['name' => 'a']]);
    }

    public function statementErrorsAreMappedByTheDriver(): void
    {
        try {
            $this->database->query('SELECT * FROM no_such_table')->fetchAll();
        } catch (StatementException $e) {
            Assert::false($e instanceof ConnectionException);
            Assert::string($e->getQuery())->contains('no_such_table');
            return;
        }

        Assert::fail('Querying a missing table did not fail.');
    }

    public function failsTransactionBegin(): never
    {
        # The driver reconnects and retries once, so the first two begins must fail.
        $this->wrapDriver()->exceptionOnTransactionBegin = 2;

        Expect::exception(ConnectionException::class);
        $this->database->begin();
    }
}
