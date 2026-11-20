<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\SchemaInterface;
use Cycle\Testing\Database\Layout;
use Cycle\Testing\ORM\Memory;
use Cycle\Testing\ORM\Schema\SchemaCompiler;
use Cycle\Testing\ORM\SchemaSource;
use Cycle\Testing\Tests\Fixtures\Note;
use Testo\Assert;
use Testo\Test;

#[Test]
final class SchemaSourceTest
{
    public function compilesAgainstTheGivenLayout(): void
    {
        $source = self::notes();

        $schema = $source->resolve(Layout::withSqlite(['secondary']));

        Assert::same($schema->define(Note::class, SchemaInterface::DATABASE), 'secondary');
    }

    public function singleLayoutLacksTheSecondDatabase(): void
    {
        try {
            self::notes()->resolve();
        } catch (\Throwable $e) {
            Assert::string($e->getMessage())->contains('secondary');
            return;
        }

        Assert::fail('Compiling an entity of `secondary` on Layout::single() did not fail.');
    }

    public function explicitLayoutWinsOverTheSuites(): void
    {
        $source = SchemaSource::compiled(
            static fn(DatabaseProviderInterface $dbal): array => SchemaCompiler::compile($dbal, [Note::class]),
            Layout::prefixed(['secondary' => 'secondary_']),
        );

        Assert::same($source->resolve()->define(Note::class, SchemaInterface::DATABASE), 'secondary');
    }

    public function ormRunsOnTheTwoDatabaseLayout(): void
    {
        $layout = Layout::withSqlite(['secondary']);
        $orm = Memory::orm(self::notes(), $layout);

        $note = $orm->make(Note::class, ['text' => 'hello']);

        Assert::instanceOf($note, Note::class);
        Assert::same($orm->getSource(Note::class)->getDatabase()->getName(), 'secondary');
    }

    private static function notes(): SchemaSource
    {
        return SchemaSource::compiled(
            static fn(DatabaseProviderInterface $dbal): array => SchemaCompiler::compile($dbal, [Note::class]),
        );
    }
}
