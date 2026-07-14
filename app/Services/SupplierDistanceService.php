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

    public function distanceToCoordinates(?float $latitude, ?float $longitude, ?string $userId): ?float
    {
        $farm = $this->farmProfileForUser($userId);
        if (! $farm?->hasCoordinates() || $latitude === null || $longitude === null) {
            return null;
        }

        return $this->haversineKm(
            (float) $farm->latitude,
            (float) $farm->longitude,
            $latitude,
            $longitude
        );
    }

    public function distanceLabel(?float $distanceKm): string
    {
        if ($distanceKm === null) {
            return 'Lokasi belum lengkap';
        }

        return number_format($distanceKm, 1, ',', '.').' km';
    }

    public function estimatedDeliveryMinutes(?float $distanceKm): ?int
    {
        if ($distanceKm === null) {
            return null;
        }

        if ($distanceKm <= 0) {
            return 30;
        }

        $averageSpeedKmh = match (true) {
            $distanceKm <= 15 => 25,
            $distanceKm <= 80 => 35,
            $distanceKm <= 200 => 45,
            default => 55,
        };

        $handlingMinutes = $distanceKm <= 15 ? 45 : 90;

        return (int) max(30, ceil(($distanceKm / $averageSpeedKmh) * 60 + $handlingMinutes));
    }

    public function estimatedDeliveryDays(?float $distanceKm): ?float
    {
        if ($distanceKm === null) {
            return null;
        }

        return match (true) {
            $distanceKm <= 40 => 0.5,
            $distanceKm <= 150 => 1.0,
            $distanceKm <= 350 => 2.0,
            $distanceKm <= 700 => 3.0,
            default => (float) min(7, max(4, (int) ceil($distanceKm / 300))),
        };
    }

    public function deliveryEstimateLabel(?float $distanceKm): string
    {
        $days = $this->estimatedDeliveryDays($distanceKm);
        if ($days === null) {
            return 'Estimasi pengiriman belum tersedia';
        }

        if ($days <= 0.5) {
            return 'Estimasi same day';
        }

        $roundedDays = (int) ceil($days);

        return "Estimasi {$roundedDays} hari";
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
