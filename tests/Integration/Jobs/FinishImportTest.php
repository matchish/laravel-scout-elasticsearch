<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs;

use Elastic\Elasticsearch\Client;
use Illuminate\Bus\BatchRepository;
use Illuminate\Support\Facades\Bus;
use Matchish\ScoutElasticSearch\Jobs\FinishImport;
use Matchish\ScoutElasticSearch\Jobs\ImportState;
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
     * A newer import cancels the batch of the one it replaces. This
     * import's index was abandoned half-built and must not go live.
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

    public function test_publishes_and_removes_the_state_once_every_range_is_finished(): void
    {
        $stage = $this->spyStage();
        $state = $this->state(2);
        $state->recordFinished($this->elasticsearch, 0);
        $state->recordFinished($this->elasticsearch, 1);

        (new FinishImport([$stage]))->forImport($state)->handle($this->elasticsearch);

        $this->assertTrue($stage->ran);
        $this->assertFalse($state->exists($this->elasticsearch), 'a console that follows the import sees it published');
    }

    public function test_never_publishes_while_a_range_is_missing_and_says_why(): void
    {
        $stage = $this->spyStage();
        $state = $this->state(2);
        $state->recordFinished($this->elasticsearch, 0);

        (new FinishImport([$stage]))->forImport($state)->handle($this->elasticsearch);

        $this->assertFalse($stage->ran, 'range 1 is not finished');
        $this->assertNotNull($state->failure($this->elasticsearch));
    }

    public function test_does_nothing_once_the_state_is_gone(): void
    {
        // Published by an earlier delivery of this job, or replaced by a
        // newer import.
        $stage = $this->spyStage();
        $state = $this->state(1);
        $state->recordFinished($this->elasticsearch, 0);
        $state->delete($this->elasticsearch);

        (new FinishImport([$stage]))->forImport($state)->handle($this->elasticsearch);

        $this->assertFalse($stage->ran);
    }

    public function test_a_failure_after_the_last_try_is_kept_for_the_console(): void
    {
        $state = $this->state(1);

        (new FinishImport([$this->spyStage()]))->forImport($state)->failed(new \Exception('cluster is read-only'));

        $this->assertSame('cluster is read-only', $state->failure($this->elasticsearch));
    }

    public function test_keeps_its_queue_when_tied_to_an_import_and_a_batch(): void
    {
        $finish = (new FinishImport([]))->onConnection('database')->onQueue('imports');

        $copy = $finish->forImport($this->state(1))->forBatch('batch-id');

        $this->assertSame('database', $copy->connection);
        $this->assertSame('imports', $copy->queue);
    }

    private function state(int $ranges): ImportState
    {
        $state = ImportState::forImport('products_'.random_int(1, 999999), $ranges);
        $state->create($this->elasticsearch);

        return $state;
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
