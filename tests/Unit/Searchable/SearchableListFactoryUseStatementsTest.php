<?php

declare(strict_types=1);

namespace Tests\Unit\Searchable;

use Matchish\ScoutElasticSearch\Searchable\SearchableListFactory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * SearchableListFactory caches the class list in a static property,
 * so each test runs in its own process.
 */
class SearchableListFactoryUseStatementsTest extends TestCase
{
    private string $appPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appPath = sys_get_temp_dir().'/scout-discovery-'.uniqid();
        mkdir($this->appPath);

        spl_autoload_register(function (string $class): void {
            $file = $this->appPath.'/'.substr($class, strlen('Fixture\\')).'.php';

            if (str_starts_with($class, 'Fixture\\') && is_file($file)) {
                require $file;
            }
        });
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->appPath.'/*.php') ?: []);
        rmdir($this->appPath);

        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_two_files_without_a_namespace_can_import_the_same_class(): void
    {
        $import = "<?php\nuse Illuminate\\Support\\Facades\\Log;\n";
        file_put_contents($this->appPath.'/first.php', $import);
        file_put_contents($this->appPath.'/second.php', $import);

        $factory = new SearchableListFactory('Fixture\\', $this->appPath);

        $this->assertSame([], $factory->make()->all());
        $this->assertSame([], $factory->getErrors());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_a_duplicate_use_statement_skips_only_that_file(): void
    {
        file_put_contents($this->appPath.'/Duplicate.php', "<?php\nnamespace Fixture;\nuse Psr\\Log\\LoggerInterface as Log;\nuse Illuminate\\Support\\Facades\\Log;\nclass Duplicate {}\n");
        file_put_contents($this->appPath.'/Model.php', "<?php\nnamespace Fixture;\nclass Model { use \\Laravel\\Scout\\Searchable; }\n");

        $factory = new SearchableListFactory('Fixture\\', $this->appPath);

        $this->assertSame(['Fixture\\Model'], $factory->make()->all());
        $this->assertCount(1, $factory->getErrors());
        $this->assertStringContainsString('the name is already in use on line 4', $factory->getErrors()[0]);
    }
}
