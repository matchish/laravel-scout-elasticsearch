<?php

namespace App;

use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use Searchable, SoftDeletes;

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
}
