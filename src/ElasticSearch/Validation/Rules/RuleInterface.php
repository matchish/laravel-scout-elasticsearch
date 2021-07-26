<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch\Validation\Rules;

interface RuleInterface
{
    /**
     * @param mixed $value
     * @return bool
     */
    public function passes($value);

}