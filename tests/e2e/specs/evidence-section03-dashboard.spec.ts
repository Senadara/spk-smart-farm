import { test, expect, Page } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';
import { PeternakanPage } from '../pages/PeternakanPage.js';

/**
 * EVIDENCE CAPTURE - Section 3: Dashboard Home & Dashboard Peternakan
 * 1 screenshot unik per skenario → qa-evidence/DASH/<TEST-ID>_<judul>.png
 * Divalidasi terhadap UI TERBARU (opsi 1). Fitur SPK (evaluasi/narasi/engine)
 * kini berada di menu "Analisa SPK" (/spk-analysis), bukan lagi di /peternakan.
 * Hasil jujur sesuai perilaku nyata.
 */

const GROUP = 'DASH';
const PW = 'Password123.';

test.describe.configure({ mode: 'serial' });

async function stable(page: Page, file: string) {
     try {
          await page.waitForLoadState('networkidle', { timeout: 15000 });
     } catch {
          await page.waitForLoadState('domcontentloaded');
     }
     await page.waitForTimeout(800);
     await page.screenshot({ path: `qa-evidence/${GROUP}/${file}`, fullPage: true });
}
async function shot(page: Page, file: string) {
     await page.waitForTimeout(250);
     await page.screenshot({ path: `qa-evidence/${GROUP}/${file}`, fullPage: true });
}

test.describe('Evidence Section 3 - Dashboard (role Petugas)', () => {
     let auth: AuthPage;
     let peternakan: PeternakanPage;
     test.setTimeout(150000);

     test.beforeEach(async ({ page }) => {
          await page.route('**/:5173/**', (route) => route.abort());
          await page.route(/.*:5173.*/, (route) => route.abort());
          auth = new AuthPage(page);
          peternakan = new PeternakanPage(page);
          await auth.loginAndWaitForDashboard('petugas@email.com', PW);
     });

     test('DASH001 - Halaman Home (Dashboard) tampil', async ({ page }) => {
          await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
          await expect(page.getByText('Produktivitas Farm Hari Ini').first()).toBeVisible();
          await stable(page, 'DASH001_home_dashboard.png');
     });

     test('PTDASH001 - Halaman Peternakan tampil lengkap', async ({ page }) => {
          await peternakan.goto();
          await peternakan.expectToBeOnPeternakanPage();
          await stable(page, 'PTDASH001_dashboard_peternakan.png');
     });

     test('PTDASH002 - Ganti filter komoditas mengubah data', async ({ page }) => {
          await peternakan.goto();
          await peternakan.expectToBeOnPeternakanPage();
          const sel = peternakan.komoditasSelect;
          const opts = await sel.locator('option').count();
          if (opts > 1) {
               await sel.selectOption({ index: 1 });
               await page.waitForLoadState('domcontentloaded');
          }
          console.log('PTDASH002_URL::' + page.url());
          await stable(page, 'PTDASH002_filter_komoditas.png');
     });

     test('PTDASH003 - Menjalankan Evaluasi Penuh (Analisa SPK)', async ({ page }) => {
          await page.goto('/spk-analysis', { waitUntil: 'domcontentloaded' });
          const btn = page.getByRole('button', { name: /Run Full Evaluation/i });
          await expect(btn).toBeVisible({ timeout: 15000 });
          await btn.click();
          await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => { });
          await page.waitForTimeout(1500);
          await shot(page, 'PTDASH003_evaluasi_penuh.png');
     });

     test('PTDASH004 - Hasil evaluasi SPK menampilkan narasi', async ({ page }) => {
          await page.goto('/spk-analysis', { waitUntil: 'domcontentloaded' });
          await expect(page.getByRole('heading', { name: /Riwayat Analisa SPK/i })).toBeVisible({ timeout: 15000 });
          await expect(page.locator('body')).toContainText(/Perlu Monitoring|kondisi|Parameter lingkungan/i);
          await stable(page, 'PTDASH004_narasi_spk.png');
     });

     test('PTDASH005 - Halaman Analisa SPK tetap tampil tanpa rekomendasi aktif', async ({ page }) => {
          await page.goto('/spk-analysis', { waitUntil: 'domcontentloaded' });
          await expect(page.locator('h1').filter({ hasText: /Pusat Analisis|Analisa SPK/i })).toBeVisible();
          const body = await page.locator('body').textContent() || '';
          expect(body).not.toMatch(/Error 500|Fatal/i);
          await stable(page, 'PTDASH005_explainability.png');
     });

     test('PTDASH004b - Mengubah rentang grafik tren', async ({ page }) => {
          await peternakan.goto();
          await peternakan.expectToBeOnPeternakanPage();
          const rangeBtns = page.getByRole('button', { name: /30 Hari|90 Hari|YTD|7 Hari/i });
          const cnt = await rangeBtns.count();
          console.log('PTDASH004b_RANGE_BTN_COUNT::' + cnt);
          if (cnt > 0) {
               await rangeBtns.first().click();
               await page.waitForTimeout(800);
          }
          await stable(page, 'PTDASH004b_rentang_grafik.png');
     });

     test('PTDASH005b - Komoditas tidak dikenal (empty state) tidak error', async ({ page }) => {
          await peternakan.gotoWithInsahKomoditas();
          const body = await page.locator('body').textContent() || '';
          expect(body).not.toMatch(/Error 500|Fatal|SQLSTATE/i);
          console.log('PTDASH005b_HAS_NOKOM::' + /Belum terhubung Data Master|tidak ditemukan|Belum ada/i.test(body));
          await stable(page, 'PTDASH005b_empty_komoditas.png');
     });

     test('PTDASH006 - Grafik Production Efficiency Trends tampil', async ({ page }) => {
          await peternakan.goto();
          await peternakan.expectToBeOnPeternakanPage();
          await expect(page.getByRole('heading', { name: /Production Efficiency Trends/i })).toBeVisible({ timeout: 15000 });
          await stable(page, 'PTDASH006_efficiency_trends.png');
     });

     test('PTDASH008 - Verdict "Lihat Laporan Lengkap"', async ({ page }) => {
          await page.goto('/spk-analysis', { waitUntil: 'domcontentloaded' });
          const laporan = page.getByRole('link', { name: /Lihat Laporan Lengkap/i })
               .or(page.getByRole('button', { name: /Lihat Laporan Lengkap/i }));
          const cnt = await laporan.count();
          console.log('PTDASH008_LAPORAN_COUNT::' + cnt);
          await stable(page, 'PTDASH008_laporan_lengkap.png');
     });

     test('PTDASH009 - Tombol Evaluasi menampilkan status memproses', async ({ page }) => {
          await page.goto('/spk-analysis', { waitUntil: 'domcontentloaded' });
          const btn = page.getByRole('button', { name: /Run Full Evaluation/i });
          await expect(btn).toBeVisible({ timeout: 15000 });
          await btn.click();
          await shot(page, 'PTDASH009_evaluasi_memproses.png');
     });

     test('PTDASH010 - Klik kartu kandang menuju detail kandang', async ({ page }) => {
          await peternakan.goto();
          await peternakan.expectToBeOnPeternakanPage();
          const card = peternakan.kandangLinks.first();
          await expect(card).toBeVisible({ timeout: 15000 });
          await card.click();
          await expect(page).toHaveURL(/\/peternakan\/.+/);
          await stable(page, 'PTDASH010_detail_kandang.png');
     });

     test('PTDASH011 - Daily Production Log tampil', async ({ page }) => {
          await peternakan.goto();
          await peternakan.expectToBeOnPeternakanPage();
          await expect(page.getByRole('heading', { name: /Daily Production Log/i })).toBeVisible({ timeout: 15000 });
          await stable(page, 'PTDASH011_production_log.png');
     });

     test('PTDASH012 - Daftar kandang kosong pada komoditas tanpa kandang', async ({ page }) => {
          await peternakan.gotoWithInsahKomoditas();
          const body = await page.locator('body').textContent() || '';
          console.log('PTDASH012_KANDANG_KOSONG::' + /Belum ada kandang|Belum terhubung Data Master|tidak ditemukan/i.test(body));
          await stable(page, 'PTDASH012_kandang_kosong.png');
     });

     test('PTDASH013 - KPI menampilkan kondisi tanpa data', async ({ page }) => {
          await peternakan.gotoWithInsahKomoditas();
          const body = await page.locator('body').textContent() || '';
          expect(body).not.toMatch(/Error 500|Fatal/i);
          console.log('PTDASH013_HAS_NODATA::' + /No data|Belum|0/i.test(body));
          await stable(page, 'PTDASH013_kpi_no_data.png');
     });

     test('PTDASH014 - Evaluasi tetap berjalan meski input sensor minim', async ({ page }) => {
          await page.goto('/spk-analysis', { waitUntil: 'domcontentloaded' });
          const btn = page.getByRole('button', { name: /Run Full Evaluation/i });
          await expect(btn).toBeVisible({ timeout: 15000 });
          await btn.click();
          await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => { });
          await page.waitForTimeout(1500);
          const body = await page.locator('body').textContent() || '';
          expect(body).not.toMatch(/Error 500|Fatal/i);
          await shot(page, 'PTDASH014_evaluasi_input_minim.png');
     });
});

