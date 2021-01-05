<?php

namespace App;

use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
