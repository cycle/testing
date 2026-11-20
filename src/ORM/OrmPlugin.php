<?php

declare(strict_types=1);

namespace Cycle\Testing\ORM;

use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\SchemaInterface;
use Cycle\Testing\Database\DatabaseContext;
use Cycle\Testing\ORM\Internal\OrmInterceptor;
use Internal\Container\Container;
use Testo\Common\PluginConfigurator;
use Testo\Pipeline\InterceptorCollector;

/**
 * Suite plugin, listed next to `DatabasePlugin`: a fresh ORM per test and a heap check after it.
 *
 * Test classes get the ORM through {@see OrmAware} ({@see OrmTestCase} implements it).
 */
final class OrmPlugin implements PluginConfigurator
{
    private readonly SchemaSource $schema;
    private readonly \Closure $factory;
    private readonly ?\Closure $bind;

    /**
     * @param SchemaSource|SchemaInterface|array|null $schema The schema every test starts with; empty by default.
     * @param (callable(DatabaseProviderInterface, SchemaInterface): ORMInterface)|null $factory Builds the ORM;
     *        {@see OrmFactory} by default.
     * @param bool $heapCheck Fail a passed test whose heap is out of sync ({@see HeapIntegrity}).
     * @param (callable(ORMInterface, DatabaseContext): (callable(): void|null))|null $bind Runs before each
     *        test with its ORM, e.g. to point a facade at it; a returned callable runs after the test.
     */
    public function __construct(
        SchemaSource|SchemaInterface|array|null $schema = null,
        ?callable $factory = null,
        private readonly bool $heapCheck = true,
        ?callable $bind = null,
    ) {
        $this->schema = SchemaSource::of($schema ?? []);
        $this->factory = ($factory ?? new OrmFactory())(...);
        $this->bind = $bind === null ? null : $bind(...);
    }

    public function configure(Container $container): void
    {
        $container->get(InterceptorCollector::class)->addInterceptor(new OrmInterceptor(
            $this->schema->resolve(...),
            $this->factory,
            $this->heapCheck,
            $this->bind,
        ));
    }
}
