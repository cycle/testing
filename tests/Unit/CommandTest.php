<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Testing\Regression\Command;
use Cycle\Testing\Regression\TemplateSet;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class CommandTest
{
    private string $root;

    /** @var resource */
    private $out;

    /** @var resource */
    private $err;

    #[BeforeTest]
    public function createProject(): void
    {
        $this->root = \str_replace('\\', '/', \sys_get_temp_dir()) . '/cycle-testing-command-' . \bin2hex(\random_bytes(4));
        \mkdir($this->root);
        \file_put_contents("{$this->root}/composer.json", '{"autoload-dev": {"psr-4": {"Acme\\\\Tests\\\\": "tests/"}}}');
        $this->out = \fopen('php://memory', 'w+');
        $this->err = \fopen('php://memory', 'w+');
    }

    #[AfterTest]
    public function removeProject(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            $file->isDir() ? \rmdir($file->getPathname()) : \unlink($file->getPathname());
        }
        \rmdir($this->root);
    }

    #[DataSet([[]], 'no arguments')]
    #[DataSet([['--help']], 'help')]
    #[DataSet([['unknown', '--help']], 'help with an unknown command')]
    #[DataSet([['--dir=a' . "\n" . 'b']], 'option value spanning lines')]
    public function usageOnRequestGoesToOutput(array $argv): void
    {
        $code = $this->run($argv);

        Assert::same($code, 0);
        Assert::string($this->output())->startsWith('Usage:');
        Assert::same($this->errors(), '');
    }

    #[DataSet([['unknown']], 'unknown command')]
    #[DataSet([['unknown--help']], 'option marker inside an argument')]
    #[DataSet([['--help!']], 'option name with a trailing symbol')]
    public function usageOnAnUnknownCommandGoesToErrors(array $argv): void
    {
        $code = $this->run($argv);

        Assert::same($code, 1);
        Assert::string($this->errors())->startsWith('Usage:');
        Assert::same($this->output(), '');
    }

    public function templatesListsEverySetWithItsDescription(): void
    {
        $expected = '';
        foreach (TemplateSet::all() as $set) {
            Assert::notSame($set->description, '');
            $expected .= \sprintf("%-16s %s\n", $set->name, $set->description);
        }

        $code = $this->run(['templates']);

        Assert::same($code, 0);
        Assert::same($this->output(), $expected);
    }

    public function newCaseWithoutIssueFailsWithUsage(): void
    {
        $code = $this->run(['new-case']);

        Assert::same($code, 1);
        Assert::string($this->errors())
            ->startsWith("new-case needs an issue number or a case name.\n\nUsage:")
            ->endsWith("List the built-in templates.\n\n");
    }

    public function optionWithoutRequiredValueFails(): void
    {
        $code = $this->run(['new-case', '1', '--template']);

        Assert::same($code, 1);
        Assert::same($this->errors(), "--template needs a value: --template=<value>.\n");
    }

    public function newCaseReportsTheWrittenFiles(): void
    {
        $code = $this->run(['new-case', 'Case1', '--template=database', '--dir=tests\\Cases/', '--namespace=\\Acme\\Cases\\']);

        Assert::same($code, 0);
        $expected = "Created tests/Cases/Case1 (Acme\\Cases\\Case1, template database):\n";
        foreach (\array_keys(TemplateSet::load('database')->files) as $file) {
            $expected .= "  tests/Cases/Case1/{$file}\n";
        }
        Assert::same($this->output(), $expected);
        Assert::string((string) \file_get_contents("{$this->root}/tests/Cases/Case1/CaseTest.php"))
            ->contains("namespace Acme\\Cases\\Case1;\n");
    }

    public function newCaseTakesTheNamespaceFromComposer(): void
    {
        $code = $this->run(['new-case', '5', '--template=database']);

        Assert::same($code, 0);
        Assert::string($this->output())->startsWith("Created tests/Regression/Issue5 (Acme\\Tests\\Regression\\Issue5, template database):\n");
    }

    private function run(array $argv): int
    {
        return (new Command($this->root, $this->out, $this->err))->run($argv);
    }

    private function output(): string
    {
        return (string) \stream_get_contents($this->out, offset: 0);
    }

    private function errors(): string
    {
        return (string) \stream_get_contents($this->err, offset: 0);
    }
}
