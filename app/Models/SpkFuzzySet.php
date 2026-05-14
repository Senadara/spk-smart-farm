<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SpkFuzzySet extends Model
{
    use HasUuids;

    protected $table = 'spk_fuzzy_sets';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'variable_id',
        'name',
        'shape',
        'a',
        'b',
        'c',
        'd',
    ];

    protected $casts = [
        'a' => 'float',
        'b' => 'float',
        'c' => 'float',
        'd' => 'float',
    ];

    /**
     * Variabel fuzzy tempat himpunan ini berada.
     */
    public function variable(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SpkFuzzyVariable::class, 'variable_id');
    }

    /**
     * Hitung derajat keanggotaan µ(x) untuk nilai x.
     *
     * Triangle  : µ = 0 di luar [a,c], puncak = 1 di b
     * Trapezoid : µ = 0 di luar [a,d], µ = 1 di [b,c]
     *
     * @param float $x Nilai crisp yang akan di-fuzzify
     * @return float Derajat keanggotaan dalam rentang [0, 1]
     */
    public function membership(float $x): float
    {
        $a = (float) $this->a;
        $b = (float) $this->b;
        $c = (float) $this->c;
        $d = $this->d !== null ? (float) $this->d : null;

        if ($this->shape === 'triangle') {
            // Titik-titik: a (kaki kiri), b (puncak), c (kaki kanan)
            if ($x <= $a || $x >= $c) {
                return 0.0;
            }
            if ($x === $b) {
                return 1.0;
            }
            if ($x < $b) {
                return ($b - $a) > 0 ? ($x - $a) / ($b - $a) : 0.0;
            }
            // $x > $b
            return ($c - $b) > 0 ? ($c - $x) / ($c - $b) : 0.0;
        }

        // Trapezoid: a (kaki kiri bawah), b (kaki kiri atas), c (kaki kanan atas), d (kaki kanan bawah)
        if ($d === null) {
            // Fallback ke triangle jika d tidak ada
            return $this->membership($x);
        }

        if ($x <= $a || $x >= $d) {
            return 0.0;
        }
        if ($x >= $b && $x <= $c) {
            return 1.0;
        }
        if ($x > $a && $x < $b) {
            return ($b - $a) > 0 ? ($x - $a) / ($b - $a) : 0.0;
        }
        // $x > $c && $x < $d
        return ($d - $c) > 0 ? ($d - $x) / ($d - $c) : 0.0;
    }
}
