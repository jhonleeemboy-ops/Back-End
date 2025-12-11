<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::updateOrCreate(
            ['username' => 'admin'],  // Search by username
            [
                'password' => Hash::make('password'),  // Update/set these fields
                'role' => 'admin'
            ]
        );
    }
}