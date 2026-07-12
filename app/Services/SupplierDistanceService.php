<?php

namespace App\Services;

use App\Models\FarmProfile;
use App\Models\MasterSupplier;

class SupplierDistanceService
{
    public function farmProfileForUser(?string $userId): ?FarmProfile
    {
        if (! $userId) {
            return null;
        }

        return FarmProfile::query()->where('user_id', $userId)->first();
    }

    public function distanceToSupplier(MasterSupplier $supplier, ?string $userId): ?float
    {
        $farm = $this->farmProfileForUser($userId);
        if (! $farm?->hasCoordinates() || $supplier->latitude === null || $supplier->longitude === null) {
            return $supplier->jarak_km !== null ? (float) $supplier->jarak_km : null;
        }

        return $this->haversineKm(
            (float) $farm->latitude,
            (float) $farm->longitude,
            (float) $supplier->latitude,
            (float) $supplier->longitude
        );
    }

    public function distanceLabel(?float $distanceKm): string
    {
        if ($distanceKm === null) {
            return 'Lokasi belum lengkap';
        }

        return number_format($distanceKm, 1, ',', '.').' km';
    }

    public function haversineKm(float $originLat, float $originLng, float $targetLat, float $targetLng): float
    {
        $earthRadiusKm = 6371;
        $latDelta = deg2rad($targetLat - $originLat);
        $lngDelta = deg2rad($targetLng - $originLng);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($originLat)) * cos(deg2rad($targetLat)) * sin($lngDelta / 2) ** 2;

        return round($earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }
}
