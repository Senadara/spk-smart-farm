import { test, expect } from '@playwright/test';
import { LoginPage } from '../pages/LoginPage.js';

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Authentication - Login Scenarios', () => {

    let loginPage: LoginPage;

    test.beforeEach(async ({ page }) => {
        test.setTimeout(120000);

        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        loginPage = new LoginPage(page);
        await loginPage.goto();
    });

    test('Positive - Harus berhasil login dengan kredensial valid (Petugas)', async ({ page }) => {
        await loginPage.login('petugas@email.com', 'Password123.');

        await expect(page).toHaveURL(/.*dashboard/, { timeout: 80000 });

        await expect(page.locator('body')).toBeVisible();
    });

    test('Negative - Gagal login dengan kata sandi yang salah', async ({ page }) => {
        await loginPage.login('petugas@email.com', 'SalahPassword123!');
        await loginPage.expectErrorMessageToBeVisible();
    });

    test('Negative - Gagal login dengan validasi format email yang invalid', async () => {
        await loginPage.login('petugas_invalid_email', 'Password123.');
        await expect(loginPage.page).toHaveURL(/.*login/);
    });
});
