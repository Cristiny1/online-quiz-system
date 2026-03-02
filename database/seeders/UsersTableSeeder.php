<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('users')->insert([
    [
        'username' => 'admin',
        'password' => Hash::make('admin123'),
        'role_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'username' => 'teacher1',
        'password' => Hash::make('teacher123'),
        'role_id' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'username' => 'student1',
        'password' => Hash::make('student123'),
        'role_id' => 3,
        'created_at' => now(),
        'updated_at' => now(),
    ],
]);
    }
}