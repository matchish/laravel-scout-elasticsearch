<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Post extends Model
{
    use Searchable;

    protected function makeAllSearchableUsing($query)
    {
        return $query->where('status', 'published');
    }
}
