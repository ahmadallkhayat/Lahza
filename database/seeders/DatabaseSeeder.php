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
        // Sign-in is by username; the email is kept for password resets and
        // future notifications, not as a credential.
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Lahza Studio',
                'email' => 'admin@lahza.test',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $this->call(LuxWeddingSeeder::class);
    }
}
