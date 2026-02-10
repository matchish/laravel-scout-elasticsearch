<?php

namespace Matchish\ScoutElasticSearch\Database\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class FromScope implements Scope
{
    /**
     * @var int
     */
    private $lastId;
    /**
     * @var int
     */
    private $perPage;

    /**
     * PageScope constructor.
     *
     * @param  int  $lastId
     * @param  int  $perPage
     */
    public function __construct(int $lastId, int $perPage)
    {
        $this->lastId = $lastId;
        $this->perPage = $perPage;
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<Model>  $builder
     * @param  Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->forPageAfterId($this->perPage, $this->lastId, $model->getTable().'.'.$model->getKeyName());
    }
}
