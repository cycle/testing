<?php

declare(strict_types=1);

namespace Cycle\Testing\Regression;

/**
 * A built-in regression case template: a folder under `resources/case-templates/` holding a runnable case
 * written in the namespace `Cycle\Testing\CaseTemplate\<Set>`.
 *
 * `template.json` may name a parent in `extends`; the set's files then replace or add to the parent's, by
 * relative path.
 */
final class TemplateSet
{
    /**
     * @param non-empty-string $name
     * @param array<non-empty-string, non-empty-string> $files Relative path => absolute source path.
     */
    private function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $files,
    ) {}

    /**
     * @param non-empty-string $name
     * @param non-empty-string|null $root Folder of the sets; the package's own by default.
     */
    public static function load(string $name, ?string $root = null): self
    {
        $root ??= self::defaultRoot();
        $chain = [];
        for ($current = $name; $current !== null;) {
            \in_array($current, $chain, true) and throw new \RuntimeException(
                \sprintf('Template `%s` extends itself through `%s`.', $name, \implode('` → `', $chain)),
            );
            $chain[] = $current;
            $current = self::manifest($root, $current)['extends'] ?? null;
        }

        $files = [];
        foreach (\array_reverse($chain) as $set) {
            $files = self::files("{$root}/{$set}") + $files;
        }
        \ksort($files);

        return new self($name, (string) (self::manifest($root, $name)['description'] ?? ''), $files);
    }

    /**
     * @param non-empty-string|null $root
     * @return list<self>
     */
    public static function all(?string $root = null): array
    {
        $root ??= self::defaultRoot();
        $sets = [];
        foreach (\scandir($root) ?: [] as $name) {
            $name !== '.' && $name !== '..' && \is_dir("{$root}/{$name}") and $sets[] = self::load($name, $root);
        }

        return $sets;
    }

    /**
     * @return non-empty-string
     */
    public static function defaultRoot(): string
    {
        return \dirname(__DIR__, 2) . '/resources/case-templates';
    }

    /**
     * @return array{extends?: non-empty-string, description?: string}
     */
    private static function manifest(string $root, string $name): array
    {
        \is_dir("{$root}/{$name}") or throw new \InvalidArgumentException(\sprintf(
            'Unknown template `%s`; available: %s.',
            $name,
            \implode(', ', \array_filter(\scandir($root) ?: [], static fn(string $d): bool => $d[0] !== '.')),
        ));
        $file = "{$root}/{$name}/template.json";

        /** @var array{extends?: non-empty-string, description?: string} */
        return \is_file($file) ? \json_decode((string) \file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR) : [];
    }

    /**
     * @return array<non-empty-string, non-empty-string>
     */
    private static function files(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);
            $relative = \str_replace('\\', '/', \substr($file->getPathname(), \strlen($dir) + 1));
            $relative === 'template.json' or $files[$relative] = $file->getPathname();
        }

        return $files;
    }
}
