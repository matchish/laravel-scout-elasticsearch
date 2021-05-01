<?php

declare(strict_types=1);

use App\BookWithCustomKey;
use Faker\Generator as Faker;

$factory->define(BookWithCustomKey::class, function (Faker $faker) {
    return [
        'custom_key' => $faker->uuid,
        'title' => $faker->sentence,
        'author' => $faker->name,
        'year' => $faker->year,
    ];
});

$factory->state(BookWithCustomKey::class, 'new-york', function (Faker $faker) {
    return [
        'title' => "{$faker->sentence} New-York {$faker->sentence}",
    ];
});

$factory->state(BookWithCustomKey::class, 'barcelona', function (Faker $faker) {
    return [
        'title' => "{$faker->sentence} Barcelona {$faker->sentence}",
    ];
});
