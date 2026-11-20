<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Schema;

use Cycle\Database\Exception\SchemaException;
use Cycle\Database\Schema\AbstractColumn;
use Cycle\Database\Schema\AbstractTable;

/**
 * Declares table columns from short string definitions.
 *
 * ```
 * [
 *     'id'      => 'primary',
 *     'name'    => 'string',                   // 255 characters
 *     'email'   => 'string(255), nullable',
 *     'status'  => 'enum(active, disabled)',
 *     'balance' => 'decimal(10, 2)',
 *     'message' => 'text, null',               // `null` is an alias of `nullable`
 * ]
 * ```
 *
 * Every column is NOT NULL unless flagged. A NOT NULL column without a default gets one guessed from its
 * type (0, '', false, the first enum value), so it can be added to a table that already has rows; flag it
 * `required` to skip that.
 */
final class TableRenderer
{
    /**
     * @param array<non-empty-string, non-empty-string> $columns Column name => definition.
     * @param array<non-empty-string, mixed> $defaults Column name => default value; a null default makes
     *        the column nullable.
     *
     * @throws SchemaException
     */
    public function renderColumns(AbstractTable $table, array $columns, array $defaults = []): void
    {
        $primaryKeys = [];
        foreach ($columns as $name => $definition) {
            $type = $this->parse($table->getName(), $name, $definition);
            $this->hasFlag($type, 'primary') and $primaryKeys[] = $name;

            $this->renderColumn(
                $table->column($name),
                $type,
                \array_key_exists($name, $defaults),
                $defaults[$name] ?? null,
            );
        }

        $primaryKeys === [] or $table->setPrimaryKeys($primaryKeys);
    }

    /**
     * @param array{type: string, options: list<string|int>, flags: list<string>} $type
     *
     * @throws SchemaException
     */
    private function renderColumn(AbstractColumn $column, array $type, bool $hasDefault, mixed $default): void
    {
        $column->nullable($this->hasFlag($type, 'null') || $this->hasFlag($type, 'nullable'));

        try {
            # Calls the type method directly, past AbstractColumn::__call().
            \call_user_func_array([$column, $type['type']], $type['options']);
        } catch (\Throwable $e) {
            throw new SchemaException(
                "Invalid column type definition in '{$column->getTable()}'.'{$column->getName()}'",
                (int) $e->getCode(),
                $e,
            );
        }

        if (\in_array($column->getAbstractType(), ['primary', 'bigPrimary', 'smallPrimary'], true)) {
            return;
        }

        if (!$hasDefault && !$column->isNullable()) {
            if (!$this->hasFlag($type, 'required') && !$this->hasFlag($type, 'primary')) {
                $column->defaultValue($this->castDefault($column));
            }

            return;
        }

        $default === null and $column->nullable(true);
        $column->defaultValue($default);
    }

    /**
     * @return array{type: string, options: list<string|int>, flags: list<string>}
     */
    private function parse(string $table, string $column, string $definition): array
    {
        if (!\preg_match(
            '/(?P<type>[a-z]+)(?: *\((?P<options>[^\)]+)\))?(?: *, *(?P<flags>.+))?/i',
            $definition,
            $matches,
        )) {
            throw new SchemaException("Invalid column type definition in '{$table}'.'{$column}'");
        }

        $split = static fn(string $list): array => $list === '' ? [] : \array_map('trim', \explode(',', $list));
        # The column methods take typed sizes: `string(64)` must pass the int 64.
        $options = \array_map(
            static fn(string $o): string|int => \preg_match('/^\d+$/', $o) === 1 ? (int) $o : $o,
            $split($matches['options'] ?? ''),
        );

        return [
            'type' => $matches['type'],
            'options' => $options,
            'flags' => $split($matches['flags'] ?? ''),
        ];
    }

    /**
     * @param array{type: string, options: list<string|int>, flags: list<string>} $type
     */
    private function hasFlag(array $type, string $flag): bool
    {
        return \in_array($flag, $type['flags'], true);
    }

    private function castDefault(AbstractColumn $column): mixed
    {
        if (\in_array($column->getAbstractType(), ['timestamp', 'datetime', 'time', 'date'], true)) {
            return 0;
        }

        if ($column->getAbstractType() === 'enum') {
            return $column->getEnumValues()[0];
        }

        return match ($column->getType()) {
            'int' => 0,
            'float' => 0.0,
            'bool' => false,
            default => '',
        };
    }
}
