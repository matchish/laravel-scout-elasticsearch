<?php

declare(strict_types=1);

use App\Book;
use Faker\Generator as Faker;

$factory->define(Book::class, function (Faker $faker) {
    return [
        'custom_key' => $faker->uuid,
        'title' => $faker->text,
        'author' => $faker->name,
        'year' => $faker->year,
    ];
});
