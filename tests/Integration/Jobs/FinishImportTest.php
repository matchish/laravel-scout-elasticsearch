<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs;

use Elastic\Elasticsearch\Client;
use Illuminate\Bus\BatchRepository;
use Illuminate\Support\Facades\Bus;
use Matchish\ScoutElasticSearch\Jobs\FinishImport;
use Matchish\ScoutElasticSearch\Jobs\Stages\StageInterface;
use Tests\IntegrationTestCase;

final class FinishImportTest extends IntegrationTestCase
{
    public function test_runs_its_stages_in_order(): void
    {
        $first = $this->spyStage();
        $second = $this->spyStage();

        (new FinishImport([$first, $second]))->handle($this->elasticsearch);

        $this->assertTrue($first->ran);
        $this->assertTrue($second->ran);
    }

    /**
     * Laravel counts the skipped jobs of a cancelled batch as
     * successful, so this callback still runs. It must not publish an
     * index that was abandoned half-built.
     */
    public function test_cancelled_batch_never_publishes_its_index(): void
    {
        $stage = $this->spyStage();
        $repository = app(BatchRepository::class);
        $batch = $repository->store(Bus::batch([])->name('scout-import:products'));
        $repository->cancel($batch->id);

        (new FinishImport([$stage]))->forBatch($batch->id)->handle($this->elasticsearch);

        $this->assertFalse($stage->ran, 'the alias switch must not run for a cancelled import');
    }

    public function test_finished_batch_publishes_normally(): void
    {
        $stage = $this->spyStage();
        $repository = app(BatchRepository::class);
        $batch = $repository->store(Bus::batch([])->name('scout-import:products'));

        (new FinishImport([$stage]))->forBatch($batch->id)->handle($this->elasticsearch);

        $this->assertTrue($stage->ran);
    }

    private function spyStage(): StageInterface
    {
        return new class implements StageInterface
        {
            public bool $ran = false;

            public function handle(?Client $elasticsearch = null): void
            {
                $this->ran = true;
            }

            public function title(): string
            {
                return 'spy';
            }

            public function estimate(): int
            {
                return 1;
            }

            public function advance(): int
            {
                return 1;
            }

            public function completed(): bool
            {
                return true;
            }
        };
    }
}
