<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs\Stages;

use App\Product;
use Matchish\ScoutElasticSearch\Jobs\Stages\CleanUp;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use stdClass;
use Tests\IntegrationTestCase;

final class CleanUpTest extends IntegrationTestCase
{
    public function test_reclaims_marked_indices_that_nothing_routes_to(): void
    {
        // Leaked by a crashed import: carries the marker, has no alias.
        $this->elasticsearch->indices()->create([
            'index' => 'products_111',
            'body' => ['mappings' => ['_meta' => ['scout_import' => true]]],
        ]);

        $this->runStage();

        $this->assertFalse($this->exists('products_111'));
    }

    public function test_never_touches_unmarked_indices(): void
    {
        // The user's own index that happens to match the naming pattern.
        $this->elasticsearch->indices()->create(['index' => 'products_2019']);

        $this->runStage();

        $this->assertTrue($this->exists('products_2019'));
    }

    public function test_keeps_marked_indices_that_still_have_an_alias(): void
    {
        // A published index: marked, but the search alias routes to it.
        $this->elasticsearch->indices()->create([
            'index' => 'products_333',
            'body' => [
                'mappings' => ['_meta' => ['scout_import' => true]],
                'aliases' => ['products' => new stdClass()],
            ],
        ]);

        $this->runStage();

        $this->assertTrue($this->exists('products_333'));
    }

    private function runStage(): void
    {
        $stage = new CleanUp(DefaultImportSourceFactory::from(Product::class));
        $stage->handle($this->elasticsearch);
    }

    private function exists(string $index): bool
    {
        return $this->elasticsearch->indices()->exists(['index' => $index])->asBool();
    }
}
