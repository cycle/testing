<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Acceptance\Common;

use Cycle\Testing\Database\DatabasePlugin;
use Cycle\Testing\Database\DatabaseTestCase;
use Cycle\Testing\Database\Internal\Current;
use Cycle\Testing\ORM\OrmPlugin;
use Cycle\Testing\Regression\CaseGenerator;
use Cycle\Testing\Regression\TemplateSet;
use Testo\Application\Application;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Assert;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

/**
 * Every built-in template generates a case that passes as generated, on the suite's driver.
 */
#[Test]
final class CaseTemplatesTest extends DatabaseTestCase
{
    private ?string $dir = null;

    #[AfterTest]
    public function removeCase(): void
    {
        $this->dir === null or self::remove($this->dir);
        $this->dir = null;
    }

    public function database(): void
    {
        $this->assertGeneratedCasePasses('database');
    }

    public function orm(): void
    {
        $this->assertGeneratedCasePasses('orm');
    }

    public function ormAttributes(): void
    {
        $this->assertGeneratedCasePasses('orm-attributes');
    }

    public function ormBehaviors(): void
    {
        $this->assertGeneratedCasePasses('orm-behaviors');
    }

    private static function remove(string $dir): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            $file->isDir() ? \rmdir($file->getPathname()) : \unlink($file->getPathname());
        }
        \rmdir($dir);
    }

    private function assertGeneratedCasePasses(string $template): void
    {
        $this->dir = \sys_get_temp_dir() . '/cycle-testing-case-' . \bin2hex(\random_bytes(4));
        # A fresh namespace per run: the classes of a generated case stay loaded for the rest of the process.
        $namespace = 'Cycle\Testing\Tests\Generated\Run' . \bin2hex(\random_bytes(4)) . '\Issue1';
        $files = (new CaseGenerator())->generate(TemplateSet::load($template), "{$this->dir}/Issue1", $namespace);
        Assert::notBlank($files);

        $outer = Current::$pool;
        try {
            $result = Application::createFromConfig(new ApplicationConfig(suites: [new SuiteConfig(
                name: 'Generated',
                location: new FinderConfig(["{$this->dir}/Issue1"]),
                plugins: [new DatabasePlugin($this->driver), new OrmPlugin()],
            )]))->run();
        } finally {
            # The nested suite clears the static the outer suite's assertions read.
            Current::$pool = $outer;
        }

        $outcome = [];
        foreach ($result as $suite) {
            foreach ($suite as $case) {
                foreach ($case as $test) {
                    \assert($test instanceof TestResult);
                    $outcome[$test->info->name] = $test->status === Status::Passed
                        ? 'passed'
                        : $test->status->name . ': ' . ($test->failure?->getMessage() ?? '');
                }
            }
        }

        Assert::notBlank($outcome);
        Assert::same($outcome, \array_fill_keys(\array_keys($outcome), 'passed'));
    }
}
