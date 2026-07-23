import { Locator, Page, expect } from '@playwright/test';
import { AuthPage } from './AuthPage.js';

/**
 * Page Object untuk panel role SUPPLIER (/supplier/*), SupplierPanelController.
 *
 * Fakta implementasi nyata:
 * - Login supplier redirect ke route supplier.dashboard (/supplier), BUKAN /dashboard.
 * - Dashboard: metrik (Produk Aktif, Stok Menipis, Pesanan Baru, Omzet Bulan Ini), Tren Omzet,
 *   Stok Perlu Perhatian, Pesanan Terbaru.
 * - Profil Toko (/supplier/store): form nama/phone/kategori/alamat/lat/lng/deskripsi/logo → "Simpan Profil".
 *   updateStore sukses (toko ada) → "Profil toko berhasil diperbarui."
 * - Produk (/supplier/products): filter (search/stock/category), kartu produk + <details> Edit,
 *   modal "Tambah Produk". Create → "Produk berhasil ditambahkan.", update → "Produk berhasil diperbarui.",
 *   delete (soft) → "Produk dinonaktifkan dari toko." Kategori WAJIB dari master (activeOptions).
 * - Pesanan (/supplier/orders): kartu status count, filter, aksi Terima/Tolak (menunggu) & Tandai Selesai (diterima).
 *   updateOrderStatus memanggil API eksternal (Node) → sukses "Status pesanan berhasil diperbarui."
 * - Keuangan (/supplier/finance): total omzet, transaksi selesai, rata-rata, grafik bulanan, tabel.
 */
export class SupplierPanelPage {
    readonly page: Page;

    constructor(page: Page) {
        this.page = page;
    }

    /** Login sebagai supplier & tunggu landing di /supplier (bukan /dashboard). */
    async loginAsSupplier(authPage: AuthPage) {
        await this.page.context().clearCookies();
        await authPage.gotoLogin();
        await authPage.login('supplier.demo@smartfarm.test', 'password123');
        await expect(this.page).toHaveURL(/\/supplier(\/|$|\?)/, { timeout: 120000 });
    }

    async gotoDashboard() {
        await this.page.goto('/supplier', { waitUntil: 'domcontentloaded' });
    }

    async gotoStore() {
        await this.page.goto('/supplier/store', { waitUntil: 'domcontentloaded' });
    }

    async gotoProducts(query = '') {
        await this.page.goto(`/supplier/products${query}`, { waitUntil: 'domcontentloaded' });
    }

    async gotoOrders(query = '') {
        await this.page.goto(`/supplier/orders${query}`, { waitUntil: 'domcontentloaded' });
    }

    async gotoFinance(query = '') {
        await this.page.goto(`/supplier/finance${query}`, { waitUntil: 'domcontentloaded' });
    }

    // ── Produk ────────────────────────────────────────────────
    /** Form modal "Tambah Produk" (satu-satunya form dengan tombol "Simpan Produk"). */
    get createProductForm(): Locator {
        return this.page.locator('form').filter({ has: this.page.getByRole('button', { name: /Simpan Produk/i }) });
    }

    productCard(name: string): Locator {
        return this.page.locator('article').filter({ hasText: name }).first();
    }

    async openCreateProduct() {
        await this.page.getByRole('button', { name: /Tambah Produk/i }).click();
        await expect(this.page.getByRole('heading', { name: /^Tambah Produk$/i })).toBeVisible({ timeout: 10000 });
    }

    async fillAndSubmitCreateProduct(params: { nama: string; deskripsi: string; harga: string; stok: string; satuan: string; }) {
        const f = this.createProductForm;
        await f.locator('input[name="nama"]').fill(params.nama);
        await f.locator('textarea[name="deskripsi"]').fill(params.deskripsi);
        // Kategori wajib; index 0 = placeholder disabled, pilih kategori master pertama.
        await f.locator('select[name="kategori"]').selectOption({ index: 1 });
        await f.locator('input[name="harga"]').fill(params.harga);
        await f.locator('input[name="stok"]').fill(params.stok);
        await f.locator('input[name="satuan"]').fill(params.satuan);
        await f.getByRole('button', { name: /Simpan Produk/i }).click();
    }

    /** Buka <details> "Edit produk" pada kartu produk lalu submit perubahan harga. */
    async editProductPrice(name: string, harga: string) {
        const card = this.productCard(name);
        // Buka <details> secara eksplisit (klik <summary> kadang tidak reliabel di headless).
        await card.locator('details').first().evaluate((d: HTMLDetailsElement) => { d.open = true; });
        // Input harga & tombol "Simpan" unik di dalam kartu (form hapus tidak punya keduanya).
        await card.locator('input[name="harga"]').fill(harga);
        await card.getByRole('button', { name: /^Simpan$/i }).click();
    }

    async deleteProduct(name: string) {
        const card = this.productCard(name);
        await card.locator('details').first().evaluate((d: HTMLDetailsElement) => { d.open = true; });
        this.page.once('dialog', dialog => dialog.accept());
        await card.getByRole('button', { name: /Nonaktifkan Produk/i }).click();
    }

    // ── Pesanan ────────────────────────────────────────────────
    orderStatusTab(label: string): Locator {
        return this.page.getByRole('link').filter({ hasText: new RegExp(`^\\s*${label}`, 'i') }).first();
    }
}
