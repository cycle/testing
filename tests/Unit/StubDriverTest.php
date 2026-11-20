<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Database\Database;
use Cycle\Testing\Database\Mock\FakePDO;
use Cycle\Testing\Database\Mock\StubDriver;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

#[Test]
final class StubDriverTest
{
    public function runsQueriesAgainstFakePdo(): void
    {
        $pdo = (new FakePDO())->willReturn([['id' => 1, 'name' => 'a'], ['id' => 2, 'name' => 'b']]);
        $db = new Database('default', '', StubDriver::make($pdo));

        $rows = $db->select()->from('users')->where('id', '>', 0)->fetchAll();

        Assert::count($rows, 2);
        Assert::same($db->getDriver()->getType(), 'test');
        Assert::string($pdo->queries()[0])->contains('FROM "users"');
        Assert::same($pdo->executed[0]['params'], [1 => 0]);
    }

    public function scriptedFailurePropagatesUnmapped(): never
    {
        $pdo = (new FakePDO())->willThrow(new \DomainException('boom'));
        $db = new Database('default', '', StubDriver::make($pdo));

        Expect::exception(\DomainException::class)->withMessage('boom');
        $db->select()->from('users')->fetchAll();
    }

    public function withoutPdoUsesInMemorySqlite(): void
    {
        $db = new Database('default', '', StubDriver::make());

        Assert::same((int) $db->query('SELECT 1 + 1')->fetchColumn(), 2);
    }
}
