<?php

declare(strict_types=1);

namespace Cycle\Testing\Database;

/**
 * Test environment settings: the defaults from `resources/docker/.env` (the file docker compose
 * interpolates), overridden by real environment variables.
 */
final class Env
{
    public const DEFAULTS_FILE = __DIR__ . '/../../resources/docker/.env';

    private static ?self $instance = null;

    /**
     * @param array<non-empty-string, string> $defaults Used when the variable is not in the environment.
     * @param array<non-empty-string, string> $overrides Win over the environment.
     */
    public function __construct(
        private readonly array $defaults = [],
        private readonly array $overrides = [],
    ) {}

    /**
     * The shared instance over {@see self::DEFAULTS_FILE}.
     */
    public static function load(): self
    {
        return self::$instance ??= self::fromFile(self::DEFAULTS_FILE);
    }

    /**
     * Parse plain `KEY=VALUE` lines; `#` comments, blank lines and surrounding quotes are allowed.
     */
    public static function fromFile(string $path): self
    {
        $lines = \is_file($path) ? \file($path, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES) : [];
        $values = [];
        foreach ($lines ?: [] as $line) {
            $line = \trim($line);
            if ($line === '' || $line[0] === '#' || !\str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = \explode('=', $line, 2);
            $key = \trim($key);
            $value = \trim($value);
            if (\strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = \substr($value, 1, -1);
            }

            $key === '' or $values[$key] = $value;
        }

        return new self($values);
    }

    /**
     * A copy whose given values win over the environment and the defaults.
     *
     * @param array<non-empty-string, string> $overrides
     */
    public function with(array $overrides): self
    {
        return new self($this->defaults, $overrides + $this->overrides);
    }

    /**
     * @throws \OutOfBoundsException When the key is neither set nor has a default.
     */
    public function get(string $key, ?string $default = null): string
    {
        if (isset($this->overrides[$key])) {
            return $this->overrides[$key];
        }

        $value = \getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $this->defaults[$key] ?? $default ?? throw new \OutOfBoundsException(
            \sprintf('Environment variable `%s` is not set and has no default.', $key),
        );
    }

    public function flag(string $key): bool
    {
        return \filter_var($this->get($key, '0'), \FILTER_VALIDATE_BOOL);
    }

    /**
     * Drivers to register suites for, from `DB_DRIVERS` (comma-separated driver values).
     *
     * The legacy `DB=<driver>` is honoured when `DB_DRIVERS` is not set in the real environment.
     *
     * @return list<Driver>
     */
    public function drivers(): array
    {
        $list = \getenv('DB_DRIVERS');
        if ($list === false && ($legacy = \getenv('DB')) !== false && $legacy !== '') {
            \fwrite(\STDERR, "cycle/testing: `DB` is deprecated, use `DB_DRIVERS`.\n");
            $list = $legacy;
        }

        $list === false and $list = $this->get('DB_DRIVERS', \implode(',', \array_column(Driver::cases(), 'value')));

        $drivers = [];
        foreach (\explode(',', $list) as $value) {
            $value = \strtolower(\trim($value));
            $value === '' or $drivers[] = Driver::tryFrom($value) ?? throw new \InvalidArgumentException(
                \sprintf('Unknown driver `%s` in DB_DRIVERS.', $value),
            );
        }

        return \array_values(\array_unique($drivers, \SORT_REGULAR));
    }

    /**
     * `DB_REQUIRED=1`: an unreachable database fails the tests instead of skipping them.
     */
    public function required(): bool
    {
        return $this->flag('DB_REQUIRED');
    }

    /**
     * `DB_DEBUG=1`: the query log is also echoed to stdout.
     */
    public function debug(): bool
    {
        return $this->flag('DB_DEBUG');
    }
}
