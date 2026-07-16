<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\FarmProfile;
use App\Models\SupplierProductCategory;
use App\Models\SupplierStore;
use App\Services\ApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(private readonly ApiService $api) {}

    public function index(): View
    {
        return view('auth.register.index');
    }

    public function owner(): View
    {
        return view('auth.register.owner');
    }

    public function supplier(): View
    {
        return view('auth.register.supplier', [
            'categoryOptions' => SupplierProductCategory::activeOptions(),
        ]);
    }

    public function storeOwner(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:30',
            'password' => 'required|string|min:8|confirmed',
            'farm_name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        try {
            $response = $this->api->post('/auth/register', [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => $validated['password'],
                'confirmPassword' => $request->input('password_confirmation'),
                'role' => 'pjawab',
            ]);
        } catch (ApiException $exception) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('error', $exception->getMessage());
        }

        $userId = data_get($response, 'data.id');
        if ($userId) {
            FarmProfile::query()->updateOrCreate(
                ['user_id' => $userId],
                [
                    'farm_name' => $validated['farm_name'],
                    'address' => $validated['address'],
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                ]
            );
        }

        return redirect()
            ->route('login')
            ->with('success', 'Akun owner berhasil dibuat. Silakan login untuk melengkapi data operasional.');
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $categoryOptions = SupplierProductCategory::activeOptions()->all();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:30',
            'password' => 'required|string|min:8|confirmed',
            'store_name' => 'required|string|max:255',
            'kategori' => 'required|array|min:1',
            'kategori.*' => ['required', 'string', Rule::in($categoryOptions)],
            'alamat' => 'required|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'deskripsi' => 'nullable|string|max:2000',
        ]);

        try {
            $response = $this->api->post('/auth/register', [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => $validated['password'],
                'confirmPassword' => $request->input('password_confirmation'),
                'role' => 'supplier',
            ]);
        } catch (ApiException $exception) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('error', $exception->getMessage());
        }

        $userId = data_get($response, 'data.id');
        if ($userId) {
            SupplierStore::query()->updateOrCreate(
                ['userId' => $userId, 'isDeleted' => false],
                [
                    'id' => SupplierStore::query()->where('userId', $userId)->value('id') ?? Str::uuid()->toString(),
                    'nama' => $validated['store_name'],
                    'phone' => $this->normalizeWhatsApp($validated['phone']),
                    'alamat' => $validated['alamat'],
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                    'deskripsi' => $validated['deskripsi'] ?? null,
                    'kategori' => $this->normalizeCategories($validated['kategori']),
                    'tokoStatus' => 'request',
                    'TypeToko' => 'umkm',
                ]
            );
        }

        return redirect()
            ->route('login')
            ->with('success', 'Akun supplier berhasil diajukan. Toko akan tampil untuk owner setelah disetujui super admin.');
    }

    private function normalizeCategories(array $categories): string
    {
        $allowed = SupplierProductCategory::activeOptions();

        return collect($categories)
            ->map(fn ($category) => trim((string) $category))
            ->filter(fn ($category) => $category !== '' && $allowed->contains($category))
            ->unique()
            ->values()
            ->implode(',');
    }

    private function normalizeWhatsApp(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?: '';
        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }
        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }
}
