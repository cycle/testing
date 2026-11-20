<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Internal;

use Cycle\Database\Database;
use Cycle\Database\Driver\DriverInterface;
use Cycle\Testing\Database\Attribute\Isolate;
use Cycle\Testing\Database\Attribute\WrapDriver;
use Cycle\Testing\Database\ConnectionPool;
use Cycle\Testing\Database\DatabaseContext;
use Cycle\Testing\Database\Isolation;
use Cycle\Testing\Database\Mock\WrapDriverInterface;
use Cycle\Testing\Database\Schema\Purger;
use Testo\Common\Messenger;
use Testo\Common\Reflection;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\SuiteInfo;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Exception\SkipTest;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestCaseRunInterceptor;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Middleware\TestSuiteRunInterceptor;

/**
 * Owns the suite's connections and isolates its tests.
 *
 * - Suite: probe the databases once, purge what a crashed run left, run `prepare`, close everything at
 *   the end.
 * - Case: attach a {@see DatabaseContext} to the instance.
 * - Test: roll back a dangling transaction, apply the isolation mode, reset the query log and the wrap
 *   driver knobs.
 *
 * `#[BeforeTest]` / `#[AfterTest]` run inside the test step (Testo's lifecycle interceptor is innermost),
 * so their work is inside the transaction and before the tables are dropped.
 *
 * @internal
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_CLOSE_TO_TEST, testType: TestType::Test)]
final class DatabaseInterceptor implements TestSuiteRunInterceptor, TestCaseRunInterceptor, TestRunInterceptor
{
    private const CONTEXT = DatabaseContext::class;

    /** Why the databases are unusable, or null while they are. */
    private ?string $unavailable = null;

    /**
     * @param \Closure(DatabaseContext): void|null $prepare
     */
    public function __construct(
        private readonly ConnectionPool $pool,
        private readonly Isolation $isolation,
        private readonly ?\Closure $prepare,
        private readonly bool $required,
        private readonly Messenger $messenger,
    ) {}

    public function runTestSuite(SuiteInfo $info, callable $next): SuiteResult
    {
        Current::$pool = $this->pool;
        $this->pool->queryLog->forwardTo($this->messenger->channel('query.sql'));
        $this->unavailable = $this->probe();

        if ($this->unavailable === null) {
            try {
                $this->pool->isMemory() or $this->purge();
                $this->prepare === null or ($this->prepare)(new DatabaseContext($this->pool->driver, $this->pool));
            } catch (\Throwable $e) {
                $this->unavailable = \sprintf('Preparing the %s database failed: %s', $this->pool->driver->name, $e->getMessage());
            }
        }

        if ($this->unavailable !== null) {
            # Marked skipped, the cases run no class hooks, which would reach for the database.
            foreach ($info->testCases->getCases() as $case) {
                foreach ($case->tests->getTests() as $test) {
                    $test->skipped = true;
                }
            }
        }

        try {
            return $next($info);
        } finally {
            $this->pool->disconnect();
            $this->pool->queryLog->forwardTo(null);
            Current::$pool = null;
        }
    }

    public function runTestCase(CaseInfo $info, callable $next): CaseResult
    {
        $class = $info->definition->reflection;
        $wrap = $class !== null && Reflection::fetchClassAttributes($class, attributeClass: WrapDriver::class, limit: 1) !== [];
        $context = new DatabaseContext($this->pool->driver, $this->pool, $wrap);

        $info->instance === null or $info = $info->withInstance(new ContextInjectingInstance($info->instance, $context));

        return $next($info->withAttribute(self::CONTEXT, $context));
    }

    public function runTest(TestInfo $info, callable $next): TestResult
    {
        if ($this->unavailable !== null) {
            $status = $this->required ? Status::Failed : Status::Skipped;
            $failure = $this->required ? new \RuntimeException($this->unavailable) : new SkipTest($this->unavailable);

            return new TestResult($info, $status, failure: $failure, summary: Summary::forTest($status));
        }

        /** @var DatabaseContext $context */
        $context = $info->caseInfo->getAttribute(self::CONTEXT);
        $isolation = $this->isolationOf($info);
        $this->rollbackAll();
        if ($isolation === Isolation::Transaction) {
            foreach (self::connections($context) as $driver) {
                $driver->beginTransaction();
            }
        }

        try {
            return $next($info);
        } finally {
            try {
                match ($isolation) {
                    Isolation::Transaction => $this->rollbackAll(),
                    Isolation::DropTables => $this->pool->isMemory() ? $this->pool->disconnect() : $this->purge(),
                    Isolation::None => null,
                };
            } finally {
                $this->pool->queryLog->reset();
                foreach ($this->pool->drivers() as $driver) {
                    $driver instanceof WrapDriverInterface and $driver->setDefaults();
                }
            }
        }
    }

    /**
     * @return list<DriverInterface> The distinct connections behind the layout's databases.
     */
    private static function connections(DatabaseContext $context): array
    {
        $drivers = [];
        foreach ($context->pool->layout->names() as $name) {
            $driver = $context->database($name)->getDriver();
            $drivers[\spl_object_id($driver)] = $driver;
        }

        return \array_values($drivers);
    }

    private function probe(): ?string
    {
        try {
            foreach ($this->pool->layout->names() as $name) {
                $this->pool->manager()->database($name)->getDriver()->connect();
            }

            return null;
        } catch (\Throwable $e) {
            return \sprintf(
                '%s at %s is unreachable: %s',
                $this->pool->driver->name,
                $this->pool->driver->config($this->pool->env)->connection->getDsn(),
                $e->getMessage(),
            );
        }
    }

    /**
     * Drop all tables on every connection of the layout, including tables outside the layout's prefixes.
     */
    private function purge(): void
    {
        $this->rollbackAll();
        foreach (self::connections(new DatabaseContext($this->pool->driver, $this->pool)) as $driver) {
            Purger::dropAll(new Database('purge', '', $driver));
        }
    }

    private function rollbackAll(): void
    {
        foreach ($this->pool->drivers() as $driver) {
            while ($driver->isConnected() && $driver->getTransactionLevel() > 0) {
                $driver->rollbackTransaction();
            }
        }
    }

    private function isolationOf(TestInfo $info): Isolation
    {
        $attributes = Reflection::fetchFunctionAttributes($info->testDefinition->reflection, attributeClass: Isolate::class, limit: 1);
        $class = $info->caseInfo->definition->reflection;
        $attributes === [] && $class !== null and $attributes = Reflection::fetchClassAttributes(
            $class,
            attributeClass: Isolate::class,
            limit: 1,
        );

        return $attributes === [] ? $this->isolation : $attributes[0]->newInstance()->mode;
    }
}
