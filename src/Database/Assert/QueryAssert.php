<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Assert;

use Cycle\Testing\Database\Internal\Current;
use Cycle\Testing\Database\QueryLog;
use Testo\Assert;
use Testo\Common\Attribute\AssertMethod;

/**
 * Counts the queries a callback runs, through the suite's {@see QueryLog} (or the one given).
 *
 * Writes are `INSERT`, `UPDATE` and `DELETE`; every other executed statement is a read.
 */
final class QueryAssert
{
    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    #[AssertMethod]
    public static function reads(int $expected, callable $callback, ?QueryLog $log = null): mixed
    {
        $run = self::log($log)->capture($callback);
        Assert::same($run['reads'], $expected, self::message('read', $expected, $run['queries']));

        return $run['result'];
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    #[AssertMethod]
    public static function writes(int $expected, callable $callback, ?QueryLog $log = null): mixed
    {
        $run = self::log($log)->capture($callback);
        Assert::same($run['writes'], $expected, self::message('write', $expected, $run['queries']));

        return $run['result'];
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    #[AssertMethod]
    public static function count(int $reads, int $writes, callable $callback, ?QueryLog $log = null): mixed
    {
        $run = self::log($log)->capture($callback);
        Assert::same($run['reads'], $reads, self::message('read', $reads, $run['queries']));
        Assert::same($run['writes'], $writes, self::message('write', $writes, $run['queries']));

        return $run['result'];
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    #[AssertMethod]
    public static function none(callable $callback, ?QueryLog $log = null): mixed
    {
        return self::count(0, 0, $callback, $log);
    }

    private static function log(?QueryLog $log): QueryLog
    {
        return $log ?? Current::$pool?->queryLog ?? throw new \LogicException(
            'No query log: run inside a database suite or pass one.',
        );
    }

    /**
     * @param list<string> $queries
     */
    private static function message(string $kind, int $expected, array $queries): string
    {
        return \sprintf(
            "Expected %d %s queries. Executed:\n%s",
            $expected,
            $kind,
            $queries === [] ? '(none)' : \implode("\n", $queries),
        );
    }
}
