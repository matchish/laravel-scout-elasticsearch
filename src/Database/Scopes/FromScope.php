<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Database\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class FromScope implements Scope
{
    /**
     * @var mixed
     */
    private $from;

    /**
     * @param mixed $from
     */
    public function __construct($from)
    {
        $this->from = $from;
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $column = $model->getKeyName();
        $builder->where($column, '>', $this->from);
    }
}
