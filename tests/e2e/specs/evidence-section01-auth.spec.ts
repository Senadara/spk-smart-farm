import { test, expect, Page } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';

/**
 * EVIDENCE CAPTURE - Section 1: Autentikasi & Sesi (AUTH001-AUTH007)
 * Tiap skenario menghasilkan TEPAT 1 screenshot unik:
 *   qa-evidence/AUTH/<TEST-ID>_<judul>.png
 * Screenshot diambil setelah UI selesai dimuat (render sempurna).
 * Hasil validasi jujur sesuai perilaku nyata aplikasi.
 */

const GROUP = 'AUTH';
const PW = 'Password123.';

test.use({ storageState: { cookies: [], origins: [] } });

test.describe.configure({ mode: 'serial' });

// Tunggu halaman benar-benar selesai dimuat lalu ambil 1 screenshot penuh.
async function capture(page: Page, fileName: string) {
     try {
          await page.waitForLoadState('networkidle', { timeout: 15000 });
     } catch {
          await page.waitForLoadState('domcontentloaded');
     }
     await page.waitForTimeout(600); // beri jeda agar animasi/gaya tuntas
     await page.screenshot({ path: `qa-evidence/${GROUP}/${fileName}`, fullPage: true });
}

test.describe('Evidence Section 1 - Autentikasi & Sesi', () => {
     test.setTimeout(120000);

     test.beforeEach(async ({ page }) => {
          await page.route('**/:5173/**', (route) => route.abort());
          await page.route(/.*:5173.*/, (route) => route.abort());
     });

     test('AUTH001 - Login dengan kredensial valid', async ({ page }) => {
          const auth = new AuthPage(page);
          await auth.gotoLogin();
          await auth.login('petugas@email.com', PW);
          await expect(page).toHaveURL(/.*dashboard/, { timeout: 80000 });
          await expect(page.locator('body')).toContainText(/Selamat datang|Selamat Datang/i);
          await capture(page, 'AUTH001_login_valid.png');
     });

     test('AUTH002 - Login dengan password salah', async ({ page }) => {
          const auth = new AuthPage(page);
          await auth.gotoLogin();
          await auth.login('petugas@email.com', 'SalahPassword123!');
          await auth.expectErrorMessageToBeVisible();
          await expect(page).toHaveURL(/.*login/);
          await capture(page, 'AUTH002_login_password_salah.png');
     });

     test('AUTH003 - Login dengan email tidak terdaftar', async ({ page }) => {
          const auth = new AuthPage(page);
          await auth.gotoLogin();
          await auth.login('tidakada@email.com', PW);
          await auth.expectErrorMessageToBeVisible();
          await expect(page).toHaveURL(/.*login/);
          await capture(page, 'AUTH003_login_email_tidak_terdaftar.png');
     });

     test('AUTH004 - Login dengan field kosong', async ({ page }) => {
          const auth = new AuthPage(page);
          await auth.gotoLogin();
          await page.getByRole('button', { name: /Masuk/i }).click();
          await expect(page).toHaveURL(/.*login/);
          // Pastikan validasi bawaan browser aktif (email wajib diisi)
          const msg = await page.locator('input[name="email"]').evaluate(
               (el: HTMLInputElement) => el.validationMessage
          );
          expect(msg).toBeTruthy();
          await capture(page, 'AUTH004_login_field_kosong.png');
     });

     test('AUTH005 - Logout dari sistem', async ({ page }) => {
          const auth = new AuthPage(page);
          await auth.gotoLogin();
          await auth.loginAndWaitForDashboard('petugas@email.com', PW);
          await auth.logout();
          await expect(page).toHaveURL(/.*login/, { timeout: 15000 });
          await expect(page.locator('body')).toContainText(/berhasil keluar/i);
          await capture(page, 'AUTH005_logout.png');
     });

     test('AUTH006 - Akses halaman setelah sesi berakhir', async ({ page, context }) => {
          await context.clearCookies();
          await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
          await expect(page).toHaveURL(/.*login/, { timeout: 15000 });
          await expect(page.getByText('SmartFarm')).toBeVisible();
          await capture(page, 'AUTH006_akses_tanpa_sesi.png');
     });

     test('AUTH007 - Halaman Profil menampilkan data & riwayat login', async ({ page }) => {
          const auth = new AuthPage(page);
          await auth.gotoLogin();
          await auth.loginAndWaitForDashboard('petugas@email.com', PW);
          await page.goto('/profil', { waitUntil: 'domcontentloaded' });
          await expect(page.getByRole('heading', { name: /Profil Saya/i })).toBeVisible();
          await expect(page.getByRole('heading', { name: /Riwayat Login/i })).toBeVisible();
          await capture(page, 'AUTH007_halaman_profil.png');
     });
});
