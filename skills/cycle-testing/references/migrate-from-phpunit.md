# Porting a PHPUnit suite with per-driver stubs

Done when every driver suite's JUnit report matches the PHPUnit baseline for that driver, except tests
listed explicitly as never having run before.

1. `composer require --dev testo/bridge-rector rector/rector`. Keep PHPUnit installed until the end.
2. Baseline per driver: `DB=x vendor/bin/phpunit --group driver-x --log-junit runtime/phpunit-x.xml`.
3. Delete the generated stubs: files under `Driver/<X>/` that only set `DRIVER`, plus `generate.php`,
   `bootstrap.php` and `DontGenerateAttribute`.
4. Hand-edited stubs that override or add methods become a class in `Feature/<X>/`; mark the common
   original `#[ExceptDriver(Driver::X)]` for the overridden methods.
5. Integration cases (`Integration/Case<N>/`, `Issue<N>/`) move to `Regression/Issue<N>/` with their entities
   and `schema.php`; a case without an issue number keeps its `Case<N>` name. Fold per-driver copies of a
   case into the common one plus `#[OnlyDriver]` / `#[ExceptDriver]`.
6. Make the common abstract `*Test` classes concrete when they have no abstract methods. A class with a
   per-driver abstract hook stays abstract, with one concrete subclass per driver folder.
7. Convert base classes **together with** their subclasses in one Rector run: `BaseTest` →
   `extends DatabaseTestCase` / `OrmTestCase`, or delete it. Sets: `TestoRectorSetList::PHPUNIT_TO_TESTO`
   plus `PHPUNIT_TO_MOCKERY`; never `withParallel()`. Then run `TESTO_POLISH` twice.
8. By hand:
   - `static::DRIVER === 'x'` → `$this->driver === Driver::X`, `pick()`, or `#[OnlyDriver]` / `#[ExceptDriver]`;
   - `markTestSkipped` for a driver → the attributes;
   - `assertSameQuery` / `assertSQL` → `SqlAssert::same`; `assertNumReads` → `QueryAssert::reads`;
     `assertSameAsInDB` → `SchemaAssert::sameAsInDb`;
   - `setUp` state that relied on a new instance per test → `#[BeforeTest]`;
   - repo copies of `TableTrait`, `TableRenderer`, `TestLogger`, `Loggable`, wrap drivers → delete, use the
     harness.
9. Verify each driver:
   `vendor/bin/testo --suite=Feature/X --suite=Regression/X --log-junit runtime/testo-x.xml`, then the
   `compare-junit.php` script of the `testo-migrate-from-phpunit` skill must report IDENTICAL.
10. Remove PHPUnit, `phpunit.xml`, the Rector configs; `composer test` becomes `testo`.
