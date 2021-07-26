<?php

namespace Matchish\ScoutElasticSearch;

use Laravel\Scout\Builder as BaseBuilder;
use Matchish\ScoutElasticSearch\DataTransferObjects\WhereFilterData;

final class Builder extends BaseBuilder
{
    /**
     * @param string $field
     * @param mixed $value
     * @return Builder
     */
    public function where($field, $value): Builder
    {
        $args = func_get_args();

        if (count($args) === 3) {
            [$field, $operator, $value] = $args;
        } else {
            $operator = '=';
        }

        $this->wheres[] = new WhereFilterData(compact('field', 'operator', 'value'));

        return $this;
    }

    /**
     * @param string $field
     * @param array $value
     * @return Builder
     */
    public function whereIn($field, $value)
    {
        $operator = 'in';
        $this->wheres[] = new WhereFilterData(compact('field', 'operator', 'value'));

        return $this;
    }

    /**
     * @param string $field
     * @param array $value
     * @return Builder
     */
    public function whereBetween($field, $value): Builder
    {
        $operator = 'between';
        $this->wheres[] = new WhereFilterData(compact('field', 'operator', 'value'));

        return $this;
    }

    /**
     * @param string $field
     * @param string $value
     * @return Builder
     */
    public function whereStartsWith($field, $value): Builder
    {
        $operator = 'startsWith';
        $value = "{$value}*";
        $this->wheres[] = new WhereFilterData(compact('field', 'operator', 'value'));

        return $this;
    }

    /**
     * @param string $field
     * @param array $value
     * @return Builder
     */
    public function whereNotBetween($field, $value): Builder
    {
        $operator = 'notBetween';
        $this->wheres[] = new WhereFilterData(compact('field', 'operator', 'value'));

        return $this;
    }

    /**
     * @param string $field
     * @return Builder
     */
    public function whereExists($field): Builder
    {
        $operator = 'exists';
        $this->wheres[] = new WhereFilterData(compact('field', 'operator'));

        return $this;
    }

    /**
     * @param string $field
     * @return Builder
     */
    public function whereNotExists($field): Builder
    {
        $operator = 'notExists';

        $this->wheres[] = new WhereFilterData(compact('field', 'operator'));

        return $this;
    }

}