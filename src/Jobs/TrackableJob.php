<?php
declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Imtigger\LaravelJobStatus\Trackable;

class TrackableJob implements ShouldQueue
{
    use Trackable;
    use Queueable;
    private mixed $stage;

    public function __construct(array $params = [])
    {
        $this->prepareStatus($params);
    }

    public function handle(): void
    {
    }
}
