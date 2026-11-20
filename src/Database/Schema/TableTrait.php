<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Schema;

use Cycle\Database\Database;
use Cycle\Database\ForeignKeyInterface;

/**
 * {@see TableBuilder} over the `default` database of the using class.
 */
trait TableTrait
{
    /**
     * @see TableBuilder::makeTable()
     */
    protected function makeTable(
        string $table,
        array $columns,
        array $fk = [],
        ?array $pk = null,
        array $defaults = [],
    ): void {
        (new TableBuilder($this->getDatabase()))->makeTable($table, $columns, $fk, $pk, $defaults);
    }

    protected function makeFK(
        string $from,
        string|array $fromKey,
        string $to,
        string|array $toColumn,
        string $onDelete = ForeignKeyInterface::CASCADE,
        string $onUpdate = ForeignKeyInterface::CASCADE,
    ): void {
        (new TableBuilder($this->getDatabase()))->makeFK($from, $fromKey, $to, $toColumn, $onDelete, $onUpdate);
    }

    protected function makeCompositeFK(
        string $from,
        array $fromKeys,
        string $to,
        array $toColumns,
        string $onDelete = ForeignKeyInterface::CASCADE,
        string $onUpdate = ForeignKeyInterface::CASCADE,
    ): void {
        (new TableBuilder($this->getDatabase()))->makeCompositeFK($from, $fromKeys, $to, $toColumns, $onDelete, $onUpdate);
    }

    protected function makeIndex(string $table, array $columns, bool $unique = false): void
    {
        (new TableBuilder($this->getDatabase()))->makeIndex($table, $columns, $unique);
    }

    abstract protected function getDatabase(): Database;
}
