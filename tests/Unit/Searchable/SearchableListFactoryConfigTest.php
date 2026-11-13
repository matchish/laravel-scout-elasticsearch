<?php

declare(strict_types=1);

namespace Tests\Unit\Searchable;

use App\Book;
use App\Product;
use Matchish\ScoutElasticSearch\Searchable\SearchableListFactory;
use Tests\TestCase;

class SearchableListFactoryConfigTest extends TestCase
{
    private string $domainPath;

    public function setUp(): void
    {
        parent::setUp();

        $this->domainPath = sys_get_temp_dir().'/scout-domain-'.uniqid();
        mkdir($this->domainPath);

        file_put_contents($this->domainPath.'/Article.php', "<?php\nnamespace ConfigFixture\\Domain;\nclass Article { use \\Laravel\\Scout\\Searchable; }\n");
        file_put_contents($this->domainPath.'/Author.php', "<?php\nnamespace ConfigFixture\\Domain;\nclass Author {}\n");

        spl_autoload_register($this->autoloader(...));
    }

    public function tearDown(): void
    {
        spl_autoload_unregister($this->autoloader(...));
        array_map('unlink', glob($this->domainPath.'/*.php') ?: []);
        rmdir($this->domainPath);

        parent::tearDown();
    }

    public function test_search_the_application_namespace_by_default(): void
    {
        $searchable = SearchableListFactory::fromConfig();

        $this->assertCount(5, $searchable);
        $this->assertContains(Product::class, $searchable);
    }

    public function test_search_the_configured_namespaces(): void
    {
        config(['elasticsearch.searchable_namespaces' => [
            'ConfigFixture\\Domain' => $this->domainPath,
        ]]);

        $this->assertSame(['ConfigFixture\\Domain\\Article'], SearchableListFactory::fromConfig()->all());
    }

    public function test_search_all_the_configured_namespaces(): void
    {
        config(['elasticsearch.searchable_namespaces' => [
            'ConfigFixture\\Domain\\' => $this->domainPath,
            'App\\' => app()->path(),
        ]]);

        $searchable = SearchableListFactory::fromConfig();

        $this->assertCount(6, $searchable);
        $this->assertContains('ConfigFixture\\Domain\\Article', $searchable);
        $this->assertContains(Book::class, $searchable);
    }

    public function test_list_a_searchable_once_when_namespaces_overlap(): void
    {
        config(['elasticsearch.searchable_namespaces' => [
            'App\\' => app()->path(),
            'App\\Product' => app()->path(),
        ]]);

        $searchable = SearchableListFactory::fromConfig();

        $this->assertCount(5, $searchable);
        $this->assertSame($searchable->unique()->values()->all(), $searchable->all());
    }

    private function autoloader(string $class): void
    {
        $prefix = 'ConfigFixture\\Domain\\';
        $file = $this->domainPath.'/'.substr($class, strlen($prefix)).'.php';

        if (str_starts_with($class, $prefix) && is_file($file)) {
            require $file;
        }
    }
}
