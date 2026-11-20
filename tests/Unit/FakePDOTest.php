<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Database\Driver\PDOStatementInterface;
use Cycle\Testing\Database\Mock\FakePDO;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Test;

#[Test]
final class FakePDOTest
{
    public function queryExecutesAtOnce(): void
    {
        $pdo = (new FakePDO())->willReturn([['id' => 1], ['id' => 2]]);

        $statement = $pdo->query('SELECT id FROM t');

        Assert::same($pdo->executed, [['sql' => 'SELECT id FROM t', 'params' => null]]);
        Assert::same($statement->rowCount(), 2);
        Assert::same(\iterator_to_array($statement), [['id' => 1], ['id' => 2]]);
    }

    public function execRecordsAndCountsTheQueuedRows(): void
    {
        $pdo = (new FakePDO())->willReturn([['a' => 1], ['a' => 2], ['a' => 3]]);

        Assert::same($pdo->exec('DELETE FROM t'), 3);
        Assert::same($pdo->exec('DELETE FROM t'), 0);
        Assert::same($pdo->queries(), ['DELETE FROM t', 'DELETE FROM t']);
    }

    public function execThrowsTheQueuedException(): never
    {
        $pdo = (new FakePDO())->willThrow(new \RuntimeException('gone'));

        Expect::exception(\RuntimeException::class)->withMessage('gone');
        $pdo->exec('DROP TABLE t');
    }

    public function tracksTheTransaction(): void
    {
        $pdo = new FakePDO();

        Assert::false($pdo->inTransaction());
        Assert::true($pdo->beginTransaction());
        Assert::true($pdo->inTransaction());
        Assert::true($pdo->commit());
        Assert::false($pdo->inTransaction());

        $pdo->beginTransaction();
        Assert::true($pdo->rollBack());
        Assert::false($pdo->inTransaction());
    }

    public function lastInsertIdIsScriptable(): void
    {
        $pdo = new FakePDO();
        Assert::same($pdo->lastInsertId(), '1');

        $pdo->lastInsertId = '42';
        Assert::same($pdo->lastInsertId('seq'), '42');
    }

    public function keepsAttributes(): void
    {
        $pdo = new FakePDO();

        Assert::null($pdo->getAttribute(\PDO::ATTR_CASE));
        Assert::true($pdo->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER));
        Assert::same($pdo->getAttribute(\PDO::ATTR_CASE), \PDO::CASE_LOWER);
    }

    public function reportsNoError(): void
    {
        $pdo = new FakePDO();

        Assert::null($pdo->errorCode());
        Assert::same($pdo->errorInfo(), ['00000', null, null]);
    }

    public function quotesByDoublingQuotes(): void
    {
        Assert::same((new FakePDO())->quote("it's"), "'it''s'");
    }

    public function boundValuesAreTheParamsWhenExecuteGetsNone(): void
    {
        $pdo = new FakePDO();
        $statement = $pdo->prepare('SELECT ? , ?');
        $byRef = 'b';

        Assert::true($statement->bindValue(1, 'a'));
        Assert::true($statement->bindParam(2, $byRef));
        $statement->execute();
        $statement->execute(['x']);

        Assert::same($pdo->executed[0]['params'], [1 => 'a', 2 => 'b']);
        Assert::same($pdo->executed[1]['params'], ['x']);
    }

    public function withoutBindingsExecutePassesNull(): void
    {
        $pdo = new FakePDO();

        $pdo->prepare('SELECT 1')->execute();

        Assert::null($pdo->executed[0]['params']);
    }

    #[DataSet([\PDO::FETCH_ASSOC, ['id' => 1, 'name' => 'a']], 'assoc')]
    #[DataSet([\PDO::FETCH_NUM, [1, 'a']], 'num')]
    #[DataSet([\PDO::FETCH_COLUMN, 1], 'column')]
    public function fetchShapesTheRow(int $mode, mixed $expected): void
    {
        $statement = self::executed([['id' => 1, 'name' => 'a']]);

        Assert::same($statement->fetch($mode), $expected);
        Assert::false($statement->fetch($mode));
    }

    public function fetchObjModeGivesAnObject(): void
    {
        $row = self::executed([['id' => 1]])->fetch(\PDO::FETCH_OBJ);

        Assert::instanceOf($row, \stdClass::class);
        Assert::same($row->id, 1);
    }

    public function fetchColumnModeOfAnEmptyRowIsNull(): void
    {
        Assert::null(self::executed([[]])->fetch(\PDO::FETCH_COLUMN));
    }

    public function fetchAllShapesEveryRowAndDrains(): void
    {
        $statement = self::executed([['id' => 1, 'n' => 'a'], ['id' => 2, 'n' => 'b']]);

        Assert::same($statement->fetchAll(\PDO::FETCH_NUM), [[1, 'a'], [2, 'b']]);
        Assert::same($statement->fetchAll(), []);
    }

    public function fetchColumnTakesOneValuePerRow(): void
    {
        $statement = self::executed([['id' => 1, 'n' => 'a'], ['id' => 2]]);

        Assert::same($statement->fetchColumn(1), 'a');
        Assert::null($statement->fetchColumn(1));
        Assert::false($statement->fetchColumn());
    }

    public function fetchObjectGivesAStdClassThenFalse(): void
    {
        $statement = self::executed([['id' => 7]]);

        $row = $statement->fetchObject();

        Assert::instanceOf($row, \stdClass::class);
        Assert::same($row->id, 7);
        Assert::false($statement->fetchObject());
    }

    public function columnCountComesFromTheFirstRow(): void
    {
        $statement = self::executed([['id' => 1, 'n' => 'a', 'x' => null]]);

        Assert::same($statement->columnCount(), 3);
        Assert::true($statement->closeCursor());
        Assert::same($statement->columnCount(), 0);
        Assert::same($statement->rowCount(), 0);
    }

    public function statementStubsAnswerNeutrally(): void
    {
        $statement = self::executed([['id' => 1]]);
        $column = null;

        Assert::true($statement->bindColumn(1, $column));
        Assert::true($statement->setAttribute(\PDO::ATTR_CURSOR_NAME, 'c'));
        Assert::null($statement->getAttribute(\PDO::ATTR_CURSOR_NAME));
        Assert::true($statement->setFetchMode(\PDO::FETCH_NUM));
        Assert::false($statement->getColumnMeta(0));
        Assert::false($statement->nextRowset());
        Assert::null($statement->errorCode());
        Assert::same($statement->errorInfo(), ['00000', null, null]);
        Assert::null($statement->debugDumpParams());
        Assert::same($statement->fetch(), ['id' => 1]);
    }

    /**
     * @param list<array<array-key, mixed>> $rows
     */
    private static function executed(array $rows): PDOStatementInterface
    {
        $statement = (new FakePDO())->willReturn($rows)->prepare('SELECT');
        $statement->execute();

        return $statement;
    }
}
