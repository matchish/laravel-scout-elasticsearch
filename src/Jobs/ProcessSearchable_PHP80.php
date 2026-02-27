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
use Junges\TrackableJobs\Concerns\Trackable;
use Matchish\ScoutElasticSearch\Contracts\SearchableContract;

/**
 * @phpstan-type SearchableModel = Model&SearchableContract
 */
class ProcessSearchable_PHP80 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Trackable {
        __construct as __baseConstruct;
    }

    /**
     * @var Collection<int, SearchableModel>
     */
    private Collection $data;

    /**
     * @param  Collection<int, SearchableModel>  $data
     */
    public function __construct(Collection $data)
    {
        $this->__baseConstruct($data->first());

        $this->trackedJob->update([
            'trackable_type' => $data->first()?->searchableAs(),
        ]);

        $this->data = $data;
    }

    /**
     * Handles the job execution.
     *
     * @return void
     */
    public function handle(): void
    {
        $freshTrackedJob = $this->trackedJob->fresh();
        if ($freshTrackedJob == null || $freshTrackedJob->finished_at !== null) {
            return;
        }
        $this->trackedJob = $freshTrackedJob;

        /** @var SearchableModel $model */
        $model = $this->data->first();

        /** @var \Laravel\Scout\Engines\Engine $engine */
        $engine = $model->searchableUsing();

        /** @var \Illuminate\Database\Eloquent\Collection<int, SearchableModel> */
        $collection = $this->data;
        $engine->update($collection);
    }
}
