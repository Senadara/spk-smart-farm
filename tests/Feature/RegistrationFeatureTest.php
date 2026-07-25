<?php

namespace Tests\Feature;

use App\Models\FarmProfile;
use App\Models\SupplierStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistrationFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_registration_creates_owner_account_through_api_and_farm_profile(): void
    {
        $userId = Str::uuid()->toString();
        $this->persistApiUser($userId, 'Owner Demo', 'owner-demo@test.local', '081200000010', 'pjawab');

        Http::fake([
            '*' => Http::response([
                'message' => 'User created',
                'data' => [
                    'id' => $userId,
                    'name' => 'Owner Demo',
                    'email' => 'owner-demo@test.local',
                    'phone' => '081200000010',
                    'role' => 'pjawab',
                ],
            ], 201),
        ]);

        $this->withSession(['_token' => 'owner-register-token'])
            ->post('/register/owner', [
                '_token' => 'owner-register-token',
                'name' => 'Owner Demo',
                'email' => 'owner-demo@test.local',
                'phone' => '081200000010',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'farm_name' => 'Peternakan Demo',
                'address' => 'Ngantang, Malang',
                'latitude' => -7.8543,
                'longitude' => 112.3701,
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/auth/register')
            && $request['role'] === 'pjawab'
            && $request['confirmPassword'] === 'password123');

        $profile = FarmProfile::query()->where('user_id', $userId)->firstOrFail();
        $this->assertSame('Peternakan Demo', $profile->farm_name);
        $this->assertSame('Ngantang, Malang', $profile->address);
        $this->assertSame(-7.8543, round((float) $profile->latitude, 4));
        $this->assertSame(112.3701, round((float) $profile->longitude, 4));
    }

    public function test_supplier_registration_creates_pending_supplier_store_for_superadmin_approval(): void
    {
        $userId = Str::uuid()->toString();
        $this->persistApiUser($userId, 'Supplier Demo', 'supplier-demo@test.local', '081200000020', 'supplier');

        Http::fake([
            '*' => Http::response([
                'message' => 'User created',
                'data' => [
                    'id' => $userId,
                    'name' => 'Supplier Demo',
                    'email' => 'supplier-demo@test.local',
                    'phone' => '081200000020',
                    'role' => 'supplier',
                ],
            ], 201),
        ]);

        $this->withSession(['_token' => 'supplier-register-token'])
            ->post('/register/supplier', [
                '_token' => 'supplier-register-token',
                'name' => 'Supplier Demo',
                'email' => 'supplier-demo@test.local',
                'phone' => '081200000020',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'store_name' => 'Toko Supplier Demo',
                'kategori' => ['Pakan', 'Vitamin'],
                'alamat' => 'Pakis, Malang',
                'latitude' => -7.9459,
                'longitude' => 112.7147,
                'deskripsi' => 'Supplier pakan dan vitamin untuk demo.',
            ])
            ->assertRedirect(route('register.supplier.submitted'))
            ->assertSessionHas('success');

        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/auth/register')
            && $request['role'] === 'supplier'
            && $request['confirmPassword'] === 'password123');

        $store = SupplierStore::query()->where('userId', $userId)->firstOrFail();
        $this->assertSame('Toko Supplier Demo', $store->nama);
        $this->assertSame('6281200000020', $store->phone);
        $this->assertSame('Pakan,Vitamin', $store->kategori);
        $this->assertSame('request', $store->tokoStatus);
        $this->assertSame('umkm', $store->TypeToko);
    }

    private function persistApiUser(string $id, string $name, string $email, string $phone, string $role): void
    {
        User::query()->create([
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => 'password123',
            'role' => $role,
            'isActive' => true,
            'isDeleted' => false,
        ]);
    }
}
