<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class QuizzesTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('quizzes')->insert([
            [
                'quiz_title' => 'Basic Math Quiz',
                'subject_id' => 1,
                'created_by' => 2, // teacher1
                'created_at' => now(),
            ],
        ]);
    }
}