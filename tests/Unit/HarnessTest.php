<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Database\Config\SQLite\FileConnectionConfig;
use Cycle\Database\Config\SQLiteDriverConfig;
use Cycle\Database\Driver\SQLite\SQLiteDriver;
use Cycle\Testing\Database\ConnectionPool;
use Cycle\Testing\Database\DatabaseContext;
use Cycle\Testing\Database\DatabasePlugin;
use Cycle\Testing\Database\Driver;
use Cycle\Testing\Database\Env;
use Cycle\Testing\Database\Isolation;
use Cycle\Testing\ORM\OrmPlugin;
use Testo\Application\Application;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Assert;
use Testo\Common\PluginConfigurator;
use Testo\Core\Context\TestResult;
use Testo\Core\Exception\SkipTest;
use Testo\Core\Log\Message;
use Testo\Core\Value\Status;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

/**
 * Runs driver suites in a nested Testo application: the package's own and the fixtures under
 * `Fixtures/HarnessSuite`.
 *
 * The harness does its work in interceptors around each test, outside the test's own coverage window, so
 * only a run nested inside a test measures what those suites check of it.
 */
#[Test]
final class HarnessTest
{
    private ?string $file = null;

    #[AfterTest]
    public function removeFile(): void
    {
        $this->file === null or @\unlink($this->file);
        $this->file = null;
    }

    public function featureSuitePassesOnSqlite(): void
    {
        $results = self::run(['Feature/Common', 'Feature/SQLite'], [new DatabasePlugin(Driver::SQLite)]);

        self::assertAllPassed($results);
    }

    public function acceptanceSuitePassesOnSqlite(): void
    {
        $results = self::run(['Acceptance/Common'], [new DatabasePlugin(Driver::SQLite), new OrmPlugin()]);

        self::assertAllPassed($results);
    }

    public function isolationHoldsOnAnInMemoryDatabase(): void
    {
        $results = self::run(
            ['Fixtures/HarnessSuite/Isolation', 'Fixtures/HarnessSuite/Memory', 'Fixtures/HarnessSuite/Functions'],
            [new DatabasePlugin(Driver::SQLite)],
        );

        self::assertAllPassed($results);
        Assert::count($results, 10);
    }

    public function isolationHoldsOnADatabaseFile(): void
    {
        $env = Env::load()->with(['DB_SQLITE_FILE' => $this->file()]);

        $results = self::run(['Fixtures/HarnessSuite/Isolation'], [new DatabasePlugin(Driver::SQLite, env: $env)]);

        self::assertAllPassed($results);
    }

    public function inlineTestsRunInADriverSuite(): void
    {
        $results = self::run(['Fixtures/HarnessSuite/Inline'], [new DatabasePlugin(Driver::SQLite)]);

        self::assertAllPassed($results);
        Assert::count($results, 1);
    }

    public function ormPluginWithoutADatabaseLeavesTestsAlone(): void
    {
        $results = self::run(['Fixtures/HarnessSuite/Functions'], [new OrmPlugin()]);

        self::assertAllPassed($results);
        Assert::count($results, 1);
    }

    public function queriesGoToTheQuerySqlChannel(): void
    {
        $results = self::run(['Fixtures/HarnessSuite/Memory'], [new DatabasePlugin(Driver::SQLite)]);

        $messages = \array_map(
            static fn(Message $message): string => $message->content,
            $results['ReconnectCase::leavesATemporaryTable']->messages->channel('query.sql'),
        );
        Assert::string(\implode('', $messages))->contains('CREATE TEMP TABLE temp_left_behind');
    }

    public function suiteStartsFromPreparedTablesOnly(): void
    {
        $file = $this->file();
        $leftover = SQLiteDriver::create(new SQLiteDriverConfig(connection: new FileConnectionConfig($file)));
        $leftover->execute('CREATE TABLE leftover (id INTEGER)');
        $leftover->disconnect();
        $pool = null;
        $prepare = static function (DatabaseContext $context) use (&$pool): void {
            $pool = $context->pool;
            $context->database()->execute('CREATE TABLE prepared (id INTEGER)');
            $context->database()->execute('INSERT INTO prepared VALUES (1)');
        };

        $results = self::run(['Fixtures/HarnessSuite/Prepared'], [new DatabasePlugin(
            Driver::SQLite,
            Isolation::Transaction,
            prepare: $prepare,
            env: Env::load()->with(['DB_SQLITE_FILE' => $file]),
        )]);

        self::assertAllPassed($results);
        Assert::instanceOf($pool, ConnectionPool::class);
        foreach ($pool->drivers() as $driver) {
            Assert::false($driver->isConnected());
        }
    }

