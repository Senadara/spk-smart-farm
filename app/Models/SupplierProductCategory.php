<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SupplierProductCategory extends Model
{
    protected $table = 'supplier_product_categories';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function activeOptions(): Collection
    {
        if (Schema::hasTable('kategoriInventaris')) {
            $hasSharedRows = DB::table('kategoriInventaris')->exists();
            $query = DB::table('kategoriInventaris')
                ->orderBy('nama');

            if (Schema::hasColumn('kategoriInventaris', 'isDeleted')) {
                $query->where('isDeleted', false);
            }

            $names = $query->pluck('nama')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->unique(fn ($name) => Str::lower((string) $name))
                ->values();

            if ($names->isNotEmpty() || $hasSharedRows) {
                return $names;
            }
        }

        if (Schema::hasTable((new self)->getTable())) {
            $names = self::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->unique(fn ($name) => Str::lower((string) $name))
                ->values();

            if ($names->isNotEmpty()) {
                return $names;
            }
        }

        return collect(self::defaultNames());
    }

    public static function syncFromMobileMaster(): void
    {
        if (! Schema::hasTable((new self)->getTable()) || ! Schema::hasTable('kategoriInventaris')) {
            return;
        }

        $query = DB::table('kategoriInventaris')
            ->select('id', 'nama')
            ->orderBy('nama');

        if (Schema::hasColumn('kategoriInventaris', 'isDeleted')) {
            $query->addSelect('isDeleted');
            $query->where('isDeleted', false);
        }

        $nextOrder = (int) self::query()->max('sort_order') + 1;

        foreach ($query->get() as $row) {
            $name = trim((string) ($row->nama ?? ''));
            if ($name === '') {
                continue;
            }

            $slug = Str::slug($name);
            $category = self::query()
                ->where('slug', $slug)
                ->first();

            $payload = [
                'name' => $name,
                'slug' => $slug,
                'description' => 'Sinkron dari Data Master mobile.',
                'is_active' => true,
            ];

            if ($category) {
                $category->fill($payload);
                if ($category->isDirty()) {
                    $category->save();
                }

                continue;
            }

            self::query()->create(array_merge($payload, [
                'sort_order' => $nextOrder++,
            ]));
        }
    }

    public static function defaultNames(): array
    {
        return array_column(self::defaultRows(), 'name');
    }

    public static function defaultRows(): array
    {
        return [
            ['name' => 'Pakan', 'description' => 'Pakan utama dan bahan campuran pakan.'],
            ['name' => 'Vitamin', 'description' => 'Suplemen dan vitamin ternak atau tanaman.'],
            ['name' => 'Vaksin', 'description' => 'Vaksin dan kebutuhan imunisasi ternak.'],
            ['name' => 'Obat', 'description' => 'Obat dan perlengkapan kesehatan.'],
            ['name' => 'Disinfektan', 'description' => 'Sanitasi kandang, alat, dan area budidaya.'],
            ['name' => 'Peralatan', 'description' => 'Alat operasional kandang atau kebun.'],
            ['name' => 'Perlengkapan', 'description' => 'Perlengkapan pendukung produksi harian.'],
            ['name' => 'Kemasan', 'description' => 'Tray telur, kemasan panen, dan kebutuhan packing.'],
            ['name' => 'Bibit & Media Tanam', 'description' => 'Bibit, benih, polybag, dan media tanam.'],
            ['name' => 'Pupuk', 'description' => 'Pupuk organik, anorganik, dan nutrisi tanaman.'],
            ['name' => 'Pestisida', 'description' => 'Pengendalian hama dan penyakit tanaman.'],
            ['name' => 'Lainnya', 'description' => 'Barang pendukung yang belum masuk kategori spesifik.'],
        ];
    }

    public static function inferForProduct(string $text): string
    {
        $lower = Str::lower($text);

        return match (true) {
            str_contains($lower, 'pakan') || str_contains($lower, 'jagung') || str_contains($lower, 'dedak') => 'Pakan',
            str_contains($lower, 'vitamin') || str_contains($lower, 'suplemen') => 'Vitamin',
            str_contains($lower, 'vaksin') => 'Vaksin',
            str_contains($lower, 'obat') || str_contains($lower, 'antibiotik') => 'Obat',
            str_contains($lower, 'disinfektan') || str_contains($lower, 'desinfektan') || str_contains($lower, 'sanitasi') => 'Disinfektan',
            str_contains($lower, 'tray') || str_contains($lower, 'kemasan') || str_contains($lower, 'packing') => 'Kemasan',
            str_contains($lower, 'alat') || str_contains($lower, 'sensor') || str_contains($lower, 'lampu') => 'Peralatan',
            str_contains($lower, 'bibit') || str_contains($lower, 'benih') || str_contains($lower, 'media tanam') => 'Bibit & Media Tanam',
            str_contains($lower, 'pupuk') || str_contains($lower, 'nutrisi tanaman') => 'Pupuk',
            str_contains($lower, 'pestisida') || str_contains($lower, 'fungisida') || str_contains($lower, 'insektisida') => 'Pestisida',
            default => 'Lainnya',
        };
    }
}
