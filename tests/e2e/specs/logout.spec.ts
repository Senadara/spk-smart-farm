import { test, expect } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Modul Autentikasi: Logout - E2E QA', () => {
    test.describe.configure({ mode: 'serial' });

    let authPage: AuthPage;

    test.beforeEach(async ({ page }, testInfo) => {
        // Arrange
        testInfo.setTimeout(240000);
        authPage = new AuthPage(page);
        
        // Blocher Vite
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        // Construct Session State
        await authPage.gotoLogin();
        await authPage.loginAndWaitForDashboard('petugas@email.com', 'Password123.');
    });

    test('Positif - Fitur Sign Out menghapus sesi dan menendang keluar sesi User Aktif', async ({ page }) => {
        /**
         * Given session web autentikasi valid dan ada di dashboard
         * When interaksi trigger click pada tombol Logout Profile dijalankan
         * Then sesi musnah dan force route redirect landing kembali ke Guest Login Node
         */

        // Arrange & Act
        await authPage.logout();
        
        // Assert
        await expect(page).toHaveURL(/.*login/, { timeout: 15000 });
    });

    test('Positif - Integritas layout form Guest Landing Page dipertahankan pada Pasca-Logout', async ({ page }) => {
        /**
         * Given user berhasil melewati siklus destruksi token di Backend via aksi Sign out
         * When route beralih otomatis menyentuh tampilan /login
         * Then element autentikasi (Input Email, Password, Tombol Form) render siap dipakai user selanjutnya
         */

        // Arrange
        const emailInputLayout = page.locator('input[name="email"]');
        const passInputLayout = page.locator('input[name="password"]');

        // Act
        await authPage.logout();
        
        // Assert
        await expect(emailInputLayout).toBeVisible({ timeout: 15000 });
        await expect(passInputLayout).toBeVisible();
    });

    test('Negatif - Mencoba Back navigasi History Browser pasca logout ditanggulangi', async ({ page }) => {
        /**
         * Given user yang telah keluar (log out) menekan tombol history navigasi 'Back' browser
         * When browser mencoba membaca cache dan redirect route terlarang
         * Then middleware otentikasi mem-block SPA state dan menahan/meredirect di /login
         */

        // Arrange
        await authPage.logout();
        await expect(page).toHaveURL(/.*login/);

        // Act
        await page.goBack();
        await page.waitForTimeout(2000); // Tunggu simulasi react framework

        // Assert
        // Harusnya terpantul balik ke login karena Guard API token / CSRF menolak akses non-session
        await expect(page).toHaveURL(/.*login/);
    });
});