    public function failingPrepareSkipsTheSuite(): void
    {
        $prepare = static function (): void {
            throw new \RuntimeException('no seed');
        };

        $results = self::run(['Fixtures/HarnessSuite/Prepared'], [new DatabasePlugin(Driver::SQLite, prepare: $prepare, env: self::optional())]);

        $result = $results['PreparedCase::seesThePreparedRowsOnly'];
        Assert::same($result->status, Status::Skipped);
        Assert::same($result->failure?->getMessage(), 'Preparing the SQLite database failed: no seed');
    }

    public function unreachableDatabaseSkipsTheSuite(): void
    {
        $results = self::run(['Fixtures/HarnessSuite/Prepared'], [new DatabasePlugin(Driver::SQLite, env: self::unreachable())]);

        $result = $results['PreparedCase::seesThePreparedRowsOnly'];
        Assert::same($result->status, Status::Skipped);
        Assert::instanceOf($result->failure, SkipTest::class);
        Assert::string((string) $result->failure?->getMessage())->startsWith('SQLite at sqlite:')->contains(' is unreachable: ');
    }

    public function unreachableRequiredDatabaseFailsTheSuite(): void
    {
        $env = self::unreachable()->with(['DB_REQUIRED' => '1']);

        $results = self::run(['Fixtures/HarnessSuite/Prepared'], [new DatabasePlugin(Driver::SQLite, env: $env)]);

        $result = $results['PreparedCase::seesThePreparedRowsOnly'];
        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, \RuntimeException::class);
        Assert::false($result->failure instanceof SkipTest);
        Assert::string((string) $result->failure?->getMessage())->contains(' is unreachable: ');
    }

    public function heapCheckFailsAnUnsyncedTest(): void
    {
        $results = self::run(['Fixtures/HarnessSuite/Orm', 'Fixtures/HarnessSuite/Functions'], [new DatabasePlugin(Driver::SQLite), new OrmPlugin()]);

        Assert::same($results['UnsyncedHeapCase::changesASavedEntity']->status, Status::Failed);
        Assert::same($results['SkippedHeapCheckCase::changesASavedEntity']->status, Status::Passed);
        Assert::same($results['functions::runsWithoutACaseClass']->status, Status::Passed);
    }

    public function heapCheckCanBeTurnedOff(): void
    {
        $unbound = 0;
        $bind = static function () use (&$unbound): \Closure {
            return static function () use (&$unbound): void {
                ++$unbound;
            };
        };

        $results = self::run(
            ['Fixtures/HarnessSuite/Orm', 'Fixtures/HarnessSuite/Functions'],
            [new DatabasePlugin(Driver::SQLite), new OrmPlugin(heapCheck: false, bind: $bind)],
        );

        self::assertAllPassed($results);
        Assert::same($unbound, \count($results));
    }

    /**
     * @param array<string, TestResult> $results
     */
    private static function assertAllPassed(array $results): void
    {
        Assert::notBlank($results);
        $failed = \array_filter($results, static fn(TestResult $r): bool => $r->status !== Status::Passed);
        Assert::same(\array_map(static fn(TestResult $r): string => (string) $r->failure?->getMessage(), $failed), []);
    }

    /**
     * An SQLite file in a folder that does not exist.
     */
    private static function unreachable(): Env
    {
        return self::optional()->with(['DB_SQLITE_FILE' => \sys_get_temp_dir() . '/cycle-testing-missing-' . \bin2hex(\random_bytes(4)) . '/db.sqlite']);
    }

    /**
     * CI exports `DB_REQUIRED=1`, which would turn the skips under test into failures.
     */
    private static function optional(): Env
    {
        return Env::load()->with(['DB_REQUIRED' => '0']);
    }

    /**
     * @param list<non-empty-string> $folders Under `tests/`.
     * @param list<PluginConfigurator> $plugins
     * @return array<string, TestResult> `Case::test` (short class name) => result.
     */
    private static function run(array $folders, array $plugins): array
    {
        $root = \dirname(__DIR__);
        $result = Application::createFromConfig(new ApplicationConfig(suites: [new SuiteConfig(
            name: 'Nested',
            location: new FinderConfig(\array_map(static fn(string $f): string => "{$root}/{$f}", $folders)),
            plugins: $plugins,
        )]))->run();

        $results = [];
        foreach ($result as $suite) {
            foreach ($suite as $case) {
                foreach ($case as $test) {
                    \assert($test instanceof TestResult);
                    $class = $test->info->caseInfo->definition->reflection?->getShortName() ?? 'functions';
                    $results["{$class}::{$test->info->name}"] = $test;
                }
            }
        }

        return $results;
    }

    /**
     * @return non-empty-string A fresh SQLite file path, removed after the test.
     */
    private function file(): string
    {
        return $this->file = \sys_get_temp_dir() . '/cycle-testing-harness-' . \bin2hex(\random_bytes(4)) . '.sqlite';
    }
}
