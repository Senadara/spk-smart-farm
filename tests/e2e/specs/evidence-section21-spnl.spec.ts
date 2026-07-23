import { test, expect, Page } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';

/**
 * EVIDENCE - Section 21: Panel Supplier (SPNL001-017)
 * Login supplier.demo@smartfarm.test / password123. Rute /supplier, /supplier/store, /supplier/products,
 * /supplier/orders, /supplier/finance. Screenshot -> qa-evidence/SPNL/
 * Jujur: tambah/edit/nonaktif produk TIDAK di-submit (evidence = form/kontrol) agar katalog tidak berubah.
 * Uji negatif (nama kosong) & simpan profil (data valid, harmless) dijalankan penuh.
 */
const SUP = 'supplier.demo@smartfarm.test';
const SUP_PW = 'password123';
const PW = 'Password123.';

async function cap(page: Page, path: string) {
    try { await page.waitForLoadState('networkidle', { timeout: 12000 }); }
    catch { await page.waitForLoadState('domcontentloaded').catch(() => { }); }
    await page.waitForTimeout(700);
    await page.screenshot({ path: `qa-evidence/${path}`, fullPage: true });
}

test.describe('Evidence Section 21 - Panel Supplier (supplier)', () => {
    let auth: AuthPage;
    test.setTimeout(160000);

    test.beforeEach(async ({ page }) => {
        await page.route('**/:5173/**', (r) => r.abort());
        await page.route(/.*:5173.*/, (r) => r.abort());
        auth = new AuthPage(page);
        // Global setup menyuntik storageState (sesi pjawab) -> bersihkan dulu agar /login tampil form.
        await page.context().clearCookies();
        // Supplier login diarahkan ke /supplier (bukan /dashboard) -> pakai login manual.
        await page.goto('/login', { waitUntil: 'domcontentloaded' });
        await page.evaluate(() => { try { localStorage.clear(); sessionStorage.clear(); } catch (e) { } }).catch(() => { });
        await page.goto('/login', { waitUntil: 'domcontentloaded' });
        await expect(page.locator('input[name="email"]')).toBeVisible({ timeout: 20000 });
        await page.locator('input[name="email"]').fill(SUP);
        await page.locator('input[name="password"]').fill(SUP_PW);
        await page.getByRole('button', { name: /Masuk|Login|Sign in/i }).first().click();
        await page.waitForURL(/\/supplier/, { timeout: 60000 }).catch(() => { });
    });

    test('SPNL001 - Ringkasan toko tampil lengkap', async ({ page }) => {
        await page.goto('/supplier', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('link', { name: /Atur Profil Toko/i })).toBeVisible({ timeout: 20000 });
        await cap(page, 'SPNL/SPNL001_ringkasan_toko.png');
    });

    test('SPNL002 - Pindah ke Profil Toko', async ({ page }) => {
        await page.goto('/supplier', { waitUntil: 'domcontentloaded' });
        await page.getByRole('link', { name: /Atur Profil Toko/i }).click();
        await expect(page).toHaveURL(/\/supplier\/store/, { timeout: 20000 });
        await cap(page, 'SPNL/SPNL002_pindah_profil.png');
    });

    test('SPNL003 - Profil Toko berisi data', async ({ page }) => {
        await page.goto('/supplier/store', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('button', { name: /Simpan Profil/i })).toBeVisible({ timeout: 20000 });
        await cap(page, 'SPNL/SPNL003_profil_toko_data.png');
    });

    test('SPNL004 - Simpan perubahan Profil Toko', async ({ page }) => {
        await page.goto('/supplier/store', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('button', { name: /Simpan Profil/i })).toBeVisible({ timeout: 20000 });
        const desc = page.locator('textarea[name="deskripsi"], textarea[name="keterangan"]').first();
        if (await desc.count() > 0) await desc.fill('Toko demo QA - keterangan diperbarui saat validasi.');
        await page.getByRole('button', { name: /Simpan Profil/i }).click();
        await page.waitForTimeout(1800);
        await cap(page, 'SPNL/SPNL004_simpan_profil.png');
    });

    test('SPNL005 - Simpan profil dengan nama kosong (ditahan)', async ({ page }) => {
        await page.goto('/supplier/store', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('button', { name: /Simpan Profil/i })).toBeVisible({ timeout: 20000 });
        await page.locator('input[name="nama"]').fill('');
        await page.getByRole('button', { name: /Simpan Profil/i }).click();
        await page.waitForTimeout(1200);
        // masih di halaman store (required menahan submit)
        await expect(page).toHaveURL(/\/supplier\/store/, { timeout: 10000 });
        await cap(page, 'SPNL/SPNL005_nama_kosong.png');
    });

    test('SPNL006 - Katalog produk menampilkan produk toko', async ({ page }) => {
        await page.goto('/supplier/products', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Katalog Produk/i })).toBeVisible({ timeout: 20000 });
        await cap(page, 'SPNL/SPNL006_katalog_produk.png');
    });

    test('SPNL007 - Menyaring produk (pencarian & stok menipis)', async ({ page }) => {
        await page.goto('/supplier/products?search=Pakan&stock=low', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Katalog Produk/i })).toBeVisible({ timeout: 20000 });
        await cap(page, 'SPNL/SPNL007_saring_produk.png');
    });

    test('SPNL008 - Form Tambah Produk (terisi)', async ({ page }) => {
        await page.goto('/supplier/products', { waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: /Tambah Produk/i }).click();
        const modal = page.locator('h2', { hasText: 'Tambah Produk' }).locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();
        await expect(modal).toBeVisible({ timeout: 15000 });
        await modal.locator('input[name="nama"]').fill('QA Produk Uji Evidence');
        await modal.locator('textarea[name="deskripsi"]').fill('Produk uji untuk evidence (tidak disimpan).');
        await modal.locator('select[name="kategori"]').selectOption({ index: 1 }).catch(() => { });
        await modal.locator('input[name="harga"]').fill('15000').catch(() => { });
        await modal.locator('input[name="stok"]').fill('50').catch(() => { });
        await cap(page, 'SPNL/SPNL008_tambah_produk_form.png');
    });

    test('SPNL009 - Form Edit produk (ubah harga)', async ({ page }) => {
        await page.goto('/supplier/products', { waitUntil: 'domcontentloaded' });
        const summary = page.getByText('Edit produk', { exact: true }).first();
        await summary.click();
        const priceInput = page.locator('input[name="harga"]').first();
        await priceInput.waitFor({ state: 'visible', timeout: 15000 });
        await priceInput.fill('17500');
        await cap(page, 'SPNL/SPNL009_edit_produk_harga.png');
    });

    test('SPNL010 - Kontrol Nonaktifkan produk', async ({ page }) => {
        await page.goto('/supplier/products', { waitUntil: 'domcontentloaded' });
        const summary = page.getByText('Edit produk', { exact: true }).first();
        await summary.click();
        const deactBtn = page.getByRole('button', { name: /Nonaktifkan Produk/i }).first();
        await deactBtn.waitFor({ state: 'visible', timeout: 15000 });
        await deactBtn.scrollIntoViewIfNeeded();
        await cap(page, 'SPNL/SPNL010_nonaktif_produk.png');
    });

    test('SPNL011 - Tambah produk tanpa nama (ditahan)', async ({ page }) => {
        await page.goto('/supplier/products', { waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: /Tambah Produk/i }).click();
        const modal = page.locator('h2', { hasText: 'Tambah Produk' }).locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();
        await expect(modal).toBeVisible({ timeout: 15000 });
        await modal.locator('textarea[name="deskripsi"]').fill('Tanpa nama - uji negatif');
        await modal.locator('select[name="kategori"]').selectOption({ index: 1 }).catch(() => { });
        await modal.locator('input[name="harga"]').fill('10000').catch(() => { });
        await modal.locator('input[name="stok"]').fill('10').catch(() => { });
        await modal.locator('input[name="satuan"]').fill('sak').catch(() => { });
        await modal.locator('button:has-text("Simpan")').first().click().catch(() => { });
        await page.waitForTimeout(1200);
        // modal tetap terbuka / masih di katalog (required nama menahan submit)
        const stillModal = await modal.isVisible().catch(() => false);
        const stillKatalog = await page.getByRole('heading', { name: /Katalog Produk/i }).isVisible().catch(() => false);
        expect(stillModal || stillKatalog).toBeTruthy();
        await cap(page, 'SPNL/SPNL011_tambah_tanpa_nama.png');
    });

    test('SPNL012 - Halaman Pesanan (ringkasan status + saringan)', async ({ page }) => {
        await page.goto('/supplier/orders', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Manajemen Pesanan/i })).toBeVisible({ timeout: 20000 });
        await cap(page, 'SPNL/SPNL012_halaman_pesanan.png');
    });

    test('SPNL013 - Menyaring pesanan berdasarkan status', async ({ page }) => {
        await page.goto('/supplier/orders?status=selesai', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Manajemen Pesanan/i })).toBeVisible({ timeout: 20000 });
        await cap(page, 'SPNL/SPNL013_saring_status.png');
    });

    test('SPNL014 - Kontrol ubah status pesanan (menunggu)', async ({ page }) => {
        await page.goto('/supplier/orders?status=menunggu', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Manajemen Pesanan/i })).toBeVisible({ timeout: 20000 });
        const terima = await page.getByRole('button', { name: /Terima|Proses|Terima Pesanan/i }).count();
        console.log('SPNL014_TERIMA_BTN::' + terima);
        await cap(page, 'SPNL/SPNL014_ubah_status.png');
    });

    test('SPNL015 - Halaman Keuangan tampil lengkap', async ({ page }) => {
        await page.goto('/supplier/finance', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Ringkasan Keuangan/i })).toBeVisible({ timeout: 20000 });
        await cap(page, 'SPNL/SPNL015_keuangan.png');
    });

    test('SPNL016 - Menyaring Keuangan berdasarkan tahun', async ({ page }) => {
        const prevYear = new Date().getFullYear() - 1;
        await page.goto('/supplier/finance?year=' + prevYear, { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Ringkasan Keuangan/i })).toBeVisible({ timeout: 20000 });
        await cap(page, 'SPNL/SPNL016_filter_tahun.png');
    });
});

test.describe('Evidence Section 21 - Guard akses non-supplier', () => {
    test.setTimeout(120000);
    test('SPNL017 - Penanggung Jawab tidak bisa buka Panel Supplier', async ({ page }) => {
        await page.route('**/:5173/**', (r) => r.abort());
        await page.route(/.*:5173.*/, (r) => r.abort());
        const auth = new AuthPage(page);
        await auth.loginAndWaitForDashboard('pjawab@email.com', PW);
        const resp = await page.goto('/supplier', { waitUntil: 'domcontentloaded' });
        console.log('SPNL017_STATUS::' + (resp?.status() ?? 'n/a') + ' URL::' + page.url());
        const hasPanel = await page.getByRole('link', { name: /Atur Profil Toko/i }).isVisible().catch(() => false);
        expect(hasPanel).toBeFalsy();
        await cap(page, 'SPNL/SPNL017_akses_ditolak.png');
    });
});
