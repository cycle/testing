<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Testing\Config;
use Cycle\Testing\Database\DatabasePlugin;
use Cycle\Testing\Database\Driver;
use Cycle\Testing\Database\Env;
use Cycle\Testing\Database\Isolation;
use Cycle\Testing\ORM\OrmPlugin;
use Testo\Application\Config\SuiteConfig;
use Testo\Assert;
use Testo\Bridge\Mockery\MockeryPlugin;
use Testo\Codecov\CodecovPlugin;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

#[Test]
final class ConfigTest
{
    /** @var list<non-empty-string> */
    private array $tempDirs = [];

    #[AfterTest]
    public function removeTempDirs(): void
    {
        foreach ($this->tempDirs as $dir) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($files as $file) {
                $file->isDir() ? \rmdir($file->getPathname()) : \unlink($file->getPathname());
            }
            \rmdir($dir);
        }
        $this->tempDirs = [];
    }

    public function buildsUnitAndDriverSuites(): void
    {
        $config = Config::forPackage('cycle/testing', root: \dirname(__DIR__, 2))
            ->unit('tests/Unit')
            ->feature('tests/Feature', drivers: [Driver::SQLite, Driver::MySQL], isolation: Isolation::Transaction)
            ->acceptance('tests/Acceptance', drivers: [Driver::SQLite])
            ->build();

        Assert::same(self::names($config->suites), ['Unit', 'Feature/SQLite', 'Feature/MySQL', 'Acceptance/SQLite']);

        $plugins = [...$config->suites[1]->plugins];
        Assert::instanceOf($plugins[0], MockeryPlugin::class);
        Assert::instanceOf($plugins[1], DatabasePlugin::class);
        Assert::same($plugins[1]->driver, Driver::SQLite);
        Assert::same($plugins[1]->isolation, Isolation::Transaction);
    }

    public function perDriverTakesAnyKind(): void
    {
        $root = $this->tempTree(['tests/Smoke/Common']);

        $config = Config::forPackage('cycle/smoke', root: $root)
            ->perDriver('Smoke', 'tests/Smoke', drivers: [Driver::Postgres])
            ->build();

        Assert::same(self::names($config->suites), ['Smoke/Postgres']);
        Assert::same(self::paths($config->suites[0]->location->includes), ["{$root}/tests/Smoke/Common"]);
    }

    public function driverWithoutFoldersGetsNoSuite(): void
    {
        $config = Config::forPackage('cycle/testing', root: \dirname(__DIR__, 2))
            ->feature('tests/Feature', drivers: [Driver::MySQL], common: 'NoSuchFolder')
            ->unit('tests/Unit')
            ->build();

        Assert::same(self::names($config->suites), ['Unit']);
    }

    public function regressionRunsEveryIssueOnEveryDriver(): void
    {
        $root = $this->tempTree(['tests/Regression/Issue1', 'tests/Regression/Issue2', 'tests/Regression/MySQL']);

        $config = Config::forPackage('cycle/orm', root: $root)
            ->regression('tests/Regression', drivers: [Driver::SQLite, Driver::MySQL])
            ->build();

        Assert::same(self::names($config->suites), ['Regression/SQLite', 'Regression/MySQL']);
        [$sqlite, $mysql] = $config->suites;
        Assert::same(self::paths($sqlite->location->includes), ["{$root}/tests/Regression"]);
        Assert::same(self::paths($sqlite->location->excludes), ["{$root}/tests/Regression/MySQL"]);
        Assert::same(self::paths($mysql->location->includes), ["{$root}/tests/Regression"]);
        Assert::same(self::paths($mysql->location->excludes), []);
    }

    public function repoWithoutTestFoldersBuildsAnEmptySuite(): void
    {
        $root = $this->tempTree([]);

        $config = Config::forPackage('cycle/empty', root: $root)
            ->unit('tests/Unit')
            ->feature('tests/Feature', drivers: [Driver::SQLite], exclude: ['tests/Fixtures'])
            ->regression('tests/Regression', drivers: [Driver::SQLite])
            ->build();

        Assert::same(\count($config->suites), 1);
        Assert::same($config->suites[0]->location->includes, []);
        Assert::same($config->src->includes, []);
    }

    public function unitSuiteGetsMockeryAndTheGivenPlugins(): void
    {
        $plugin = new OrmPlugin();
        $plugins = (static function () use ($plugin): \Generator {
            yield 'orm' => $plugin;
        })();

        $config = Config::forPackage('cycle/testing', root: \dirname(__DIR__, 2))
            ->unit('tests/Unit', plugins: $plugins)
            ->unit('tests/Unit', plugins: ['orm' => $plugin], name: 'Again')
            ->build();

        foreach ($config->suites as $suite) {
            Assert::array([...$suite->plugins])->isList()->hasCount(2);
            Assert::instanceOf([...$suite->plugins][0], MockeryPlugin::class);
            Assert::same([...$suite->plugins][1], $plugin);
        }
    }

    public function driverSuitesGetTheGivenPluginsAfterTheDatabase(): void
    {
        [$first, $second] = [new OrmPlugin(), new OrmPlugin()];

        $config = Config::forPackage('cycle/testing', root: \dirname(__DIR__, 2))
            ->feature('tests/Feature/', drivers: [Driver::SQLite], plugins: [$first, $second])
            ->build();

        $plugins = [...$config->suites[0]->plugins];
        Assert::count($plugins, 4);
        Assert::instanceOf($plugins[1], DatabasePlugin::class);
        Assert::same([$plugins[2], $plugins[3]], [$first, $second]);
    }

    public function rootAndPathsMayEndWithASlash(): void
    {
        $root = $this->tempTree(['tests/Feature/Common']);

        $config = Config::forPackage('cycle/smoke', root: "{$root}/")
            ->feature('tests/Feature/', drivers: [Driver::SQLite])
            ->build();

        Assert::same(self::paths($config->suites[0]->location->includes), ["{$root}/tests/Feature/Common"]);
    }

    public function driverFoldersCanBeRenamed(): void
    {
        $root = $this->tempTree(['tests/Feature/Common', 'tests/Feature/MySql8', 'tests/Feature/MySQL']);

        $config = Config::forPackage('cycle/smoke', root: $root)
            ->feature('tests/Feature', drivers: [Driver::MySQL], folders: ['mysql' => 'MySql8'])
            ->build();

        Assert::same(self::paths($config->suites[0]->location->includes), ["{$root}/tests/Feature/Common", "{$root}/tests/Feature/MySql8"]);
    }

    public function wholePathSuiteExcludesEveryOtherDriverAndTheGivenPaths(): void
    {
        $root = $this->tempTree(['tests/Regression/Fixtures', 'tests/Regression/MySQL', 'tests/Regression/Postgres', 'tests/Regression/SQLite']);

        $config = Config::forPackage('cycle/orm', root: $root)
            ->regression('tests/Regression', drivers: [Driver::SQLite], exclude: ['tests/Regression/Fixtures'])
            ->build();

        Assert::same(self::paths($config->suites[0]->location->excludes), [
            "{$root}/tests/Regression/Fixtures",
            "{$root}/tests/Regression/MySQL",
            "{$root}/tests/Regression/Postgres",
        ]);
    }

    public function driversComeFromTheGivenEnv(): void
    {
        $saved = \getenv('DB_DRIVERS');
        \putenv('DB_DRIVERS');

        try {
            $config = Config::forPackage('cycle/testing', root: \dirname(__DIR__, 2), env: new Env(['DB_DRIVERS' => 'postgres']))
                ->feature('tests/Feature')
                ->build();
        } finally {
            $saved === false or \putenv("DB_DRIVERS={$saved}");
        }

        Assert::same(self::names($config->suites), ['Feature/Postgres']);
    }

    public function addingASuiteLeavesTheOriginalConfigAlone(): void
    {
        $base = Config::forPackage('cycle/testing', root: \dirname(__DIR__, 2));

        $base->unit('tests/Unit');
        $base->plugins(new OrmPlugin());

        Assert::same(self::names($base->build()->suites), ['Unit']);
        Assert::count([...$base->build()->plugins], 1);
    }

    public function buildCollectsCoverageOnlyOnRequest(): void
    {
        $plugin = new OrmPlugin();

        $plugins = [...Config::forPackage('cycle/testing', root: \dirname(__DIR__, 2))->plugins($plugin)->build()->plugins];

        Assert::count($plugins, 2);
        Assert::instanceOf($plugins[0], CodecovPlugin::class);
        Assert::same($plugins[1], $plugin);
    }

    /**
     * @param list<SuiteConfig> $suites
     * @return list<string>
     */
    private static function names(array $suites): array
    {
        return \array_map(static fn(SuiteConfig $s): string => $s->name, $suites);
    }

    /**
     * @param iterable<\Stringable> $paths
     * @return list<string>
     */
    private static function paths(iterable $paths): array
    {
        $result = [];
        foreach ($paths as $path) {
            $result[] = \str_replace('\\', '/', (string) $path);
        }

        return $result;
    }

    /**
     * @param list<non-empty-string> $dirs
     * @return non-empty-string Root with the given folders created beneath.
     */
    private function tempTree(array $dirs): string
    {
        $root = \str_replace('\\', '/', \sys_get_temp_dir()) . '/cycle-testing-' . \bin2hex(\random_bytes(4));
        \mkdir($root);
        $this->tempDirs[] = $root;
        foreach ($dirs as $dir) {
            \mkdir("{$root}/{$dir}", recursive: true);
        }

        return $root;
    }
}
