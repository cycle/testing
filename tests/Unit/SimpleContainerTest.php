<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Testing\Container\SimpleContainer;
use Psr\Container\NotFoundExceptionInterface;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

#[Test]
final class SimpleContainerTest
{
    public function returnsDefinitions(): void
    {
        $container = new SimpleContainer(['a' => 1, 'null' => null]);

        Assert::same($container->get('a'), 1);
        Assert::null($container->get('null'));
        Assert::true($container->has('null'));
        Assert::false($container->has('b'));
    }

    public function undefinedIdIsNotFound(): void
    {
        Expect::exception(NotFoundExceptionInterface::class);

        (new SimpleContainer())->get(\stdClass::class);
    }

    public function factoryResultIsMemoised(): void
    {
        $calls = 0;
        $container = new SimpleContainer(factory: static function (string $id) use (&$calls): object {
            ++$calls;
            return $id === 'known' ? new \stdClass() : throw new \LogicException($id);
        });

        Assert::true($container->has('known'));
        Assert::same($container->get('known'), $container->get('known'));
        Assert::same($calls, 1);
        Assert::false($container->has('unknown'));
    }
}
