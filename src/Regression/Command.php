<?php

declare(strict_types=1);

namespace Cycle\Testing\Regression;

/**
 * `vendor/bin/cycle-testing`: generates regression cases from the built-in templates.
 *
 * @internal
 */
final class Command
{
    private const USAGE = <<<'TXT'
        Usage:
          cycle-testing new-case <issue> [--template=orm] [--dir=tests/Regression] [--namespace=<ns>] [--force]
          cycle-testing templates

        new-case   Copy a template into <dir>/Issue<issue>/ (or <dir>/<issue>/ for a name).
                   The namespace comes from composer.json autoload-dev (PSR-4) unless --namespace is given.
        templates  List the built-in templates.

        TXT;

    /**
     * @param resource $out
     * @param resource $err
     */
    public function __construct(
        private readonly string $projectRoot,
        private $out = \STDOUT,
        private $err = \STDERR,
    ) {}

    /**
     * @param list<string> $argv Arguments without the script name.
     */
    public function run(array $argv): int
    {
        [$args, $options] = self::parse($argv);

        try {
            return match ($args[0] ?? null) {
                'new-case' => $this->newCase($args[1] ?? null, $options),
                'templates' => $this->templates(),
                default => $this->usage(isset($options['help']) || $args === [] ? 0 : 1),
            };
        } catch (\Throwable $e) {
            \fwrite($this->err, $e->getMessage() . "\n");
            return 1;
        }
    }

    /**
     * @param list<string> $argv
     * @return array{list<string>, array<string, string|true>}
     */
    private static function parse(array $argv): array
    {
        $args = $options = [];
        foreach ($argv as $arg) {
            if (\preg_match('~^--([\w-]+)(?:=(.*))?$~s', $arg, $m) === 1) {
                $options[$m[1]] = $m[2] ?? true;
                continue;
            }
            $args[] = $arg;
        }

        return [$args, $options];
    }

    /**
     * @param array<string, string|true> $options
     */
    private static function string(array $options, string $name, string $default): string
    {
        $value = $options[$name] ?? $default;
        $value === true and throw new \InvalidArgumentException("--{$name} needs a value: --{$name}=<value>.");

        return $value;
    }

    /**
     * @param array<string, string|true> $options
     */
    private function newCase(?string $issue, array $options): int
    {
        $issue === null and throw new \InvalidArgumentException("new-case needs an issue number or a case name.\n\n" . self::USAGE);
        $set = TemplateSet::load(self::string($options, 'template', 'orm'));
        $dir = \rtrim(\str_replace('\\', '/', self::string($options, 'dir', 'tests/Regression')), '/');
        $name = CaseGenerator::caseName($issue);
        $namespace = isset($options['namespace'])
            ? \trim(self::string($options, 'namespace', ''), '\\') . '\\' . $name
            : CaseGenerator::namespaceOf($this->projectRoot, "{$dir}/{$name}");

        $files = (new CaseGenerator())->generate($set, "{$this->projectRoot}/{$dir}/{$name}", $namespace, isset($options['force']));

        \fwrite($this->out, \sprintf("Created %s (%s, template %s):\n", "{$dir}/{$name}", $namespace, $set->name));
        foreach ($files as $file) {
            \fwrite($this->out, '  ' . \substr($file, \strlen($this->projectRoot) + 1) . "\n");
        }

        return 0;
    }

    private function templates(): int
    {
        foreach (TemplateSet::all() as $set) {
            \fwrite($this->out, \sprintf("%-16s %s\n", $set->name, $set->description));
        }

        return 0;
    }

    private function usage(int $code): int
    {
        \fwrite($code === 0 ? $this->out : $this->err, self::USAGE);

        return $code;
    }
}
