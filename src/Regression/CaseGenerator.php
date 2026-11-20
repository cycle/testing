<?php

declare(strict_types=1);

namespace Cycle\Testing\Regression;

/**
 * Writes a regression case from a {@see TemplateSet}: the files are copied as they are, with the template
 * namespace `Cycle\Testing\CaseTemplate\<Set>` replaced by the case's namespace.
 */
final class CaseGenerator
{
    private const TEMPLATE_NAMESPACE = '~Cycle\\\\Testing\\\\CaseTemplate\\\\\w+~';

    /**
     * The case folder name: `Issue<N>` for an issue number, otherwise the name itself.
     *
     * @return non-empty-string
     */
    public static function caseName(string $issue): string
    {
        \preg_match('~^\d+$~', $issue) === 1 and $issue = "Issue{$issue}";
        \preg_match('~^[A-Za-z_]\w*$~', $issue) === 1 or throw new \InvalidArgumentException(
            "`{$issue}` is neither an issue number nor a valid namespace segment.",
        );

        return $issue;
    }

    /**
     * The PSR-4 namespace of a folder of the project, from `autoload-dev` (then `autoload`) of its
     * `composer.json`; the longest matching prefix wins.
     *
     * @param non-empty-string $projectRoot
     * @param non-empty-string $dir Relative to the project root.
     * @return non-empty-string
     */
    public static function namespaceOf(string $projectRoot, string $dir): string
    {
        $file = "{$projectRoot}/composer.json";
        \is_file($file) or throw new \RuntimeException("No composer.json in `{$projectRoot}`.");
        /** @var array{autoload-dev?: array{psr-4?: array<string, string|list<string>>}, autoload?: array{psr-4?: array<string, string|list<string>>}} $composer */
        $composer = \json_decode((string) \file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);

        $dir = self::normalize($dir);
        $best = null;
        foreach ([$composer['autoload-dev']['psr-4'] ?? [], $composer['autoload']['psr-4'] ?? []] as $map) {
            foreach ($map as $prefix => $paths) {
                foreach ((array) $paths as $path) {
                    $path = self::normalize($path);
                    $inside = $path === '' || $dir === $path || \str_starts_with($dir, "{$path}/");
                    if (!$inside || ($best !== null && \strlen($path) <= \strlen($best[1]))) {
                        continue;
                    }
                    $best = [$prefix, $path];
                }
            }
            if ($best !== null) {
                break;
            }
        }

        $best === null and throw new \RuntimeException(
            "`{$dir}` is under no PSR-4 root of composer.json; pass the namespace explicitly.",
        );
        [$prefix, $path] = $best;
        $rest = \trim(\substr($dir, \strlen($path)), '/');

        /** @var non-empty-string */
        return \rtrim($prefix, '\\') . ($rest === '' ? '' : '\\' . \str_replace('/', '\\', $rest));
    }

    /**
     * @param non-empty-string $dir The case folder; it must not exist or be empty unless `$force`.
     * @param non-empty-string $namespace The case's namespace.
     * @return list<non-empty-string> The written files.
     */
    public function generate(TemplateSet $set, string $dir, string $namespace, bool $force = false): array
    {
        if (\is_dir($dir) && !$force && (\scandir($dir) ?: []) !== ['.', '..']) {
            throw new \RuntimeException("`{$dir}` already exists; pass --force to overwrite it.");
        }

        $written = [];
        foreach ($set->files as $relative => $source) {
            $target = "{$dir}/{$relative}";
            \is_dir(\dirname($target)) or \mkdir(\dirname($target), 0777, true);
            \file_put_contents($target, self::rewrite((string) \file_get_contents($source), $namespace));
            $written[] = $target;
        }

        return $written;
    }

    private static function rewrite(string $code, string $namespace): string
    {
        return (string) \preg_replace_callback(self::TEMPLATE_NAMESPACE, static fn(): string => $namespace, $code);
    }

    private static function normalize(string $path): string
    {
        $path = \str_replace('\\', '/', $path);
        \str_starts_with($path, './') and $path = \substr($path, 2);

        return \trim($path, '/');
    }
}
