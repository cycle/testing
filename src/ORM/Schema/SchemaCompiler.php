<?php

declare(strict_types=1);

namespace Cycle\Testing\ORM\Schema;

use Cycle\Annotated\Embeddings;
use Cycle\Annotated\Entities;
use Cycle\Annotated\Locator\TokenizerEmbeddingLocator;
use Cycle\Annotated\Locator\TokenizerEntityLocator;
use Cycle\Annotated\MergeColumns;
use Cycle\Annotated\MergeIndexes;
use Cycle\Annotated\TableInheritance;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\Parser\Typecast;
use Cycle\ORM\SchemaInterface;
use Cycle\Schema\Compiler;
use Cycle\Schema\Generator\GenerateModifiers;
use Cycle\Schema\Generator\GenerateRelations;
use Cycle\Schema\Generator\GenerateTypecast;
use Cycle\Schema\Generator\RenderModifiers;
use Cycle\Schema\Generator\RenderRelations;
use Cycle\Schema\Generator\RenderTables;
use Cycle\Schema\Generator\ResetTables;
use Cycle\Schema\Generator\SyncTables;
use Cycle\Schema\Generator\ValidateEntities;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Spiral\Tokenizer\ClassesInterface;

/**
 * Compiles an ORM schema from annotated entities. Needs `cycle/annotated` and `cycle/schema-builder`.
 */
final class SchemaCompiler
{
    /**
     * Run the generator pipeline against the database provider. With the default pipeline the tables are
     * also created there ({@see SyncTables}).
     *
     * @param ClassesInterface|list<class-string> $classes Entity and embeddable classes.
     * @param list<GeneratorInterface>|null $generators Replaces {@see self::defaultGenerators()}.
     * @param array<int, mixed> $defaults Schema defaults; a typecast handler by default.
     * @return array<non-empty-string, array<int, mixed>>
     */
    public static function compile(
        DatabaseProviderInterface $dbal,
        ClassesInterface|array $classes,
        ?array $generators = null,
        array $defaults = [SchemaInterface::TYPECAST_HANDLER => [Typecast::class]],
    ): array {
        $classes instanceof ClassesInterface or $classes = new ClassList(...$classes);

        return (new Compiler())->compile(
            new Registry($dbal),
            $generators ?? self::defaultGenerators($classes),
            $defaults,
        );
    }

    /**
     * @return list<GeneratorInterface>
     */
    public static function defaultGenerators(ClassesInterface $classes, bool $syncTables = true): array
    {
        return \array_values(\array_filter([
            new ResetTables(),
            new Embeddings(new TokenizerEmbeddingLocator($classes)),
            new Entities(new TokenizerEntityLocator($classes)),
            new TableInheritance(),
            new MergeColumns(),
            new GenerateRelations(),
            new GenerateModifiers(),
            new ValidateEntities(),
            new RenderTables(),
            new RenderRelations(),
            new RenderModifiers(),
            new MergeIndexes(),
            $syncTables ? new SyncTables() : null,
            new GenerateTypecast(),
        ]));
    }
}
