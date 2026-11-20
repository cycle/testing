<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Feature\Common;

use Cycle\Database\Exception\StatementException\ConnectionException;
use Cycle\Testing\Database\Attribute\WrapDriver;
use Cycle\Testing\Database\DatabaseTestCase;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

#[Test]
#[WrapDriver]
final class WrapDriverTest extends DatabaseTestCase
{
    public function countsDisconnects(): void
    {
        $driver = $this->wrapDriver();
        Assert::same($driver, $this->database->getDriver());

        $driver->connect();
        $driver->disconnect();

        Assert::same($driver->disconnectCalls, 1);
    }

    public function knobsAreResetBetweenTests(): void
    {
        Assert::same($this->wrapDriver()->disconnectCalls, 0);
    }

    public function failsTransactionBegin(): never
    {
        # The driver reconnects and retries once, so the first two begins must fail.
        $this->wrapDriver()->exceptionOnTransactionBegin = 2;

        Expect::exception(ConnectionException::class);
        $this->database->begin();
    }
}
