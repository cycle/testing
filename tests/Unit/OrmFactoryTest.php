<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\ORM\Collection\ArrayCollectionFactory;
use Cycle\ORM\Collection\DoctrineCollectionFactory;
use Cycle\ORM\Options;
use Cycle\ORM\ORM;
use Cycle\Testing\ORM\Memory;
use Cycle\Testing\ORM\OrmFactory;
use Cycle\Testing\ORM\SchemaSource;
use Cycle\Testing\Tests\Fixtures\UserSchema;
use Testo\Assert;
use Testo\Test;

#[Test]
final class OrmFactoryTest
{
    public function defaultsToDoctrineCollectionsAndHarnessOptions(): void
    {
        $orm = Memory::orm(UserSchema::schema());
        \assert($orm instanceof ORM);

        Assert::instanceOf($orm->getFactory()->collection(), DoctrineCollectionFactory::class);
        Assert::instanceOf($orm->getFactory()->collection('array'), ArrayCollectionFactory::class);
        $options = $orm->getService(Options::class);
        Assert::true($options->ignoreUninitializedRelations);
        Assert::true($options->groupByToDeduplicate);
    }

    public function givenOptionsReplaceTheDefaults(): void
    {
        $options = new Options();

        $orm = Memory::orm(UserSchema::schema(), factory: new OrmFactory(options: $options));
        \assert($orm instanceof ORM);

        Assert::same($orm->getService(Options::class), $options);
    }

    public function schemaSourceResolvesOncePerLayout(): void
    {
        $source = SchemaSource::of(UserSchema::schema());

        Assert::same($source->resolve(), $source->resolve());
        Assert::true($source->resolve()->defines('user'));
    }

    public function schemaObjectIsUsedAsIs(): void
    {
        $schema = SchemaSource::of(UserSchema::schema())->resolve();

        Assert::same(SchemaSource::of($schema)->resolve(), $schema);
    }
}
