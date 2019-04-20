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
     * ProgressReport constructor.
     * @param int $advance
     * @param string|null $message
     */
    public function __construct(int $advance, string $message = null)
    {
        $this->advance = $advance;
        $this->message = $message;
    }
}
