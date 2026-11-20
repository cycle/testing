<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Testing\Database\Assert\SqlAssert;
use Cycle\Testing\Database\Driver;
use Cycle\Testing\Database\Memory;
use Testo\Assert;
use Testo\Test;

#[Test]
final class SqlAssertTest
{
    public function placeholdersBecomeDriverQuotes(): void
    {
        SqlAssert::same('SELECT `a` FROM `t`', 'SELECT {a}  FROM {t}', Driver::MySQL);
        SqlAssert::same("SELECT [a]\nFROM [t]", 'SELECT {a} FROM {t}', Driver::SQLServer);
    }

    public function withoutDriverQuotesAreIgnored(): void
    {
        SqlAssert::same('SELECT "a" FROM "t"', 'SELECT {a} FROM [t]');
    }

    public function mismatchFails(): void
    {
        try {
            SqlAssert::same('SELECT "a" FROM "t"', 'SELECT {b} FROM {t}', Driver::Postgres);
        } catch (\Throwable $e) {
            Assert::string($e->getMessage())->contains('SELECT');

            return;
        }

        Assert::fail('No failure for different SQL.');
    }

    public function queryAndParameters(): void
    {
        $query = Memory::database()->select('id')->from('users')->where('id', 7);

        SqlAssert::sameWithParameters($query, 'SELECT {id} FROM {users} WHERE {id} = ?', [7], Driver::SQLite);
        SqlAssert::same($query, 'SELECT {id} FROM {users} WHERE {id} = ?', Memory::database()->getDriver());
    }
}
