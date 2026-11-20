<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Assert;

use Cycle\Database\Driver\DriverInterface;
use Cycle\Database\Injection\FragmentInterface;
use Cycle\Database\Injection\ParameterInterface;
use Cycle\Database\Query\ActiveQuery;
use Cycle\Database\Query\QueryParameters;
use Cycle\Testing\Database\Driver;
use Cycle\Testing\Database\Internal\Current;
use Testo\Assert;
use Testo\Common\Attribute\AssertMethod;

/**
 * SQL comparison that holds across drivers.
 *
 * Write the expected SQL with `{` and `}` around identifiers: `SELECT * FROM {users}`. They become the
 * driver's quotes (`"users"`, `` `users` ``, `[users]`), and whitespace is ignored on both sides. The
 * driver is the given one, else the running suite's; with neither, identifier quotes are stripped from
 * both sides instead.
 */
final class SqlAssert
{
    #[AssertMethod]
    public static function same(
        string|FragmentInterface|ActiveQuery $actual,
        string $expected,
        Driver|DriverInterface|null $driver = null,
        string $message = '',
    ): void {
        $actual = $actual instanceof ActiveQuery ? $actual->sqlStatement() : (string) $actual;
        $quotes = self::quotes($driver);

        if ($quotes === null) {
            $strip = static fn(string $sql): string => (string) \preg_replace('/[\s"`\[\]{}]+/', '', $sql);
            Assert::same($strip($actual), $strip($expected), $message);

            return;
        }

        $expected = \str_replace(['{', '}'], $quotes, $expected);
        $squash = static fn(string $sql): string => (string) \preg_replace('/\s+/', '', $sql);
        Assert::same($squash($actual), $squash($expected), $message);
    }

    /**
     * The values the query binds, in order; parameter objects are unwrapped.
     *
     * @param list<mixed> $expected
     */
    #[AssertMethod]
    public static function sameParameters(ActiveQuery $query, array $expected, string $message = ''): void
    {
        $parameters = new QueryParameters();
        $query->sqlStatement($parameters);

        $actual = \array_map(
            static fn(mixed $p): mixed => $p instanceof ParameterInterface ? $p->getValue() : $p,
            $parameters->getParameters(),
        );

        Assert::equals(\array_values($actual), $expected, $message);
    }

    /**
     * @param list<mixed> $parameters
     */
    #[AssertMethod]
    public static function sameWithParameters(
        ActiveQuery $query,
        string $expected,
        array $parameters,
        Driver|DriverInterface|null $driver = null,
    ): void {
        self::same($query, $expected, $driver);
        self::sameParameters($query, $parameters);
    }

    /**
     * @return array{string, string}|null
     */
    private static function quotes(Driver|DriverInterface|null $driver): ?array
    {
        $driver ??= Current::$pool?->driver;

        return match (true) {
            $driver instanceof Driver => $driver->quotes(),
            $driver instanceof DriverInterface => \explode('\a', $driver->getQueryCompiler()->quoteIdentifier('\a'), 2) + [1 => ''],
            default => null,
        };
    }
}
