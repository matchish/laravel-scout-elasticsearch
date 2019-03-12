<?php

namespace App;

use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use Searchable;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'price',
    ];
}
