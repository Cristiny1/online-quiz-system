<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class SubjectsTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('subjects')->insert([
            [
                'subject_name' => 'Mathematics',
                'created_by' => 2, // teacher1
                'created_at' => now(),
            ],
        ]);
    }
}