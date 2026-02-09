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
use Junges\TrackableJobs\TrackableJob;
use Laravel\Scout\Searchable;

class ProcessSearchable extends TrackableJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Collection<int, Model|Searchable>
     */
    private Collection $data;

    /**
     * @param  Collection<int, Model|Searchable>  $data
     */
    public function __construct(Collection $data)
    {
        $this->data = $data;

        parent::__construct();
    }

    public function trackableKey(): ?string
    {
        return \strval($this->data->first()->getKey());
    }

    public function trackableType(): ?string
    {
        return $this->data->first()->searchableAs();
    }

    /**
     * Handles the job execution.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->trackedJob = $this->trackedJob->fresh();
        if ($this->trackedJob == null || $this->trackedJob->finished_at !== null) {
            return;
        }

        /** @var Model|Searchable $model */
        $model = $this->data->first();

        /** @var \Laravel\Scout\Engines\Engine $engine */
        $engine = $model->searchableUsing();

        $engine->update($this->data);
    }
}
