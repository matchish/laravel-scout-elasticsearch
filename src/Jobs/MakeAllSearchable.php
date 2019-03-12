<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 04.03.19
 * Time: 11:56
 */

namespace Matchish\ScoutElasticSearch\Jobs;


use App\Product;
use Elasticsearch\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * @internal
 */
final class MakeAllSearchable implements ShouldQueue
{

    use Queueable;
    /**
     * @var string
     */
    private $searchable;

    /**
     * MakeAllSearchable constructor.
     * @param string $searchable
     */
    public function __construct(string $searchable)
    {
        $this->searchable = $searchable;
    }

    /**
     * Handle the job.
     *
     * @return void
     */
    public function handle()
    {
        $searchable = new $this->searchable;
        $totalSearchables = $searchable::count();
        if (!$totalSearchables) {
            return;
        }
        $chunkSize = (int) config('scout.chunk.searchable', 500);
        $totalChunks = (int) ceil($totalSearchables / $chunkSize);
        collect(range(1, $totalChunks))->each(function($page) use($searchable, $chunkSize) {
            $searchable = new $this->searchable;

            $results = $searchable->forPage($page, $chunkSize)->get();
            $countResults = $results->count();
            if ($countResults == 0) {
                return false;
            }
            $results->filter->shouldBeSearchable()->searchable();

        });

    }

}
