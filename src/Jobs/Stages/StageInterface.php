<?php

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;

interface StageInterface
{
    /**
     * @return string
     */
    public function title(): string;

    /**
     * @return int
     */
    public function estimate(): int;

    /**
     * @return int
     */
    public function advance(): int;

    /**
     * @return bool
     */
    public function completed(): bool;

    /**
     * @param  Client  $elasticsearch
     * @return void
     */
    public function handle(Client $elasticsearch): void;
}
