<?php

namespace Matchish\ScoutElasticSearch\Searchable;

interface ImportSourceFactory
{
    public static function from(string $className): ImportSource;
}
