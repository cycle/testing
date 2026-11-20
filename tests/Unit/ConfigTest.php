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
use Testo\Test;

#[Test]
final class ConfigTest
{
    public function buildsUnitAndDriverSuites(): void
    {
        $config = Config::forPackage('cycle/testing', root: \dirname(__DIR__, 2))
            ->unit('tests/Unit')
            ->acceptance('tests/Acceptance', drivers: [Driver::SQLite, Driver::MySQL], isolation: Isolation::Transaction)
            ->build();

        $names = \array_map(static fn(SuiteConfig $s): string => $s->name, $config->suites);
        Assert::same($names, ['Unit', 'Acceptance/SQLite', 'Acceptance/MySQL']);

        $plugins = [...$config->suites[1]->plugins];
        Assert::instanceOf($plugins[0], MockeryPlugin::class);
        Assert::instanceOf($plugins[1], DatabasePlugin::class);
        Assert::same($plugins[1]->driver, Driver::SQLite);
        Assert::same($plugins[1]->isolation, Isolation::Transaction);
    }

    public function driverWithoutFoldersGetsNoSuite(): void
    {
        $config = Config::forPackage('cycle/testing', root: \dirname(__DIR__, 2))
            ->acceptance('tests/Acceptance', drivers: [Driver::Postgres], common: 'NoSuchFolder')
            ->unit('tests/Unit')
            ->build();

        Assert::same(\count($config->suites), 1);
    }

    public function repoWithoutTestFoldersBuildsAnEmptySuite(): void
    {
        $root = \sys_get_temp_dir() . '/cycle-testing-' . \bin2hex(\random_bytes(4));
        \mkdir($root);

        try {
            $config = Config::forPackage('cycle/empty', root: $root)
                ->unit('tests/Unit')
                ->acceptance('tests/Acceptance', drivers: [Driver::SQLite], exclude: ['tests/Fixtures'])
                ->build();
        } finally {
            \rmdir($root);
        }

        Assert::same(\count($config->suites), 1);
        Assert::same($config->suites[0]->location->includes, []);
        Assert::same($config->src->includes, []);
    }
}
