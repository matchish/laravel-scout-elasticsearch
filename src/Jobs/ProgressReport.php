<?php


namespace Matchish\ScoutElasticSearch\Jobs;


class ProgressReport
{

    /**
     * @var int
     */
    public $advance;
    /**
     * @var string|null
     */
    public $message;
    /**
     * @var null
     */
    public $jobId;

    /**
     * ProgressReport constructor.
     * @param int $advance
     * @param string|null $message
     * @param null $jobId
     */
    public function __construct(int $advance, string $message = null, $jobId = null)
    {
        $this->advance = $advance;
        $this->message = $message;
        $this->jobId = $jobId;
    }
}
