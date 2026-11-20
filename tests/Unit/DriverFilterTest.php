<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Testing\Database\Attribute\ExceptDriver;
use Cycle\Testing\Database\Attribute\OnlyDriver;
use Cycle\Testing\Database\Driver;
use Testo\Assert;
use Testo\Test;

#[Test]
final class DriverFilterTest
{
    public function onlyDriverAllowsEveryListedDriver(): void
    {
        $filter = new OnlyDriver(Driver::MySQL, Driver::Postgres, Driver::SQLServer);

        Assert::same($filter->drivers, [Driver::MySQL, Driver::Postgres, Driver::SQLServer]);
        Assert::false($filter->allows(Driver::SQLite));
    }

    public function exceptDriverRejectsEveryListedDriver(): void
    {
        $filter = new ExceptDriver(Driver::MySQL, Driver::Postgres, Driver::SQLServer);

        Assert::same($filter->drivers, [Driver::MySQL, Driver::Postgres, Driver::SQLServer]);
        Assert::true($filter->allows(Driver::SQLite));
        Assert::false($filter->allows(Driver::SQLServer));
    }
}
