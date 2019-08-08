<?php

namespace Matchish\ScoutElasticSearch\Console\Commands;

class DefaultImportSourceFactory implements ImportSourceFactory
{
    public static function from(string $className): ImportSource
    {
        return new DefaultImportSource($className);
    }
}
