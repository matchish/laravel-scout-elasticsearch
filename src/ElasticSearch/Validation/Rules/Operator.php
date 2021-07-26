<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch\Validation\Rules;

final class Operator implements RuleInterface
{
    /**
     * @param mixed $value
     * @return bool
     */
    public function passes($value): bool
    {
        $operators = [
            '=',
            '!=',
            '<>',
            '<',
            '<=',
            '>',
            '>=',
            'between',
            'notBetween',
            'exists',
            'notExists',
            'in',
            'startsWith',
        ];

        return in_array($value, $operators);
    }
}