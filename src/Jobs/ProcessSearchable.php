<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Matchish\ScoutElasticSearch\Contracts\SearchableContract;
use Matchish\ScoutElasticSearch\Jobs\TrackableJobs\TrackableJob;

/**
 * @phpstan-type SearchableModel = Model&SearchableContract
 */
class ProcessSearchable extends TrackableJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Collection<int, Model>
     */
    private Collection $data;

    /**
     * @param  Collection<int, Model>  $data
     */
    public function __construct(Collection $data)
    {
        $this->data = $data;

        parent::__construct();
    }

    public function trackableKey(): ?string
    {
        $first = $this->data->first();
        if ($first === null) {
            return null;
        }
        /** @var int|string $key */
        $key = $first->getKey();

        return \strval($key);
    }

    public function trackableType(): ?string
    {
        $first = $this->data->first();
        if ($first === null) {
            return null;
        }

        /** @var SearchableModel $first */
        return $first->searchableAs();
    }

    public function handle(): void
    {
        if ($this->trackedJob === null) {
            return;
        }
        $this->trackedJob = $this->trackedJob->fresh();
        if ($this->trackedJob === null || $this->trackedJob->finished_at !== null) {
            return;
        }

        /** @var SearchableModel $model */
        $model = $this->data->first();

        /** @var \Laravel\Scout\Engines\Engine $engine */
        $engine = $model->searchableUsing();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Model> */
        $collection = $this->data;
        $engine->update($collection);
    }
}
