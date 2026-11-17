<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $staffPassword = env('DEMO_STAFF_PASSWORD');

        if (blank($staffPassword)) {
            throw new \RuntimeException('DEMO_STAFF_PASSWORD must be supplied through the untracked local environment before seeding staff accounts.');
        }

        $users = [
            [
                'name' => 'Super Administrator',
                'email' => 'admin@agriconnect.local',
                'email_verified_at' => now(),
                'password' => Hash::make($staffPassword),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'MAO Director',
                'email' => 'director@agriconnect.local',
                'email_verified_at' => now(),
                'password' => Hash::make($staffPassword),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Agricultural Specialist',
                'email' => 'specialist@agriconnect.local',
                'email_verified_at' => now(),
                'password' => Hash::make($staffPassword),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Module Developer',
                'email' => 'developer@agriconnect.local',
                'email_verified_at' => now(),
                'password' => Hash::make($staffPassword),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Auditor',
                'email' => 'auditor@agriconnect.local',
                'email_verified_at' => now(),
                'password' => Hash::make($staffPassword),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $roleBySlug = DB::table('roles')->pluck('id', 'slug');
        $roleAssignments = [
            'admin@agriconnect.local' => 'super_admin',
            'director@agriconnect.local' => 'mao_admin',
            'specialist@agriconnect.local' => 'municipal_agriculturist',
            'developer@agriconnect.local' => 'module_personnel',
            'auditor@agriconnect.local' => 'authorized_viewer',
        ];

        foreach ($users as $user) {
            $email = $user['email'];
            $existing = DB::table('users')->where('email', $email)->first();

            if ($existing) {
                $userId = $existing->id;
                DB::table('users')->where('id', $userId)->update([
                    'name' => $user['name'],
                    'email_verified_at' => $user['email_verified_at'],
                    'updated_at' => now(),
                ]);
            } else {
                $userId = DB::table('users')->insertGetId($user);
            }

            $roleId = $roleBySlug[$roleAssignments[$email]] ?? null;
            if ($roleId) {
                DB::table('role_user')->insertOrIgnore([
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
