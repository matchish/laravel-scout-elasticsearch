<?php

namespace Tests\Fixtures;

use App\Product;

class ProductWithStringPartitionKey extends Product
{
    protected $table = 'products';

    public function searchablePartitionKey(): string
    {
        return 'title';
    }
}
