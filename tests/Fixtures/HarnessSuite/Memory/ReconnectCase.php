<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Fixtures\HarnessSuite\Memory;

use Cycle\Database\Database;
use Cycle\Testing\Database\DatabaseTestCase;
use Testo\Assert;
use Testo\Test;

/**
 * An in-memory database is reconnected after each test, which drops even the temporary tables a purge
 * would keep. The tests run in declaration order.
 */
#[Test]
final class ReconnectCase extends DatabaseTestCase
{
    public function leavesATemporaryTable(): void
    {
        $this->database->execute('CREATE TEMP TABLE temp_left_behind (id INTEGER)');

        Assert::same(self::temporaryTables($this->database), ['temp_left_behind']);
    }

    public function startsWithoutIt(): void
    {
        Assert::same(self::temporaryTables($this->database), []);
    }

    /**
     * @return list<string>
     */
    private static function temporaryTables(Database $database): array
    {
        return \array_column($database->query('SELECT name FROM sqlite_temp_master')->fetchAll(), 'name');
    }
}
