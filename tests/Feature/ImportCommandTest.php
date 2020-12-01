<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Book;
use App\BookWithCustomKey;
use App\Product;
use Illuminate\Support\Facades\Artisan;
use stdClass;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\IntegrationTestCase;

final class ImportCommandTest extends IntegrationTestCase
{
    public function test_import_entites(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = rand(1, 5);

        factory(Product::class, $productsAmount)->create();

        $productsUnsearchableAmount = rand(1, 5);
        factory(Product::class, $productsUnsearchableAmount)->states(['archive'])->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import', [
            'searchable' => [Product::class],
        ]);
        $params = [
            'index' => 'products',
            'body' => [
                'query' => [
                    'match_all' => new stdClass(),
                ],
            ],
        ];
        $response = $this->elasticsearch->search($params);
        $this->assertEquals($productsAmount, $response['hits']['total']['value']);
    }

    public function test_import_entites_in_queue(): void
    {
        $this->app['config']->set('scout.queue', ['connection' => 'sync', 'queue' => 'scout']);

        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = rand(1, 5);
        factory(Product::class, $productsAmount)->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');
        $params = [
            'index' => 'products',
            'body' => [
                'query' => [
                    'match_all' => new stdClass(),
                ],
            ],
        ];
        $response = $this->elasticsearch->search($params);
        $this->assertEquals($productsAmount, $response['hits']['total']['value']);
    }

    public function test_import_all_pages(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = 10;

        factory(Product::class, $productsAmount)->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');
        $params = [
            'index' => (new Product())->searchableAs(),
            'body' => [
                'query' => [
                    'match_all' => new stdClass(),
                ],
            ],
        ];
        $response = $this->elasticsearch->search($params);
        $this->assertEquals($productsAmount, $response['hits']['total']['value']);
    }

    public function test_import_with_custom_key_all_pages(): void
    {
        $this->app['config']['scout.key'] = 'title';

        $dispatcher = Book::getEventDispatcher();
        Book::unsetEventDispatcher();

        $booksAmount = 10;

        factory(Book::class, $booksAmount)->create();

        Book::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');
        $params = [
            'index' => (new BookWithCustomKey())->searchableAs(),
            'body' => [
                'query' => [
                    'match_all' => new stdClass(),
                ],
            ],
        ];
        $response = $this->elasticsearch->search($params);
        $this->assertEquals($booksAmount, $response['hits']['total']['value']);
    }

    public function test_remove_old_index_after_switching_to_new(): void
    {
        $params = [
            'index' => 'products_old',
            'body' => [
                'aliases' => ['products' => new stdClass()],
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                ],
            ],
        ];
        $this->elasticsearch->indices()->create($params);
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = rand(1, 5);

        factory(Product::class, $productsAmount)->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $this->assertFalse($this->elasticsearch->indices()->exists(['index' => 'products_old']), 'Old index must be deleted');
    }

    public function test_progress_report()
    {
        $output = new BufferedOutput();
        Artisan::call('scout:import', ['searchable' => [Product::class, Book::class]], $output);

        $output = explode("\n", $output->fetch());
        $this->assertEquals(
            trans('scout::import.start', ['searchable' => Product::class]),
            trim($output[0]));
        $this->assertEquals(
            '[OK] '.trans('scout::import.done', ['searchable' => Product::class]),
            trim($output[14]));
        $this->assertEquals(
            trans('scout::import.start', ['searchable' => Book::class]),
            trim($output[16]));
        $this->assertEquals(
            '[OK] '.trans('scout::import.done', ['searchable' => Book::class]),
            trim($output[30]));
    }

    public function test_progress_report_in_queue()
    {
        $this->app['config']->set('scout.queue', ['connection' => 'sync', 'queue' => 'scout']);

        $output = new BufferedOutput();
        Artisan::call('scout:import', [], $output);

        $output = array_map('trim', explode("\n", $output->fetch()));

        $this->assertContains(trans('scout::import.start', ['searchable' => Product::class]), $output);
        $this->assertContains('[OK] '.trans('scout::import.done.queue', ['searchable' => Product::class]), $output);
    }
}
