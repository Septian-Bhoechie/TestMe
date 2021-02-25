<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Question;
use Faker\Generator as Faker;

$factory->define(Question::class, function (Faker $faker) {
    return [
        'class_id' => 1,
        // 'exam_id' => 1,
        'subject_id' => 1,
        'question' => $faker->text()
    ];
});
