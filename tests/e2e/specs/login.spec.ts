import { test, expect } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Modul Autentikasi: Login - E2E QA', () => {

    let authPage: AuthPage;

    test.beforeEach(async ({ page }) => {
        // Arrange
        test.setTimeout(120000);

        // Blocker akses Vite HMR
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        authPage = new AuthPage(page);
        
        // Act
        await authPage.gotoLogin();
    });

    test('Positif - Kredensial valid menghasilkan redirect sukses ke Dashboard Utama', async ({ page }) => {
        /**
         * Given user anonim berada pada halaman /login
         * When memberikan pasangan email dan kata sandi Petugas yang valid di database
         * Then sistem memberikan HTTP HttpCookie session dan routing user menuju panel /dashboard
         */

        // Arrange
        const emailValid = 'petugas@email.com';
        const passwordValid = 'Password123.';
        const expectedDashboardPattern = /.*dashboard/;

        // Act
        await authPage.login(emailValid, passwordValid);

        // Assert
        // Pastikan load render valid pasca navigasi SPAs
        await expect(page).toHaveURL(expectedDashboardPattern, { timeout: 80000 });
        await expect(page.locator('body')).toBeVisible();
    });

    test('Negatif - Menggunakan kata sandi salah akan mencetak peringatan error visibility di UI', async ({ page }) => {
        /**
         * Given user mencoba melakukan percobaan peretasan masuk otentikasi
         * When mem-bypass form dengan password yang diubah (incorrect string)
         * Then server API tidak boleh memberikan Token dan Frontend Wajib render State Error Message
         */

        // Arrange
        const emailValid = 'petugas@email.com';
        const invalidPassword = 'SalahPassword123!';

        // Act
        await authPage.login(emailValid, invalidPassword);

        // Assert
        await authPage.expectErrorMessageToBeVisible();
        await expect(page).toHaveURL(/.*login/);
    });

    test('Negatif - Format struktur Email tidak ter-resolve oleh Browser Validation Constraints', async ({ page }) => {
        /**
         * Given kolom teks email membutuhkan konvensi string RFC
         * When pengetik menuliskan struktur non-email dan melakukan konfirmasi Hit Submit
         * Then validasi Frontend (HTML5 / JS) memblokir transisi dan halaman tidak berubah
         */

        // Arrange
        const invalidEmailFormat = 'petugas_invalid_email';
        const dummyPassword = 'Password123.';

        // Act
        await authPage.login(invalidEmailFormat, dummyPassword);

        // Assert
        // Verifikasi page tidak ter-redirect
        await expect(page).toHaveURL(/.*login/);
    });
});
