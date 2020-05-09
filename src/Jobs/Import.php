<?php
declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs;

use Elasticsearch\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Support\LazyCollection;
use Matchish\ScoutElasticSearch\Jobs\Stages\CallableStage;
use Matchish\ScoutElasticSearch\ProgressReportable;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;
use Spatie\Async\Pool;
use Throwable;

/**
 * @internal
 */
final class Import
{
    use Queueable;
    use ProgressReportable;

    /**
     * @var ImportSource
     */
    private $source;

    /**
     * @param ImportSource $source
     */
    public function __construct(ImportSource $source)
    {
        $this->source = $source;
    }

    /**
     * @param Client $elasticsearch
     */
    public function handle(Client $elasticsearch): void
    {
        $stages = $this->stages();
//        $estimate = $stages->sum->estimate();
        $this->progressBar()->setMaxSteps(100);
        $progressbar = $this->progressBar();

        $stages->each(function ($stage) {

            if (is_iterable($stage)) {
                foreach ($stage as $s) {
                    $pool = Pool::create();
                    foreach ($s as $i) {
                        $pool->add(new CallableStage($i))->then(function ($output) {
//                             Handle success
                        })->catch(function (Throwable $exception) {
//                             Handle exception
                        });
//                        $elasticsearch = app(Client::class);
//                        $i->handle($elasticsearch);
                    }
                    $pool->wait();
                }
            } else {
                $elasticsearch = app(Client::class);
                $stage->handle($elasticsearch);
            }
        });
    }

    private function stages(): LazyCollection
    {
        return ImportStages::fromSource($this->source);
    }
}
