<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 18.03.19
 * Time: 14:02
 */

namespace Matchish\ScoutElasticSearch\ElasticSearch;


interface Alias
{
    public function name(): string;

    public function config(): array;
}