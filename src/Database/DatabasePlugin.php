<?php

declare(strict_types=1);

namespace Cycle\Testing\Database;

use Cycle\Testing\Database\Internal\DatabaseInterceptor;
use Cycle\Testing\Database\Internal\LocatorInterceptor;
use Internal\Container\Container;
use Testo\Common\Messenger;
use Testo\Common\PluginConfigurator;
use Testo\Pipeline\InterceptorCollector;

/**
 * Suite plugin that runs the suite's tests against one database driver.
 *
 * Register one per driver suite (`Config::feature()` and its siblings do it for you):
 *
 * ```
 * new SuiteConfig(
 *     name: 'Feature/MySQL',
 *     location: ['tests/Feature/Common', 'tests/Feature/MySQL'],
 *     plugins: [new DatabasePlugin(Driver::MySQL)],
 * )
 * ```
 *
 * The suite container gets the {@see ConnectionPool}.
 */
final class DatabasePlugin implements PluginConfigurator
{
    private readonly ?\Closure $prepare;

    /**
     * @param callable(DatabaseContext): void|null $prepare Runs once per suite, after leftover tables are
     *        dropped: create the schema and seed it. Pairs with {@see Isolation::Transaction}.
     */
    public function __construct(
        public readonly Driver $driver,
        public readonly Isolation $isolation = Isolation::DropTables,
        public readonly ?Layout $layout = null,
        ?callable $prepare = null,
        public readonly ?Env $env = null,
    ) {
        $this->prepare = $prepare === null ? null : $prepare(...);
    }

    public function configure(Container $container): void
    {
        $env = $this->env ?? Env::load();
        $pool = new ConnectionPool($this->driver, $this->layout, $env, new QueryLog(echo: $env->debug()));
        $container->set($pool);

        $collector = $container->get(InterceptorCollector::class);
        $collector->addInterceptor(new LocatorInterceptor($this->driver));
        $collector->addInterceptor(new DatabaseInterceptor(
            $pool,
            $this->isolation,
            $this->prepare,
            $env->required(),
            $container->get(Messenger::class),
        ));
    }
}
