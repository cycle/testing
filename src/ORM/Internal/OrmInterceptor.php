<?php

declare(strict_types=1);

namespace Cycle\Testing\ORM\Internal;

use Cycle\ORM\ORMInterface;
use Cycle\ORM\SchemaInterface;
use Cycle\Testing\Database\DatabaseContext;
use Cycle\Testing\ORM\Attribute\SkipHeapCheck;
use Cycle\Testing\ORM\HeapIntegrity;
use Cycle\Testing\ORM\OrmAware;
use Testo\Common\Reflection;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;

/**
 * Builds a fresh ORM for every test and checks its heap afterwards.
 *
 * Ordered inside the database interceptor: the ORM is built after the transaction begins, and the heap is
 * checked before the rollback or the table drop. The check is an interceptor, not an `#[AfterTest]`
 * hook, because only an interceptor can turn a passed test into a failed one.
 *
 * @internal
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_CLOSE_TO_TEST + 1, testType: TestType::Test)]
final class OrmInterceptor implements TestRunInterceptor
{
    /**
     * @param \Closure(\Cycle\Testing\Database\Layout): SchemaInterface $schema
     * @param \Closure(\Cycle\Database\DatabaseProviderInterface, SchemaInterface): ORMInterface $factory
     * @param \Closure(ORMInterface, DatabaseContext): (callable(): void|null)|null $bind
     */
    public function __construct(
        private readonly \Closure $schema,
        private readonly \Closure $factory,
        private readonly bool $heapCheck,
        private readonly ?\Closure $bind,
    ) {}

    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $context = $info->caseInfo->getAttribute(DatabaseContext::class);
        if (!$context instanceof DatabaseContext) {
            return $next($info);
        }

        $orm = ($this->factory)($context->dbal(), ($this->schema)($context->pool->layout));
        $instance = $info->caseInfo->instance?->getInstance();
        $instance instanceof OrmAware and $instance->attachOrm($orm);
        $unbind = $this->bind === null ? null : ($this->bind)($orm, $context);

        try {
            /** @var TestResult $result */
            $result = $next($info);
            if (!$this->heapCheck || $result->status !== Status::Passed || self::skipsCheck($info)) {
                return $result;
            }

            $violations = HeapIntegrity::violations($instance instanceof OrmAware ? $instance->currentOrm() : $orm);

            return $violations === [] ? $result : new TestResult(
                $info,
                Status::Failed,
                failure: new HeapIntegrityException(\implode("\n", $violations)),
                attributes: $result->attributes,
                messages: $result->messages,
                summary: Summary::forTest(Status::Failed),
            );
        } finally {
            $unbind === null or $unbind();
        }
    }

    private static function skipsCheck(TestInfo $info): bool
    {
        $class = $info->caseInfo->definition->reflection;

        return Reflection::fetchFunctionAttributes($info->testDefinition->reflection, attributeClass: SkipHeapCheck::class, limit: 1) !== []
            || ($class !== null && Reflection::fetchClassAttributes($class, attributeClass: SkipHeapCheck::class, limit: 1) !== []);
    }
}
