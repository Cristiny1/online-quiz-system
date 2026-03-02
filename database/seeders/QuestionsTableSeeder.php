<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuestionsTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('questions')->insert([
            [
                'quiz_id' => 1,
                'question_text' => 'What is 2 + 2?',
                'created_at' => now(),
            ],
            [
                'quiz_id' => 1,
                'question_text' => 'What is 5 x 3?',
                'created_at' => now(),
            ],
        ]);
    }
}