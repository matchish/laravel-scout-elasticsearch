<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch\Config;

/**
 * @method static array hosts()
 * @method static user()
 * @method static password()
 * @method static elasticCloudId()
 * @method static apiKey()
 */
class Config
{
    /**
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return (new self())->parse()->$method(...$parameters);
    }

    /**
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters)
    {
        return (new self())->parse()->$method(...$parameters);
    }

    /**
     * @return Storage
     */
    public function parse(): Storage
    {
        return Storage::load('elasticsearch');
    }
}
