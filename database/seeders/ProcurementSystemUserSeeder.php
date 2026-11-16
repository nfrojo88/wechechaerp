<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProcurementSystemUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Idempotent: creates a dedicated System user if not exists, or returns the existing one.
     */
    public function run(): void
    {
        $userData = [
            'name' => 'System',
        ];

        if (Schema::hasColumn('users', 'password')) {
            $userData['password'] = Hash::make(Str::random(40));
        }

        if (Schema::hasColumn('users', 'is_active')) {
            $userData['is_active'] = true;
        }

        if (Schema::hasColumn('users', 'role')) {
            $userData['role'] = 'system';
        }

        $systemUser = User::firstOrCreate(
            ['email' => 'system@wechechaerp.local'],
            $userData
        );

        $this->command?->info("Procurement system user ID: {$systemUser->id}");
    }
}
