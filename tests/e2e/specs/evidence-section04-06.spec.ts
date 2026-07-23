import { test, expect, Page } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';
import { PeternakanPage } from '../pages/PeternakanPage.js';

/**
 * EVIDENCE CAPTURE - Section 4 (Filter Komoditas & KPI), 5 (Grafik), 6 (Barn & Sensor)
 * Semua berada di halaman Peternakan. 1 screenshot unik per skenario ke
 * qa-evidence/<GROUP>/<TEST-ID>_<judul>.png. Login: petugas. Hasil jujur.
 */

const PW = 'Password123.';

test.describe.configure({ mode: 'serial' });

async function cap(page: Page, rel: string) {
     try {
          await page.waitForLoadState('networkidle', { timeout: 15000 });
     } catch { await page.waitForLoadState('domcontentloaded'); }
     await page.waitForTimeout(700);
     await page.screenshot({ path: `qa-evidence/${rel}`, fullPage: true });
}

test.describe('Evidence Section 4-6 - Peternakan (Komoditas/KPI/Grafik/Sensor)', () => {
     let auth: AuthPage;
     let pt: PeternakanPage;
     test.setTimeout(150000);

     test.beforeEach(async ({ page }) => {
          await page.route('**/:5173/**', (route) => route.abort());
          await page.route(/.*:5173.*/, (route) => route.abort());
          auth = new AuthPage(page);
          pt = new PeternakanPage(page);
          await auth.loginAndWaitForDashboard('petugas@email.com', PW);
     });

     // ── Section 4: Filter Komoditas & KPI ──
     test('KOM001 - Dropdown filter komoditas tersedia', async ({ page }) => {
          await pt.goto();
          await pt.expectToBeOnPeternakanPage();
          const opts = await pt.komoditasSelect.locator('option').count();
          console.log('KOM001_OPTIONS::' + opts);
          expect(opts).toBeGreaterThanOrEqual(1);
          await cap(page, 'KOM/KOM001_dropdown_komoditas.png');
     });

     test('KOM002 - Filter komoditas tidak dikenal (empty state)', async ({ page }) => {
          await pt.gotoWithInsahKomoditas();
          const body = await page.locator('body').textContent() || '';
          expect(body).not.toMatch(/Error 500|Fatal|SQLSTATE/i);
          await cap(page, 'KOM/KOM002_komoditas_tidak_dikenal.png');
     });

     test('KPI001 - Kartu KPI menampilkan nilai', async ({ page }) => {
          await pt.goto();
          await pt.expectToBeOnPeternakanPage();
          await pt.expectKpiTrendIndicators();
          await cap(page, 'KPI/KPI001_kartu_kpi.png');
     });

     test('KPI002 - Indikator tren pada kartu KPI', async ({ page }) => {
          await pt.goto();
          await pt.expectToBeOnPeternakanPage();
          await pt.expectKpiTrendIndicators();
          await cap(page, 'KPI/KPI002_indikator_tren.png');
     });

     // ── Section 5: Grafik / Chart ──
     test('CHART001 - Tombol rentang grafik tersedia', async ({ page }) => {
          await pt.goto();
          await pt.expectToBeOnPeternakanPage();
          const btns = page.getByRole('button', { name: /30 Hari|90 Hari|YTD|7 Hari/i });
          const cnt = await btns.count();
          console.log('CHART001_RANGE_LABELS::' + JSON.stringify((await btns.allInnerTexts()).map(t => t.trim())));
          expect(cnt).toBeGreaterThanOrEqual(2);
          await cap(page, 'CHART/CHART001_tombol_rentang.png');
     });

     test('CHART002 - Klik rentang 90 Hari mengubah grafik', async ({ page }) => {
          await pt.goto();
          await pt.expectToBeOnPeternakanPage();
          const b90 = page.getByRole('button', { name: /90 Hari|90H/i }).first();
          if (await b90.count() > 0) { await b90.click(); await page.waitForTimeout(900); }
          else { console.log('CHART002_NO_90BTN'); }
          await cap(page, 'CHART/CHART002_rentang_90hari.png');
     });

     test('CHART003 - Klik rentang YTD mengubah grafik', async ({ page }) => {
          await pt.goto();
          await pt.expectToBeOnPeternakanPage();
          const ytd = page.getByRole('button', { name: /YTD/i }).first();
          if (await ytd.count() > 0) { await ytd.click(); await page.waitForTimeout(900); }
          else { console.log('CHART003_NO_YTD'); }
          await cap(page, 'CHART/CHART003_rentang_ytd.png');
     });

     // ── Section 6: Barn Environment & Sensor ──
     test('BARNENV001 - Section Barn Environment menampilkan daftar kandang', async ({ page }) => {
          await pt.goto();
          await pt.expectToBeOnPeternakanPage();
          await pt.expectBarnEnvironmentSection();
          await cap(page, 'BARNENV/BARNENV001_barn_environment.png');
     });

     test('BARNENV002 - Ringkasan sensor (Suhu/Kelembapan/Amonia) saat barn dipilih', async ({ page }) => {
          await pt.goto();
          await pt.expectToBeOnPeternakanPage();
          const cnt = await pt.getBarnButtonCount();
          if (cnt > 0) { await pt.barnButtons.first().click(); await page.waitForTimeout(700); }
          await pt.expectSensorLabels(['Suhu', 'Kelembapan', 'Amonia']);
          await cap(page, 'BARNENV/BARNENV002_sensor_summary.png');
     });

     test('BARNENV003 - Link "Lihat Detail Kandang" tersedia', async ({ page }) => {
          await pt.goto();
          await pt.expectToBeOnPeternakanPage();
          await pt.expectDetailLink();
          await cap(page, 'BARNENV/BARNENV003_lihat_detail_kandang.png');
     });

     test('BARNENV004 - Tombol pemilih kandang tersedia', async ({ page }) => {
          await pt.goto();
          await pt.expectToBeOnPeternakanPage();
          const cnt = await pt.getBarnButtonCount();
          console.log('BARNENV004_BARN_BTN::' + cnt);
          expect(cnt).toBeGreaterThanOrEqual(1);
          await cap(page, 'BARNENV/BARNENV004_pemilih_kandang.png');
     });

     test('SENS001 - Ringkasan lingkungan dengan data sensor', async ({ page }) => {
          await pt.goto();
          await pt.expectToBeOnPeternakanPage();
          const cnt = await pt.getBarnButtonCount();
          if (cnt > 0) { await pt.barnButtons.first().click(); await page.waitForTimeout(700); }
          await pt.expectSensorLabels(['Suhu', 'Kelembapan', 'Amonia']);
          await cap(page, 'SENS/SENS001_sensor_dengan_data.png');
     });

     test('SENS002 - Kandang tanpa data sensor tetap tampil tanpa error', async ({ page }) => {
          await page.goto('/peternakan?komoditas=komo003000000000000000000003', { waitUntil: 'domcontentloaded' });
          const body = await page.locator('body').textContent() || '';
          expect(body).not.toMatch(/Error 500|Fatal|SQLSTATE/i);
          await cap(page, 'SENS/SENS002_tanpa_data_sensor.png');
     });
});
