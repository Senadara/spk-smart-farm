import { test, expect } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Pengujian Alur Logout', () => {
    test.describe.configure({ mode: 'serial' });

    let authPage: AuthPage;

    test.beforeEach(async ({ page }, testInfo) => {
        testInfo.setTimeout(240000);
        authPage = new AuthPage(page);
        await authPage.gotoLogin();
        await authPage.loginAndWaitForDashboard('petugas@email.com', 'Password123.');
    });

    test('Positif - User berhasil logout dan diarahkan ke halaman login', async ({ page }) => {
        await authPage.logout();
        await expect(page).toHaveURL(/.*login/, { timeout: 15000 });
    });

    test('Positif - Form login tampil kembali setelah logout', async ({ page }) => {
        await authPage.logout();
        await expect(page.locator('input[name="email"]')).toBeVisible();
        await expect(page.locator('input[name="password"]')).toBeVisible();
    });
});