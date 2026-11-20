<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Testing\Regression\CaseGenerator;
use Cycle\Testing\Regression\Command;
use Cycle\Testing\Regression\TemplateSet;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Expect;
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

    #[DataSet(['12a'], 'digits then letters')]
    #[DataSet(['a-b'], 'a dash after a valid start')]
    #[DataSet(['-b'], 'a dash before a valid end')]
    public function caseNameMustBeANamespaceSegment(string $issue): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessage("`{$issue}` is neither an issue number nor a valid namespace segment.");

        CaseGenerator::caseName($issue);
    }

    public function namespaceNeedsComposerJson(): never
    {
        $root = $this->project([]);
        \unlink("{$root}/composer.json");

        Expect::exception(\RuntimeException::class)->withMessage("No composer.json in `{$root}`.");

        CaseGenerator::namespaceOf($root, 'tests/Regression');
    }

    public function namespaceOutsideEveryRootFails(): never
    {
        $root = $this->project(['autoload' => ['psr-4' => ['App\\' => 'src/']]]);

        Expect::exception(\RuntimeException::class)->withMessageContaining('`tests/Regression` is under no PSR-4 root');

        CaseGenerator::namespaceOf($root, 'tests/Regression');
    }

    /**
     * @param array<string, string|list<string>> $dev
     * @param array<string, string|list<string>> $prod
     */
    #[DataSet([[], ['App\\Tests\\' => 'tests/']], 'from autoload when autoload-dev has no match')]
    #[DataSet([['App\\' => ''], [], 'App\tests\Regression\Issue1'], 'from the root mapping')]
    #[DataSet([['App\\Tests\\' => 'tests/', 'Long\\' => 'tests-longer-folder/']], 'ignoring a longer root it is not under')]
    #[DataSet([['Other\\' => 'lib/', 'App\\Tests\\' => 'tests/']], 'after a root it is not under')]
    #[DataSet([['App\\Tests\\' => ['lib/', 'tests/']]], 'from the second path of a root')]
    #[DataSet([['App\\Tests\\' => 'tests/', 'Other\\Tests\\' => 'tests/']], 'from the first of equal roots')]
    #[DataSet([['App\\Tests\\' => './tests']], 'from a dot-relative root')]
    #[DataSet([['App\\Tests\\' => 'tests\\']], 'from a root with backslashes')]
    #[DataSet([['App\\Tests\\' => 'tests/'], ['Prod\\' => 'tests/Regression/']], 'from autoload-dev before autoload')]
    public function namespaceOfIssueFolder(array $dev, array $prod = [], string $expected = 'App\Tests\Regression\Issue1'): void
    {
        $root = $this->project(['autoload-dev' => ['psr-4' => $dev], 'autoload' => ['psr-4' => $prod]]);

        Assert::same(CaseGenerator::namespaceOf($root, 'tests/Regression/Issue1'), $expected);
    }

    public function namespaceOfTheRootFolderItself(): void
    {
        $root = $this->project(['autoload-dev' => ['psr-4' => ['App\\Tests\\' => 'tests/Regression']]]);

        Assert::same(CaseGenerator::namespaceOf($root, 'tests\\Regression'), 'App\Tests');
    }

    public function namespaceFromTheLongerRootEvenWithAShortPrefix(): void
    {
        $root = $this->project(['autoload-dev' => ['psr-4' => ['Very\\Long\\Prefix\\' => 'tests/', 'Short\\' => 'tests/Regression/']]]);

        Assert::same(CaseGenerator::namespaceOf($root, 'tests/Regression/Issue1'), 'Short\Issue1');
    }

    public function generateFillsAnEmptyExistingFolder(): void
    {
        $root = $this->project([]);
        \mkdir("{$root}/case");
        $set = TemplateSet::load('orm');

        $written = (new CaseGenerator())->generate($set, "{$root}/case", 'Acme\Case1');

        Assert::same($written, \array_map(static fn(string $file): string => "{$root}/case/{$file}", \array_keys($set->files)));
        Assert::same(\fileperms("{$root}/case/Entity") & 07777, 0777 & ~\umask());
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
