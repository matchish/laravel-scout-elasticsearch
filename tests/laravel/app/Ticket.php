<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Ticket extends Model
{
    use Searchable, SoftDeletes;

    protected $fillable = [
        'from',
        'to',
        'date',
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
