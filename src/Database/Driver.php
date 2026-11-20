<?php

declare(strict_types=1);

namespace Cycle\Testing\Database;

use Cycle\Database\Config\DriverConfig;
use Cycle\Database\Config\MySQL\TcpConnectionConfig as MySQLConnection;
use Cycle\Database\Config\MySQLDriverConfig;
use Cycle\Database\Config\Postgres\TcpConnectionConfig as PostgresConnection;
use Cycle\Database\Config\PostgresDriverConfig;
use Cycle\Database\Config\SQLite\FileConnectionConfig;
use Cycle\Database\Config\SQLite\MemoryConnectionConfig;
use Cycle\Database\Config\SQLiteDriverConfig;
use Cycle\Database\Config\SQLServer\TcpConnectionConfig as SQLServerConnection;
use Cycle\Database\Config\SQLServerDriverConfig;
use Cycle\Database\Driver\DriverInterface;
use Cycle\Testing\Database\Mock\MySQLWrapDriver;
use Cycle\Testing\Database\Mock\PostgresWrapDriver;
use Cycle\Testing\Database\Mock\SQLiteWrapDriver;
use Cycle\Testing\Database\Mock\SQLServerWrapDriver;

/**
 * A database driver a suite runs against.
 *
 * The value is the key used in env vars (`DB_<VALUE>_*`), `DB_DRIVERS` and {@see self::pick()}; the case
 * name is the driver folder name in a per-driver suite and the suffix of the suite name.
 */
enum Driver: string
{
    case SQLite = 'sqlite';
    case MySQL = 'mysql';
    case Postgres = 'postgres';
    case SQLServer = 'sqlserver';

    /**
     * Connection timeout in seconds; an unreachable server fails the availability probe within it.
     */
    public const CONNECT_TIMEOUT = 2;

    /**
     * @return non-empty-string The folder holding tests that run only on this driver, e.g. `MySQL`.
     */
    public function folder(): string
    {
        return $this->name;
    }

    /**
     * @param non-empty-string $kind Suite kind, e.g. `Feature`.
     * @return non-empty-string For example `Feature/MySQL`.
     */
    public function suiteName(string $kind): string
    {
        return $kind . '/' . $this->name;
    }

    /**
     * Build the driver config from env values (see `resources/docker/.env`).
     *
     * Every config logs interpolated queries, so the query log shows SQL with the bound values.
     *
     * @param class-string<DriverInterface>|null $driverClass Replaces the default driver class,
     *        e.g. with a wrap driver.
     */
    public function config(?Env $env = null, ?string $driverClass = null): DriverConfig
    {
        $env ??= Env::load();
        $options = ['logInterpolatedQueries' => true];
        /** @var array<int, non-empty-string> $timeout PDO converts the value; the config's docblock is narrower. */
        $timeout = [\PDO::ATTR_TIMEOUT => (string) self::CONNECT_TIMEOUT];

        $config = match ($this) {
            self::SQLite => new SQLiteDriverConfig(
                connection: $env->get('DB_SQLITE_FILE', '') === ''
                    ? new MemoryConnectionConfig()
                    : new FileConnectionConfig($env->get('DB_SQLITE_FILE')),
                options: $options,
            ),
            self::MySQL => new MySQLDriverConfig(
                connection: new MySQLConnection(
                    database: $env->get('DB_MYSQL_DATABASE'),
                    host: $env->get('DB_MYSQL_HOST'),
                    port: (int) $env->get('DB_MYSQL_PORT'),
                    charset: 'utf8mb4',
                    user: $env->get('DB_MYSQL_USER'),
                    password: $env->get('DB_MYSQL_PASSWORD'),
                    options: $timeout,
                ),
                options: $options,
            ),
            self::Postgres => new PostgresDriverConfig(
                connection: new PostgresConnection(
                    database: $env->get('DB_POSTGRES_DATABASE'),
                    host: $env->get('DB_POSTGRES_HOST'),
                    port: (int) $env->get('DB_POSTGRES_PORT'),
                    user: $env->get('DB_POSTGRES_USER'),
                    password: $env->get('DB_POSTGRES_PASSWORD'),
                    options: $timeout,
                ),
                schema: 'public',
                options: $options,
            ),
            self::SQLServer => new SQLServerDriverConfig(
                connection: new SQLServerConnection(
                    database: $env->get('DB_SQLSERVER_DATABASE'),
                    host: $env->get('DB_SQLSERVER_HOST'),
                    port: (int) $env->get('DB_SQLSERVER_PORT'),
                    # pdo_sqlsrv ignores PDO::ATTR_TIMEOUT for the login; LoginTimeout goes into the DSN.
                    timeout: self::CONNECT_TIMEOUT,
                    trustServerCertificate: true,
                    user: $env->get('DB_SQLSERVER_USER'),
                    password: $env->get('DB_SQLSERVER_PASSWORD'),
                ),
                options: $options,
            ),
        };

        $driverClass === null or $config->driver = $driverClass;

        return $config;
    }

    /**
     * @return class-string<DriverInterface> The instrumented subclass of this driver ({@see Mock\WrapDriverTrait}).
     */
    public function wrapDriverClass(): string
    {
        return match ($this) {
            self::SQLite => SQLiteWrapDriver::class,
            self::MySQL => MySQLWrapDriver::class,
            self::Postgres => PostgresWrapDriver::class,
            self::SQLServer => SQLServerWrapDriver::class,
        };
    }

    /**
     * Select a value for this driver by named argument: `pick(default: 'A', sqlserver: 'B')`.
     *
     * Keys are driver values (`sqlite`, `mysql`, `postgres`, `sqlserver`) or `default`.
     *
     * @throws \LogicException When neither this driver's key nor `default` is given.
     */
    public function pick(mixed ...$variants): mixed
    {
        foreach (\array_keys($variants) as $key) {
            $key === 'default' || self::tryFrom((string) $key) !== null or throw new \InvalidArgumentException(
                \sprintf('Unknown variant `%s`: use a driver value or `default`.', $key),
            );
        }

        return match (true) {
            \array_key_exists($this->value, $variants) => $variants[$this->value],
            \array_key_exists('default', $variants) => $variants['default'],
            default => throw new \LogicException(\sprintf('No variant for `%s` and no `default`.', $this->value)),
        };
    }

    public function supports(Feature $feature): bool
    {
        return \in_array($this, $feature->drivers(), true);
    }

    /**
     * @return array{non-empty-string, non-empty-string} The opening and closing identifier quotes.
     */
    public function quotes(): array
    {
        return match ($this) {
            self::MySQL => ['`', '`'],
            self::SQLServer => ['[', ']'],
            self::SQLite, self::Postgres => ['"', '"'],
        };
    }

    /**
     * Quote an identifier the way this driver's query compiler does: `quote('users')` → `"users"`.
     */
    public function quote(string $identifier): string
    {
        [$open, $close] = $this->quotes();

        return $open . $identifier . $close;
    }
}
