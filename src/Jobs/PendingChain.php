<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 09.03.19
 * Time: 11:08
 */

namespace Matchish\ScoutElasticSearch\Jobs;
use Illuminate\Foundation\Bus\PendingDispatch;


/**
 * @internal
 */
final class PendingChain
{
    /**
     * @var array
     */
    private $jobs;

    /**
     * PendingChain constructor.
     * @param array $jobs
     */
    public function __construct(array $jobs)
    {
        $this->jobs = $jobs;
    }

    /**
     * @return PendingDispatch
     */
    public function dispatch(): PendingDispatch
    {
        return (new PendingDispatch(array_shift($this->jobs)))->chain($this->jobs);
    }
}
