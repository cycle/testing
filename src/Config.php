<?php

declare(strict_types=1);

namespace Cycle\Testing;

use Cycle\Testing\Database\DatabasePlugin;
use Cycle\Testing\Database\Driver;
use Cycle\Testing\Database\Env;
use Cycle\Testing\Database\Isolation;
use Cycle\Testing\Database\Layout;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Bridge\Mockery\MockeryPlugin;
use Testo\Codecov\CodecovPlugin;
use Testo\Codecov\Config\CoverageLevel;
use Testo\Codecov\Config\CoverageMode;
use Testo\Codecov\Report\CloverReport;
use Testo\Common\PluginConfigurator;

/**
 * Builds the `testo.php` config of a Cycle package: a unit suite, per-driver suites of each kind, coverage.
 *
 * ```
 * return Config::forPackage('cycle/orm', root: __DIR__)
 *     ->unit('tests/Unit')
 *     ->feature('tests/Feature', layout: Layout::prefixed(['secondary' => 'secondary_']))
 *     ->regression('tests/Regression')
 *     ->build();
 * ```
 *
 * Everything it emits is plain Testo config, so a repo can always write `testo.php` by hand instead.
 */
final class Config
{
    /** @var list<SuiteConfig> */
    private array $suites = [];

    /** @var list<PluginConfigurator> */
    private array $plugins = [];

    /**
     * @param list<non-empty-string>|FinderConfig $src
     */
    private function __construct(
        private readonly string $name,
        private readonly array|FinderConfig $src,
        private readonly string $root,
        private readonly Env $env,
    ) {}

    /**
     * @param non-empty-string $name Package name, used in coverage reports.
     * @param list<non-empty-string>|FinderConfig $src Production code: the coverage scope.
     * @param string|null $root Project root that relative paths resolve against, usually `__DIR__`; the
     *        working directory by default.
     */
    public static function forPackage(
        string $name,
        array|FinderConfig $src = ['src'],
        ?string $root = null,
        ?Env $env = null,
    ): self {
        return new self($name, $src, \rtrim($root ?? (string) \getcwd(), '/\\'), $env ?? Env::load());
    }

    /**
     * A suite with no database. Missing paths are left out; with none left, no suite is added.
     *
     * @param non-empty-string|list<non-empty-string>|FinderConfig $location
     * @param iterable<PluginConfigurator> $plugins
     */
    public function unit(string|array|FinderConfig $location, iterable $plugins = [], string $name = 'Unit'): self
    {
        $location instanceof FinderConfig or $location = $this->existing(\is_string($location) ? [$location] : $location);

        return $location === [] ? $this : $this->suite(new SuiteConfig(
            name: $name,
            location: $location,
            plugins: [...$this->mockery(), ...self::list($plugins)],
        ));
    }

    /**
     * One feature at a time against a real database: suites `Feature/<Driver>`.
     *
     * @param non-empty-string $path Holds `Common/` and the driver folders.
     * @param list<Driver>|null $drivers
     * @param (callable(Database\DatabaseContext): void)|null $prepare
     * @param iterable<PluginConfigurator> $plugins
     * @param non-empty-string|null $common
     * @param array<value-of<Driver>, non-empty-string> $folders
     * @param list<non-empty-string> $exclude
     * @see self::perDriver() for the options.
     */
    public function feature(
        string $path,
        ?array $drivers = null,
        Isolation $isolation = Isolation::DropTables,
        ?Layout $layout = null,
        ?callable $prepare = null,
        iterable $plugins = [],
        ?string $common = 'Common',
        array $folders = [],
        array $exclude = [],
    ): self {
        return $this->perDriver('Feature', $path, $drivers, $isolation, $layout, $prepare, $plugins, $common, $folders, $exclude);
    }

    /**
     * End-to-end scenarios through the public API: suites `Acceptance/<Driver>`.
     *
     * @param non-empty-string $path Holds `Common/` and the driver folders.
     * @param list<Driver>|null $drivers
     * @param (callable(Database\DatabaseContext): void)|null $prepare
     * @param iterable<PluginConfigurator> $plugins
     * @param non-empty-string|null $common
     * @param array<value-of<Driver>, non-empty-string> $folders
     * @param list<non-empty-string> $exclude
     * @see self::perDriver() for the options.
     */
    public function acceptance(
        string $path,
        ?array $drivers = null,
        Isolation $isolation = Isolation::DropTables,
        ?Layout $layout = null,
        ?callable $prepare = null,
        iterable $plugins = [],
        ?string $common = 'Common',
        array $folders = [],
        array $exclude = [],
    ): self {
        return $this->perDriver('Acceptance', $path, $drivers, $isolation, $layout, $prepare, $plugins, $common, $folders, $exclude);
    }

