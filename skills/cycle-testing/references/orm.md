# ORM helpers

Register `new OrmPlugin(...)` in the acceptance suite's `plugins`. It builds a **fresh ORM for every test**
on the suite's `DatabaseManager` and checks the heap after each passed test.

```php
new OrmPlugin(
    schema: SchemaSource::fromArray([...]),      // or a SchemaInterface / array; empty by default
    factory: new OrmFactory(commandGenerator: $generator),   // any callable(dbal, schema): ORMInterface
    heapCheck: true,
    bind: static function (ORMInterface $orm, DatabaseContext $ctx): callable {
        Facade::setOrm($orm);                     // e.g. a static facade
        return static fn() => Facade::reset();    // runs after the test
    },
)
```

`OrmFactory` (the default) uses Doctrine collections when installed (`array` stays available by name),
`withIgnoreUninitializedRelations(true)` and `withGroupByToDeduplicate(true)`.

## `OrmTestCase extends DatabaseTestCase`

| Member | Meaning |
|---|---|
| `$orm` | the test's ORM |
| `withSchema(array\|SchemaInterface)` | replace the schema for the rest of the test (call it in `#[BeforeTest]` or the test) |
| `loadSchema($file)` | same, from a PHP file returning the schema array |
| `save(...$entities)` | persist and run through `EntityManager` |
| `extractEntity($e)` | mapper extraction |
| `assertClearState()`, `assertEntitySynced(...)` | the heap check as an explicit assertion |

```php
#[BeforeTest]
public function defineSchema(): void
{
    $this->makeTable('user', ['id' => 'primary', 'email' => 'string']);
    $this->withSchema([User::class => [/* … */]]);
}

public function loadsOne(): void
{
    QueryAssert::reads(1, fn() => $this->orm->getRepository(User::class)->findByPK(1));
}
```

## Heap check

After a passed test the plugin compares every heap entity with its node (column data and tracked
relations). A mismatch turns the test **failed** with `HeapIntegrityException`: the ORM would write wrong
data on the next sync. Opt out with `#[SkipHeapCheck]` on the method or class.

## Schemas

- `SchemaSource::fromArray($array)`, `fromSchema($schema)`, `fromFile($path)`, `compiled(fn(DatabaseProviderInterface $dbal): array)`.
  A compiled source runs once, against a throwaway in-memory SQLite database.
- `Schema\SchemaCompiler::compile($dbal, [Entity::class, ...] | ClassesInterface, ?generators)` runs the
  annotated pipeline (`ResetTables` … `SyncTables`, `GenerateTypecast`) and creates the tables in `$dbal`.
  `SchemaCompiler::defaultGenerators($classes, syncTables: false)` builds the pipeline without DDL.
- `Schema\ClassList` is a `ClassesInterface` over an explicit class list.

## Unit tests

`ORM\Memory::orm($schema)` returns an ORM over in-memory SQLite, for Unit tests that need no suite.
