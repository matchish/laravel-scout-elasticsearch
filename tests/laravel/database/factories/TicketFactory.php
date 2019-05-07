<?php

declare(strict_types=1);

use App\Ticket;
use Faker\Generator as Faker;

$factory->define(Ticket::class, function (Faker $faker) {
    return [
        'custom_key' => $faker->uuid,
        'from' => $faker->text,
        'to' => $faker->name,
        'date' => $faker->date(),
    ];
});

$factory->define(Ticket::class, 'new-york', function (Faker $faker) {
    return [
        'custom_key' => $faker->uuid,
        'from' => $faker->city,
        'to' => 'New-York',
        'date' => $faker->date(),
    ];
});

$factory->define(Ticket::class, 'barselona', function (Faker $faker) {
    return [
        'custom_key' => $faker->uuid,
        'from' => $faker->city,
        'to' => 'Barselona',
        'date' => $faker->date(),
    ];
});
