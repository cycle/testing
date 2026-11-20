<?php

declare(strict_types=1);

namespace Cycle\Testing\Database;

/**
 * The named databases a suite provides, and how each maps onto a connection.
 *
 * `default` always exists and uses the suite's driver. Other databases either share that connection
 * with a table prefix ({@see self::prefixed()}) or get their own in-memory SQLite connection
 * ({@see self::withSqlite()}).
 */
final class Layout
{
    /**
     * @param non-empty-array<non-empty-string, array{prefix: string, sqlite: bool}> $databases
     */
    private function __construct(
        private readonly array $databases,
    ) {}

    /**
     * Only `default`.
     */
    public static function single(): self
    {
        return new self(['default' => ['prefix' => '', 'sqlite' => false]]);
    }

    /**
     * Databases on the suite's connection, told apart by table prefix:
     * `prefixed(['secondary' => 'secondary_'])`. A `default` key sets the prefix of `default`.
     *
     * @param array<non-empty-string, string> $prefixes Database name => table prefix.
     */
    public static function prefixed(array $prefixes): self
    {
        $databases = ['default' => ['prefix' => '', 'sqlite' => false]];
        foreach ($prefixes as $name => $prefix) {
            $databases[$name] = ['prefix' => $prefix, 'sqlite' => false];
        }

        return new self($databases);
    }

    /**
     * Extra databases, each on its own in-memory SQLite connection whatever the suite's driver.
     *
     * @param list<non-empty-string> $names
     */
    public static function withSqlite(array $names): self
    {
        $databases = ['default' => ['prefix' => '', 'sqlite' => false]];
        foreach ($names as $name) {
            $name === 'default' and throw new \InvalidArgumentException('`default` always uses the suite driver.');
            $databases[$name] = ['prefix' => '', 'sqlite' => true];
        }

        return new self($databases);
    }

    /**
     * @return non-empty-list<non-empty-string>
     */
    public function names(): array
    {
        return \array_keys($this->databases);
    }

    public function prefix(string $name): string
    {
        return $this->databases[$name]['prefix'] ?? throw new \OutOfBoundsException("No database `{$name}`.");
    }

    /**
     * Whether the database has its own in-memory SQLite connection.
     */
    public function isSqlite(string $name): bool
    {
        return $this->databases[$name]['sqlite'] ?? throw new \OutOfBoundsException("No database `{$name}`.");
    }
}
