<?php

declare(strict_types=1);

namespace Cycle\Testing\Database;

use Cycle\Database\Config\SQLite\MemoryConnectionConfig;
use Cycle\Database\Config\SQLiteDriverConfig;
use Cycle\Database\Database;
use Cycle\Database\DatabaseManager;
use Cycle\Database\Driver\SQLite\SQLiteDriver;

/**
 * In-memory SQLite databases for unit tests, outside any driver suite.
 */
final class Memory
{
    /**
     * A manager over the layout's databases, each a fresh in-memory SQLite database.
     */
    public static function manager(?Layout $layout = null, ?QueryLog $log = null): DatabaseManager
    {
        $env = Env::load()->with(['DB_SQLITE_FILE' => '']);

        return (new ConnectionPool(Driver::SQLite, $layout, $env, $log ?? new QueryLog()))->manager();
    }

    public static function database(string $name = 'default', string $prefix = ''): Database
    {
        return new Database($name, $prefix, SQLiteDriver::create(new SQLiteDriverConfig(connection: new MemoryConnectionConfig())));
    }
}