    /**
     * Reproductions of reported issues, one folder per issue (`Issue123/`): suites `Regression/<Driver>`.
     *
     * Every issue folder runs on every driver; a driver folder (`MySQL/`), if any, joins only its own suite.
     *
     * @param non-empty-string $path Holds the issue folders.
     * @param list<Driver>|null $drivers
     * @param (callable(Database\DatabaseContext): void)|null $prepare
     * @param iterable<PluginConfigurator> $plugins
     * @param non-empty-string|null $common
     * @param array<value-of<Driver>, non-empty-string> $folders
     * @param list<non-empty-string> $exclude
     * @see self::perDriver() for the options.
     */
    public function regression(
        string $path,
        ?array $drivers = null,
        Isolation $isolation = Isolation::DropTables,
        ?Layout $layout = null,
        ?callable $prepare = null,
        iterable $plugins = [],
        ?string $common = null,
        array $folders = [],
        array $exclude = [],
    ): self {
        return $this->perDriver('Regression', $path, $drivers, $isolation, $layout, $prepare, $plugins, $common, $folders, $exclude);
    }

    /**
     * One suite per driver, named `<name>/<Driver>`, over `<path>/<common>` plus `<path>/<Driver>`.
     *
     * Missing folders are left out; a driver with neither folder gets no suite. With `common: null` the
     * whole path is common, except the other drivers' folders.
     *
     * @param non-empty-string $name Suite kind, e.g. `Feature`.
     * @param non-empty-string $path
     * @param list<Driver>|null $drivers Drivers to register; `DB_DRIVERS` by default.
     * @param (callable(Database\DatabaseContext): void)|null $prepare Runs once per suite; see {@see DatabasePlugin}.
     * @param iterable<PluginConfigurator> $plugins Added after the database plugin, e.g. `OrmPlugin`.
     * @param non-empty-string|null $common Folder joining every driver suite; `null` makes it the path itself.
     * @param array<value-of<Driver>, non-empty-string> $folders Driver value => folder, to rename a driver folder.
     * @param list<non-empty-string> $exclude Paths under the root that hold no tests, e.g. fixtures.
     */
    public function perDriver(
        string $name,
        string $path,
        ?array $drivers = null,
        Isolation $isolation = Isolation::DropTables,
        ?Layout $layout = null,
        ?callable $prepare = null,
        iterable $plugins = [],
        ?string $common = 'Common',
        array $folders = [],
        array $exclude = [],
    ): self {
        $path = \rtrim($path, '/\\');
        $plugins = self::list($plugins);
        $folder = static fn(Driver $driver): string => $path . '/' . ($folders[$driver->value] ?? $driver->folder());
        $config = $this;
        foreach ($drivers ?? $this->env->drivers() as $driver) {
            if ($common === null) {
                $include = $this->existing([$path]);
                $others = \array_values(\array_filter(Driver::cases(), static fn(Driver $d): bool => $d !== $driver));
                $skip = [...$exclude, ...\array_map($folder, $others)];
            } else {
                $include = $this->existing(["{$path}/{$common}", $folder($driver)]);
                $skip = $exclude;
            }

            $include === [] or $config = $config->suite(new SuiteConfig(
                name: $driver->suiteName($name),
                location: new FinderConfig(include: $include, exclude: $this->existing($skip)),
                plugins: [
                    ...$this->mockery(),
                    new DatabasePlugin($driver, $isolation, $layout, $prepare, $this->env),
                    ...$plugins,
                ],
            ));
        }

        return $config;
    }

    /**
     * Add a suite as is.
     */
    public function suite(SuiteConfig $suite): self
    {
        $clone = clone $this;
        $clone->suites[] = $suite;

        return $clone;
    }

    /**
     * Add application-level plugins.
     */
    public function plugins(PluginConfigurator ...$plugins): self
    {
        $clone = clone $this;
        $clone->plugins = [...$this->plugins, ...\array_values($plugins)];

        return $clone;
    }

    /**
     * The config; coverage goes to `runtime/clover.xml` when collected (`--coverage`).
     */
    public function build(): ApplicationConfig
    {
        return new ApplicationConfig(
            src: $this->src instanceof FinderConfig ? $this->src : $this->existing($this->src),
            # Testo demands a suite; one that locates nothing makes a repo without test folders run empty.
            suites: $this->suites ?: [new SuiteConfig(name: 'Unit', location: new FinderConfig())],
            plugins: [
                new CodecovPlugin(
                    level: CoverageLevel::Line,
                    collect: CoverageMode::Never,
                    reports: [new CloverReport($this->root . '/runtime/clover.xml', $this->name)],
                ),
                ...$this->plugins,
            ],
        );
    }

    /**
     * @param iterable<PluginConfigurator> $plugins
     * @return list<PluginConfigurator>
     */
    private static function list(iterable $plugins): array
    {
        return \is_array($plugins) ? \array_values($plugins) : \iterator_to_array($plugins, false);
    }

    /**
     * Without the plugin, Mockery expectations would go unverified silently.
     *
     * @return list<PluginConfigurator>
     */
    private function mockery(): array
    {
        return \class_exists(MockeryPlugin::class) && \class_exists(\Mockery::class) ? [new MockeryPlugin()] : [];
    }

    /**
     * Paths resolved against the root, missing ones left out: Testo rejects a location that does not exist.
     *
     * @param list<string> $paths
     * @return list<non-empty-string>
     */
    private function existing(array $paths): array
    {
        return \array_values(\array_filter(\array_map($this->path(...), $paths), \file_exists(...)));
    }

    /**
     * @return non-empty-string
     */
    private function path(string $path): string
    {
        return \preg_match('~^(?:[a-z]:)?[/\\\\]~i', $path) === 1 ? $path : $this->root . '/' . $path;
    }
}
