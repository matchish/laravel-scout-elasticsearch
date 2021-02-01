<?php

namespace Matchish\ScoutElasticSearch\Database\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ChunkScope implements Scope
{
    /**
     * @var mixed
     */
    private $start;
    /**
     * @var mixed
     */
    private $end;

    /**
     * ChunkScope constructor.
     * @param mixed $start
     * @param mixed $end
     */
    public function __construct($start, $end)
    {
        $this->start = $start;
        $this->end = $end;
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $start = $this->start;
        $end = $this->end;
        $builder
            ->when(! is_null($start), function ($query) use ($start, $model) {
                return $query->where($model->getKeyName(), '>', $start);
            })
            ->when(! is_null($end), function ($query) use ($end, $model) {
                return $query->where($model->getKeyName(), '<=', $end);
            });
    }

    public function key(): string
    {
        return static::class;
    }
}
