<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Testing\Database\Layout;
use Cycle\Testing\Database\Memory;
use Testo\Assert;
use Testo\Test;

#[Test]
final class LayoutTest
{
    public function prefixedSharesConnection(): void
    {
        $manager = Memory::manager(Layout::prefixed(['secondary' => 'secondary_']));

        Assert::same($manager->database('secondary')->getPrefix(), 'secondary_');
        Assert::same($manager->database('secondary')->getDriver(), $manager->database()->getDriver());
    }

    public function withSqliteSeparatesConnection(): void
    {
        $manager = Memory::manager(Layout::withSqlite(['secondary']));

        Assert::notSame($manager->database('secondary')->getDriver(), $manager->database()->getDriver());
        Assert::same(Layout::withSqlite(['secondary'])->names(), ['default', 'secondary']);
    }

    public function defaultCanBePrefixed(): void
    {
        $layout = Layout::prefixed(['default' => 'tests_', 'slave' => '']);

        Assert::same($layout->prefix('default'), 'tests_');
        Assert::false($layout->isSqlite('slave'));
    }
}
