<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Schema;

use Cycle\Database\Database;
use Cycle\Database\ForeignKeyInterface;

/**
 * Creates tables, foreign keys and indexes in a database from short definitions (see {@see TableRenderer}).
 */
final class TableBuilder
{
    private readonly TableRenderer $renderer;

    public function __construct(
        private readonly Database $database,
    ) {
        $this->renderer = new TableRenderer();
    }

    /**
     * @param array<non-empty-string, non-empty-string> $columns Column name => definition, e.g. `'string(32), nullable'`.
     * @param array<non-empty-string|int, array{table: string, column: string|list<string>, from?: string|list<string>, onDelete?: string, onUpdate?: string}> $fk
     *        Column (or `from`) => referenced `table` and `column`; rules default to CASCADE.
     * @param list<non-empty-string>|null $pk Explicit primary key; by default the `primary` columns.
     * @param array<non-empty-string, mixed> $defaults Column name => default value.
     */
    public function makeTable(
        string $table,
        array $columns,
        array $fk = [],
        ?array $pk = null,
        array $defaults = [],
    ): void {
        $schema = $this->database->table($table)->getSchema();
        $this->renderer->renderColumns($schema, $columns, $defaults);

        foreach ($fk as $column => $options) {
            $schema->foreignKey((array) ($options['from'] ?? $column))
                ->references($options['table'], (array) $options['column'])
                ->onUpdate($options['onUpdate'] ?? ForeignKeyInterface::CASCADE)
                ->onDelete($options['onDelete'] ?? ForeignKeyInterface::CASCADE);
        }

        empty($pk) or $schema->setPrimaryKeys($pk);
        $schema->save();
    }

    /**
     * @param non-empty-string|list<non-empty-string> $fromKey
     * @param non-empty-string|list<non-empty-string> $toColumn
     */
    public function makeFK(
        string $from,
        string|array $fromKey,
        string $to,
        string|array $toColumn,
        string $onDelete = ForeignKeyInterface::CASCADE,
        string $onUpdate = ForeignKeyInterface::CASCADE,
    ): void {
        $schema = $this->database->table($from)->getSchema();
        $schema->foreignKey((array) $fromKey)
            ->references($to, (array) $toColumn)
            ->onDelete($onDelete)
            ->onUpdate($onUpdate);
        $schema->save();
    }

    /**
     * @param list<non-empty-string> $fromKeys
     * @param list<non-empty-string> $toColumns
     */
    public function makeCompositeFK(
        string $from,
        array $fromKeys,
        string $to,
        array $toColumns,
        string $onDelete = ForeignKeyInterface::CASCADE,
        string $onUpdate = ForeignKeyInterface::CASCADE,
    ): void {
        $this->makeFK($from, $fromKeys, $to, $toColumns, $onDelete, $onUpdate);
    }

    /**
     * @param list<non-empty-string> $columns
     */
    public function makeIndex(string $table, array $columns, bool $unique = false): void
    {
        $schema = $this->database->table($table)->getSchema();
        $schema->index($columns)->unique($unique);
        $schema->save();
    }
}
