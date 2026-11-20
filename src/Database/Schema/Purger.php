<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Schema;

use Cycle\Database\Database;
use Cycle\Database\Driver\HandlerInterface;

/**
 * Empties a database by dropping its tables.
 */
final class Purger
{
    /**
     * Drop every table of the database (tables outside its prefix stay).
     *
     * All foreign keys go first, so tables can then be dropped in any order.
     */
    public static function dropAll(Database $database): void
    {
        foreach ($database->getTables() as $table) {
            $schema = $table->getSchema();
            foreach ($schema->getForeignKeys() as $foreign) {
                $schema->dropForeignKey($foreign->getColumns());
            }

            $schema->save(HandlerInterface::DROP_FOREIGN_KEYS);
        }

        foreach ($database->getTables() as $table) {
            $schema = $table->getSchema();
            $schema->declareDropped();
            $schema->save();
        }
    }
}
