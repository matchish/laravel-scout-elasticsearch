<?php
declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\Queue;
use Illuminate\Support\Facades\Bus;
use Matchish\ScoutElasticSearch\Jobs\CreateWriteIndex;
use Matchish\ScoutElasticSearch\Jobs\ImportChain;
use Matchish\ScoutElasticSearch\Jobs\MakeAllSearchable;
use Matchish\ScoutElasticSearch\Jobs\PendingChain;
use Matchish\ScoutElasticSearch\Jobs\RefreshIndex;
use Matchish\ScoutElasticSearch\Jobs\SwitchToNewAndRemoveOldIndex;
use Tests\TestCase;

class PendingChainTest extends \Orchestra\Testbench\TestCase
{

    public function test_dispatch()
    {
        $chain = new PendingChain([new JobChainingTestFirstJob(), new JobChainingTestSecondJob()]);
        $chain->dispatch()->onConnection('sync');
        $this->assertEquals(true, JobChainingTestFirstJob::$ran);
        $this->assertEquals(true, JobChainingTestSecondJob::$ran);
    }

    public function test_jobs_executed_in_right_order()
    {
        $chain = new PendingChain([new JobChainingTestFirstJob(), new JobChainingTestSecondJob()]);
        $chain->dispatch()->onConnection('sync');
        $this->assertGreaterThan(JobChainingTestFirstJob::$time, JobChainingTestSecondJob::$time);
    }
}

class JobChainingTestFirstJob implements ShouldQueue
{
    use Queueable;
    public static $ran = false;
    public static $usedQueue = null;
    public static $usedConnection = null;
    public static $time = null;
    public function handle()
    {
        static::$ran = true;
        static::$usedQueue = $this->queue;
        static::$usedConnection = $this->connection;
        static::$time = microtime(true);
    }
}
class JobChainingTestSecondJob implements ShouldQueue
{
    use Queueable;
    public static $ran = false;
    public static $usedQueue = null;
    public static $usedConnection = null;
    public static $time = null;
    public function handle()
    {
        static::$ran = true;
        static::$usedQueue = $this->queue;
        static::$usedConnection = $this->connection;
        static::$time = microtime(true);
    }
}
