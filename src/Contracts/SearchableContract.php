<?php

namespace Matchish\ScoutElasticSearch\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * \Laravel\Scout\Searchable trait contract.
 * 
 * @phpstan-type SearchableModel = Model&SearchableContract
 * @phpstan-type SearchableModelWithElasticParams = SearchableModel&ElasticParamsContract
 */
interface SearchableContract
{
    /**
     * Perform a search against the model's indexed data.
     *
     * @param  string  $query
     * @param  \Closure|null  $callback
     * @return \Laravel\Scout\Builder<SearchableModel>
     */
    public static function search(string $query = '', ?\Closure $callback = null);

    /**
     * Make all instances of the model searchable.
     *
     * @param  int|null  $chunk
     * @return void
     */
    public static function makeAllSearchable(?int $chunk = null);

    /**
     * Get a query builder for making all instances of the model searchable.
     *
     * @return \Illuminate\Database\Eloquent\Builder<SearchableModel>
     */
    public static function makeAllSearchableQuery();

    /**
     * Remove all instances of the model from the search index.
     *
     * @return void
     */
    public static function removeAllFromSearch();

    /**
     * Enable search syncing for this model.
     *
     * @return void
     */
    public static function enableSearchSyncing();

    /**
     * Disable search syncing for this model.
     *
     * @return void
     */
    public static function disableSearchSyncing();

    /**
     * Temporarily disable search syncing for the given callback.
     *
     * @param  callable  $callback
     * @return mixed
     */
    public static function withoutSyncingToSearch($callback);

    /**
     * Register the searchable macros.
     *
     * @return void
     */
    public function registerSearchableMacros();

    /**
     * Dispatch the job to make the given models searchable.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, SearchableModel>  $models
     * @return void
     */
    public function queueMakeSearchable($models);

    /**
     * Synchronously make the given models searchable.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, SearchableModel>  $models
     * @return void
     */
    public function syncMakeSearchable($models);

    /**
     * Dispatch the job to make the given models unsearchable.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, SearchableModel>  $models
     * @return void
     */
    public function queueRemoveFromSearch($models);

    /**
     * Synchronously make the given models unsearchable.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, SearchableModel>  $models
     * @return void
     */
    public function syncRemoveFromSearch($models);

    /**
     * Determine if the model should be searchable.
     *
     * @return bool
     */
    public function shouldBeSearchable();

    /**
     * When updating a model, this method determines if we should update the search index.
     *
     * @return bool
     */
    public function searchIndexShouldBeUpdated();

    /**
     * Modify the collection of models being made searchable.
     *
     * @param  \Illuminate\Support\Collection<int, SearchableModel>  $models
     * @return \Illuminate\Support\Collection<int, SearchableModel>
     */
    public function makeSearchableUsing(\Illuminate\Support\Collection $models);

    /**
     * Make the given model instance searchable.
     *
     * @return void
     */
    public function searchable();

    /**
     * Synchronously make the given model instance searchable.
     *
     * @return void
     */
    public function searchableSync();

    /**
     * Remove the given model instance from the search index.
     *
     * @return void
     */
    public function unsearchable();

    /**
     * Synchronously remove the given model instance from the search index.
     *
     * @return void
     */
    public function unsearchableSync();

    /**
     * Determine if the model existed in the search index prior to an update.
     *
     * @return bool
     */
    public function wasSearchableBeforeUpdate();

    /**
     * Determine if the model existed in the search index prior to deletion.
     *
     * @return bool
     */
    public function wasSearchableBeforeDelete();

    /**
     * Get the requested models from an array of object IDs.
     *
     * @param  \Laravel\Scout\Builder<SearchableModel>  $builder
     * @param  array<int|string>  $ids
     * @return mixed
     */
    public function getScoutModelsByIds(\Laravel\Scout\Builder $builder, array $ids);

    /**
     * Get a query builder for retrieving the requested models from an array of object IDs.
     *
     * @param  \Laravel\Scout\Builder<SearchableModel>  $builder
     * @param  array<int|string>  $ids
     * @return mixed
     */
    public function queryScoutModelsByIds(\Laravel\Scout\Builder $builder, array $ids);

    /**
     * Get the index name for the model when searching.
     *
     * @return string
     */
    public function searchableAs();

    /**
     * Get the index name for the model when indexing.
     *
     * @return string
     */
    public function indexableAs();

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray();

    /**
     * Get the Scout engine for the model.
     *
     * @return mixed
     */
    public function searchableUsing();

    /**
     * Get the queue connection that should be used when syncing.
     *
     * @return string
     */
    public function syncWithSearchUsing();

    /**
     * Get the queue that should be used with syncing.
     *
     * @return string
     */
    public function syncWithSearchUsingQueue();

    /**
     * Determine if the model uses soft deletes.
     *
     * @return bool
     */
    public static function usesSoftDelete();

    /**
     * Sync the soft deleted status for this model into the metadata.
     *
     * @return $this
     */
    public function pushSoftDeleteMetadata();

    /**
     * Get all Scout related metadata.
     *
     * @return array<string, mixed>
     */
    public function scoutMetadata();

    /**
     * Set a Scout related metadata.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function withScoutMetadata($key, $value);

    /**
     * Get the value used to index the model.
     *
     * @return mixed
     */
    public function getScoutKey();

    /**
     * Get the auto-incrementing key type for querying models.
     *
     * @return string
     */
    public function getScoutKeyType();

    /**
     * Get the key name used to index the model.
     *
     * @return mixed
     */
    public function getScoutKeyName();
}