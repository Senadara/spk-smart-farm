<?php

namespace App\Http\Controllers\Spk;

use App\Http\Controllers\Controller;
use App\Models\SpkFuzzyProfile;
use App\Models\SpkFuzzyVariable;
use App\Services\Fuzzy\MamdaniEngine;
use App\Services\Fuzzy\NarrativeGenerator;
use App\Services\Notifications\NodeMobileNotificationClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SpkSimulationController extends Controller
{
    public function __construct(
        private readonly MamdaniEngine $engine,
        private readonly NarrativeGenerator $narrator,
        private readonly NodeMobileNotificationClient $notificationClient,
    ) {}

    public function index(Request $request)
    {
        $profiles = $this->getProfiles();
        $profile = $this->resolveProfile($request->query('profile_id'));
        $variables = $this->getInputVariables($profile?->id);

        return view('spk.simulation', $this->viewPayload(
            $profiles,
            $profile,
            $variables,
            $this->defaultInputValues($variables),
        ));
    }

    public function run(Request $request)
    {
        $validated = $request->validate([
            'profile_id' => ['nullable', 'string', 'exists:spk_fuzzy_profiles,id'],
            'input_values' => ['required', 'array'],
        ]);

        $profile = $this->resolveProfile($validated['profile_id'] ?? null);
        $variables = $this->getInputVariables($profile?->id);

        [$inputs, $inputValues, $errors] = $this->inputValuesFromRequest($request, $variables);

        if (! empty($errors)) {
            return back()
                ->withErrors($errors)
                ->withInput();
        }

        $result = $this->engine->processCascaded($inputs, $profile?->id, $profile?->commodity_id);
        $narrative = $this->narrator->generate($result, 'Simulasi Manual');

        return view('spk.simulation', $this->viewPayload(
            $this->getProfiles(),
            $profile,
            $variables,
            $inputValues,
            $result,
            $narrative,
        ));
    }

    public function sendNotification(Request $request)
    {
        abort_unless(
            in_array($this->role(), ['pjawab', 'owner', 'admin'], true),
            403,
            'Hanya penanggung jawab, owner, atau admin yang dapat mengirim notifikasi uji.'
        );

        $validated = $request->validate([
            'profile_id' => ['nullable', 'string', 'exists:spk_fuzzy_profiles,id'],
            'input_values' => ['required', 'array'],
            'target_role' => ['required', 'string', 'in:pjawab,owner,petugas,admin'],
            'notification_title' => ['required', 'string', 'max:120'],
            'notification_body' => ['required', 'string', 'max:500'],
        ]);

        $profile = $this->resolveProfile($validated['profile_id'] ?? null);
        $variables = $this->getInputVariables($profile?->id);
        [$inputs, $inputValues, $errors] = $this->inputValuesFromRequest($request, $variables);

        if (! empty($errors)) {
            return back()
                ->withErrors($errors)
                ->withInput();
        }

        $result = $this->engine->processCascaded($inputs, $profile?->id, $profile?->commodity_id);
        $narrative = $this->narrator->generate($result, 'Simulasi Manual');
        $notificationResponse = $this->notificationClient->sendToRole(
            $validated['target_role'],
            $validated['notification_title'],
            $validated['notification_body'],
            $this->notificationPayload($profile, $result)
        );

        $notificationResult = [
            'success' => (bool) ($notificationResponse['success'] ?? false),
            'target_role' => $validated['target_role'],
            'title' => $validated['notification_title'],
            'message' => (bool) ($notificationResponse['success'] ?? false)
                ? 'Notifikasi uji berhasil dikirim ke gateway mobile.'
                : $this->notificationErrorMessage($notificationResponse),
            'response' => $notificationResponse,
        ];

        return view('spk.simulation', $this->viewPayload(
            $this->getProfiles(),
            $profile,
            $variables,
            $inputValues,
            $result,
            $narrative,
            $notificationResult,
        ));
    }

    private function getProfiles()
    {
        return SpkFuzzyProfile::with('commodity')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    private function resolveProfile(?string $profileId = null): ?SpkFuzzyProfile
    {
        if ($profileId) {
            return SpkFuzzyProfile::with('commodity')->find($profileId);
        }

        return SpkFuzzyProfile::with('commodity')->find(SpkFuzzyProfile::resolveForContext()?->id);
    }

    private function getInputVariables(?string $profileId)
    {
        return SpkFuzzyVariable::with('sets')
            ->where('type', 'input')
            ->whereIn('group', ['lingkungan', 'kesehatan'])
            ->when($profileId, fn ($query) => $query->where('profile_id', $profileId))
            ->orderByRaw("FIELD(`group`, 'lingkungan', 'kesehatan')")
            ->orderBy('name')
            ->get();
    }

    private function groupVariables($variables): array
    {
        return [
            'lingkungan' => $variables->where('group', 'lingkungan')->values(),
            'kesehatan' => $variables->where('group', 'kesehatan')->values(),
        ];
    }

    private function inputValuesFromRequest(Request $request, $variables): array
    {
        $inputs = [];
        $inputValues = [];
        $errors = [];

        foreach ($variables as $variable) {
            $rawValue = $request->input("input_values.{$variable->id}");
            $inputValues[$variable->id] = $rawValue;

            if ($rawValue === null || $rawValue === '') {
                $errors["input_values.{$variable->id}"] = "Nilai {$variable->name} wajib diisi.";
                continue;
            }

            if (! is_numeric($rawValue)) {
                $errors["input_values.{$variable->id}"] = "Nilai {$variable->name} harus berupa angka.";
                continue;
            }

            $inputs[$variable->name] = (float) $rawValue;
        }

        return [$inputs, $inputValues, $errors];
    }

    private function viewPayload($profiles, ?SpkFuzzyProfile $profile, $variables, array $inputValues, ?array $result = null, ?string $narrative = null, ?array $notificationResult = null): array
    {
        return [
            'profiles' => $profiles,
            'profile' => $profile,
            'variables' => $variables,
            'groupedVariables' => $this->groupVariables($variables),
            'inputValues' => $inputValues,
            'result' => $result,
            'narrative' => $narrative,
            'notificationDefaults' => $this->notificationDefaults($result),
            'notificationResult' => $notificationResult,
            'notificationTargetRoles' => $this->notificationTargetRoles(),
        ];
    }

    private function notificationDefaults(?array $result): array
    {
        if (! $result) {
            return [
                'title' => 'Uji Notifikasi SPK',
                'body' => 'Ini notifikasi uji dari simulasi SPK Laravel. Nilai input saat ini akan dihitung sebelum dikirim.',
            ];
        }

        $diagnosis = data_get($result, 'kausalitas.label')
            ?: data_get($result, 'kesehatan.label')
            ?: 'Perlu perhatian';
        $recommendation = data_get($result, 'kausalitas.recommendation');
        $body = "Simulasi SPK menghasilkan {$diagnosis}.";

        if ($recommendation) {
            $body .= " Rekomendasi: {$recommendation}";
        }

        return [
            'title' => Str::limit("Uji SPK: {$diagnosis}", 120, ''),
            'body' => Str::limit($body, 500, ''),
        ];
    }

    private function notificationPayload(?SpkFuzzyProfile $profile, array $result): array
    {
        return [
            'type' => 'SPK_SIMULATION_TEST',
            'source' => 'laravel-spk-simulation',
            'severity' => $this->severityForResult($result),
            'profile_id' => $profile?->id,
            'profile_name' => $profile?->name,
            'commodity' => $profile?->commodity?->nama,
            'status_lingkungan' => data_get($result, 'lingkungan.label'),
            'status_produktivitas' => data_get($result, 'kesehatan.label'),
            'diagnosis_kausalitas' => data_get($result, 'kausalitas.label'),
            'recommendation' => data_get($result, 'kausalitas.recommendation'),
            'scores' => [
                'lingkungan' => data_get($result, 'lingkungan.value'),
                'produktivitas' => data_get($result, 'kesehatan.value'),
            ],
            'backoffice_url' => route('spk.simulation.index', ['profile_id' => $profile?->id], true),
            'sent_at' => now()->toIso8601String(),
        ];
    }

    private function severityForResult(array $result): string
    {
        $text = Str::lower(implode(' ', array_filter([
            data_get($result, 'lingkungan.label'),
            data_get($result, 'kesehatan.label'),
            data_get($result, 'kausalitas.label'),
            data_get($result, 'kausalitas.diagnosis'),
        ])));

        return match (true) {
            Str::contains($text, ['kritis', 'buruk', 'anomali', 'wabah', 'darurat']) => 'critical',
            Str::contains($text, ['waspada', 'gangguan', 'turun', 'perlu']) => 'warning',
            default => 'info',
        };
    }

    private function notificationErrorMessage(array $response): string
    {
        return data_get($response, 'error')
            ?: data_get($response, 'response.message')
            ?: data_get($response, 'response.error')
            ?: data_get($response, 'response.data.error')
            ?: data_get($response, 'response.data.remediation')
            ?: data_get($response, 'response.data.data.error')
            ?: data_get($response, 'response.data.data.remediation')
            ?: 'Gateway Node mengembalikan respons gagal. Cek detail respons di bawah.';
    }

    private function notificationTargetRoles(): array
    {
        return [
            'pjawab' => 'Penanggung Jawab',
            'owner' => 'Owner',
            'petugas' => 'Petugas',
            'admin' => 'Admin',
        ];
    }

    private function role(): string
    {
        return strtolower((string) session('user.role'));
    }

    private function defaultInputValues($variables): array
    {
        $defaults = [
            'suhu' => 27,
            'kelembapan' => 65,
            'amonia' => 5,
            'hdp' => 85,
            'fcr' => 2.2,
            'feed_intake' => 115,
            'mortalitas' => 0.1,
        ];

        $values = [];

        foreach ($variables as $variable) {
            $values[$variable->id] = $defaults[$this->canonicalVariableName($variable->name)] ?? '';
        }

        return $values;
    }

    private function canonicalVariableName(string $name): string
    {
        $key = strtolower(trim(str_replace([' ', '-'], '_', $name)));

        $aliases = [
            'temperature' => 'suhu',
            'temp' => 'suhu',
            'humidity' => 'kelembapan',
            'humiditas' => 'kelembapan',
            'kelembaban' => 'kelembapan',
            'ammonia' => 'amonia',
            'feed' => 'feed_intake',
            'pakan' => 'feed_intake',
            'konsumsi_pakan' => 'feed_intake',
            'feed_conversion_ratio' => 'fcr',
            'mortality' => 'mortalitas',
        ];

        return $aliases[$key] ?? $key;
    }
}
