<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Database\Exception\StatementException\ConnectionException;
use Cycle\Testing\Database\Mock\TestPDO;
use Cycle\Testing\Database\Mock\TestPDOStatement;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

#[Test]
final class TestPDOTest
{
    public function delegatesToThePdo(): void
    {
        $pdo = self::pdo();

        Assert::same($pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)'), 0);
        Assert::same($pdo->exec("INSERT INTO t (name) VALUES ('a')"), 1);
        Assert::same($pdo->lastInsertId(), '1');
        Assert::same($pdo->quote("it's"), "'it''s'");
        Assert::same($pdo->errorCode(), '00000');
        Assert::same($pdo->errorInfo()[0], '00000');
        Assert::true($pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC));
        Assert::same($pdo->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE), \PDO::FETCH_ASSOC);
    }

    public function unknownMethodsReachThePdo(): void
    {
        Assert::true(\in_array('sqlite', self::pdo()->getAvailableDrivers(), true));
    }

    public function runsTransactions(): void
    {
        $pdo = self::pdo();

        Assert::true($pdo->beginTransaction());
        Assert::true($pdo->inTransaction());
        Assert::true($pdo->commit());
        $pdo->beginTransaction();
        Assert::true($pdo->rollBack());
        Assert::false($pdo->inTransaction());
    }

    public function failsTheGivenNumberOfBegins(): void
    {
        $failures = 1;
        $pdo = new TestPDO(new \PDO('sqlite::memory:'), $failures, null);

        try {
            $pdo->beginTransaction();
            Assert::fail('The first begin did not fail.');
        } catch (ConnectionException $e) {
            Assert::same($e->getQuery(), 'Test exception');
        }

        Assert::same($failures, 0);
        Assert::true($pdo->beginTransaction());
    }

    public function queryWrapsTheStatement(): void
    {
        $statement = self::pdo()->query('SELECT 1 AS one');

        Assert::instanceOf($statement, TestPDOStatement::class);
        Assert::same($statement->fetchColumn(), 1);
    }

    public function failedQueryGivesFalse(): void
    {
        $pdo = self::pdo();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

        Assert::false($pdo->query('SELECT * FROM missing'));
        Assert::false($pdo->prepare('SELECT * FROM missing'));
    }

    public function callbackReplacesExecute(): void
    {
        $seen = [];
        $pdo = self::pdo(static function (\PDOStatement $statement, ?array $params) use (&$seen): bool {
            $seen[] = [$statement->queryString, $params];
            return $statement->execute($params);
        });

        $statement = $pdo->prepare('SELECT ? AS v');

        Assert::true($statement->execute(['x']));
        Assert::same($seen, [['SELECT ? AS v', ['x']]]);
        Assert::same($statement->fetch(\PDO::FETCH_ASSOC), ['v' => 'x']);
    }

    public function statementDelegatesFetching(): void
    {
        $pdo = self::pdo();
        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO t (name) VALUES ('a'), ('b'), ('c')");

        $statement = $pdo->prepare('SELECT id, name FROM t WHERE id >= :min ORDER BY id');
        $min = 1;
        Assert::true($statement->bindParam(':min', $min, \PDO::PARAM_INT));
        $statement->execute();

        Assert::same($statement->columnCount(), 2);
        Assert::same($statement->getColumnMeta(1)['name'], 'name');
        Assert::same($statement->fetch(\PDO::FETCH_NUM), [1, 'a']);
        Assert::same($statement->fetchObject()->name, 'b');
        Assert::same($statement->fetchAll(\PDO::FETCH_COLUMN, 1), ['c']);
        Assert::true($statement->closeCursor());
    }

    public function statementAsksThePdoStatementForTheNextRowset(): never
    {
        $statement = self::pdo()->query('SELECT 1');

        # SQLite has no multiple rowsets: the PDO statement refuses.
        Expect::exception(\PDOException::class);
        $statement->nextRowset();
    }

    public function statementBindsValuesAndColumns(): void
    {
        $statement = self::pdo()->prepare('SELECT :v AS v');
        Assert::true($statement->bindValue(':v', 'x'));
        $statement->execute();

        $v = null;
        Assert::true($statement->bindColumn('v', $v));
        Assert::true($statement->setFetchMode(\PDO::FETCH_ASSOC));
        Assert::same($statement->fetch(), ['v' => 'x']);
        Assert::same($statement->rowCount(), 0);
        Assert::same($statement->errorCode(), '00000');
        Assert::same($statement->errorInfo()[0], '00000');
    }

    public function statementIsIterable(): void
    {
        $statement = self::pdo()->query('SELECT 1 AS a UNION ALL SELECT 2');
        $statement->setFetchMode(\PDO::FETCH_NUM);

        Assert::same(\iterator_to_array($statement->getIterator(), false), [[1], [2]]);
    }

    public function statementAttributesReachThePdoStatement(): never
    {
        $statement = self::pdo()->prepare('SELECT 1');

        Expect::exception(\PDOException::class);
        $statement->getAttribute(\PDO::ATTR_CURSOR_NAME);
    }

    public function statementAttributeWritesReachThePdoStatement(): void
    {
        $pdo = new \PDO('sqlite::memory:', options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT]);
        $failures = 0;
        $statement = (new TestPDO($pdo, $failures, null))->prepare('SELECT 1');

        # Whether SQLite accepts the attribute depends on the PHP version: the wrapper answers as PDO does.
        Assert::same(
            $statement->setAttribute(\PDO::ATTR_CURSOR_NAME, 'c'),
            $pdo->prepare('SELECT 1')->setAttribute(\PDO::ATTR_CURSOR_NAME, 'c'),
        );
    }

    public function statementDumpsItsParams(): void
    {
        $statement = self::pdo()->prepare('SELECT :v');

        \ob_start();
        $result = $statement->debugDumpParams();
        $dump = (string) \ob_get_clean();

        Assert::null($result);
        Assert::string($dump)->contains('SELECT :v');
    }

    private static function pdo(?\Closure $queryCallback = null): TestPDO
    {
        $failures = 0;

        return new TestPDO(new \PDO('sqlite::memory:'), $failures, $queryCallback);
    }
}
