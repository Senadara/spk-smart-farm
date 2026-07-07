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
    public function generateInsights(string $userId, ?int $produkId = null): Collection
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
                    $supplierName = $cheapest->supplier?->nama ?? 'Supplier';
                    $insights->push([
                        'type' => 'price_gap',
                        'message' => "Selisih harga antar supplier mencapai {$diff}% untuk produk ini (termurah: {$supplierName}).",
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
                $drop = $best->value > 0
                    ? round((($best->value - $worst->value) / $best->value) * 100, 1)
                    : 0;

                if ($drop >= 5) {
                    $worstName = $worst->supplier?->nama ?? 'Supplier';
                    $bestName = $best->supplier?->nama ?? 'supplier terbaik';
                    $insights->push([
                        'type' => 'quality_gap',
                        'message' => "{$worstName} menawarkan kualitas lebih rendah (~{$drop}% di bawah {$bestName}) meski harga bisa lebih kompetitif.",
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
                ->orderBy('value')
                ->first();

            $estimatedDays = $slow && $slow->value > 0 ? round(100 / $slow->value, 1) : null;
            if ($slow && $estimatedDays !== null && $estimatedDays >= 3) {
                $supplierName = $slow->supplier?->nama ?? 'Supplier';
                $insights->push([
                    'type' => 'delivery_risk',
                    'message' => "{$supplierName} memiliki estimasi pengiriman lebih lama (~{$estimatedDays} hari) - pertimbangkan untuk pesanan mendesak.",
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
            $supplierName = $latestRanking->supplier?->nama ?? 'Supplier';
            $score = number_format($latestRanking->final_score, 4);
            $insights->push([
                'type' => 'saw_recommendation',
                'message' => "Rekomendasi SAW saat ini: {$supplierName} (skor {$score}, peringkat #{$latestRanking->ranking}).",
                'severity' => 'success',
            ]);
        }

        return $insights;
    }

    public function logSelection(string $userId, int $supplierId, int $produkId, ?float $score = null, ?int $ranking = null): void
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
