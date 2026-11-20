<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Testing\Regression\CaseGenerator;
use Cycle\Testing\Regression\Command;
use Cycle\Testing\Regression\TemplateSet;
use Testo\Assert;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

#[Test]
final class CaseGeneratorTest
{
    private ?string $root = null;

    #[AfterTest]
    public function removeProject(): void
    {
        if ($this->root === null) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            $file->isDir() ? \rmdir($file->getPathname()) : \unlink($file->getPathname());
        }
        \rmdir($this->root);
        $this->root = null;
    }

    public function caseNameFromIssueNumber(): void
    {
        Assert::same(CaseGenerator::caseName('482'), 'Issue482');
        Assert::same(CaseGenerator::caseName('Case9'), 'Case9');
    }

    public function namespaceFromTheLongestPsr4Root(): void
    {
        $root = $this->project([
            'autoload' => ['psr-4' => ['Cycle\\ORM\\' => 'src/']],
            'autoload-dev' => ['psr-4' => ['Cycle\\ORM\\Tests\\' => 'tests/ORM/', 'Cycle\\ORM\\Fixtures\\' => 'tests/']],
        ]);

        Assert::same(
            CaseGenerator::namespaceOf($root, 'tests/ORM/Regression/Issue482'),
            'Cycle\ORM\Tests\Regression\Issue482',
        );
    }

    public function overlayReplacesOnlyItsFiles(): void
    {
        $set = TemplateSet::load('orm-behaviors');
        $base = TemplateSet::load('orm-attributes');

        Assert::same(\array_keys($set->files), \array_keys($base->files));
        Assert::true(\str_contains($set->files['Entity/User.php'], 'orm-behaviors'));
        Assert::true(\str_contains($set->files['Entity/Tag.php'], 'orm-attributes'));
    }

    public function commandWritesTheCaseInTheProjectNamespace(): void
    {
        $root = $this->project(['autoload-dev' => ['psr-4' => ['Acme\\Tests\\' => 'tests/']]]);
        $out = \fopen('php://memory', 'w+');

        $code = (new Command($root, $out, $out))->run(['new-case', '77', '--template=orm']);

        Assert::same($code, 0);
        $test = (string) \file_get_contents("{$root}/tests/Regression/Issue77/CaseTest.php");
        Assert::true(\str_contains($test, 'namespace Acme\Tests\Regression\Issue77;'));
        $schema = (string) \file_get_contents("{$root}/tests/Regression/Issue77/schema.php");
        Assert::true(\str_contains($schema, 'use Acme\Tests\Regression\Issue77\Entity\User;'));
        Assert::false(\str_contains($schema, 'CaseTemplate'));

        $again = (new Command($root, $out, $out))->run(['new-case', '77']);
        Assert::same($again, 1);
    }

    public function commandTakesDirAndNamespace(): void
    {
        $root = $this->project([]);
        $out = \fopen('php://memory', 'w+');

        $code = (new Command($root, $out, $out))
            ->run(['new-case', 'Case1', '--template=database', '--dir=tests/Cases', '--namespace=Acme\Cases']);

        Assert::same($code, 0);
        $test = (string) \file_get_contents("{$root}/tests/Cases/Case1/CaseTest.php");
        Assert::true(\str_contains($test, 'namespace Acme\Cases\Case1;'));
    }

    /**
     * @return non-empty-string
     */
    private function project(array $composer): string
    {
        $this->root = \str_replace('\\', '/', \sys_get_temp_dir()) . '/cycle-testing-project-' . \bin2hex(\random_bytes(4));
        \mkdir($this->root);
        \file_put_contents("{$this->root}/composer.json", \json_encode($composer + ['name' => 'acme/test']));

        return $this->root;
    }
}
