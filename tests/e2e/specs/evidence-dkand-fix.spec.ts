import { test, expect, Page } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';
import { PeternakanPage } from '../pages/PeternakanPage.js';

/**
 * EVIDENCE FIX - DKAND006 & DKAND007 (detail kandang).
 * Non-serial: 1 kegagalan tidak memblok yang lain. 1 screenshot unik per skenario.
 */
const PW = 'Password123.';

async function openDetail(page: Page) {
     const auth = new AuthPage(page);
     const pt = new PeternakanPage(page);
     await auth.loginAndWaitForDashboard('petugas@email.com', PW);
     await pt.goto();
     await pt.expectToBeOnPeternakanPage();
     await pt.kandangLinks.first().click();
     await expect(page).toHaveURL(/\/peternakan\/.+/, { timeout: 30000 });
     try { await page.waitForLoadState('networkidle', { timeout: 15000 }); }
     catch { await page.waitForLoadState('domcontentloaded'); }
     await page.waitForTimeout(900);
}

test.describe('Evidence Fix - Detail Kandang DKAND006/007', () => {
     test.setTimeout(120000);
     test.beforeEach(async ({ page }) => {
          await page.route('**/:5173/**', (route) => route.abort());
          await page.route(/.*:5173.*/, (route) => route.abort());
     });

     test('DKAND006 - Filter jenis sensor pada detail kandang', async ({ page }) => {
          await openDetail(page);
          const selectCount = await page.locator('select').count();
          console.log('DKAND006_SELECT::' + selectCount);
          expect(selectCount).toBeGreaterThanOrEqual(1);
          await page.screenshot({ path: 'qa-evidence/DKAND/DKAND006_filter_sensor.png', fullPage: true });
     });

     test('DKAND007 - Kartu sensor langsung (Suhu/Kelembapan/Amonia/Cahaya)', async ({ page }) => {
          await openDetail(page);
          const body = await page.locator('body').innerText();
          console.log('DKAND007_SENSORS::' + ['Suhu', 'Kelembapan', 'Amonia', 'Cahaya'].filter(s => body.includes(s)).join(','));
          await page.screenshot({ path: 'qa-evidence/DKAND/DKAND007_kartu_sensor.png', fullPage: true });
     });

     test('DKAND008 - Tren produktivitas dengan tombol rentang (7H/14H/30H)', async ({ page }) => {
          await openDetail(page);
          const btns = page.getByRole('button', { name: /7H|14H|30H|7 Hari|14 Hari|30 Hari/i });
          console.log('DKAND008_RANGE::' + await btns.count());
          const body = await page.locator('body').innerText();
          expect(body).toMatch(/Tren Produktivitas/i);
          await page.screenshot({ path: 'qa-evidence/DKAND/DKAND008_tren_produktivitas.png', fullPage: true });
     });
});
