<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs;

use App\Product;
use Matchish\ScoutElasticSearch\ElasticSearch\ImportAlias;
use Matchish\ScoutElasticSearch\Jobs\ImportRange;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use Matchish\ScoutElasticSearch\Searchable\Range;
use stdClass;
use Tests\IntegrationTestCase;

/**
 * A row deleted after an import job has read its chunk, but before
 * the chunk is written, must not survive in the new index. The
 * Eloquent `retrieved` event fires during chunk hydration — exactly
 * inside that window — which makes the interleaving deterministic.
 */
final class DeleteDuringImportTest extends IntegrationTestCase
{
    private const INDEX = 'products_import';

    public function test_product_deleted_while_its_chunk_is_in_memory_does_not_survive(): void
    {
        // Boot the model while the dispatcher is still in place, so
        // Scout registers its observer; only then silence events for
        // the seeding, the way an existing catalogue would already be
        // in the database before an import starts.
        new Product();

        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();
        $products = factory(Product::class, 3)->create();
        Product::setEventDispatcher($dispatcher);
        /** @var Product $victim */
        $victim = $products[1];

        // The write index, exactly as CreateWriteIndex builds it: the
        // shared write alias (observers route deletes here during an
        // import) plus the import alias range jobs write through.
        $this->elasticsearch->indices()->create([
            'index' => self::INDEX,
            'body' => ['aliases' => [
                'products' => ['is_write_index' => true],
                ImportAlias::of(self::INDEX) => new stdClass(),
            ]],
        ]);

        // The moment the job hydrates the victim's chunk, an "admin"
        // deletes the product through plain Eloquent. The Scout
        // observer fires and sends the delete to the write alias.
        $deleted = false;
        Product::retrieved(function (Product $model) use ($victim, &$deleted) {
            if (! $deleted && (int) $model->getKey() === (int) $victim->getKey()) {
                $deleted = true;
                $victim->delete();
            }
        });

        $source = DefaultImportSourceFactory::from(Product::class);
        $job = new ImportRange(
            $source,
            Range::between((int) $products[0]->getKey(), null),
            'id',
            ImportAlias::of(self::INDEX),
            500
        );
        $job->handle($this->elasticsearch);

        $this->elasticsearch->indices()->refresh(['index' => self::INDEX]);

        $this->assertFalse(
            $this->elasticsearch->exists(['index' => self::INDEX, 'id' => (string) $victim->getKey()])->asBool(),
            'a product deleted during the import must not be in the new index'
        );
    }

    public function test_product_updated_while_its_chunk_is_in_memory_keeps_the_new_value(): void
    {
        new Product();

        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();
        $products = factory(Product::class, 3)->create(['title' => 'old title']);
        Product::setEventDispatcher($dispatcher);
        /** @var Product $victim */
        $victim = $products[1];

        $this->elasticsearch->indices()->create([
            'index' => self::INDEX,
            'body' => ['aliases' => [
                'products' => ['is_write_index' => true],
                ImportAlias::of(self::INDEX) => new stdClass(),
            ]],
        ]);

        $updated = false;
        Product::retrieved(function (Product $model) use ($victim, &$updated) {
            if (! $updated && (int) $model->getKey() === (int) $victim->getKey()) {
                $updated = true;
                $victim->update(['title' => 'new title']);
            }
        });

        $job = new ImportRange(
            DefaultImportSourceFactory::from(Product::class),
            Range::between((int) $products[0]->getKey(), null),
            'id',
            ImportAlias::of(self::INDEX),
            500
        );
        $job->handle($this->elasticsearch);

        $this->elasticsearch->indices()->refresh(['index' => self::INDEX]);
        $document = $this->elasticsearch->get([
            'index' => self::INDEX,
            'id' => (string) $victim->getKey(),
        ])->asArray();

        $this->assertSame(
            'new title',
            $document['_source']['title'] ?? null,
            'the live update must not be overwritten by the import snapshot'
        );
    }
}
