<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

interface Alias
{
    public function name(): string;

    /**
     * @return array<mixed>
     */
    public function config(): array;
}
