<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

interface Alias
{
    public function name(): string;

    public function config(): array;
}
