<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'id' => 'b1fadf5c-e36e-40d1-9770-4415b3af55f0',
                'name' => 'User Demo',
                'email' => 'user@email.com',
                'phone' => '08123456789',
                'password' => 'Password123.',
                'role' => 'user',
            ],
            [
                'id' => 'c6b5e54d-4bb9-47b6-96c7-3c2327bc65b1',
                'name' => 'Inventor Demo',
                'email' => 'inventor@email.com',
                'phone' => '08123456789',
                'password' => 'Password123.',
                'role' => 'inventor',
            ],
            [
                'id' => 'd7f77064-5f90-4a9f-b663-279d0000ecbe',
                'name' => 'Penjual Demo',
                'email' => 'penjual@email.com',
                'phone' => '08123456789',
                'password' => 'Password123.',
                'role' => 'penjual',
            ],
            [
                'id' => '6e84fcc8-b5c2-4c60-94ef-bb9b7af6005c',
                'name' => 'Petugas Demo',
                'email' => 'petugas@email.com',
                'phone' => '08123456789',
                'password' => 'Password123.',
                'role' => 'petugas',
            ],
            [
                'id' => 'fc571afa-e66b-437b-8b15-dce68edee3f3',
                'name' => 'Penanggung Jawab Demo',
                'email' => 'pjawab@email.com',
                'phone' => '08123456789',
                'password' => 'Password123.',
                'role' => 'pjawab',
            ],
            [
                'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                'name' => 'Admin Demo',
                'email' => 'admin@email.com',
                'phone' => '08123456780',
                'password' => 'Password123.',
                'role' => 'admin',
            ],
            [
                'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
                'name' => 'Owner Demo',
                'email' => 'owner@email.com',
                'phone' => '08123456781',
                'password' => 'Password123.',
                'role' => 'owner',
            ],
            [
                'id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
                'name' => 'Supplier Demo SmartFarm',
                'email' => 'supplier.demo@smartfarm.test',
                'phone' => '081234567890',
                'password' => 'password123',
                'role' => 'supplier',
            ],
            [
                'id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
                'name' => 'Pembeli Demo',
                'email' => 'buyer.demo@smartfarm.test',
                'phone' => '081234567891',
                'password' => 'password123',
                'role' => 'user',
            ],
            [
                'id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
                'name' => 'Test User',
                'email' => 'test@example.com',
                'phone' => '08123456782',
                'password' => 'password',
                'role' => 'pjawab',
            ],
        ];

        foreach ($accounts as $account) {
            $existingId = User::query()->where('email', $account['email'])->value('id');

            User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'id' => $existingId ?? $account['id'],
                    'name' => $account['name'],
                    'phone' => $account['phone'],
                    'password' => $account['password'],
                    'role' => $account['role'],
                    'isActive' => true,
                    'isDeleted' => false,
                ]
            );
        }

        $ownerId = User::query()->where('email', 'pjawab@email.com')->value('id');
        if ($ownerId) {
            User::query()
                ->where('email', 'petugas@email.com')
                ->update(['owner_id' => $ownerId]);
        }

        $this->command?->info('UserAccountSeeder: akun demo berhasil disiapkan tanpa data kandang.');
    }
}
