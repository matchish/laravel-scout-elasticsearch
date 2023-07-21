<?php

declare(strict_types=1);

use App\Post;
use Faker\Generator as Faker;

$factory->define(Post::class, function (Faker $faker) {
    return [
        'title' => $faker->text,
        'body' => $faker->text,
        'status' => 'draft',
        'date' => $faker->date(),
    ];
});

$factory->state(Post::class, 'draft', function () {
    return [
        'status' => 'draft',
    ];
});

$factory->state(Post::class, 'published', function () {
    return [
        'status' => 'published',
    ];
});
