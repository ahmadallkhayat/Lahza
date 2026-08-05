<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Single studio account. There is no registration route, so this (or
        // the console) is the only way an account comes into existence.
        User::updateOrCreate(
            ['email' => 'admin@lahza.test'],
            [
                'name' => 'Lahza Studio',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $this->call(LuxWeddingSeeder::class);
    }
}
