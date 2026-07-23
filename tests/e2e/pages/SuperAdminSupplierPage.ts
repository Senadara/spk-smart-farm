import { Locator, Page, expect } from '@playwright/test';

/**
 * Page Object untuk menu Super Admin → Manajemen Mitra Supplier (role: admin).
 * Route: /super-admin/suppliers (SuperAdmin\SupplierAdminController).
 *
 * Fakta implementasi nyata:
 * - Status toko: request (Menunggu) / active (Aktif) / reject (Ditolak) via query ?status=.
 * - "Tambah Supplier Manual" membuka form (spk.suppliers.form) → supplier langsung AKTIF.
 * - Master Supplier Manual: daftar MasterSupplier (limit 8) dengan tombol Edit.
 * - Kartu toko punya tombol "Setujui" (jika status != active) & "Tolak" (jika status != reject, ada confirm()).
 */
export class SuperAdminSupplierPage {
    readonly page: Page;
    readonly pageTitle: Locator;
    readonly eyebrow: Locator;
    readonly addManualButton: Locator;
    readonly searchInput: Locator;
    readonly searchButton: Locator;

    constructor(page: Page) {
        this.page = page;
        this.pageTitle = page.getByRole('heading', { name: /Manajemen Mitra Supplier/i });
        this.eyebrow = page.getByText('Super Admin', { exact: true }).first();
        this.addManualButton = page.getByRole('link', { name: /Tambah Supplier Manual/i });
        this.searchInput = page.locator('input[name="search"]');
        this.searchButton = page.getByRole('button', { name: /^Cari$/i });
    }

    async goto(status?: 'request' | 'active' | 'reject') {
        const url = status ? `/super-admin/suppliers?status=${status}` : '/super-admin/suppliers';
        await this.page.goto(url, { waitUntil: 'domcontentloaded' });
    }

    async expectPageReady() {
        await expect(this.pageTitle).toBeVisible({ timeout: 30000 });
    }

    /** Tab statistik berbentuk <a> berisi label + jumlah. */
    statTab(label: 'Menunggu' | 'Aktif' | 'Ditolak'): Locator {
        return this.page.getByRole('link').filter({ hasText: new RegExp(label, 'i') }).first();
    }

    /** Kartu toko supplier (<article>) pada daftar toko. */
    storeCard(name: string): Locator {
        return this.page.locator('article').filter({ hasText: name }).first();
    }

    async openCreateForm() {
        await this.addManualButton.click();
        await expect(this.page.getByRole('heading', { name: /Tambah mitra supplier baru/i })).toBeVisible({ timeout: 10000 });
    }

    /** Isi form supplier. selectFirstCategory=true untuk centang kategori pertama yang tersedia. */
    async fillSupplierForm(params: {
        nama?: string;
        whatsapp?: string;
        alamat?: string;
        deskripsi?: string;
        selectFirstCategory?: boolean;
    }) {
        if (params.nama !== undefined) await this.page.locator('input[name="nama"]').fill(params.nama);
        if (params.whatsapp !== undefined) await this.page.locator('input[name="whatsapp"]').fill(params.whatsapp);
        if (params.alamat !== undefined) await this.page.locator('textarea[name="alamat"]').fill(params.alamat);
        if (params.deskripsi !== undefined) await this.page.locator('textarea[name="deskripsi"]').fill(params.deskripsi);
        if (params.selectFirstCategory) {
            await this.page.locator('input[name="kategori[]"]').first().check();
        }
    }

    async submitCreate() {
        await this.page.getByRole('button', { name: /Tambah Mitra/i }).click();
    }

    async submitUpdate() {
        await this.page.getByRole('button', { name: /Simpan Perubahan/i }).click();
    }
}
