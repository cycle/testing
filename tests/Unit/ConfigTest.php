<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Testing\Config;
use Cycle\Testing\Database\DatabasePlugin;
use Cycle\Testing\Database\Driver;
use Cycle\Testing\Database\Isolation;
use Testo\Application\Config\SuiteConfig;
use Testo\Assert;
use Testo\Bridge\Mockery\MockeryPlugin;
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
            ->feature('tests/Feature', drivers: [Driver::Postgres], common: 'NoSuchFolder')
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
