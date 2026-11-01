<?php

namespace Tests\Fixtures;

use App\Book;

class BookWithoutPartitionKey extends Book
{
    protected $table = 'books';

    public function getKeyType()
    {
        return 'string';
    }

    public function getKeyName()
    {
        return 'custom_key';
    }
}
