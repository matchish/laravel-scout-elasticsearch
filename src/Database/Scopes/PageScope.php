<?php

namespace Matchish\ScoutElasticSearch\Database\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class PageScope implements Scope
{
    /**
     * @var int
     */
    private $page;
    /**
     * @var int
     */
    private $perPage;

    /**
     * PageScope constructor.
     * @param int $page
     * @param int $perPage
     */
    public function __construct(int $page, int $perPage)
    {
        $this->page = $page;
        $this->perPage = $perPage;
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
        $builder->forPage($this->page, $this->perPage);
    }
}
