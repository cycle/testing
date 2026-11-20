<?php

declare(strict_types=1);

namespace Cycle\Testing\Tests\Unit;

use Cycle\Testing\Regression\TemplateSet;
use Testo\Assert;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class TemplateSetTest
{
    private string $root;

    #[BeforeTest]
    public function createRoot(): void
    {
        $this->root = \str_replace('\\', '/', \sys_get_temp_dir()) . '/cycle-testing-templates-' . \bin2hex(\random_bytes(4));
        \mkdir($this->root);
    }

    #[AfterTest]
    public function removeRoot(): void
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

    public function loadsFromTheGivenRoot(): void
    {
        $this->set('base', ['description' => 'Base set'], ['A.php', 'Sub/B.php']);
        $this->set('child', ['extends' => 'base'], ['Sub/B.php', 'C.php']);

        $base = TemplateSet::load('base', $this->root);
        $child = TemplateSet::load('child', $this->root);

        Assert::same($base->description, 'Base set');
        Assert::same($child->description, '');
        Assert::same(\array_map(static fn(string $p): string => \str_replace('\\', '/', $p), $child->files), [
            'A.php' => "{$this->root}/base/A.php",
            'C.php' => "{$this->root}/child/C.php",
            'Sub/B.php' => "{$this->root}/child/Sub/B.php",
        ]);
    }

    public function allLoadsEveryFolderOfTheRoot(): void
    {
        $this->set('one', ['description' => 'First'], ['A.php']);
        $this->set('two', ['description' => 'Second'], ['B.php']);
        \file_put_contents("{$this->root}/README.md", '');

        $sets = TemplateSet::all($this->root);

        Assert::same(\array_map(static fn(TemplateSet $set): string => $set->name, $sets), ['one', 'two']);
        Assert::same($sets[1]->description, 'Second');
    }

    public function circularExtendsFails(): never
    {
        $this->set('a', ['extends' => 'b'], []);
        $this->set('b', ['extends' => 'a'], []);

        Expect::exception(\RuntimeException::class)->withMessage('Template `a` extends itself through `a` → `b`.');

        TemplateSet::load('a', $this->root);
    }

    public function unknownTemplateNamesTheAvailableOnes(): never
    {
        $this->set('one', [], []);
        $this->set('two', [], []);

        Expect::exception(\InvalidArgumentException::class)->withMessage('Unknown template `three`; available: one, two.');

        TemplateSet::load('three', $this->root);
    }

    /**
     * @param array{extends?: string, description?: string} $manifest
     * @param list<non-empty-string> $files
     */
    private function set(string $name, array $manifest, array $files): void
    {
        \mkdir("{$this->root}/{$name}");
        $manifest === [] or \file_put_contents("{$this->root}/{$name}/template.json", \json_encode($manifest));
        foreach ($files as $file) {
            \is_dir(\dirname("{$this->root}/{$name}/{$file}")) or \mkdir(\dirname("{$this->root}/{$name}/{$file}"), recursive: true);
            \file_put_contents("{$this->root}/{$name}/{$file}", '<?php');
        }
    }
}
