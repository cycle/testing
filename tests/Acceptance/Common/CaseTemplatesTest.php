<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Acceptance\Common;

use Cycle\Testing\Config;
use Cycle\Testing\Database\DatabaseTestCase;
use Cycle\Testing\Database\Internal\Current;
use Cycle\Testing\ORM\OrmPlugin;
use Cycle\Testing\Regression\Command;
use Testo\Application\Application;
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

    /**
     * The way a user adds a case: `cycle-testing new-case`, then a `testo.php` with a regression suite.
     */
    private function assertGeneratedCasePasses(string $template): void
    {
        $this->dir = \sys_get_temp_dir() . '/cycle-testing-case-' . \bin2hex(\random_bytes(4));
        # A fresh namespace per run: the classes of a generated case stay loaded for the rest of the process.
        $namespace = 'Cycle\Testing\Tests\Generated\Run' . \bin2hex(\random_bytes(4));
        $output = \fopen('php://memory', 'w+');

        $code = (new Command($this->dir, $output, $output))
            ->run(['new-case', '1', "--template={$template}", "--namespace={$namespace}"]);

        \rewind($output);
        Assert::string((string) \stream_get_contents($output))->startsWith('Created tests/Regression/Issue1 ');
        Assert::same($code, 0);

        $outer = Current::$pool;
        try {
            $config = Config::forPackage('cycle/generated', src: [], root: $this->dir)
                ->regression('tests/Regression', drivers: [$this->driver], plugins: [new OrmPlugin()])
                ->build();
            $result = Application::createFromConfig($config)->run();
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
