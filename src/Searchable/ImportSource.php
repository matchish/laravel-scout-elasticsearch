<?php

namespace Matchish\ScoutElasticSearch\Searchable;

interface ImportSource
{
    public function syncWithSearchUsingQueue();

    public function syncWithSearchUsing();

    public function searchableAs();

    public function chunked();

    public function get();
}
