<?php

declare(strict_types=1);

namespace Cycle\Testing\Database\Internal;

use Cycle\Testing\Database\Attribute\DriverFilter;
use Cycle\Testing\Database\DatabaseAware;
use Cycle\Testing\Database\Driver;
use Cycle\Testing\ORM\OrmAware;
use Testo\Common\Reflection;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\CaseLocatorInterceptor;
use Testo\Tokenizer\Reflection\FileDefinitions;

/**
 * Shapes the located tests of a driver suite:
 *
 * - tests that `#[OnlyDriver]` / `#[ExceptDriver]` keep out of this suite's driver are deactivated, not
 *   skipped: they are not part of this suite at all, as if they had no copy for the driver;
 * - the harness callbacks (`attachDatabase()`, `attachOrm()`) are not tests, although a class-level
 *   `#[Test]` makes every public `void` method one.
 *
 * @internal
 */
#[InterceptorOptions(testType: TestType::Test)]
final class LocatorInterceptor implements CaseLocatorInterceptor
{
    public function __construct(
        private readonly Driver $driver,
    ) {}

    public function locateTestCases(FileDefinitions $file, callable $next): CaseDefinitions
    {
        /** @var CaseDefinitions $result */
        $result = $next($file);

        foreach ($result->getCases() as $case) {
            if ($case->type !== TestType::Test->value) {
                continue;
            }

            $classAllows = $case->reflection === null || self::allows(Reflection::fetchClassAttributes(
                $case->reflection,
                attributeClass: DriverFilter::class,
                flags: \ReflectionAttribute::IS_INSTANCEOF,
                mergePolicy: Reflection::MERGE_FIRST,
            ), $this->driver);

            # Every member, not only tests: the test locator may sit inside this one and mark them later.
            foreach ($case->tests->all() as $test) {
                self::isHarnessCallback($test->reflection) and $test->isTest = false;

                $own = Reflection::fetchFunctionAttributes(
                    $test->reflection,
                    attributeClass: DriverFilter::class,
                    flags: \ReflectionAttribute::IS_INSTANCEOF,
                    mergePolicy: Reflection::MERGE_FIRST,
                );

                ($own === [] ? $classAllows : self::allows($own, $this->driver)) or $test->active = false;
            }
        }

        return $result;
    }

    private static function isHarnessCallback(\ReflectionFunctionAbstract $method): bool
    {
        if (!$method instanceof \ReflectionMethod) {
            return false;
        }

        $class = $method->getDeclaringClass();

        return ($method->name === 'attachDatabase' && $class->implementsInterface(DatabaseAware::class))
            || ($method->name === 'attachOrm' && \interface_exists(OrmAware::class) && $class->implementsInterface(OrmAware::class));
    }

    /**
     * @param list<\ReflectionAttribute<DriverFilter>> $attributes
     */
    private static function allows(array $attributes, Driver $driver): bool
    {
        foreach ($attributes as $attribute) {
            $filter = $attribute->newInstance();
            \assert($filter instanceof DriverFilter);
            if (!$filter->allows($driver)) {
                return false;
            }
        }

        return true;
    }
}
