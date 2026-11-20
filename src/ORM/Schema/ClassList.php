<?php

declare(strict_types=1);

namespace Cycle\Testing\ORM\Schema;

use Spiral\Tokenizer\ClassesInterface;

/**
 * A class locator over an explicit list, so schema compiling scans nothing.
 */
final class ClassList implements ClassesInterface
{
    /** @var list<class-string> */
    private readonly array $classes;

    /**
     * @param class-string ...$classes
     */
    public function __construct(string ...$classes)
    {
        $this->classes = \array_values($classes);
    }

    /**
     * Untyped: spiral/tokenizer 2.x declares `$target` without a type.
     *
     * @param object|string|null $target
     * @return array<class-string, \ReflectionClass>
     */
    public function getClasses($target = null): array
    {
        $result = [];
        foreach ($this->classes as $class) {
            $reflection = new \ReflectionClass($class);
            if ($target === null || $reflection->isSubclassOf($target) || $reflection->getName() === (\is_object($target) ? $target::class : $target)) {
                $result[$reflection->getName()] = $reflection;
            }
        }

        return $result;
    }
}
