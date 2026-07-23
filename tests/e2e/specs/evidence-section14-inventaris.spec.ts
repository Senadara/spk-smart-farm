import { test, expect, Page } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';

/**
 * EVIDENCE CAPTURE - Section 14: Inventaris (INV001-020) → qa-evidence/INV/
 * Opsi 1: divalidasi terhadap UI terbaru "Monitoring Stok & Restock Supplier".
 * Petugas: view/filter/chart/search + guard. Penanggung Jawab: fitur restock supplier.
 */
const PW = 'Password123.';
async function cap(page: Page, file: string) {
     try { await page.waitForLoadState('networkidle', { timeout: 15000 }); }
     catch { await page.waitForLoadState('domcontentloaded'); }
     await page.waitForTimeout(800);
     await page.screenshot({ path: `qa-evidence/INV/${file}`, fullPage: true });
}
async function shot(page: Page, file: string) {
     await page.waitForTimeout(300);
     await page.screenshot({ path: `qa-evidence/INV/${file}`, fullPage: true });
}

test.describe('Evidence Section 14 - Inventaris (Petugas)', () => {
     let auth: AuthPage;
     test.setTimeout(150000);
     test.beforeEach(async ({ page }) => {
          await page.route('**/:5173/**', (route) => route.abort());
          await page.route(/.*:5173.*/, (route) => route.abort());
          auth = new AuthPage(page);
          await auth.loginAndWaitForDashboard('petugas@email.com', PW);
          await page.goto('/inventory', { waitUntil: 'domcontentloaded' });
          await expect(page.getByRole('heading', { name: /Monitoring Stok & Restock Supplier/i })).toBeVisible({ timeout: 20000 });
     });

     test('INV001 - Dashboard Inventaris menampilkan overview stok', async ({ page }) => {
          await cap(page, 'INV001_dashboard_overview.png');
     });
     test('INV001b - Empat kartu KPI tampil dengan nilai', async ({ page }) => {
          await expect(page.getByText('Total Item', { exact: true }).first()).toBeVisible();
          await expect(page.getByText('Avg. Sisa Hari', { exact: true }).first()).toBeVisible();
          await cap(page, 'INV001b_kartu_kpi.png');
     });
     test('INV002 - Bagian Rekomendasi Restock tampil', async ({ page }) => {
          await expect(page.getByRole('heading', { name: /^\s*Rekomendasi Restock\s*$/i })).toBeVisible();
          await cap(page, 'INV002_rekomendasi_restock.png');
     });
     test('INV003 - Tabel Stok Tersinkron menampilkan item & kolom', async ({ page }) => {
          await expect(page.getByRole('heading', { name: /^\s*Stok Tersinkron\s*$/i })).toBeVisible();
          await expect(page.locator('table tbody tr').first()).toBeVisible();
          await cap(page, 'INV003_tabel_stok.png');
     });
     test('INV004 - Menyaring tabel berdasarkan kategori', async ({ page }) => {
          const sel = page.locator('select[x-model="categoryFilter"]');
          if (await sel.count() > 0 && await sel.locator('option').count() > 1) await sel.selectOption({ index: 1 });
          await cap(page, 'INV004_filter_kategori.png');
     });
     test('INV005 - Menyaring tabel berdasarkan status', async ({ page }) => {
          const sel = page.locator('select[x-model="tableStatusFilter"]');
          if (await sel.count() > 0) await sel.selectOption('critical').catch(() => {});
          await cap(page, 'INV005_filter_status.png');
     });
     test('INV006 - Mencari item berdasarkan nama', async ({ page }) => {
          await page.getByPlaceholder('Cari item...').fill('Pakan');
          await page.waitForTimeout(400);
          await cap(page, 'INV006_pencarian_nama.png');
     });
     test('INV007 - Grafik tren pemakaian dengan tombol rentang', async ({ page }) => {
          const b = page.getByRole('button', { name: /^\s*5H\s*$/i }).first();
          if (await b.count() > 0) { await b.click(); await page.waitForTimeout(600); }
          await cap(page, 'INV007_grafik_tren_pemakaian.png');
     });
     test('INV008 - Grafik distribusi pemakaian per kandang + filter', async ({ page }) => {
          const sel = page.locator('select[x-model="usageFilter"]');
          if (await sel.count() > 0) await sel.selectOption('pakan').catch(() => {});
          await page.waitForTimeout(500);
          await cap(page, 'INV008_distribusi_pemakaian.png');
     });
     test('INV009 - Riwayat pergerakan/sinkronisasi stok tampil', async ({ page }) => {
          await expect(page.getByRole('heading', { name: /Riwayat Sinkronisasi Stok/i })).toBeVisible();
          await cap(page, 'INV009_riwayat_stok.png');
     });
     test('INV010 - Pencarian tanpa hasil (kata kunci tidak dikenal)', async ({ page }) => {
          await page.getByPlaceholder('Cari item...').fill('zzzznotfound');
          await page.waitForTimeout(400);
          await cap(page, 'INV010_pencarian_tanpa_hasil.png');
     });
     test('INV011 - Input karakter spesial pada pencarian tidak error', async ({ page }) => {
          await page.getByPlaceholder('Cari item...').fill('!#$%^&');
          await page.waitForTimeout(400);
          const body = await page.locator('body').textContent() || '';
          expect(body).not.toMatch(/Error 500|Fatal/i);
          await cap(page, 'INV011_karakter_spesial.png');
     });
     test('INV012 - Menghapus kata kunci mengembalikan semua data', async ({ page }) => {
          const s = page.getByPlaceholder('Cari item...');
          await s.fill('Pakan'); await page.waitForTimeout(300);
          await s.fill(''); await page.waitForTimeout(300);
          await cap(page, 'INV012_hapus_pencarian.png');
     });
     test('INV013 - Tombol Generate PO & Analysis (tidak tersedia di UI terbaru)', async ({ page }) => {
          const cnt = await page.getByRole('button', { name: /Generate PO|Analysis/i }).count();
          console.log('INV013_BTN::' + cnt);
          await cap(page, 'INV013_generate_po_analysis.png');
     });
     test('INV014 - Tombol Tambah Inventaris (input via mobile/API, web read-only)', async ({ page }) => {
          const cnt = await page.getByRole('button', { name: /Tambah Inventaris/i }).count();
          console.log('INV014_BTN::' + cnt);
          await cap(page, 'INV014_tambah_inventaris.png');
     });
     test('INV020 - Petugas: tombol restock supplier disembunyikan', async ({ page }) => {
          await expect(page.getByRole('link', { name: /Cari Barang Supplier/i })).toHaveCount(0);
          await expect(page.getByRole('button', { name: /^\s*Atur\s*$/i })).toHaveCount(0);
          await expect(page.getByRole('link', { name: /^\s*Cari Supplier\s*$/i })).toHaveCount(0);
          await cap(page, 'INV020_guard_petugas.png');
     });
});

