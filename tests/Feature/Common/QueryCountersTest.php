<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Feature\Common;

use Cycle\Testing\Database\DatabaseTestCase;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

/**
 * The tests run in declaration order: the second checks that the first's capture point did not carry over.
 */
#[Test]
final class QueryCountersTest extends DatabaseTestCase
{
    #[BeforeTest]
    public function createTable(): void
    {
        $this->makeTable('counted', ['id' => 'primary', 'name' => 'string(32), nullable']);
    }

    public function countsSinceTheCapture(): void
    {
        $table = $this->database->table('counted');
        $table->insertOne(['name' => 'a']);
        $table->select()->fetchAll();

        $this->captureWriteQueries();
        $this->captureReadQueries();
        $table->insertOne(['name' => 'b']);
        $table->insertOne(['name' => 'c']);
        $table->select()->fetchAll();

        $this->assertNumWrites(2);
        $this->assertNumReads(1);
    }

    public function withoutCaptureCountsFromTheTestStart(): void
    {
        $this->database->table('counted')->insertOne(['name' => 'd']);

        $this->assertNumWrites(1);
    }
}
