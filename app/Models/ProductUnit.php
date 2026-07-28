<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductUnit extends Model
{
    protected $table = 'product_units';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'name',
        'symbol',
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
        if (Schema::hasTable('satuan')) {
            $hasSharedRows = DB::table('satuan')->exists();
            $query = DB::table('satuan')
                ->orderBy('nama');

            if (Schema::hasColumn('satuan', 'isDeleted')) {
                $query->where('isDeleted', false);
            }

            $symbols = $query->pluck(Schema::hasColumn('satuan', 'lambang') ? 'lambang' : 'nama')
                ->map(fn ($symbol) => trim((string) $symbol))
                ->filter()
                ->unique(fn ($symbol) => Str::lower((string) $symbol))
                ->values();

            if ($symbols->isNotEmpty() || $hasSharedRows) {
                return $symbols;
            }
        }

        if (Schema::hasTable((new self)->getTable())) {
            $symbols = self::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('symbol')
                ->map(fn ($symbol) => trim((string) $symbol))
                ->filter()
                ->unique(fn ($symbol) => Str::lower((string) $symbol))
                ->values();

            if ($symbols->isNotEmpty()) {
                return $symbols;
            }
        }

        return collect(self::defaultSymbols());
    }

    public static function syncFromMobileMaster(): void
    {
        if (! Schema::hasTable((new self)->getTable()) || ! Schema::hasTable('satuan')) {
            return;
        }

        $columns = ['id', 'nama'];
        $hasSymbol = Schema::hasColumn('satuan', 'lambang');
        if ($hasSymbol) {
            $columns[] = 'lambang';
        }

        $query = DB::table('satuan')
            ->select($columns)
            ->orderBy('nama');

        if (Schema::hasColumn('satuan', 'isDeleted')) {
            $query->addSelect('isDeleted');
            $query->where('isDeleted', false);
        }

        $nextOrder = (int) self::query()->max('sort_order') + 1;

        foreach ($query->get() as $row) {
            $name = trim((string) ($row->nama ?? ''));
            $symbol = trim((string) ($hasSymbol ? ($row->lambang ?? '') : ''));

            if ($name === '' && $symbol === '') {
                continue;
            }

            if ($symbol === '') {
                $symbol = $name;
            }
            if ($name === '') {
                $name = $symbol;
            }

            $unit = self::query()
                ->where('symbol', $symbol)
                ->orWhere('name', $name)
                ->first();

            $payload = [
                'name' => $name,
                'symbol' => $symbol,
                'description' => 'Sinkron dari Data Master mobile.',
                'is_active' => true,
            ];

            if ($unit) {
                $unit->fill($payload);
                if ($unit->isDirty()) {
                    $unit->save();
                }

                continue;
            }

            self::query()->create(array_merge($payload, [
                'sort_order' => $nextOrder++,
            ]));
        }
    }

    public static function defaultSymbols(): array
    {
        return array_column(self::defaultRows(), 'symbol');
    }

    public static function defaultRows(): array
    {
        return [
            ['name' => 'Piece', 'symbol' => 'Pcs', 'description' => 'Satuan item umum.'],
            ['name' => 'Kilogram', 'symbol' => 'Kg', 'description' => 'Berat dalam kilogram.'],
            ['name' => 'Gram', 'symbol' => 'Gram', 'description' => 'Berat kecil dalam gram.'],
            ['name' => 'Liter', 'symbol' => 'Liter', 'description' => 'Volume cairan.'],
            ['name' => 'Sak', 'symbol' => 'Sak', 'description' => 'Kemasan pakan atau pupuk.'],
            ['name' => 'Karung', 'symbol' => 'Karung', 'description' => 'Kemasan besar berbentuk karung.'],
            ['name' => 'Tray', 'symbol' => 'Tray', 'description' => 'Kemasan telur per tray.'],
            ['name' => 'Botol', 'symbol' => 'Botol', 'description' => 'Kemasan cair botol.'],
            ['name' => 'Pack', 'symbol' => 'Pack', 'description' => 'Kemasan paket.'],
        ];
    }
}
