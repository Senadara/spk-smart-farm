import { test, expect, Page } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';
import { PeternakanPage } from '../pages/PeternakanPage.js';

/**
 * EVIDENCE CAPTURE - Section 7 (Fuzzy Engine & SPK Lingkungan), 8 (Daily Production Log &
 * Produktivitas), 9 (Daftar Kandang & Detail Kandang). 1 screenshot unik per skenario.
 * Opsi 1: Fuzzy Decision Engine + evaluasi kini di menu Analisa SPK. Login: petugas.
 */

const PW = 'Password123.';
test.describe.configure({ mode: 'serial' });

async function cap(page: Page, rel: string) {
     try { await page.waitForLoadState('networkidle', { timeout: 15000 }); }
     catch { await page.waitForLoadState('domcontentloaded'); }
     await page.waitForTimeout(700);
     await page.screenshot({ path: `qa-evidence/${rel}`, fullPage: true });
}
async function openDetail(page: Page, pt: PeternakanPage) {
     await pt.goto();
     await pt.expectToBeOnPeternakanPage();
     await pt.kandangLinks.first().click();
     await expect(page).toHaveURL(/\/peternakan\/.+/);
}

test.describe('Evidence Section 7-9 - Fuzzy/Produksi/Kandang', () => {
     let auth: AuthPage;
     let pt: PeternakanPage;
     test.setTimeout(160000);

     test.beforeEach(async ({ page }) => {
          await page.route('**/:5173/**', (route) => route.abort());
          await page.route(/.*:5173.*/, (route) => route.abort());
          auth = new AuthPage(page);
          pt = new PeternakanPage(page);
          await auth.loginAndWaitForDashboard('petugas@email.com', PW);
     });

     // ── Section 7: Fuzzy Decision Engine & SPK Lingkungan (di Analisa SPK) ──
     test('FUZZY001 - Panel Fuzzy Decision Engine tampil', async ({ page }) => {
          await page.goto('/spk-analysis', { waitUntil: 'domcontentloaded' });
          await expect(page.getByRole('heading', { name: /Fuzzy Decision Engine/i })).toBeVisible({ timeout: 15000 });
          await cap(page, 'FUZZY/FUZZY001_panel_fuzzy_engine.png');
     });

     test('FUZZY002 - Filter kandang pada Fuzzy Engine tersedia', async ({ page }) => {
          await page.goto('/spk-analysis', { waitUntil: 'domcontentloaded' });
          const selects = page.locator('select');
          const cnt = await selects.count();
          console.log('FUZZY002_SELECT_COUNT::' + cnt);
          expect(cnt).toBeGreaterThanOrEqual(1);
          await cap(page, 'FUZZY/FUZZY002_filter_kandang.png');
     });

     test('FUZZY003 - Tombol "Run Full Evaluation" tersedia', async ({ page }) => {
          await page.goto('/spk-analysis', { waitUntil: 'domcontentloaded' });
          await expect(page.getByRole('button', { name: /Run Full Evaluation/i })).toBeVisible({ timeout: 15000 });
          await cap(page, 'FUZZY/FUZZY003_tombol_evaluasi.png');
     });

     test('SPKL001 - Evaluasi SPK Lingkungan menghasilkan status', async ({ page }) => {
          await page.goto('/spk-analysis', { waitUntil: 'domcontentloaded' });
          await page.getByRole('button', { name: /Run Full Evaluation/i }).click();
          await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
          await page.waitForTimeout(1500);
          const body = await page.locator('body').innerText();
          console.log('SPKL001_STATUS_OPTIMAL::' + /Optimal/i.test(body));
          console.log('SPKL001_STATUS_WASPADA::' + /Waspada|Buruk/i.test(body));
          console.log('SPKL001_STATUS_MONITORING::' + /Perlu Monitoring/i.test(body));
          await cap(page, 'SPKL/SPKL001_status_lingkungan.png');
     });

     // ── Section 8: Daily Production Log & Produktivitas ──
     test('LOG001 - Daily Production Log menampilkan tabel', async ({ page }) => {
          await pt.goto();
          await pt.expectToBeOnPeternakanPage();
          await expect(page.getByRole('heading', { name: /Daily Production Log/i })).toBeVisible({ timeout: 15000 });
          await cap(page, 'LOG/LOG001_production_log_tabel.png');
     });

     test('LOG002 - Pencarian log memfilter tabel', async ({ page }) => {
          await pt.goto();
          await pt.expectToBeOnPeternakanPage();
          await pt.searchProductionLog('test');
          await cap(page, 'LOG/LOG002_cari_log.png');
     });

     test('LOG003 - Pencarian log tanpa hasil tidak error', async ({ page }) => {
          await pt.goto();
          await pt.expectToBeOnPeternakanPage();
          await pt.searchProductionLog('ZZZZNODATA');
          const body = await page.locator('body').textContent() || '';
          expect(body).not.toMatch(/Error 500|Fatal/i);
          await cap(page, 'LOG/LOG003_cari_tanpa_hasil.png');
     });

     test('PROD001 - Ringkasan produktivitas kandang dengan data', async ({ page }) => {
          await openDetail(page, pt);
          const body = await page.locator('body').innerText();
          console.log('PROD001_HAS_HDP::' + /HDP/i.test(body) + ' FCR::' + /FCR/i.test(body));
          await cap(page, 'PROD/PROD001_ringkasan_produktivitas.png');
     });

     test('PROD002 - Ringkasan produktivitas kandang tanpa data', async ({ page }) => {
          await page.goto('/peternakan?komoditas=komo003000000000000000000003', { waitUntil: 'domcontentloaded' });
          const body = await page.locator('body').textContent() || '';
          expect(body).not.toMatch(/Error 500|Fatal|SQLSTATE/i);
          await cap(page, 'PROD/PROD002_tanpa_data_produksi.png');
     });

     test('SPKP001 - Evaluasi SPK Produktivitas menghasilkan status', async ({ page }) => {
          await page.goto('/spk-analysis', { waitUntil: 'domcontentloaded' });
          await page.getByRole('button', { name: /Run Full Evaluation/i }).click();
          await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
          await page.waitForTimeout(1500);
          const body = await page.locator('body').innerText();
          console.log('SPKP001_HAS_PRODUKTIVITAS::' + /Produktivitas|HDP|FCR/i.test(body));
          await cap(page, 'SPKP/SPKP001_status_produktivitas.png');
     });

     // ── Section 9: Daftar Kandang & Detail Kandang ──
     test('KANDANG001 - Section Daftar Kandang tampil', async ({ page }) => {
          await pt.goto();
          await pt.expectToBeOnPeternakanPage();
          await expect(page.getByRole('heading', { name: /Daftar Kandang/i })).toBeVisible({ timeout: 15000 });
          await cap(page, 'KANDANG/KANDANG001_daftar_kandang.png');
     });

     test('KANDANG002 - Kartu kandang menampilkan status', async ({ page }) => {
          await pt.goto();
          await pt.expectToBeOnPeternakanPage();
          await expect(pt.kandangLinks.first()).toBeVisible({ timeout: 15000 });
          await cap(page, 'KANDANG/KANDANG002_kartu_status.png');
     });

     test('KANDANG003 - Klik kartu kandang membuka detail', async ({ page }) => {
          await openDetail(page, pt);
          await cap(page, 'KANDANG/KANDANG003_buka_detail.png');
     });

     test('DKAND001 - Detail kandang menampilkan header (probe)', async ({ page }) => {
          await openDetail(page, pt);
          const headings = await page.locator('h1, h2, h3').allInnerTexts();
          console.log('DKAND_HEADINGS::' + JSON.stringify(headings));
          const buttons = await page.getByRole('button').allInnerTexts();
          console.log('DKAND_BUTTONS::' + JSON.stringify(buttons.map(b => b.trim()).filter(Boolean)));
          const selectCount = await page.locator('select').count();
          console.log('DKAND_SELECT_COUNT::' + selectCount);
          const body = await page.locator('body').innerText();
          console.log('DKAND_HAS_KEMBALI::' + /Kembali/i.test(body));
          console.log('DKAND_HAS_6J::' + /6J|6 Jam/i.test(body));
          console.log('DKAND_SENSORS::' + ['Suhu','Kelembapan','Amonia','Cahaya'].filter(s => body.includes(s)).join(','));
          await cap(page, 'DKAND/DKAND001_header_detail.png');
     });

     test('DKAND002 - Ringkasan info kandang (overview)', async ({ page }) => {
          await openDetail(page, pt);
          const body = await page.locator('body').innerText();
          console.log('DKAND002_OVERVIEW::' + ['Lokasi','Breed','Populasi','Kapasitas','Umur'].filter(s => body.includes(s)).join(','));
          await cap(page, 'DKAND/DKAND002_overview.png');
     });

     test('DKAND003 - Kartu KPI pada detail kandang', async ({ page }) => {
          await openDetail(page, pt);
          const body = await page.locator('body').innerText();
          console.log('DKAND003_KPI::' + ['HDP','HHEP','FCR','Mortalitas','Afkir'].filter(s => body.includes(s)).join(','));
          await cap(page, 'DKAND/DKAND003_kpi_detail.png');
     });

     test('DKAND004 - Tombol Kembali menuju halaman Peternakan', async ({ page }) => {
          await openDetail(page, pt);
          const back = page.getByRole('link', { name: /Kembali/i }).or(page.getByRole('button', { name: /Kembali/i })).first();
          if (await back.count() > 0) {
               await back.click();
               await page.waitForTimeout(800);
          }
          console.log('DKAND004_URL::' + page.url());
          await cap(page, 'DKAND/DKAND004_tombol_kembali.png');
     });

     test('DKAND005 - Tombol rentang grafik sensor (6J/12J/24J)', async ({ page }) => {
          await openDetail(page, pt);
          const btns = page.getByRole('button', { name: /6J|12J|24J|6 Jam|12 Jam|24 Jam/i });
          const cnt = await btns.count();
          console.log('DKAND005_RANGE::' + cnt);
          await cap(page, 'DKAND/DKAND005_rentang_sensor.png');
     });

     test('DKAND006 - Filter jenis sensor pada detail kandang', async ({ page }) => {
          await openDetail(page, pt);
          const selectCount = await page.locator('select').count();
          console.log('DKAND006_SELECT::' + selectCount);
          await cap(page, 'DKAND/DKAND006_filter_sensor.png');
     });

     test('DKAND007 - Kartu sensor langsung (Suhu/Kelembapan/Amonia/Cahaya)', async ({ page }) => {
          await openDetail(page, pt);
          const body = await page.locator('body').innerText();
          console.log('DKAND007_SENSORS::' + ['Suhu','Kelembapan','Amonia','Cahaya'].filter(s => body.includes(s)).join(','));
          await cap(page, 'DKAND/DKAND007_kartu_sensor.png');
     });
});
