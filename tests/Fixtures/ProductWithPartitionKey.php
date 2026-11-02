<?php

namespace Tests\Fixtures;

use App\Product;

class ProductWithPartitionKey extends Product
{
    protected $table = 'products';

    public function searchablePartitionKey(): string
    {
        return 'weight';
    }
}
