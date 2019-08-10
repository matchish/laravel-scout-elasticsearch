<?php


namespace Matchish\ScoutElasticSearch\Database\Scopes;


use Illuminate\Database\Eloquent\Scope;

abstract class ImportScope implements Scope
{
    public function key()
    {
        return static::class;
    }
}
