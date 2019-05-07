<?php

declare(strict_types=1);

use App\Ticket;
use Faker\Generator as Faker;

$factory->define(Ticket::class, function (Faker $faker) {
    return [
        'custom_key' => $faker->uuid,
        'from' => $faker->city,
        'to' => $faker->city,
        'date' => $faker->date(),
    ];
});

$factory->state(Ticket::class, 'new-york', function (Faker $faker) {
    return [
        'to' => 'New-York',
    ];
});

$factory->state(Ticket::class, 'barselona', function (Faker $faker) {
    return [
        'to' => 'Barselona',
    ];
});
