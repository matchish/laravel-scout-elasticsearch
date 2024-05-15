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
use Laravel\Scout\Searchable;

class ProcessSearchable implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Trackable {
        __construct as __baseConstruct;
    }

    /**
     * @var Collection<int, Model|Searchable>
     */
    private $data;

    /**
     * @param  Collection<int, Model|Searchable>  $data
     */
    public function __construct(Collection $data)
    {
        $this->__baseConstruct($data->first());
        $this->data = $data;
    }

    /**
     * Handles the job execution.
     *
     * @return void
     */
    public function handle(): void
    {
        /** @var Model|Searchable $model */
        $model = $this->data->first();

        /** @var \Laravel\Scout\Engines\Engine $engine */
        $engine = $model->searchableUsing();

        $engine->update($this->data);
    }
}
