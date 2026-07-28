import { Locator, Page, expect } from '@playwright/test';
import { AuthPage } from './AuthPage.js';

/**
 * Page Object untuk panel role SUPPLIER (/supplier/*), SupplierPanelController.
 *
 * REDESIGN Nanda (page-based, bukan modal):
 * - Login supplier redirect ke route supplier.dashboard (/supplier), BUKAN /dashboard.
 * - Dashboard: metrik (Produk Aktif, Stok Menipis, Pesanan Baru, Omzet Bulan Ini), Tren Omzet,
 *   Stok Perlu Perhatian, Pesanan Terbaru.
 * - Profil Toko (/supplier/store): form nama/phone/kategori/alamat/lat/lng/deskripsi/logo → "Simpan Profil".
 *   updateStore sukses (toko ada) → "Profil toko berhasil diperbarui."
 * - Produk (/supplier/products): daftar berupa TABEL (baris <tr>), filter (search/stock/category) + "Terapkan".
 *     * "Tambah Produk" = LINK ke /supplier/products/create (halaman terpisah, form "Simpan Produk").
 *     * "Edit" = LINK ke /supplier/products/{id}/edit (halaman, "Simpan Perubahan" + "Nonaktifkan Produk").
 *     * "Restok" = LINK ke /supplier/products/{id}/stock (halaman, form type/quantity/note → "Simpan Stok").
 *     * Kategori & Satuan WAJIB dipilih dari master (select). Create → "Produk berhasil ditambahkan.",
 *       update → "Produk berhasil diperbarui.", delete (soft) → "Produk dinonaktifkan dari toko."
 *       stock: restock → "Restock stok berhasil dicatat.", correction_out melebihi stok → "Stok tidak boleh menjadi minus."
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

    // ── Produk (page-based) ────────────────────────────────────
    /** Baris tabel produk berdasarkan nama. */
    productRow(name: string): Locator {
        return this.page.locator('tbody tr').filter({ hasText: name }).first();
    }

    /** Alias historis dipakai beberapa spec; kini mengacu ke baris tabel produk. */
    productCard(name: string): Locator {
        return this.productRow(name);
    }

    /** Form pada halaman Tambah Produk (action tepat berakhir /supplier/products). */
    get createProductForm(): Locator {
        return this.page.locator('form[action$="/supplier/products"]');
    }

    /** Buka halaman Tambah Produk via link "Tambah Produk". */
    async openCreateProduct() {
        await this.gotoProducts();
        await this.page.getByRole('link', { name: /Tambah Produk/i }).first().click();
        await expect(this.page).toHaveURL(/\/supplier\/products\/create/, { timeout: 15000 });
        await expect(this.page.getByRole('heading', { name: /^Tambah Produk$/i })).toBeVisible({ timeout: 10000 });
    }

    /** Isi & submit form Tambah Produk (asumsi sudah di halaman create). Kategori & satuan dipilih dari master. */
    async fillAndSubmitCreateProduct(params: { nama: string; deskripsi: string; harga: string; stok: string; satuan?: string; }) {
        await this.page.locator('input[name="nama"]').fill(params.nama);
        await this.page.locator('textarea[name="deskripsi"]').fill(params.deskripsi);
        await this.page.locator('select[name="kategori"]').selectOption({ index: 1 });
        const satuanSel = this.page.locator('select[name="satuan"]');
        if (params.satuan) {
            await satuanSel.selectOption({ label: params.satuan }).catch(async () => {
                await satuanSel.selectOption({ index: 0 });
            });
        } else {
            await satuanSel.selectOption({ index: 0 });
        }
        await this.page.locator('input[name="harga"]').fill(params.harga);
        await this.page.locator('input[name="stok"]').fill(params.stok);
        await this.page.getByRole('button', { name: /Simpan Produk/i }).click();
    }

    /** Alur lengkap: buka create → isi → submit. */
    async createProduct(params: { nama: string; deskripsi: string; harga: string; stok: string; satuan?: string; }) {
        await this.openCreateProduct();
        await this.fillAndSubmitCreateProduct(params);
    }

    /** Buka halaman Edit produk (via baris tabel), ubah harga, simpan. */
    async editProductPrice(name: string, harga: string) {
        await this.gotoProducts(`?search=${encodeURIComponent(name)}`);
        await this.productRow(name).getByRole('link', { name: /^Edit$/i }).click();
        await expect(this.page).toHaveURL(/\/supplier\/products\/[^/]+\/edit/, { timeout: 15000 });
        await this.page.locator('input[name="harga"]').fill(harga);
        await this.page.getByRole('button', { name: /Simpan Perubahan/i }).click();
    }

    /** Buka halaman Edit produk, klik "Nonaktifkan Produk" (konfirmasi native diterima). */
    async deleteProduct(name: string) {
        await this.gotoProducts(`?search=${encodeURIComponent(name)}`);
        await this.productRow(name).getByRole('link', { name: /^Edit$/i }).click();
        await expect(this.page).toHaveURL(/\/supplier\/products\/[^/]+\/edit/, { timeout: 15000 });
        this.page.once('dialog', dialog => dialog.accept());
        await this.page.getByRole('button', { name: /Nonaktifkan Produk/i }).click();
    }

    /** Buka halaman Restok produk (via baris tabel). */
    async gotoProductStock(name: string) {
        await this.gotoProducts(`?search=${encodeURIComponent(name)}`);
        await this.productRow(name).getByRole('link', { name: /^Restok$/i }).click();
        await expect(this.page).toHaveURL(/\/supplier\/products\/[^/]+\/stock/, { timeout: 15000 });
    }

    /** Catat pergerakan stok pada halaman Restok (asumsi sudah di halaman stock). */
    async submitStockMovement(params: { type: 'restock' | 'correction_in' | 'correction_out'; quantity: string; note?: string; }) {
        await this.page.locator('select[name="type"]').selectOption(params.type);
        await this.page.locator('input[name="quantity"]').fill(params.quantity);
        if (params.note !== undefined) {
            await this.page.locator('input[name="note"]').fill(params.note);
        }
        await this.page.getByRole('button', { name: /Simpan Stok/i }).click();
    }

    // ── Pesanan ────────────────────────────────────────────────
    orderStatusTab(label: string): Locator {
        return this.page.getByRole('link').filter({ hasText: new RegExp(`^\\s*${label}`, 'i') }).first();
    }
}
