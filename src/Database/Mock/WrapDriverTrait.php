<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Mock;

use Cycle\Database\Driver\PDOInterface;
use Cycle\Database\Exception\StatementException;
use Cycle\Database\Exception\StatementException\ConnectionException;

/**
 * Instruments a real driver: counts disconnects, fails the next N transaction begins, and routes
 * statement execution through a callback. {@see self::setDefaults()} restores the knobs; the database
 * plugin calls it after every test.
 */
trait WrapDriverTrait
{
    public int $disconnectCalls = 0;
    public int $exceptionOnTransactionBegin = 0;

    /** @var \Closure(\PDOStatement $pdo, ?array $params): bool|null */
    public ?\Closure $queryCallback = null;

    /**
     * @param \Closure(\PDOStatement $pdo, ?array $params): bool $callback
     */
    public function setQueryCallback(\Closure $callback): void
    {
        $this->queryCallback = $callback;
    }

    public function disconnect(): void
    {
        ++$this->disconnectCalls;
        parent::disconnect();
    }

    public function setDefaults(): void
    {
        $this->disconnectCalls = 0;
        $this->exceptionOnTransactionBegin = 0;
        $this->queryCallback = null;
    }

    protected function getPDO(): PDOInterface
    {
        $pdo = parent::getPDO();

        return new TestPDO(
            pdo: $pdo,
            exceptionOnTransactionBegin: $this->exceptionOnTransactionBegin,
            queryCallback: $this->queryCallback,
        );
    }

    protected function mapException(\Throwable $exception, string $query): StatementException
    {
        if ($exception instanceof ConnectionException) {
            return $exception;
        }
        return parent::mapException($exception, $query);
    }
}
