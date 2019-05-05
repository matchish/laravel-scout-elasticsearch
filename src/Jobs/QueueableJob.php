<?php


namespace Matchish\ScoutElasticSearch\Jobs;


use Elasticsearch\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Matchish\ScoutElasticSearch\ProgressReportable;

class QueueableJob  implements ShouldQueue
{
    use Queueable;
    use ProgressReportable;

    public function handle()
    {
        
    }
}
