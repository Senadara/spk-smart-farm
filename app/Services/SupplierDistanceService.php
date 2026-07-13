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

    public function deliveryEstimateLabel(?float $distanceKm): string
    {
        $minutes = $this->estimatedDeliveryMinutes($distanceKm);
        if ($minutes === null) {
            return 'Estimasi pengiriman belum tersedia';
        }

        if ($minutes < 60) {
            return "Estimasi {$minutes} menit";
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours < 24) {
            return $remainingMinutes > 0
                ? "Estimasi {$hours} jam {$remainingMinutes} menit"
                : "Estimasi {$hours} jam";
        }

        $days = (int) ceil($hours / 24);

        return "Estimasi {$days} hari";
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
