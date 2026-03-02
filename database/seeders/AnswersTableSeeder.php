<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class AnswersTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('answers')->insert([
            // Question 1: 2+2
            ['question_id' => 1, 'answer_text' => '3', 'is_correct' => false],
            ['question_id' => 1, 'answer_text' => '4', 'is_correct' => true],
            ['question_id' => 1, 'answer_text' => '5', 'is_correct' => false],
            ['question_id' => 1, 'answer_text' => '6', 'is_correct' => false],

            // Question 2: 5x3
            ['question_id' => 2, 'answer_text' => '8', 'is_correct' => false],
            ['question_id' => 2, 'answer_text' => '15', 'is_correct' => true],
            ['question_id' => 2, 'answer_text' => '10', 'is_correct' => false],
            ['question_id' => 2, 'answer_text' => '20', 'is_correct' => false],
        ]);
    }
}