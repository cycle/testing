<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Database\Injection\Fragment;
use Cycle\Testing\Database\Assert\SqlAssert;
use Cycle\Testing\Database\Driver;
use Cycle\Testing\Database\Memory;
use Testo\Assert;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Expect;
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

    public function fragmentIsComparedAsSql(): void
    {
        SqlAssert::same(new Fragment('SELECT "a"'), 'SELECT {a}', Driver::SQLite);
    }

    public function withoutDriverOtherSqlFails(): never
    {
        Expect::exception(AssertionException::class)->withMessageContaining('SELECTa');

        SqlAssert::same('SELECT a', 'SELECT b');
    }

    public function givenDriverDecidesTheQuotes(): never
    {
        Expect::exception(AssertionException::class)->withMessageContaining('SELECT`a`');

        SqlAssert::same('SELECT "a"', 'SELECT {a}', Driver::MySQL);
    }

    public function givenConnectionDecidesTheQuotes(): never
    {
        Expect::exception(AssertionException::class)->withMessageContaining('SELECT`a`');

        SqlAssert::same('SELECT `a`', 'SELECT {a}', Memory::database()->getDriver());
    }

    public function otherParametersFail(): never
    {
        $query = Memory::database()->select('id')->from('users')->where('id', 7);

        Expect::exception(AssertionException::class);

        SqlAssert::sameParameters($query, [8]);
    }

    public function sqlWithParametersChecksTheSql(): never
    {
        $query = Memory::database()->select('id')->from('users')->where('id', 7);

        Expect::exception(AssertionException::class)->withMessageContaining('name');

        SqlAssert::sameWithParameters($query, 'SELECT {name} FROM {users} WHERE {id} = ?', [7], Driver::SQLite);
    }

    public function sqlWithParametersChecksTheParameters(): never
    {
        $query = Memory::database()->select('id')->from('users')->where('id', 7);

        Expect::exception(AssertionException::class);

        SqlAssert::sameWithParameters($query, 'SELECT {id} FROM {users} WHERE {id} = ?', [8], Driver::SQLite);
    }

    public function queryAndParameters(): void
    {
        $query = Memory::database()->select('id')->from('users')->where('id', 7);

        SqlAssert::sameWithParameters($query, 'SELECT {id} FROM {users} WHERE {id} = ?', [7], Driver::SQLite);
        SqlAssert::same($query, 'SELECT {id} FROM {users} WHERE {id} = ?', Memory::database()->getDriver());
    }
}
