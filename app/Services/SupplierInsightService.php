<?php

namespace App\Services;

use App\Models\MasterSupplier;
use App\Models\SpkParameter;
use App\Models\SpkRanking;
use App\Models\SpkSupplierParameterValue;
use App\Models\SpkSupplierSelectionLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupplierInsightService
{
    /**
     * @return Collection<int, array{type: string, message: string, severity: string}>
     */
    public function generateInsights(int $userId, ?int $produkId = null): Collection
    {
        $insights = collect();

        $topSupplier = SpkSupplierSelectionLog::where('user_id', $userId)
            ->when($produkId, fn ($q) => $q->where('produk_id', $produkId))
            ->select('supplier_id', DB::raw('COUNT(*) as total'))
            ->groupBy('supplier_id')
            ->orderByDesc('total')
            ->first();

        if ($topSupplier) {
            $name = MasterSupplier::find($topSupplier->supplier_id)?->nama ?? 'Supplier';
            $insights->push([
                'type' => 'selection_pattern',
                'message' => "{$name} paling sering dipilih ({$topSupplier->total}x) berdasarkan riwayat rekomendasi.",
                'severity' => 'info',
            ]);
        }

        $hargaParam = SpkParameter::where('nama_parameter', 'like', '%Harga%')->first();
        if ($hargaParam && $produkId) {
            $values = SpkSupplierParameterValue::where('produk_id', $produkId)
                ->where('parameter_id', $hargaParam->id)
                ->with('supplier')
                ->orderBy('value')
                ->get();

            if ($values->count() >= 2) {
                $cheapest = $values->first();
                $expensive = $values->last();
                $diff = $expensive->value > 0
                    ? round((($expensive->value - $cheapest->value) / $expensive->value) * 100, 1)
                    : 0;

                if ($diff > 0) {
                    $insights->push([
                        'type' => 'price_gap',
                        'message' => "Selisih harga antar supplier mencapai {$diff}% untuk produk ini (termurah: {$cheapest->supplier->nama}).",
                        'severity' => 'warning',
                    ]);
                }
            }
        }

        $kualitasParam = SpkParameter::where('nama_parameter', 'like', '%Kualitas%')->first();
        if ($kualitasParam && $produkId) {
            $recent = SpkSupplierParameterValue::where('produk_id', $produkId)
                ->where('parameter_id', $kualitasParam->id)
                ->with('supplier')
                ->get();

            if ($recent->count() >= 2) {
                $best = $recent->sortByDesc('value')->first();
                $worst = $recent->sortBy('value')->first();
                $drop = $worst->value > 0
                    ? round((($best->value - $worst->value) / $best->value) * 100, 1)
                    : 0;

                if ($drop >= 5) {
                    $insights->push([
                        'type' => 'quality_gap',
                        'message' => "{$worst->supplier->nama} menawarkan kualitas lebih rendah (~{$drop}% di bawah {$best->supplier->nama}) meski harga bisa lebih kompetitif.",
                        'severity' => 'warning',
                    ]);
                }
            }
        }

        $kecepatanParam = SpkParameter::where('nama_parameter', 'like', '%Kecepatan%')->first();
        if ($kecepatanParam && $produkId) {
            $slow = SpkSupplierParameterValue::where('produk_id', $produkId)
                ->where('parameter_id', $kecepatanParam->id)
                ->with('supplier')
                ->orderByDesc('value')
                ->first();

            if ($slow && $slow->value >= 3) {
                $insights->push([
                    'type' => 'delivery_risk',
                    'message' => "{$slow->supplier->nama} memiliki estimasi pengiriman lebih lama ({$slow->value} hari) — pertimbangkan untuk pesanan mendesak.",
                    'severity' => 'danger',
                ]);
            }
        }

        $latestRanking = SpkRanking::where('user_id', $userId)
            ->when($produkId, fn ($q) => $q->where('produk_id', $produkId))
            ->where('is_valid', true)
            ->orderBy('ranking')
            ->with('supplier')
            ->first();

        if ($latestRanking) {
            $insights->push([
                'type' => 'saw_recommendation',
                'message' => "Rekomendasi SAW saat ini: {$latestRanking->supplier->nama} (skor " . number_format($latestRanking->final_score, 4) . ', peringkat #' . $latestRanking->ranking . ').',
                'severity' => 'success',
            ]);
        }

        return $insights;
    }

    public function logSelection(int $userId, int $supplierId, int $produkId, ?float $score = null, ?int $ranking = null): void
    {
        SpkSupplierSelectionLog::create([
            'user_id' => $userId,
            'supplier_id' => $supplierId,
            'produk_id' => $produkId,
            'final_score' => $score,
            'ranking' => $ranking,
        ]);
    }
}
