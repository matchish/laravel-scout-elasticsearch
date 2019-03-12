<?php

namespace Matchish\ScoutElasticSearch\Searchable;


use Illuminate\Support\Collection;

interface SearchableListFactory
{
    public function make(): Collection;
}
