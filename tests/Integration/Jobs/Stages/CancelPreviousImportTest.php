<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs\Stages;

use App\Product;
use Illuminate\Bus\BatchRepository;
use Illuminate\Support\Facades\Bus;
use Matchish\ScoutElasticSearch\Jobs\Stages\CancelPreviousImport;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use Tests\IntegrationTestCase;

final class CancelPreviousImportTest extends IntegrationTestCase
{
    public function test_cancels_unfinished_batches_of_the_same_index(): void
    {
        /** @var BatchRepository $repository */
        $repository = app(BatchRepository::class);
        $running = $repository->store(Bus::batch([])->name('scout-import:products'));
        $other = $repository->store(Bus::batch([])->name('scout-import:books'));

        $stage = new CancelPreviousImport(DefaultImportSourceFactory::from(Product::class));
        $stage->handle($this->elasticsearch);

        $this->assertNotNull($repository->find($running->id)->cancelledAt);
        $this->assertNull($repository->find($other->id)->cancelledAt);
    }

    public function test_does_nothing_when_no_previous_import_runs(): void
    {
        $stage = new CancelPreviousImport(DefaultImportSourceFactory::from(Product::class));
        $stage->handle($this->elasticsearch);

        $this->addToAssertionCount(1);
    }
}
