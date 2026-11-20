<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Testing\Database\Assert\QueryAssert;
use Cycle\Testing\Database\Memory;
use Cycle\Testing\Database\QueryLog;
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
