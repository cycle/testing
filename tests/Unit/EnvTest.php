<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Testing\Database\Driver;
use Cycle\Testing\Database\Env;
use Testo\Assert;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class EnvTest
{
    private const VARIABLES = ['DB_DRIVERS', 'DB', 'CYCLE_TESTING_PROBE'];

    /** @var array<string, string|false> The variables as the run started with them, e.g. `DB_DRIVERS` in CI. */
    private array $saved = [];

    #[BeforeTest]
    public function clearEnvironment(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->saved[$name] = \getenv($name);
            \putenv($name);
        }
    }

    #[AfterTest]
    public function restoreEnvironment(): void
    {
        foreach ($this->saved as $name => $value) {
            \putenv($value === false ? $name : "{$name}={$value}");
        }
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
        \file_put_contents($file, "# comment\n\nA=1\nB = \"two words\"\nC='x=y'\n  # D=1\n#E=1\nF=\"\"\nG=xax\nH=\"mixed'\nI=x\n");
        $env = Env::fromFile($file);
        \unlink($file);

        Assert::same($env->get('A'), '1');
        Assert::same($env->get('B'), 'two words');
        Assert::same($env->get('C'), 'x=y');
        Assert::same($env->get('# D', 'none'), 'none');
        Assert::same($env->get('#E', 'none'), 'none');
        Assert::same($env->get('F'), '');
        Assert::same($env->get('G'), 'xax');
        Assert::same($env->get('H'), "\"mixed'");
        Assert::same($env->get('I'), 'x');
    }

    public function sharedInstanceIsLoadedOnce(): void
    {
        Assert::same(Env::load(), Env::load());
    }

    public function defaultsWinOverTheFallback(): void
    {
        Assert::same((new Env(['CYCLE_TESTING_PROBE' => 'default']))->get('CYCLE_TESTING_PROBE', 'fallback'), 'default');
        Assert::same((new Env())->get('CYCLE_TESTING_PROBE', 'fallback'), 'fallback');
    }

    public function everyDriverByDefault(): void
    {
        Assert::same((new Env())->drivers(), Driver::cases());
    }

    /**
     * In a child process: the deprecation notice goes to the real STDERR, which Infection's run of the
     * suite does not tolerate.
     */
    public function legacyDbVariableNamesTheDriver(): void
    {
        $code = \sprintf(
            'require %s; echo implode(",", array_column((new %s())->drivers(), "value"));',
            \var_export(\dirname(__DIR__, 2) . '/vendor/autoload.php', true),
            Env::class,
        );
        $process = \proc_open(
            [\PHP_BINARY, '-r', $code],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['DB' => 'mysql'] + \array_diff_key(\getenv(), ['DB_DRIVERS' => true]),
        );
        $out = \stream_get_contents($pipes[1]);
        $err = \stream_get_contents($pipes[2]);
        \proc_close($process);

        Assert::same($out, 'mysql');
        Assert::same($err, "cycle/testing: `DB` is deprecated, use `DB_DRIVERS`.\n");
    }

    public function dbDriversWinsOverTheLegacyVariable(): void
    {
        \putenv('DB=mysql');
        \putenv('DB_DRIVERS=sqlite');

        Assert::same((new Env())->drivers(), [Driver::SQLite]);
    }

    public function emptyLegacyVariableIsIgnored(): void
    {
        \putenv('DB=');

        Assert::same((new Env(['DB_DRIVERS' => 'postgres']))->drivers(), [Driver::Postgres]);
    }

    public function unknownDriverFails(): never
    {
        \putenv('DB_DRIVERS=sqlite,oracle');

        Expect::exception(\InvalidArgumentException::class)->withMessage('Unknown driver `oracle` in DB_DRIVERS.');

        (new Env())->drivers();
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
        \putenv('DB_DRIVERS= MySQL, mysql,sqlite');

        Assert::same((new Env())->drivers(), [Driver::MySQL, Driver::SQLite]);
    }
}
