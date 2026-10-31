<?php

declare(strict_types=1);

namespace Tests\Integration\Engines;

use App\Post;
use App\Product;
use stdClass;
use Tests\IntegrationTestCase;

/**
 * Scout's observers remove a model's document when the model is
 * deleted. A delete is the newest fact about its row, so it removes the
 * document even when another request edited the row after this model
 * instance was loaded. No import is involved here.
 */
final class LiveDeleteTest extends IntegrationTestCase
{
    public function test_delete_removes_the_document_after_another_request_edited_the_row(): void
    {
        // Boot the model while the dispatcher is in place, so Scout
        // registers its observer.
        new Post();
        $loaded = Post::withoutEvents(function () {
            return Post::forceCreate([
                'title' => 'old',
                'body' => 'body',
                'status' => 'published',
                'date' => '2000-01-01 00:00:00',
                'updated_at' => '2000-01-01 00:00:00',
            ]);
        });
        $this->elasticsearch->indices()->create([
            'index' => 'posts_index',
            'body' => ['aliases' => ['posts' => new stdClass()]],
        ]);

        $other = Post::findOrFail($loaded->getKey());
        $other->updated_at = '2000-01-02 00:00:00';
        $other->title = 'new';
        $other->save();

        // Post has no soft deletes, so this is a plain delete.
        $loaded->delete();

        $this->assertFalse(Post::whereKey($loaded->getKey())->exists());
        $this->assertFalse($this->documentExists('posts_index', $loaded->getKey()));
    }

    public function test_force_delete_removes_the_document_after_another_request_edited_the_row(): void
    {
        new Product();
        $loaded = Product::withoutEvents(function () {
            return factory(Product::class)->create([
                'title' => 'old title',
                'updated_at' => '2000-01-01 00:00:00',
            ]);
        });
        $this->elasticsearch->indices()->create([
            'index' => 'products_index',
            'body' => ['aliases' => ['products' => new stdClass()]],
        ]);

        $other = Product::findOrFail($loaded->getKey());
        $other->updated_at = '2000-01-02 00:00:00';
        $other->update(['title' => 'new title']);
        $this->assertTrue($this->documentExists('products_index', $loaded->getKey()), 'precondition: the edit is indexed');

        $loaded->forceDelete();

        $this->assertFalse(Product::withTrashed()->whereKey($loaded->getKey())->exists());
        $this->assertFalse($this->documentExists('products_index', $loaded->getKey()));
    }

    /**
     * @param  mixed  $id
     */
    private function documentExists(string $index, $id): bool
    {
        return $this->elasticsearch->exists(['index' => $index, 'id' => (string) $id])->asBool();
    }
}
