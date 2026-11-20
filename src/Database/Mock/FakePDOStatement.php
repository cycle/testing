<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Mock;

use Cycle\Database\Driver\PDOStatementInterface;

/**
 * A statement of {@see FakePDO}: on execute it takes the next queued rows.
 *
 * @internal
 */
final class FakePDOStatement implements PDOStatementInterface
{
    /** @var list<array<array-key, mixed>> */
    private array $rows = [];

    /** @var array<array-key, mixed> */
    private array $bound = [];

    /**
     * @param \Closure(string, array|null): list<array<array-key, mixed>> $execute
     */
    public function __construct(
        public readonly string $queryString,
        private readonly \Closure $execute,
    ) {}

    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->rows);
    }

    public function execute(?array $params = null): bool
    {
        $this->rows = ($this->execute)($this->queryString, $params ?? ($this->bound ?: null));

        return true;
    }

    public function fetch(
        int $mode = \PDO::FETCH_BOTH,
        int $cursorOrientation = \PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0,
    ): mixed {
        $row = \array_shift($this->rows);

        return $row === null ? false : self::shape($row, $mode);
    }

    public function bindParam(
        int|string $param,
        mixed &$var,
        int $type = \PDO::PARAM_STR,
        ?int $maxLength = null,
        mixed $driverOptions = null,
    ): bool {
        $this->bound[$param] = $var;

        return true;
    }

    public function bindColumn(
        int|string $column,
        mixed &$var,
        int $type = \PDO::PARAM_STR,
        ?int $maxLength = null,
        mixed $driverOptions = null,
    ): bool {
        return true;
    }

    public function bindValue(int|string $param, mixed $value, int $type = \PDO::PARAM_STR): bool
    {
        $this->bound[$param] = $value;

        return true;
    }

    public function rowCount(): int
    {
        return \count($this->rows);
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = \array_shift($this->rows);

        return $row === null ? false : (\array_values($row)[$column] ?? null);
    }

    public function fetchAll(int $mode = \PDO::FETCH_BOTH, mixed ...$args): array
    {
        $rows = \array_map(static fn(array $row): mixed => self::shape($row, $mode), $this->rows);
        $this->rows = [];

        return $rows;
    }

    public function fetchObject(?string $class = \stdClass::class, array $constructorArgs = []): object|false
    {
        $row = \array_shift($this->rows);

        return $row === null ? false : (object) $row;
    }

    public function errorCode(): ?string
    {
        return null;
    }

    public function errorInfo(): array
    {
        return ['00000', null, null];
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return true;
    }

    public function getAttribute(int $name): mixed
    {
        return null;
    }

    public function columnCount(): int
    {
        return \count($this->rows[0] ?? []);
    }

    public function getColumnMeta(int $column): array|false
    {
        return false;
    }

    public function setFetchMode($mode, $className = null, ...$params)
    {
        return true;
    }

    public function nextRowset(): bool
    {
        return false;
    }

    public function closeCursor(): bool
    {
        $this->rows = [];

        return true;
    }

    public function debugDumpParams(): ?bool
    {
        return null;
    }

    private static function shape(array $row, int $mode): mixed
    {
        return match ($mode) {
            \PDO::FETCH_NUM => \array_values($row),
            \PDO::FETCH_OBJ => (object) $row,
            \PDO::FETCH_COLUMN => \array_values($row)[0] ?? null,
            default => $row,
        };
    }
}
