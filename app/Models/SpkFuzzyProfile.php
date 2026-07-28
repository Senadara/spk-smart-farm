<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpkFuzzyProfile extends Model
{
    use HasUuids;

    protected $table = 'spk_fuzzy_profiles';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'commodity_id',
        'jenis_budidaya_id',
        'name',
        'version',
        'status',
        'is_active',
        'reviewed_by',
        'reviewed_at',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function commodity(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Komoditas::class, 'commodity_id');
    }

    public function jenisBudidaya(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(JenisBudidaya::class, 'jenis_budidaya_id');
    }

    public function variables(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SpkFuzzyVariable::class, 'profile_id');
    }

    public function rules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SpkFuzzyRule::class, 'profile_id');
    }

    public function inputSources(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SpkFuzzyInputSource::class, 'profile_id');
    }

    public function logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SpkFuzzyLog::class, 'profile_id');
    }

    public static function resolveForContext(?string $commodityId = null, ?string $coopId = null, ?string $profileId = null): ?self
    {
        if ($profileId) {
            return self::find($profileId);
        }

        $jenisBudidayaId = self::resolveJenisBudidayaIdFromCoop($coopId)
            ?: self::resolveJenisBudidayaIdFromCommodity($commodityId);
        $commodityId = $commodityId ?: self::resolveCommodityIdFromCoop($coopId);

        if ($jenisBudidayaId && Schema::hasColumn('spk_fuzzy_profiles', 'jenis_budidaya_id')) {
            $profile = self::query()
                ->where('jenis_budidaya_id', $jenisBudidayaId)
                ->where('is_active', true)
                ->where('status', 'active')
                ->latest('updatedAt')
                ->first();

            if ($profile) {
                return $profile;
            }

            $profile = self::query()
                ->where('jenis_budidaya_id', $jenisBudidayaId)
                ->whereIn('status', ['review', 'draft', 'active'])
                ->latest('updatedAt')
                ->first();

            if ($profile) {
                return $profile;
            }
        }

        if ($commodityId) {
            $profile = self::query()
                ->where('commodity_id', $commodityId)
                ->where('is_active', true)
                ->where('status', 'active')
                ->latest('updatedAt')
                ->first();

            if ($profile) {
                return $profile;
            }

            $profile = self::query()
                ->where('commodity_id', $commodityId)
                ->whereIn('status', ['review', 'draft', 'active'])
                ->latest('updatedAt')
                ->first();

            if ($profile) {
                return $profile;
            }
        }

        return self::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->latest('updatedAt')
            ->first()
            ?: self::query()->latest('updatedAt')->first();
    }

    public static function resolveCommodityIdFromCoop(?string $coopId): ?string
    {
        if (! $coopId) {
            return null;
        }

        $jenisBudidayaId = DB::table('unitBudidaya')
            ->where('id', $coopId)
            ->value('jenisBudidayaId');

        if (! $jenisBudidayaId) {
            return null;
        }

        return DB::table('komoditas')
            ->where('jenisBudidayaId', $jenisBudidayaId)
            ->where('isDeleted', 0)
            ->orderBy('nama')
            ->value('id');
    }

    public static function resolveJenisBudidayaIdFromCoop(?string $coopId): ?string
    {
        if (! $coopId || ! Schema::hasTable('unitBudidaya')) {
            return null;
        }

        return DB::table('unitBudidaya')
            ->where('id', $coopId)
            ->value('jenisBudidayaId');
    }

    public static function resolveJenisBudidayaIdFromCommodity(?string $commodityId): ?string
    {
        if (! $commodityId || ! Schema::hasTable('komoditas')) {
            return null;
        }

        return DB::table('komoditas')
            ->where('id', $commodityId)
            ->value('jenisBudidayaId');
    }
}
