import { test, expect } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Autentikasi - Skenario Login', () => {

    let authPage: AuthPage;

    test.beforeEach(async ({ page }) => {
        test.setTimeout(120000);

        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        authPage = new AuthPage(page);
        await authPage.gotoLogin();
    });

    test('Positif - Berhasil login dengan kredensial valid (Petugas)', async ({ page }) => {
        await authPage.login('petugas@email.com', 'Password123.');

        await expect(page).toHaveURL(/.*dashboard/, { timeout: 80000 });

        await expect(page.locator('body')).toBeVisible();
    });

    test('Negatif - Gagal login dengan kata sandi yang salah', async ({ page }) => {
        await authPage.login('petugas@email.com', 'SalahPassword123!');
        await authPage.expectErrorMessageToBeVisible();
    });

    test('Negatif - Gagal login saat format email tidak valid', async () => {
        await authPage.login('petugas_invalid_email', 'Password123.');
        await expect(authPage.page).toHaveURL(/.*login/);
    });
});