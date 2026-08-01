<?php

namespace Matchish\ScoutElasticSearch\Jobs;

use Illuminate\Support\Collection;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Jobs\Stages\CancelPreviousImport;
use Matchish\ScoutElasticSearch\Jobs\Stages\CatchUp;
use Matchish\ScoutElasticSearch\Jobs\Stages\CleanUp;
use Matchish\ScoutElasticSearch\Jobs\Stages\CreateWriteIndex;
use Matchish\ScoutElasticSearch\Jobs\Stages\DispatchImportRanges;
use Matchish\ScoutElasticSearch\Jobs\Stages\PullFromSource;
use Matchish\ScoutElasticSearch\Jobs\Stages\RefreshIndex;
use Matchish\ScoutElasticSearch\Jobs\Stages\StageInterface;
use Matchish\ScoutElasticSearch\Jobs\Stages\SwitchToNewAndRemoveOldIndex;
use Matchish\ScoutElasticSearch\Jobs\Stages\WaitForImportRanges;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;

/**
 * @extends Collection<int, StageInterface>
 */
class ImportStages extends Collection
{
    /**
     * @param  ImportSource  $source
     * @param  bool  $parallel
     * @param  bool  $catchUp
     * @param  bool  $watch  follow the range jobs and report their progress
     * @return self
     */
    public static function fromSource(ImportSource $source, bool $parallel = false, bool $catchUp = false, bool $watch = false)
    {
        $index = Index::fromSource($source);

        if ($parallel) {
            // The indented stages run on a queue worker, after every
            // range job of the batch has finished. Everything above
            // them runs immediately, in the process that starts the
            // import.
            $dispatch = new DispatchImportRanges($source, $index, new FinishImport(array_values(array_filter([
                $catchUp ? new CatchUp($source, $index) : null,
                new RefreshIndex($index),
                new SwitchToNewAndRemoveOldIndex($source, $index),
            ]))));

            /** @var array<StageInterface> $stages */
            $stages = array_values(array_filter([
                new CancelPreviousImport($source),
                new CleanUp($source),
                new CreateWriteIndex($source, $index),
                $dispatch,
                $watch ? new WaitForImportRanges($dispatch) : null,
            ]));
        } else {
            /** @var array<StageInterface> $stages */
            $stages = [
                new CleanUp($source),
                new CreateWriteIndex($source, $index),
                PullFromSource::chunked($source),
                new RefreshIndex($index),
                new SwitchToNewAndRemoveOldIndex($source, $index),
            ];
        }

        return (new self($stages))->flatten()->filter();
    }
}
