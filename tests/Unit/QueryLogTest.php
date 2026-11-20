<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Testing\Database\Assert\QueryAssert;
use Cycle\Testing\Database\Memory;
use Cycle\Testing\Database\QueryLog;
use Psr\Log\AbstractLogger;
use Testo\Assert;
use Testo\Test;

#[Test]
final class QueryLogTest
{
    public function countsOnlyExecutedQueries(): void
    {
        $log = new QueryLog();
        $log->info('Begin transaction');
        $log->info('INSERT INTO t VALUES (1)', ['elapsed' => 0.1]);
        $log->info('  update t SET a = 1', ['elapsed' => 0.1]);
        $log->info('SELECT * FROM t', ['elapsed' => 0.1]);
        $log->info('SELECT * FROM information_schema.tables', ['elapsed' => 0.1]);

        Assert::same($log->countWrites(), 2);
        Assert::same($log->countReads(), 1);
        Assert::count($log->queries(), 3);

        $log->reset();
        Assert::same($log->countReads() + $log->countWrites(), 0);
    }

    public function marksCountFromThemOn(): void
    {
        $log = new QueryLog();
        $log->info('SELECT 1', ['elapsed' => 0.1]);
        $log->info('DELETE FROM t', ['elapsed' => 0.1]);
        Assert::same([$log->readsSinceMark(), $log->writesSinceMark()], [1, 1]);

        $log->markReads();
        $log->markWrites();
        $log->info('SELECT 2', ['elapsed' => 0.1]);
        Assert::same([$log->readsSinceMark(), $log->writesSinceMark()], [1, 0]);
        Assert::same($log->countReads(), 2);

        $log->reset();
        $log->info('UPDATE t SET a = 1', ['elapsed' => 0.1]);
        Assert::same([$log->readsSinceMark(), $log->writesSinceMark()], [0, 1]);
    }

    public function captureReportsTheDifference(): void
    {
        $log = new QueryLog();
        $log->info('SELECT 1', ['elapsed' => 0.1]);

        $run = $log->capture(static function () use ($log): string {
            $log->info('DELETE FROM t', ['elapsed' => 0.1]);

            return 'done';
        });

        Assert::same($run['result'], 'done');
        Assert::same([$run['reads'], $run['writes']], [0, 1]);
        Assert::same($run['queries'], ['DELETE FROM t']);
    }

    public function forwardsEveryMessageAsALine(): void
    {
        $forward = new class extends AbstractLogger {
            /** @var list<string> */
            public array $lines = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->lines[] = (string) $message;
            }
        };
        $log = new QueryLog();
        $log->forwardTo($forward);

        $log->info(new class implements \Stringable {
            public function __toString(): string
            {
                return 'SELECT 1';
            }
        }, ['elapsed' => 0.1]);
        $log->info('Begin transaction');

        Assert::same($forward->lines, ["SELECT 1\n", "Begin transaction\n"]);
        Assert::same($log->queries(), ['SELECT 1']);
    }

    public function postgresCatalogQueriesAreNotCounted(): void
    {
        $log = new QueryLog();
        foreach (['tc.constraint_name', 'pg_indexes', 'pg_constraint', 'information_schema', 'pg_class'] as $marker) {
            $log->info("SELECT {$marker} FROM x", ['elapsed' => 0.1]);
        }

        Assert::same($log->countReads(), 0);
    }

    public function queryAssertOnRealDriver(): void
    {
        $log = new QueryLog();
        $db = Memory::manager(log: $log)->database();
        $db->execute('CREATE TABLE t (id INTEGER)');

        QueryAssert::writes(1, static fn() => $db->insert('t')->values(['id' => 1])->run(), $log);
        $count = QueryAssert::reads(1, static fn(): int => $db->table('t')->count(), $log);

        Assert::same($count, 1);
    }
}
