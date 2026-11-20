<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Testing\Database\DatabasePlugin;
use Cycle\Testing\Database\Driver;
use Cycle\Testing\ORM\OrmPlugin;
use Testo\Application\Application;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Assert;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Test;

#[Test]
final class OrmPluginTest
{
    public function passedHeapCheckCountsAsAnAssertion(): void
    {
        $statuses = self::run(\dirname(__DIR__) . '/Fixtures/HeapCheckSuite');

        Assert::same($statuses['savesWithoutAssertions'], Status::Passed);
        Assert::same($statuses['skipsTheCheck'], Status::Risky);
    }

    /**
     * @return array<string, Status> Test name => status.
     */
    private static function run(string $location): array
    {
        $result = Application::createFromConfig(new ApplicationConfig(suites: [new SuiteConfig(
            name: 'Nested',
            location: new FinderConfig([$location]),
            plugins: [new DatabasePlugin(Driver::SQLite), new OrmPlugin()],
        )]))->run();

        $statuses = [];
        foreach ($result as $suite) {
            foreach ($suite as $case) {
                foreach ($case as $test) {
                    \assert($test instanceof TestResult);
                    $statuses[$test->info->name] = $test->status;
                }
            }
        }

        return $statuses;
    }
}
