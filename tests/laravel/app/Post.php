<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Post extends Model
{
    use Searchable;

    protected function makeAllSearchableUsing($query)
    {
        return $query->where('status', 'published');
    }
}
