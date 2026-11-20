<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Testing\Database\Assert\QueryAssert;
use Cycle\Testing\Database\QueryLog;
use Testo\Assert;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Expect;
use Testo\Test;

#[Test]
final class QueryAssertTest
{
    public function returnsTheCallbackResult(): void
    {
        $log = new QueryLog();

        $result = QueryAssert::count(1, 1, static function () use ($log): string {
            $log->info('SELECT 1', ['elapsed' => 0.1]);
            $log->info('DELETE FROM t', ['elapsed' => 0.1]);

            return 'done';
        }, $log);

        Assert::same($result, 'done');
        Assert::same(QueryAssert::none(static fn(): int => 5, $log), 5);
    }

    public function unexpectedReadsFailWithTheExecutedQueries(): never
    {
        $log = new QueryLog();

        Expect::exception(AssertionException::class)->withMessageContaining("Expected 0 read queries. Executed:\nSELECT 1\nSELECT 2");

        QueryAssert::reads(0, static function () use ($log): void {
            $log->info('SELECT 1', ['elapsed' => 0.1]);
            $log->info('SELECT 2', ['elapsed' => 0.1]);
        }, $log);
    }

    public function missingWritesFailWithNoneExecuted(): never
    {
        Expect::exception(AssertionException::class)->withMessageContaining("Expected 1 write queries. Executed:\n(none)");

        QueryAssert::writes(1, static fn() => null, new QueryLog());
    }

    public function countChecksTheReads(): never
    {
        $log = new QueryLog();

        Expect::exception(AssertionException::class)->withMessageContaining('Expected 0 read queries.');

        QueryAssert::count(0, 0, static fn() => $log->info('SELECT 1', ['elapsed' => 0.1]), $log);
    }

    public function countChecksTheWrites(): never
    {
        $log = new QueryLog();

        Expect::exception(AssertionException::class)->withMessageContaining('Expected 0 write queries.');

        QueryAssert::count(0, 0, static fn() => $log->info('DELETE FROM t', ['elapsed' => 0.1]), $log);
    }

    public function outsideADatabaseSuiteTheLogIsRequired(): never
    {
        Expect::exception(\LogicException::class)->withMessage('No query log: run inside a database suite or pass one.');

        QueryAssert::none(static fn() => null);
    }
}
