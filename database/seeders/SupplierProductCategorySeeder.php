<?php

namespace Database\Seeders;

use App\Models\SupplierProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SupplierProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (SupplierProductCategory::defaultRows() as $index => $row) {
            SupplierProductCategory::query()->updateOrCreate(
                ['slug' => Str::slug($row['name'])],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }

        $this->seedSharedInventoryCategories();
        $this->seedSharedProductUnits();
    }

    private function seedSharedInventoryCategories(): void
    {
        if (! Schema::hasTable('kategoriInventaris')) {
            return;
        }

        foreach (SupplierProductCategory::defaultRows() as $row) {
            $this->upsertSharedCategory($row['name']);
        }
    }

    private function seedSharedProductUnits(): void
    {
        if (! Schema::hasTable('satuan')) {
            return;
        }

        foreach ($this->sharedProductUnits() as $row) {
            $this->upsertSharedUnit($row['name'], $row['symbol']);
        }
    }

    private function upsertSharedCategory(string $name): void
    {
        $existingId = $this->findSharedCategoryId($name);
        $payload = array_merge([
            'nama' => $name,
            'updatedAt' => now(),
        ], Schema::hasColumn('kategoriInventaris', 'isDeleted') ? ['isDeleted' => false] : []);

        if ($existingId) {
            DB::table('kategoriInventaris')->where('id', $existingId)->update($payload);

            return;
        }

        DB::table('kategoriInventaris')->insert(array_merge($payload, [
            'id' => (string) Str::uuid(),
            'createdAt' => now(),
        ]));
    }

    private function upsertSharedUnit(string $name, string $symbol): void
    {
        $existingId = $this->findSharedUnitId($name, $symbol);

        $payload = array_merge([
            'nama' => $name,
            'lambang' => $symbol,
            'updatedAt' => now(),
        ], Schema::hasColumn('satuan', 'isDeleted') ? ['isDeleted' => false] : []);

        if ($existingId) {
            DB::table('satuan')->where('id', $existingId)->update($payload);

            return;
        }

        DB::table('satuan')->insert(array_merge($payload, [
            'id' => (string) Str::uuid(),
            'createdAt' => now(),
        ]));
    }

    private function sharedProductUnits(): array
    {
        return [
            ['name' => 'Piece', 'symbol' => 'Pcs'],
            ['name' => 'Kilogram', 'symbol' => 'kg'],
            ['name' => 'Gram', 'symbol' => 'g'],
            ['name' => 'Liter', 'symbol' => 'L'],
            ['name' => 'Mililiter', 'symbol' => 'ml'],
            ['name' => 'Sak', 'symbol' => 'Sak'],
            ['name' => 'Karung', 'symbol' => 'Karung'],
            ['name' => 'Tray', 'symbol' => 'Tray'],
            ['name' => 'Botol', 'symbol' => 'Botol'],
            ['name' => 'Paket', 'symbol' => 'Paket'],
            ['name' => 'Ekor', 'symbol' => 'ekor'],
            ['name' => 'Buah', 'symbol' => 'buah'],
            ['name' => 'Batang', 'symbol' => 'batang'],
        ];
    }

    private function findSharedCategoryId(string $name): ?string
    {
        $normalized = $this->normalizeMasterKey($name);

        return DB::table('kategoriInventaris')
            ->get(['id', 'nama'])
            ->first(fn ($row) => $this->normalizeMasterKey($row->nama ?? '') === $normalized)
            ?->id;
    }

    private function findSharedUnitId(string $name, string $symbol): ?string
    {
        $normalizedName = $this->normalizeMasterKey($name);
        $normalizedSymbol = $this->normalizeMasterKey($symbol);

        return DB::table('satuan')
            ->get(['id', 'nama', 'lambang'])
            ->first(fn ($row) => $this->normalizeMasterKey($row->nama ?? '') === $normalizedName
                || $this->normalizeMasterKey($row->lambang ?? '') === $normalizedSymbol)
            ?->id;
    }

    private function normalizeMasterKey(mixed $value): string
    {
        return Str::of((string) $value)
            ->squish()
            ->lower()
            ->toString();
    }
}
