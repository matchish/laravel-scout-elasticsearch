<?php

declare(strict_types=1);

use App\Product;
use Faker\Generator as Faker;

$factory->define(Product::class, function (Faker $faker) {
    return [
        'title' => $faker->text,
        'custom_key' => $faker->uuid,
        'price' => $faker->randomDigitNotNull,
    ];
});

$factory->state(Product::class, 'iphone', function (Faker $faker) {
    return [
        'title' => 'IPhone '.$faker->randomDigitNotNull,
        'price' => $faker->randomDigitNotNull,
    ];
});

$factory->state(Product::class, 'kindle', function (Faker $faker) {
    return [
        'title' => 'Amazon Kindle Fire '.$faker->randomDigitNotNull,
        'price' => $faker->randomDigitNotNull,
    ];
});

$factory->state(Product::class, 'promo', function (Faker $faker) {
    return [
        'price' => 100,
    ];
});

$factory->state(Product::class, 'used', function (Faker $faker) {
    return [
        'type' => 'used',
    ];
});

$factory->state(Product::class, 'like new', function (Faker $faker) {
    return [
        'type' => 'like new',
    ];
});

$factory->state(Product::class, 'archive', function (Faker $faker) {
    return [
        'type' => 'archive',
    ];
});

$factory->state(Product::class, 'new', function (Faker $faker) {
    return [
        'type' => 'new',
    ];
});

$factory->state(Product::class, 'cheap', function (Faker $faker) {
    return [
        'price' => $faker->numberBetween(30, 70),
    ];
});

$factory->state(Product::class, 'luxury', function (Faker $faker) {
    return [
        'price' => $faker->numberBetween(1000),
    ];
});
