<?php

declare(strict_types=1);

namespace Tests\Integration\Engines;

use App\Post;
use stdClass;
use Tests\IntegrationTestCase;

/**
 * A delete is the latest fact about a row, whatever the model instance
 * that sends it last saw. No import is involved here: this is ordinary
 * Scout syncing.
 */
final class StaleModelDeleteTest extends IntegrationTestCase
{
    public function test_deleting_a_model_loaded_before_another_edit_removes_the_document(): void
    {
        // Boot the model while the dispatcher is in place, so Scout
        // registers its observer.
        new Post();
        $stale = Post::withoutEvents(function () {
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

        // Another request edits the post after this instance was loaded.
        $fresh = Post::findOrFail($stale->getKey());
        $fresh->updated_at = '2000-01-02 00:00:00';
        $fresh->title = 'new';
        $fresh->save();

        // Post has no soft deletes, so this is a plain delete.
        $stale->delete();

        $this->assertFalse(Post::whereKey($stale->getKey())->exists());
        $this->assertFalse(
            $this->elasticsearch->exists(['index' => 'posts_index', 'id' => (string) $stale->getKey()])->asBool(),
            'a deleted post must not stay searchable because its model instance was stale'
        );
    }
}
