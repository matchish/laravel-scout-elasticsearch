<?php

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;

interface StageInterface
{
    public function title(): string;

    public function estimate(): int;

    public function handle(Client $elasticsearch): void;
}
