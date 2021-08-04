<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Console\Commands;

use Illuminate\Console\Command;
// use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Collection;
// use Imtigger\LaravelJobStatus\JobStatus;
use Matchish\ScoutElasticSearch\Jobs\Import;
use Matchish\ScoutElasticSearch\Jobs\TrackableJob;
use Matchish\ScoutElasticSearch\Searchable\ImportSourceFactory;
use Matchish\ScoutElasticSearch\Searchable\SearchableListFactory;
// use Illuminate\Support\Facades\Event;

final class ImportCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $signature = 'scout:import {searchable?* : The name of the searchable}';
    /**
     * {@inheritdoc}
     */
    protected $description = 'Create new index and import all searchable into the one';

    /**
     * {@inheritdoc}
     */
    public function handle(): void
    {
        $this->searchableList((array) $this->argument('searchable'))
        ->each(function ($searchable) {
            $this->import($searchable);
        });
    }

    private function searchableList(array $argument): Collection
    {
        return collect($argument)->whenEmpty(function () {
            $factory = new SearchableListFactory(app()->getNamespace(), app()->path());

            return $factory->make();
        });
    }

    private function import(string $searchable): void
    {
        $sourceFactory = app(ImportSourceFactory::class);
        $source = $sourceFactory::from($searchable);
        $job = new Import($source);
        $progressbar = (new ProgressBarFactory($this->output))->create();
//        $progressbar->setMaxSteps($job->);

        if (config('scout.queue')) {
            $job = (new TrackableJob())->chain([$job]);
        }

        // Event::listen(
        //     'event.*',
        //     function (array $data) {
        //         var_dump($data);
        //         }
        // );

        $startMessage = trans('scout::import.start', ['searchable' => "<comment>$searchable</comment>"]);
        $this->line($startMessage);

        /* @var ImportSource $source */
        dispatch($job)->allOnQueue($source->syncWithSearchUsingQueue())
            ->allOnConnection($source->syncWithSearchUsing());

        if (config('scout.queue')) {
            $isQueuedMessage = trans('scout::import.done.queue', [
                'searchable' => $searchable,
            ]);
            $this->line($isQueuedMessage);
        }
        // $inProgress = true;
        // $isQueued = true;
        // while ($isQueued) {
        //     sleep(1);
        //     // @phpstan-ignore-next-line
        //     $isQueued = JobStatus::whereId($job->getJobStatusId())->first()->IsQueued;
        // }
        // @phpstan-ignore-next-line
        // $progressbar->setMaxSteps(JobStatus::whereId($job->getJobStatusId())->first()->progress_max);
        // while ($inProgress) {
//            //TODO: check errors
//            $progress = JobStatus::where('type', 'like', $job->getJobStatusId() . '%')->whereStatus('finished')
//                ->sum('progress');
//            $lastStage = JobStatus::where('type', 'like', $job->getJobStatusId() . '%')
//                ->orderBy('started_at')->last();
//            $lastCheckStatusTime = time();
//            $progressbar->setMessage(str_replace($job->getJobStatusId() . '_', '', $lastStage->type));
//            $progressbar->setProgress($progress);
            // $inProgress = false;
            // sleep(5);
        // }

        $doneMessage = trans('scout::import.done', [
            'searchable' => $searchable,
        ]);
        $this->output->success($doneMessage);
    }
}
