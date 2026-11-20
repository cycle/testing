<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Mock;

use Cycle\Database\Driver\PDOInterface;
use Cycle\Database\Driver\PDOStatementInterface;

/**
 * A scriptable PDO for unit tests without a database: queue what each next statement returns, then read
 * back what was executed.
 *
 * ```
 * $pdo = (new FakePDO())->willReturn([['id' => 1]]);
 * $driver = StubDriver::make($pdo);
 * ```
 */
final class FakePDO implements PDOInterface
{
    /** @var list<array{sql: string, params: array|null}> */
    public array $executed = [];

    public string|false $lastInsertId = '1';

    /** @var list<list<array<array-key, mixed>>|\Throwable> */
    private array $queue = [];

    private bool $inTransaction = false;

    /** @var array<int, mixed> */
    private array $attributes = [];

    /**
     * Queue the rows the next executed statement returns.
     *
     * @param list<array<array-key, mixed>> $rows
     */
    public function willReturn(array $rows): self
    {
        $this->queue[] = $rows;

        return $this;
    }

    /**
     * Make the next executed statement throw.
     */
    public function willThrow(\Throwable $exception): self
    {
        $this->queue[] = $exception;

        return $this;
    }

    /**
     * @return list<string> The SQL of the executed statements, in order.
     */
    public function queries(): array
    {
        return \array_column($this->executed, 'sql');
    }

    public function prepare(string $query, array $options = []): PDOStatementInterface
    {
        return new FakePDOStatement($query, $this->record(...));
    }

    public function query($statement, $mode = \PDO::ATTR_DEFAULT_FETCH_MODE, ...$fetch_mode_args): PDOStatementInterface
    {
        $result = new FakePDOStatement((string) $statement, $this->record(...));
        $result->execute();

        return $result;
    }

    public function exec(string $statement): int
    {
        return \count($this->record($statement, null));
    }

    public function beginTransaction(): bool
    {
        $this->inTransaction = true;

        return true;
    }

    public function commit(): bool
    {
        $this->inTransaction = false;

        return true;
    }

    public function rollBack(): bool
    {
        $this->inTransaction = false;

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->lastInsertId;
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        $this->attributes[$attribute] = $value;

        return true;
    }

    public function getAttribute(int $attribute): mixed
    {
        return $this->attributes[$attribute] ?? null;
    }

    public function errorCode(): ?string
    {
        return null;
    }

    public function errorInfo(): array
    {
        return ['00000', null, null];
    }

    public function quote(string $string, int $type = \PDO::PARAM_STR): string
    {
        return "'" . \str_replace("'", "''", $string) . "'";
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function record(string $sql, ?array $params): array
    {
        $this->executed[] = ['sql' => $sql, 'params' => $params];
        $next = \array_shift($this->queue) ?? [];
        $next instanceof \Throwable and throw $next;

        return $next;
    }
}
