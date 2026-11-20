<?php

declare(strict_types=1);

namespace Cycle\Testing\ORM;

use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\Schema;
use Cycle\ORM\SchemaInterface;
use Cycle\Testing\Database\Layout;
use Cycle\Testing\Database\Memory;

/**
 * Where an ORM schema comes from; resolved once per layout, on first use.
 */
final class SchemaSource
{
    /** @var array<string, SchemaInterface> Layout fingerprint => schema. */
    private array $schemas = [];

    /**
     * @param \Closure(Layout): SchemaInterface $resolve
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
     * A schema compiled by the callback, against throwaway in-memory SQLite databases: the compiled schema
     * does not depend on the driver. Tables the compiler syncs land in those databases, not in the suite's;
     * create them in the suite with the plugin's `prepare` callback.
     *
     * The databases follow `$layout`, else the suite's layout (passed in by {@see OrmPlugin}), else
     * `Layout::single()`: entities mapped to `secondary` need a layout that has it.
     *
     * @param callable(DatabaseProviderInterface): array $compile For example {@see Schema\SchemaCompiler::compile()}.
     */
    public static function compiled(callable $compile, ?Layout $layout = null): self
    {
        $compile = $compile(...);

        return new self(static fn(Layout $suite): SchemaInterface => new Schema($compile(Memory::manager($layout ?? $suite))));
    }

    public static function of(self|SchemaInterface|array $schema): self
    {
        return match (true) {
            $schema instanceof self => $schema,
            $schema instanceof SchemaInterface => self::fromSchema($schema),
            default => self::fromArray($schema),
        };
    }

    /**
     * @param Layout|null $layout The suite's layout; only a compiled schema depends on it.
     */
    public function resolve(?Layout $layout = null): SchemaInterface
    {
        $layout ??= Layout::single();

        return $this->schemas[\serialize($layout)] ??= ($this->resolve)($layout);
    }
}