test.describe('Evidence Section 3 - Pengaturan Fuzzy (role Penanggung Jawab)', () => {
     test.setTimeout(120000);
     test.beforeEach(async ({ page }) => {
          await page.route('**/:5173/**', (route) => route.abort());
          await page.route(/.*:5173.*/, (route) => route.abort());
          const auth = new AuthPage(page);
          await auth.loginAndWaitForDashboard('pjawab@email.com', PW);
     });

     test('PTDASH007 - Akses halaman Pengaturan Fuzzy', async ({ page }) => {
          await page.goto('/spk-analysis', { waitUntil: 'domcontentloaded' });
          const gear = page.locator('a[href*="settings/fuzzy"]').first();
          const cnt = await gear.count();
          console.log('PTDASH007_GEAR_COUNT::' + cnt);
          if (cnt > 0) {
               await gear.click();
               await page.waitForLoadState('domcontentloaded');
          } else {
               await page.goto('/settings/fuzzy', { waitUntil: 'domcontentloaded' });
          }
          console.log('PTDASH007_URL::' + page.url());
          try {
               await page.waitForLoadState('networkidle', { timeout: 15000 });
          } catch { /* ignore */ }
          await page.waitForTimeout(800);
          await page.screenshot({ path: `qa-evidence/${GROUP}/PTDASH007_pengaturan_fuzzy.png`, fullPage: true });
     });
});
