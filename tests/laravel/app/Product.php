<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Matchish\ScoutElasticSearch\Traits\ElasticParams;

class Product extends Model
{
    use Searchable, SoftDeletes, ElasticParams;

    protected $fillable = [
        'title',
        'type',
        'slug',
        'description',
        'price',
    ];

    public function getScoutKeyName()
    {
        return config('scout.key', $this->getKeyName());
    }

    public function getScoutKey()
    {
        return $this->getAttribute($this->getScoutKeyName());
    }

    public function shouldBeSearchable()
    {
        return $this->type != 'archive';
    }
}
