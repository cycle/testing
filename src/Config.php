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
 * Builds the `testo.php` config of a Cycle package: a unit suite, one acceptance suite per driver, coverage.
 *
 * ```
 * return Config::forPackage('cycle/orm', root: __DIR__)
 *     ->unit('tests/Unit')
 *     ->acceptance('tests/Acceptance', layout: Layout::prefixed(['secondary' => 'secondary_']))
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
     * A suite with no database.
     *
     * @param non-empty-string|list<non-empty-string>|FinderConfig $location
     * @param iterable<PluginConfigurator> $plugins
     */
    public function unit(string|array|FinderConfig $location, iterable $plugins = [], string $name = 'Unit'): self
    {
        return $this->suite(new SuiteConfig(
            name: $name,
            location: $location instanceof FinderConfig
                ? $location
                : \array_map($this->path(...), \is_string($location) ? [$location] : $location),
            plugins: [...$this->mockery(), ...self::list($plugins)],
        ));
    }

    /**
     * One suite per driver, named `<name>/<Driver>`, over `<root>/<common>` plus `<root>/<Driver>`.
     *
     * Missing folders are left out; a driver with neither folder gets no suite.
     *
     * @param non-empty-string $root
     * @param non-empty-string $name
     * @param list<Driver>|null $drivers Drivers to register; `DB_DRIVERS` by default.
     * @param (callable(Database\DatabaseContext): void)|null $prepare Runs once per suite; see {@see DatabasePlugin}.
     * @param iterable<PluginConfigurator> $plugins Added after the database plugin, e.g. `OrmPlugin`.
     * @param array<value-of<Driver>, non-empty-string> $folders Driver value => folder, to rename a driver folder.
     * @param list<non-empty-string> $exclude Paths under the root that hold no tests, e.g. fixtures.
     */
    public function acceptance(
        string $root,
        string $name = 'Acceptance',
        ?array $drivers = null,
        Isolation $isolation = Isolation::DropTables,
        ?Layout $layout = null,
        ?callable $prepare = null,
        iterable $plugins = [],
        string $common = 'Common',
        array $folders = [],
        array $exclude = [],
    ): self {
        $root = \rtrim($root, '/\\');
        $plugins = self::list($plugins);
        $config = $this;
        foreach ($drivers ?? $this->env->drivers() as $driver) {
            $include = \array_values(\array_filter(
                \array_map($this->path(...), ["{$root}/{$common}", $root . '/' . ($folders[$driver->value] ?? $driver->folder())]),
                \is_dir(...),
            ));

            $include === [] or $config = $config->suite(new SuiteConfig(
                name: $driver->suiteName($name),
                location: new FinderConfig(include: $include, exclude: \array_map($this->path(...), $exclude)),
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
        $this->suites === [] and throw new \LogicException('No suites: add unit() or acceptance() with existing folders.');

        return new ApplicationConfig(
            src: $this->src,
            suites: $this->suites,
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
     * Without the plugin, Mockery expectations would go unverified silently.
     *
     * @return list<PluginConfigurator>
     */
    private function mockery(): array
    {
        return \class_exists(MockeryPlugin::class) && \class_exists(\Mockery::class) ? [new MockeryPlugin()] : [];
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
     * @return non-empty-string
     */
    private function path(string $path): string
    {
        return \preg_match('~^(?:[a-z]:)?[/\\\\]~i', $path) === 1 ? $path : $this->root . '/' . $path;
    }
}
