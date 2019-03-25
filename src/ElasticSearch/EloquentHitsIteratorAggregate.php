<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 20.03.19
 * Time: 12:39
 */

namespace Matchish\ScoutElasticSearch\ElasticSearch;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Searchable;
use Traversable;


/**
 * @internal
 */
final class EloquentHitsIteratorAggregate implements \IteratorAggregate
{
    /**
     * @var array
     */
    private $keys;
    /**
     * @var Model
     */
    private $model;
    /**
     * @var callable|null
     */
    private $callback;

    /**
     * @param array $keys
     * @param Model $model
     * @param callable|null $callback
     */
    public function __construct(array $keys, Model $model, callable $callback = null)
    {
        $this->keys = $keys;
        $this->model = $model;
        $this->callback = $callback;
    }

    /**
     * Retrieve an external iterator
     * @link https://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     * @since 5.0.0
     */
    public function getIterator()
    {
        /** @var Searchable $model */
        $model = $this->model;
        $builder = new Builder($this->model, '');
        if (!empty($this->callback)) {
            $builder->query($this->callback);
        }
        $models = $model->getScoutModelsByIds(
            $builder, $this->keys
        )->keyBy(function ($model) {
            return $model->getScoutKey();
        });
        $hits = collect($this->keys)->map(function ($key) use ($models) {
            return isset($models[$key]) ? $models[$key] : null;
        })->filter()->all();
        return new \ArrayIterator($hits);
    }
}