<?php

declare(strict_types=1);

namespace Cycle\Testing\ORM;

use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\Schema;
use Cycle\ORM\SchemaInterface;
use Cycle\Testing\Database\Memory;

/**
 * Where an ORM schema comes from; resolved once, on first use.
 */
final class SchemaSource
{
    private ?SchemaInterface $schema = null;

    /**
     * @param \Closure(): SchemaInterface $resolve
     */
    private function __construct(
        private readonly \Closure $resolve,
    ) {}

    public static function empty(): self
    {
        return self::fromArray([]);
    }

    /**
     * @param array<non-empty-string, array<int, mixed>> $schema
     */
    public static function fromArray(array $schema): self
    {
        return new self(static fn(): SchemaInterface => new Schema($schema));
    }

    public static function fromSchema(SchemaInterface $schema): self
    {
        return new self(static fn(): SchemaInterface => $schema);
    }

    /**
     * A PHP file that returns the schema array.
     */
    public static function fromFile(string $path): self
    {
        return new self(static fn(): SchemaInterface => new Schema(require $path));
    }

    /**
     * A schema compiled by the callback, against a throwaway in-memory SQLite database: the compiled schema
     * does not depend on the driver. Tables the compiler syncs land in that database, not in the suite's;
     * create them in the suite with the plugin's `prepare` callback.
     *
     * @param callable(DatabaseProviderInterface): array $compile For example {@see Schema\SchemaCompiler::compile()}.
     */
    public static function compiled(callable $compile): self
    {
        $compile = $compile(...);

        return new self(static fn(): SchemaInterface => new Schema($compile(Memory::manager())));
    }

    public static function of(self|SchemaInterface|array $schema): self
    {
        return match (true) {
            $schema instanceof self => $schema,
            $schema instanceof SchemaInterface => self::fromSchema($schema),
            default => self::fromArray($schema),
        };
    }

    public function resolve(): SchemaInterface
    {
        return $this->schema ??= ($this->resolve)();
    }
}
