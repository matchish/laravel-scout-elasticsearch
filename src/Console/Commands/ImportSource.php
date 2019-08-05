<?php


namespace Matchish\ScoutElasticSearch\Console\Commands;


interface ImportSource
{
    public function syncWithSearchUsingQueue();

    public function syncWithSearchUsing();

    public function searchableAs();

    public function chunked();

    public function get();
}
