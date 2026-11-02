<?php

namespace Tests\Fixtures;

use App\Product;

class ProductWithBigIntegerKey extends Product
{
    protected $table = 'big_integer_products';
}
