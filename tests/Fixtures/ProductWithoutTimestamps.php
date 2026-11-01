<?php

namespace Tests\Fixtures;

use App\Product;

class ProductWithoutTimestamps extends Product
{
    public $timestamps = false;

    protected $table = 'products';
}
