<?php

namespace Matchish\ScoutElasticSearch\Console\Commands;

interface ImportSourceFactory
{
    public static function from(string $className): ImportSource;
}
