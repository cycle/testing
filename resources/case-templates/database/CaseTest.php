<?php

declare(strict_types=1);

namespace Cycle\Testing\CaseTemplate\Database;

use Cycle\Testing\Database\Assert\SqlAssert;
use Cycle\Testing\Database\DatabaseTestCase;
use Testo\Assert;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

/**
 * Regression case: replace the sample tables and tests with the issue's reproduction.
 */
#[Test]
final class CaseTest extends DatabaseTestCase
{
    #[BeforeTest]
    public function createTables(): void
    {
        $this->makeTable('user', ['id' => 'primary', 'login' => 'string(64)']);
        $this->makeTable(
            'post',
            ['id' => 'primary', 'user_id' => 'integer', 'title' => 'string(255)'],
            fk: ['user_id' => ['table' => 'user', 'column' => 'id']],
        );

        $this->database->table('user')->insertMultiple(['login'], [['user-1'], ['user-2']]);
        $this->database->table('post')->insertMultiple(
            ['user_id', 'title'],
            [[1, 'Title 1'], [2, 'Title 2'], [2, 'Title 3']],
        );
    }

    public function selectsWithJoin(): void
    {
        $titles = $this->database->select('post.title')
            ->from('post')
            ->innerJoin('user')->on('user.id', 'post.user_id')
            ->where('user.login', 'user-2')
            ->orderBy('post.id')
            ->fetchAll();

        Assert::same(\array_column($titles, 'title'), ['Title 2', 'Title 3']);
    }

    public function buildsExpectedSql(): void
    {
        SqlAssert::same(
            $this->database->select()->from('post')->where('user_id', 2),
            'SELECT * FROM {post} WHERE {user_id} = ?',
        );
    }

    public function updateIsOneWrite(): void
    {
        $this->captureWriteQueries();
        $this->database->update('post', ['title' => 'Changed'], ['id' => 1])->run();

        $this->assertNumWrites(1);
        Assert::same($this->database->table('post')->select('title')->where('id', 1)->fetchAll()[0]['title'], 'Changed');
    }
}
