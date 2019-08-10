<?php

namespace Matchish\ScoutElasticSearch\Searchable;

class DefaultImportSourceFactory implements ImportSourceFactory
{
    public static function from(string $className, array $scopes = []): ImportSource
    {
        return new DefaultImportSource($className, $scopes);
    }
}
