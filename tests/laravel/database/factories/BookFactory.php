<?php

declare(strict_types=1);

use App\Book;
use Faker\Generator as Faker;

$factory->define(Book::class, function (Faker $faker) {
    return [
        'custom_key' => $faker->uuid,
        'title' => $faker->sentence,
        'author' => $faker->name,
        'year' => $faker->year,
    ];
});

$factory->define(Book::class, 'new-york', function (Faker $faker) {
    return [
        'custom_key' => $faker->uuid,
        'title' => "{$faker->sentence} New-York {$faker->sentence}",
        'author' => $faker->name,
        'year' => $faker->year,
    ];
});

$factory->define(Book::class, 'barselona', function (Faker $faker) {
    return [
        'custom_key' => $faker->uuid,
        'title' => "{$faker->sentence} Barselona {$faker->sentence}",
        'author' => $faker->name,
        'year' => $faker->year,
    ];
});
