<?php

declare(strict_types=1);

namespace Cycle\Testing\Database;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * PSR-3 logger attached to every pooled driver: counts and records the executed queries.
 *
 * A message counts as a query only when its context carries `elapsed`, which the driver adds for executed
 * statements and not for transaction notices. `INSERT`, `UPDATE` and `DELETE` are writes; everything else is
 * a read, except Postgres catalog introspection, which the driver runs on its own.
 *
 * Messages are forwarded to {@see self::forwardTo()} (the Testo `query.sql` channel, which attaches them to
 * the running test's report).
 */
final class QueryLog extends AbstractLogger
{
    private int $reads = 0;
    private int $writes = 0;
    private int $readMark = 0;
    private int $writeMark = 0;

    /** @var list<string> */
    private array $queries = [];

    private ?LoggerInterface $forward = null;

    public function forwardTo(?LoggerInterface $logger): void
    {
        $this->forward = $logger;
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $message = (string) $message;
        $this->forward?->log($level, $message . "\n", $context);

        if (empty($context['elapsed'])) {
            return;
        }

        $sql = \strtolower(\ltrim($message));
        if (\str_starts_with($sql, 'insert') || \str_starts_with($sql, 'update') || \str_starts_with($sql, 'delete')) {
            ++$this->writes;
        } elseif (self::isPostgresIntrospection($sql)) {
            return;
        } else {
            ++$this->reads;
        }

        $this->queries[] = $message;
    }

    public function countReads(): int
    {
        return $this->reads;
    }

    public function countWrites(): int
    {
        return $this->writes;
    }

    /**
     * @return list<string> Counted queries since the last {@see self::reset()}, in order.
     */
    public function queries(): array
    {
        return $this->queries;
    }

    /**
     * Start counting reads afresh for {@see self::readsSinceMark()}; the totals keep running.
     */
    public function markReads(): void
    {
        $this->readMark = $this->reads;
    }

    /**
     * Start counting writes afresh for {@see self::writesSinceMark()}; the totals keep running.
     */
    public function markWrites(): void
    {
        $this->writeMark = $this->writes;
    }

    /**
     * Reads since {@see self::markReads()}, or since the last {@see self::reset()} when not marked since.
     */
    public function readsSinceMark(): int
    {
        return $this->reads - $this->readMark;
    }

    /**
     * Writes since {@see self::markWrites()}, or since the last {@see self::reset()} when not marked since.
     */
    public function writesSinceMark(): int
    {
        return $this->writes - $this->writeMark;
    }

    /**
     * Zero the counters, the marks and the recorded queries; the harness does it after every test.
     */
    public function reset(): void
    {
        $this->reads = 0;
        $this->writes = 0;
        $this->readMark = 0;
        $this->writeMark = 0;
        $this->queries = [];
    }

    /**
     * Run the callback and report the queries it executed.
     *
     * @template T
     * @param callable(): T $callback
     * @return array{result: T, reads: int, writes: int, queries: list<string>}
     */
    public function capture(callable $callback): array
    {
        $reads = $this->reads;
        $writes = $this->writes;
        $offset = \count($this->queries);

        $result = $callback();

        return [
            'result' => $result,
            'reads' => $this->reads - $reads,
            'writes' => $this->writes - $writes,
            'queries' => \array_slice($this->queries, $offset),
        ];
    }

    private static function isPostgresIntrospection(string $sql): bool
    {
        foreach (['tc.constraint_name', 'pg_indexes', 'pg_constraint', 'information_schema', 'pg_class'] as $marker) {
            if (\str_contains($sql, $marker)) {
                return true;
            }
        }

        return false;
    }
}
