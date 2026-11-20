<?php

declare(strict_types=1);

namespace Cycle\Testing\Database;

use Cycle\Database\Config\ConnectionConfig;
use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\Config\DriverConfig;
use Cycle\Database\Config\SQLite\MemoryConnectionConfig;
use Cycle\Database\Config\SQLiteDriverConfig;
use Cycle\Database\Database;
use Cycle\Database\DatabaseManager;
use Cycle\Database\Driver\DriverInterface;
use Cycle\Database\NamedInterface;
use Psr\Log\LoggerAwareInterface;

/**
 * The connections of one suite, which means one driver.
 *
 * Drivers are created lazily, memoised by their options and reused by every test of the suite; the
 * plugin closes them all when the suite ends.
 */
final class ConnectionPool
{
    public readonly Layout $layout;
    public readonly Env $env;

    /** @var array<string, DriverInterface> */
    private array $drivers = [];

    /** @var array<string, DatabaseManager> */
    private array $managers = [];

    public function __construct(
        public readonly Driver $driver,
        ?Layout $layout = null,
        ?Env $env = null,
        public readonly QueryLog $queryLog = new QueryLog(),
    ) {
        $this->layout = $layout ?? Layout::single();
        $this->env = $env ?? Env::load();
    }

    /**
     * A driver on the suite's database, memoised by its options.
     *
     * Options are applied to a copy of the suite's driver config: `driver(driverOptions: ['options' =>
     * ['withDatetimeMicroseconds' => true]])`, or `driverOptions: ['schema' => ['public', 'other']]` for
     * Postgres. The `options` key is merged into the defaults; other keys replace config properties.
     *
     * @param array<non-empty-string, mixed> $driverOptions DriverConfig property => value.
     * @param array<non-empty-string, mixed> $connectionOptions ConnectionConfig property => value.
     * @param class-string<DriverInterface>|null $driverClass
     */
    public function driver(
        array $driverOptions = [],
        array $connectionOptions = [],
        ?string $driverClass = null,
    ): DriverInterface {
        $key = \hash('crc32', $driverClass . ':' . \json_encode($driverOptions) . \json_encode($connectionOptions));

        return $this->drivers[$key] ??= $this->create(
            'default',
            self::applyOptions($this->driver->config($this->env, $driverClass), $driverOptions, $connectionOptions),
        );
    }

    /**
     * The manager over the layout's databases.
     *
     * @param bool $wrap Put `default` (and every database sharing its connection) on the wrap driver
     *        ({@see Driver::wrapDriverClass()}).
     */
    public function manager(bool $wrap = false): DatabaseManager
    {
        $key = $wrap ? 'wrap' : 'plain';
        if (isset($this->managers[$key])) {
            return $this->managers[$key];
        }

        $manager = new DatabaseManager(new DatabaseConfig(['default' => 'default', 'databases' => [], 'connections' => []]));
        $main = $this->driver(driverClass: $wrap ? $this->driver->wrapDriverClass() : null);
        foreach ($this->layout->names() as $name) {
            /** @psalm-suppress InvalidArgument The config templates are narrower than SQLite's config. */
            $driver = $this->layout->isSqlite($name)
                ? $this->drivers["sqlite:{$name}"] ??= $this->create($name, new SQLiteDriverConfig(
                    connection: new MemoryConnectionConfig(),
                    options: ['logInterpolatedQueries' => true],
                ))
                : $main;
            $manager->addDatabase(new Database($name, $this->layout->prefix($name), $driver));
        }

        return $this->managers[$key] = $manager;
    }

    /**
     * @return list<DatabaseManager> The managers built so far.
     */
    public function managers(): array
    {
        return \array_values($this->managers);
    }

    /**
     * @return list<DriverInterface> The drivers created so far.
     */
    public function drivers(): array
    {
        return \array_values($this->drivers);
    }

    /**
     * Whether `default` is an in-memory SQLite database, which closing the connection empties.
     */
    public function isMemory(): bool
    {
        return $this->driver === Driver::SQLite
            && $this->driver->config($this->env)->connection instanceof MemoryConnectionConfig;
    }

    public function disconnect(): void
    {
        foreach ($this->drivers as $driver) {
            $driver->disconnect();
        }
    }

    /**
     * @param array<non-empty-string, mixed> $driverOptions
     * @param array<non-empty-string, mixed> $connectionOptions
     */
    private static function applyOptions(DriverConfig $config, array $driverOptions, array $connectionOptions): DriverConfig
    {
        foreach ($driverOptions as $key => $value) {
            $key === 'options' and $value += $config->options;
            $config->$key = $value;
        }

        if ($connectionOptions !== []) {
            $connection = clone $config->connection;
            foreach ($connectionOptions as $key => $value) {
                $connection->$key = $value;
            }
            /** @var ConnectionConfig $connection */
            $config->connection = $connection;
        }

        return $config;
    }

    /**
     * @param non-empty-string $name Named like `DatabaseConfig` names its drivers: after the connection,
     *        which code under test compares to tell databases apart.
     */
    private function create(string $name, DriverConfig $config): DriverInterface
    {
        /**
         * @psalm-suppress ArgumentTypeCoercion, InvalidArgument Each driver accepts its own config subclass.
         * @var DriverInterface $driver
         */
        $driver = $config->driver::create($config);
        $driver instanceof NamedInterface and $driver = $driver->withName($name);
        $driver instanceof LoggerAwareInterface and $driver->setLogger($this->queryLog);

        return $driver;
    }
}
