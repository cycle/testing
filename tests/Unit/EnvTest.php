<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Testing\Database\Driver;
use Cycle\Testing\Database\Env;
use Testo\Assert;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

#[Test]
final class EnvTest
{
    #[AfterTest]
    public function clearEnvironment(): void
    {
        \putenv('DB_DRIVERS');
        \putenv('CYCLE_TESTING_PROBE');
    }

    public function shippedDefaultsCoverEveryDriver(): void
    {
        $env = Env::fromFile(Env::DEFAULTS_FILE);

        foreach (['MYSQL', 'POSTGRES', 'SQLSERVER'] as $driver) {
            foreach (['HOST', 'PORT', 'DATABASE', 'USER', 'PASSWORD', 'IMAGE'] as $key) {
                Assert::notSame($env->get("DB_{$driver}_{$key}"), '');
            }
        }
        Assert::same($env->get('DB_SQLITE_FILE'), '');
    }

    public function parsesCommentsAndQuotes(): void
    {
        $file = \tempnam(\sys_get_temp_dir(), 'env');
        \file_put_contents($file, "# comment\n\nA=1\nB = \"two words\"\nC='x=y'\n");
        $env = Env::fromFile($file);
        \unlink($file);

        Assert::same($env->get('A'), '1');
        Assert::same($env->get('B'), 'two words');
        Assert::same($env->get('C'), 'x=y');
    }

    public function environmentWinsOverDefaultsAndOverridesWinOverBoth(): void
    {
        \putenv('CYCLE_TESTING_PROBE=env');
        $env = new Env(['CYCLE_TESTING_PROBE' => 'default']);

        Assert::same($env->get('CYCLE_TESTING_PROBE'), 'env');
        Assert::same($env->with(['CYCLE_TESTING_PROBE' => 'override'])->get('CYCLE_TESTING_PROBE'), 'override');
    }

    public function missingKeyThrows(): never
    {
        Expect::exception(\OutOfBoundsException::class);
        (new Env())->get('CYCLE_TESTING_MISSING');
    }

    public function driversFromList(): void
    {
        \putenv('DB_DRIVERS= MySQL, sqlite,mysql');

        Assert::same((new Env())->drivers(), [Driver::MySQL, Driver::SQLite]);
    }
}
