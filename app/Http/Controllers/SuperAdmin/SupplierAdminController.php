<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\MasterSupplier;
use App\Models\SpkRanking;
use App\Models\SupplierProductCategory;
use App\Models\SupplierStore;
use App\Services\Notifications\SupplierNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierAdminController extends Controller
{
    public function __construct(
        private readonly SupplierNotificationService $supplierNotificationService,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->input('status', 'request');
        if (! in_array($status, ['request', 'active', 'reject'], true)) {
            $status = 'request';
        }

        $stores = SupplierStore::query()
            ->where('isDeleted', false)
            ->where('tokoStatus', $status)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($inner) use ($search) {
                    $inner->where('nama', 'like', "%{$search}%")
                        ->orWhere('alamat', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('kategori', 'like', "%{$search}%");
                });
            })
            ->latest('updatedAt')
            ->paginate(12)
            ->withQueryString();

        $counts = SupplierStore::query()
            ->where('isDeleted', false)
            ->whereIn('tokoStatus', ['request', 'active', 'reject'])
            ->selectRaw('tokoStatus, COUNT(*) as total')
            ->groupBy('tokoStatus')
            ->pluck('total', 'tokoStatus');

        $manualSuppliers = MasterSupplier::query()
            ->orderBy('nama')
            ->limit(8)
            ->get();

        return view('superadmin.suppliers.index', compact(
            'stores',
            'counts',
            'status',
            'manualSuppliers'
        ));
    }

    public function create(): View
    {
        return view('spk.suppliers.form', [
            'supplier' => null,
            'store' => null,
            'categoryOptions' => SupplierProductCategory::activeOptions(),
            'selectedCategories' => [],
            'formAction' => route('superadmin.suppliers.store'),
            'indexRoute' => route('superadmin.suppliers.index'),
            'formEyebrow' => 'Super Admin',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateManagedSupplier($request);
        $phone = $this->normalizeWhatsApp($validated['whatsapp']);
        $categories = $this->normalizeSupplierCategories($validated['kategori'] ?? []);

        $supplier = DB::transaction(function () use ($validated, $phone, $categories) {
            $supplier = MasterSupplier::query()->create([
                'nama' => $validated['nama'],
                'alamat' => $validated['alamat'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'kontak' => $phone,
                'deskripsi' => $validated['deskripsi'] ?? null,
                'kategori' => $categories,
                'rating' => 0,
                'jarak_km' => null,
            ]);

            $this->syncManagedSupplierStore($supplier, null, $categories, 'active');

            return $supplier;
        });

        return redirect()
            ->route('superadmin.suppliers.index', ['status' => 'active'])
            ->with('success', "Mitra supplier {$supplier->nama} berhasil ditambahkan dan langsung aktif.");
    }

    public function edit(MasterSupplier $supplier): View
    {
        $store = $this->storeForSupplier($supplier);

        return view('spk.suppliers.form', [
            'supplier' => $supplier,
            'store' => $store,
            'categoryOptions' => SupplierProductCategory::activeOptions(),
            'selectedCategories' => $this->supplierCategoryArray($supplier->kategori),
            'formAction' => route('superadmin.suppliers.update', $supplier),
            'indexRoute' => route('superadmin.suppliers.index', ['status' => $store?->tokoStatus ?? 'active']),
            'formEyebrow' => 'Super Admin',
        ]);
    }

    public function update(Request $request, MasterSupplier $supplier): RedirectResponse
    {
        $validated = $this->validateManagedSupplier($request);
        $phone = $this->normalizeWhatsApp($validated['whatsapp']);
        $categories = $this->normalizeSupplierCategories($validated['kategori'] ?? []);
        $store = $this->storeForSupplier($supplier);

        DB::transaction(function () use ($supplier, $store, $validated, $phone, $categories) {
            $supplier->update([
                'nama' => $validated['nama'],
                'alamat' => $validated['alamat'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'kontak' => $phone,
                'deskripsi' => $validated['deskripsi'] ?? null,
                'kategori' => $categories,
            ]);

            $this->syncManagedSupplierStore($supplier, $store, $categories, $store?->tokoStatus ?: 'active');
            SpkRanking::query()->where('supplier_id', $supplier->id)->update(['is_valid' => false]);
        });

        return redirect()
            ->route('superadmin.suppliers.index', ['status' => $store?->tokoStatus ?? 'active'])
            ->with('success', 'Data mitra supplier berhasil diperbarui.');
    }

    public function approve(Request $request, SupplierStore $store): RedirectResponse
    {
        abort_if($store->isDeleted, 404);
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($store) {
            $supplier = $this->supplierForStore($store) ?? MasterSupplier::query()->create([
                'nama' => $store->nama,
                'alamat' => $store->alamat,
                'latitude' => $store->latitude,
                'longitude' => $store->longitude,
                'kontak' => $this->normalizeWhatsApp((string) $store->phone),
                'deskripsi' => $store->deskripsi,
                'kategori' => $store->kategori,
                'rating' => 0,
                'jarak_km' => null,
                'logo_url' => $store->logoToko,
            ]);

            $supplier->update([
                'nama' => $store->nama,
                'alamat' => $store->alamat,
                'latitude' => $store->latitude,
                'longitude' => $store->longitude,
                'kontak' => $this->normalizeWhatsApp((string) $store->phone),
                'deskripsi' => $store->deskripsi,
                'kategori' => $store->kategori,
                'logo_url' => $store->logoToko,
            ]);

            $store->update([
                'tokoStatus' => 'active',
                'approvalReason' => null,
                'approvalNotifiedAt' => now(),
            ]);
            SpkRanking::query()->where('supplier_id', $supplier->id)->update(['is_valid' => false]);
        });

        if (! empty($validated['reason'])) {
            $store->forceFill(['approvalReason' => $validated['reason']])->save();
        }

        $this->supplierNotificationService->supplierApproved($store->fresh());

        return back()->with('success', 'Supplier disetujui dan notifikasi email sudah diproses.');
    }

    public function reject(Request $request, SupplierStore $store): RedirectResponse
    {
        abort_if($store->isDeleted, 404);
        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ], [
            'reason.required' => 'Alasan penolakan supplier wajib diisi.',
            'reason.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        $store->update([
            'tokoStatus' => 'reject',
            'approvalReason' => $validated['reason'],
            'approvalNotifiedAt' => now(),
        ]);

        $this->supplierNotificationService->supplierRejected($store->fresh(), $validated['reason']);

        return back()->with('success', 'Pengajuan supplier ditolak dan notifikasi email sudah diproses.');
    }

    private function validateManagedSupplier(Request $request): array
    {
        $categoryOptions = SupplierProductCategory::activeOptions()->all();

        return $request->validate([
            'nama' => 'required|string|max:255',
            'whatsapp' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s().]+$/'],
            'alamat' => 'required|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'kategori' => 'required|array|min:1',
            'kategori.*' => ['required', 'string', Rule::in($categoryOptions)],
            'deskripsi' => 'nullable|string|max:2000',
        ], [
            'whatsapp.regex' => 'Nomor WhatsApp hanya boleh berisi angka, spasi, tanda +, tanda -, titik, atau kurung.',
            'kategori.required' => 'Pilih minimal satu kategori supplier.',
            'kategori.min' => 'Pilih minimal satu kategori supplier.',
            'kategori.*.in' => 'Kategori supplier harus dipilih dari daftar master kategori.',
        ]);
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
        if (str_starts_with($digits, '620')) {
            return '62'.substr($digits, 3);
        }

        return $digits;
    }

    private function normalizeSupplierCategories(array $categories): string
    {
        $allowed = SupplierProductCategory::activeOptions();

        return collect($categories)
            ->map(fn ($category) => trim((string) $category))
            ->filter(fn ($category) => $category !== '' && $allowed->contains($category))
            ->unique()
            ->values()
            ->implode(',');
    }

    private function supplierCategoryArray(?string $categories): array
    {
        $allowed = SupplierProductCategory::activeOptions();

        return collect(explode(',', (string) $categories))
            ->map(fn ($category) => trim($category))
            ->filter()
            ->map(fn ($category) => $allowed->first(
                fn ($option) => Str::lower($option) === Str::lower($category)
            ))
            ->filter()
            ->values()
            ->all();
    }

    private function syncManagedSupplierStore(MasterSupplier $supplier, ?SupplierStore $store, string $categories, string $status): SupplierStore
    {
        $store ??= $this->storeForSupplier($supplier);

        $payload = [
            'nama' => $supplier->nama,
            'phone' => $supplier->kontak,
            'alamat' => $supplier->alamat,
            'latitude' => $supplier->latitude,
            'longitude' => $supplier->longitude,
            'deskripsi' => $supplier->deskripsi,
            'kategori' => $categories,
            'isDeleted' => false,
            'tokoStatus' => $status,
            'TypeToko' => $store?->TypeToko ?: 'umkm',
        ];

        if ($store) {
            $store->fill($payload);
            $store->save();

            return $store;
        }

        return SupplierStore::query()->create([
            'id' => Str::uuid()->toString(),
            'userId' => null,
            ...$payload,
        ]);
    }

    private function storeForSupplier(MasterSupplier $supplier): ?SupplierStore
    {
        $phone = preg_replace('/[^0-9]/', '', (string) $supplier->kontak);

        return SupplierStore::query()
            ->where('isDeleted', false)
            ->where(function ($query) use ($supplier, $phone) {
                $query->where('nama', $supplier->nama);

                if ($phone !== '') {
                    $query->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', '') = ?", [$phone]);
                }
            })
            ->first();
    }

    private function supplierForStore(SupplierStore $store): ?MasterSupplier
    {
        $phone = preg_replace('/[^0-9]/', '', (string) $store->phone);

        return MasterSupplier::query()
            ->where(function ($query) use ($store, $phone) {
                $query->where('nama', $store->nama);

                if ($phone !== '') {
                    $query->orWhereRaw("REPLACE(REPLACE(REPLACE(kontak, '+', ''), '-', ''), ' ', '') = ?", [$phone]);
                }
            })
            ->first();
    }
}
