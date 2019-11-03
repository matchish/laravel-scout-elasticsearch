<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Book extends Model
{
    use Searchable, SoftDeletes;

    protected $fillable = [
        'title',
        'author',
        'year',
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
