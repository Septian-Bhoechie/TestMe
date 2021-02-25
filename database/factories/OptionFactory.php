<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Option;
use Faker\Generator as Faker;

$factory->define(Option::class, function (Faker $faker) {
    return [
        'question_id' => 1,
        'body' => $faker->text(),
        'isCorrect' => $faker->boolean(25)
    ];
});
