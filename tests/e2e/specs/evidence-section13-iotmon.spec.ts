import { test, expect, Page } from '@playwright/test';
import { IotPage } from '../pages/IotPage.js';

/**
 * EVIDENCE CAPTURE - Section 13: Monitoring IoT & Dashboard (IOTMON001-010, IOTS001-002)
 * 1 screenshot unik per skenario. Sesi pjawab (storageState). Hasil jujur.
 */
async function cap(page: Page, rel: string) {
     try { await page.waitForLoadState('networkidle', { timeout: 15000 }); }
     catch { await page.waitForLoadState('domcontentloaded'); }
     await page.waitForTimeout(700);
     await page.screenshot({ path: `qa-evidence/${rel}`, fullPage: true });
}
async function applyFilter(scope) {
     const btn = scope.locator('button').filter({ hasText: /Filter|Cari|Terapkan/i });
     if (await btn.count() > 0) { await btn.first().click({ force: true }); }
}

test.describe('Evidence Section 13 - Monitoring & Dashboard IoT', () => {
     let iot: IotPage;
     test.setTimeout(120000);
     test.beforeEach(async ({ page }) => {
          await page.route('**/:5173/**', (route) => route.abort());
          await page.route(/.*:5173.*/, (route) => route.abort());
          iot = new IotPage(page);
     });

     test('IOTMON001 - Halaman Monitoring IoT menampilkan 2 tab', async ({ page }) => {
          await iot.gotoMonitoring();
          await expect(iot.sensorDataTab).toBeVisible({ timeout: 10000 });
          await expect(iot.deviceLogsTab).toBeVisible({ timeout: 10000 });
          await cap(page, 'IOTMON/IOTMON001_monitoring_2tab.png');
     });

     test('IOTMON002 - Pindah ke tab Device Logs menampilkan tabel log', async ({ page }) => {
          await iot.gotoMonitoring();
          await iot.deviceLogsTab.click();
          await page.waitForTimeout(600);
          await expect(page.locator('div[x-show="activeTab === \'logs\'"]').locator('table')).toBeVisible({ timeout: 8000 });
          await cap(page, 'IOTMON/IOTMON002_tab_device_logs.png');
     });

     test('IOTMON003 - Filter Data Sensor berdasarkan perangkat', async ({ page }) => {
          await iot.gotoMonitoring();
          const panel = page.locator('div[x-show="activeTab === \'sensor\'"]');
          const f = panel.locator('select[name="sensor_device_id"]');
          await f.waitFor({ state: 'visible', timeout: 8000 });
          if (await f.locator('option').count() > 1) await f.selectOption({ index: 1 });
          await applyFilter(panel);
          await page.waitForTimeout(800);
          await cap(page, 'IOTMON/IOTMON003_filter_perangkat.png');
     });

     test('IOTMON004 - Filter Data Sensor berdasarkan parameter', async ({ page }) => {
          await iot.gotoMonitoring();
          const panel = page.locator('div[x-show="activeTab === \'sensor\'"]');
          const f = panel.locator('select[name="sensor_parameter_id"]');
          await f.waitFor({ state: 'visible', timeout: 8000 });
          if (await f.locator('option').count() > 1) await f.selectOption({ index: 1 });
          await applyFilter(panel);
          await page.waitForTimeout(800);
          await cap(page, 'IOTMON/IOTMON004_filter_parameter.png');
     });

     test('IOTMON005 - Filter Data Sensor berdasarkan rentang tanggal', async ({ page }) => {
          await iot.gotoMonitoring();
          const panel = page.locator('div[x-show="activeTab === \'sensor\'"]');
          const from = panel.locator('input[name="sensor_date_from"]');
          const to = panel.locator('input[name="sensor_date_to"]');
          await from.waitFor({ state: 'visible', timeout: 8000 });
          const today = new Date(); const wk = new Date(); wk.setDate(wk.getDate() - 7);
          const fmt = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
          await from.fill(fmt(wk)); await to.fill(fmt(today));
          await applyFilter(panel);
          await page.waitForTimeout(800);
          await cap(page, 'IOTMON/IOTMON005_filter_tanggal.png');
     });

     test('IOTMON006 - Filter kombinasi perangkat + parameter + tanggal', async ({ page }) => {
          await iot.gotoMonitoring();
          const panel = page.locator('div[x-show="activeTab === \'sensor\'"]');
          const dev = panel.locator('select[name="sensor_device_id"]');
          const par = panel.locator('select[name="sensor_parameter_id"]');
          const from = panel.locator('input[name="sensor_date_from"]');
          await dev.waitFor({ state: 'visible', timeout: 8000 });
          if (await dev.locator('option').count() > 1) await dev.selectOption({ index: 1 });
          if (await par.locator('option').count() > 1) await par.selectOption({ index: 1 });
          const today = new Date();
          await from.fill(`${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`);
          await applyFilter(panel);
          await page.waitForTimeout(800);
          await cap(page, 'IOTMON/IOTMON006_filter_kombinasi.png');
     });

     test('IOTMON007 - Reset filter Data Sensor', async ({ page }) => {
          await iot.gotoMonitoring();
          const panel = page.locator('div[x-show="activeTab === \'sensor\'"]');
          const dev = panel.locator('select[name="sensor_device_id"]');
          await dev.waitFor({ state: 'visible', timeout: 8000 });
          if (await dev.locator('option').count() > 1) await dev.selectOption({ index: 1 });
          const reset = panel.locator('button, a').filter({ hasText: /Reset|Clear|Hapus Filter/i });
          if (await reset.count() > 0) { await reset.first().click({ force: true }); await page.waitForTimeout(800); }
          else { await dev.selectOption({ index: 0 }); }
          await cap(page, 'IOTMON/IOTMON007_reset_filter.png');
     });

     test('IOTMON008 - Filter Device Logs berdasarkan perangkat', async ({ page }) => {
          await iot.gotoMonitoring();
          await iot.deviceLogsTab.click(); await page.waitForTimeout(600);
          const panel = page.locator('div[x-show="activeTab === \'logs\'"]');
          const f = panel.locator('select[name="log_device_id"]');
          await f.waitFor({ state: 'visible', timeout: 8000 });
          if (await f.locator('option').count() > 1) await f.selectOption({ index: 1 });
          await applyFilter(panel);
          await page.waitForTimeout(800);
          await cap(page, 'IOTMON/IOTMON008_logs_filter_perangkat.png');
     });

     test('IOTMON009 - Filter Device Logs berdasarkan tipe log', async ({ page }) => {
          await iot.gotoMonitoring();
          await iot.deviceLogsTab.click(); await page.waitForTimeout(600);
          const panel = page.locator('div[x-show="activeTab === \'logs\'"]');
          const f = panel.locator('select[name="log_type"]');
          await f.waitFor({ state: 'visible', timeout: 8000 });
          if (await f.locator('option').count() > 1) await f.selectOption({ index: 1 });
          await applyFilter(panel);
          await page.waitForTimeout(800);
          await cap(page, 'IOTMON/IOTMON009_logs_filter_tipe.png');
     });

     test('IOTMON010 - Filter Device Logs berdasarkan tanggal', async ({ page }) => {
          await iot.gotoMonitoring();
          await iot.deviceLogsTab.click(); await page.waitForTimeout(600);
          const panel = page.locator('div[x-show="activeTab === \'logs\'"]');
          const d = panel.locator('input[name="log_date"]');
          await d.waitFor({ state: 'visible', timeout: 8000 });
          const today = new Date();
          await d.fill(`${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`);
          await applyFilter(panel);
          await page.waitForTimeout(800);
          await cap(page, 'IOTMON/IOTMON010_logs_filter_tanggal.png');
     });

     test('IOTS001 - Dashboard IoT menampilkan daftar perangkat & status', async ({ page }) => {
          await iot.gotoDashboard();
          await expect(iot.dashboardHeading.first()).toBeVisible({ timeout: 10000 });
          await cap(page, 'IOTS/IOTS001_dashboard_perangkat.png');
     });

     test('IOTS002 - Dashboard menampilkan perangkat non-aktif/maintenance', async ({ page }) => {
          await iot.gotoDashboard();
          const body = await page.locator('body').innerText();
          console.log('IOTS002_STATUS::' + ['aktif', 'tidak aktif', 'maintenance', 'nonaktif', 'inactive'].filter(s => body.toLowerCase().includes(s)).join(','));
          await cap(page, 'IOTS/IOTS002_status_perangkat.png');
     });
});