test.describe('Evidence Section 14 - Inventaris (Penanggung Jawab)', () => {
     let auth: AuthPage;
     test.setTimeout(150000);
     test.beforeEach(async ({ page }) => {
          await page.route('**/:5173/**', (route) => route.abort());
          await page.route(/.*:5173.*/, (route) => route.abort());
          auth = new AuthPage(page);
          await auth.loginAndWaitForDashboard('pjawab@email.com', PW);
          await page.goto('/inventory', { waitUntil: 'domcontentloaded' });
          await expect(page.getByRole('heading', { name: /Monitoring Stok & Restock Supplier/i })).toBeVisible({ timeout: 20000 });
     });

     test('INV015 - Membuka jendela Detail Stok Tersinkron', async ({ page }) => {
          await page.getByRole('button', { name: /^\s*Detail\s*$/i }).first().click();
          await expect(page.getByRole('heading', { name: /Detail Stok Tersinkron/i })).toBeVisible({ timeout: 10000 });
          await shot(page, 'INV015_detail_stok.png');
     });
     test('INV016 - Mengatur batas pemesanan ulang (restock config)', async ({ page }) => {
          await page.getByRole('button', { name: /^\s*Atur\s*$/i }).first().click();
          await expect(page.getByRole('heading', { name: /Atur Batas Restock/i })).toBeVisible({ timeout: 10000 });
          await page.locator('input[name="lead_time_days"]').fill('7');
          await page.locator('input[name="safety_stock_days"]').fill('3');
          await Promise.all([
               page.waitForURL(/\/inventory(\?.*)?$/),
               page.getByRole('button', { name: /^\s*Simpan Batas\s*$/i }).click(),
          ]);
          await expect(page.getByText('Konfigurasi restock berhasil diperbarui.').first()).toBeVisible({ timeout: 15000 });
          await shot(page, 'INV016_atur_batas_restock.png');
     });
     test('INV017 - Halaman rekomendasi supplier restock', async ({ page }) => {
          await Promise.all([
               page.waitForURL(/\/inventory\/items\/.*\/supplier-recommendations/),
               page.getByRole('link', { name: /^\s*Cari Supplier\s*$/i }).first().click(),
          ]);
          await expect(page.getByRole('heading', { name: /Urutan Supplier yang Disarankan/i })).toBeVisible({ timeout: 15000 });
          await cap(page, 'INV017_rekomendasi_supplier.png');
     });
     test('INV018 - Beralih tampilan rekomendasi (Semua Stok / Stok Menipis)', async ({ page }) => {
          await page.getByRole('link', { name: /^\s*Semua Stok\s*$/i }).click();
          await expect(page).toHaveURL(/restock_view=all/);
          await cap(page, 'INV018_toggle_rekomendasi.png');
     });
     test('INV019 - Tambah produk rekomendasi ke keranjang (kondisional)', async ({ page }) => {
          await Promise.all([
               page.waitForURL(/\/inventory\/items\/.*\/supplier-recommendations/),
               page.getByRole('link', { name: /^\s*Cari Supplier\s*$/i }).first().click(),
          ]);
          const addBtn = page.getByRole('button', { name: /Tambah ke Keranjang/i }).first();
          console.log('INV019_ADD_BTN::' + await addBtn.count());
          await cap(page, 'INV019_tambah_keranjang.png');
     });
});
