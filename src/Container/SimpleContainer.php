<?php

declare(strict_types=1);

namespace Cycle\Testing\Container;

use Psr\Container\ContainerInterface;

/**
 * A container of fixed definitions for tests that need a PSR-11 container, e.g. `EventDrivenCommandGenerator`.
 *
 * No autowiring: an id resolves to its definition, else to the factory's result, which is memoised.
 * {@see self::has()} asks the factory too, so it may build the value.
 */
final class SimpleContainer implements ContainerInterface
{
    private readonly \Closure $factory;

    /**
     * @param array<string, mixed> $definitions Id => value.
     * @param (\Closure(string): mixed)|null $factory Builds a value for an undefined id, like `get()`; throws
     *        when it cannot. Without one, an undefined id is not found.
     */
    public function __construct(
        private array $definitions = [],
        ?\Closure $factory = null,
    ) {
        $this->factory = $factory ?? static fn(string $id): never => throw new NotFoundException(
            "No definition or class found for \"{$id}\".",
        );
    }

    public function get(string $id): mixed
    {
        \array_key_exists($id, $this->definitions) or $this->definitions[$id] = ($this->factory)($id);

        return $this->definitions[$id];
    }

    public function has(string $id): bool
    {
        try {
            $this->get($id);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
