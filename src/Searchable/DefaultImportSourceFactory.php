<?php

namespace Matchish\ScoutElasticSearch\Searchable;

class DefaultImportSourceFactory implements ImportSourceFactory
{
    public static function from(string $className): ImportSource
    {
        return new DefaultImportSource($className);
    }
}
