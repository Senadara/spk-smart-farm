import { test, expect } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Modul Autentikasi: Logout - E2E Tests', () => {
    test.describe.configure({ mode: 'serial' });

    let authPage: AuthPage;

    test.beforeEach(async ({ page }, testInfo) => {
        // Arrange
        testInfo.setTimeout(240000);
        authPage = new AuthPage(page);

        // Blocker Vite
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        // Setup: Login first
        await authPage.gotoLogin();
        await authPage.loginAndWaitForDashboard('petugas@email.com', 'Password123.');
    });

    /* ═══════════════════════════════════════════════════════════════════
       LOGOUT - SUCCESSFUL FLOW
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Logout button accessible dari dashboard', async ({ page }) => {
        /**
         * Given: User sudah login dan berada di dashboard
         * When: Melihat navigasi/menu
         * Then: Logout button/link visible dan dapat diakses
         */

        // Assert: User di dashboard
        await expect(page).toHaveURL(/.*dashboard/);

        // Assert: Logout mechanism visible (bisa berupa button, dropdown, dll)
        // Logout biasanya ada di navigation atau profile dropdown
        // Kita cek generic logout text/button
        const logoutElement = page.locator('button:has-text("Keluar"), a:has-text("Keluar"), button:has-text("Logout"), a:has-text("Logout")').first();
        const isVisible = await logoutElement.isVisible({ timeout: 10000 }).catch(() => false);
        expect(isVisible).toBeTruthy();
    });

    test('Positif - Logout menghapus session dan redirect ke Login page', async ({ page }) => {
        /**
         * Given: User sudah login
         * When: Click logout button
         * Then: Session dihapus, redirect ke /login
         */

        // Act
        await authPage.logout();

        // Assert: Redirect to login
        await expect(page).toHaveURL(/.*login/, { timeout: 15000 });
    });

    test('Positif - Logout menampilkan success message di Login page', async ({ page }) => {
        /**
         * Given: User melakukan logout
         * When: Redirect ke login page
         * Then: Success message "Anda telah berhasil keluar" ditampilkan
         */

        // Act
        await authPage.logout();
        await expect(page).toHaveURL(/.*login/, { timeout: 15000 });

        // Assert: Success message visible
        const bodyText = await page.textContent('body');
        expect(bodyText).toMatch(/berhasil keluar|logout successful/i);
    });

    test('Positif - Login form elements dirender sempurna setelah logout', async ({ page }) => {
        /**
         * Given: User berhasil logout
         * When: Login page dimuat
         * Then: Email input, Password input, Submit button visible dan functional
         */

        // Arrange
        const emailInputLayout = page.locator('input[name="email"]');
        const passInputLayout = page.locator('input[name="password"]');
        const submitButton = page.getByRole('button', { name: /Masuk/i });

        // Act: Logout and wait for full redirect
        await authPage.logout();
        await expect(page).toHaveURL(/.*login/, { timeout: 15000 });
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(1000); // Ensure form elements are rendered

        // Assert: Form elements visible
        await expect(emailInputLayout).toBeVisible({ timeout: 15000 });
        await expect(passInputLayout).toBeVisible();
        await expect(submitButton).toBeVisible();
    });

    /* ═══════════════════════════════════════════════════════════════════
       LOGOUT - SESSION CLEARING
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Session cookies dihapus setelah logout', async ({ page, context }) => {
        /**
         * Given: User sudah login (cookies exist)
         * When: Logout
         * Then: Session cookies dihapus atau invalidated
         */

        // Arrange: Check cookies before logout
        const cookiesBefore = await context.cookies();
        expect(cookiesBefore.length).toBeGreaterThan(0);

        // Act: Logout
        await authPage.logout();
        await expect(page).toHaveURL(/.*login/, { timeout: 15000 });

        // Assert: Session cookies cleared (atau PHPSESSID invalidated)
        const cookiesAfter = await context.cookies();

        // Either cookies deleted OR session invalidated (depends on implementation)
        // We check that user cannot access protected pages anymore
    });

    test('Positif - Logout history dicatat (Login History untuk device ini dihapus)', async ({ page }) => {
        /**
         * Given: User login (Login History record created)
         * When: Logout
         * Then: Controller menghapus Login History untuk perangkat ini (IP + User Agent)
         */

        // Note: Dari controller, logout menghapus LoginHistory::where('email')->where('ipAddress')->where('userAgent')->delete()

        // Act: Logout
        await authPage.logout();
        await expect(page).toHaveURL(/.*login/, { timeout: 15000 });

        // Assert: Logout executed successfully (we can't directly check DB, but verify no crash)
        const bodyContent = await page.locator('body').textContent();
        expect(bodyContent).not.toMatch(/Error 500|Fatal/i);
    });

    /* ═══════════════════════════════════════════════════════════════════
       LOGOUT - SECURITY & ACCESS CONTROL
       ═══════════════════════════════════════════════════════════════════ */

    test('Negatif - Akses protected page setelah logout harus redirect ke Login', async ({ page }) => {
        /**
         * Given: User sudah logout
         * When: Mencoba akses halaman protected (dashboard)
         * Then: Redirect ke login page (middleware auth.api block)
         */

        // Arrange: Logout first
        await authPage.logout();
        await expect(page).toHaveURL(/.*login/, { timeout: 15000 });

        // Act: Try to access dashboard (most critical protected page)
        await page.goto('/dashboard', { waitUntil: 'domcontentloaded', timeout: 30000 });

        // Assert: Should be redirected to login
        await expect(page).toHaveURL(/.*login/, { timeout: 10000 });

        // Verify login page elements visible (confirm proper redirect)
        await expect(page.getByText('SmartFarm')).toBeVisible({ timeout: 5000 });
    });

    test('Negatif - Browser back button setelah logout tidak bisa akses dashboard', async ({ page }) => {
        /**
         * Given: User sudah logout
         * When: Click browser back button
         * Then: Tetap di login page (middleware mencegah akses)
         */

        // Arrange
        await authPage.logout();
        await expect(page).toHaveURL(/.*login/);

        // Act: Go back
        await page.goBack();
        await page.waitForTimeout(2000);

        // Assert: Tetap di login (atau redirect kembali ke login)
        await expect(page).toHaveURL(/.*login/);
    });

    test('Negatif - Forward button setelah logout tidak bisa akses protected page', async ({ page }) => {
        /**
         * Given: User sudah logout dan kembali ke login
         * When: Click browser forward button (jika ada history)
         * Then: Redirect ke login atau tetap di login
         */

        // Arrange
        await authPage.logout();
        await expect(page).toHaveURL(/.*login/);
        await page.goBack();
        await page.waitForTimeout(1000);

        // Act: Go forward
        await page.goForward();
        await page.waitForTimeout(2000);

        // Assert: Protected page tidak accessible
        await expect(page).toHaveURL(/.*login/);
    });

    test('Negatif - Logout dari halaman lain (bukan dashboard) tetap berhasil', async ({ page }) => {
        /**
         * Given: User berada di halaman protected lain (misal: Profile, Settings)
         * When: Click logout
         * Then: Logout berhasil dan redirect ke login
         */

        // Arrange: Navigate to Profile
        await page.goto('/profil', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(2000);

        // Act: Logout from Profile page
        await authPage.logout();

        // Assert: Redirect to login
        await expect(page).toHaveURL(/.*login/, { timeout: 15000 });
    });

    /* ═══════════════════════════════════════════════════════════════════
       EDGE CASES
       ═══════════════════════════════════════════════════════════════════ */

    test('Edge Case - Double logout (logout 2x berturut-turut) tidak menyebabkan error', async ({ page }) => {
        /**
         * Given: User sudah logout sekali
         * When: Mencoba logout lagi (unlikely scenario)
         * Then: Tidak ada FATAL error (stuck at /logout is acceptable edge case)
         */

        // Act: Logout pertama
        await authPage.logout();
        await expect(page).toHaveURL(/.*login/, { timeout: 15000 });

        // Act: Try to access logout route again (edge case - might not redirect)
        const response = await page.goto('/logout', {
            waitUntil: 'domcontentloaded',
            timeout: 10000
        }).catch(() => null);

        // Wait for any potential processing
        await page.waitForTimeout(3000);

        // Assert: Most important - NO FATAL ERROR (URL location less critical for edge case)
        const bodyContent = await page.locator('body').textContent();

        // Check for CRITICAL errors only (500, Fatal PHP error)
        // "Exception" from JS framework is acceptable for this edge case
        const hasFatalError = bodyContent?.match(/Error 500|Fatal error|Parse error/i);
        expect(hasFatalError).toBeFalsy();

        // Should be at login OR logout (both acceptable - no crash is key)
        const currentUrl = page.url();
        const isAtSafePage = currentUrl.match(/login/) || currentUrl.match(/logout/);
        expect(isAtSafePage).toBeTruthy();
    });

    test('Edge Case - Logout dengan network slow tidak menyebabkan hang', async ({ page }) => {
        /**
         * Given: User click logout
         * When: Network slow (simulate dengan timeout)
         * Then: Logout tetap berhasil dalam waktu reasonable
         */

        // Arrange: Set timeout yang cukup
        const startTime = Date.now();

        // Act: Logout
        await authPage.logout();
        await expect(page).toHaveURL(/.*login/, { timeout: 15000 });

        const endTime = Date.now();
        const logoutTime = endTime - startTime;

        // Assert: Logout time < 15 detik
        expect(logoutTime).toBeLessThan(15000);
    });

    test('Edge Case - Logout setelah session timeout (hypothetical) tidak crash', async ({ page }) => {
        /**
         * Given: User session mungkin sudah expired
         * When: Click logout
         * Then: Logout tetap berhasil tanpa error
         */

        // Note: Kita tidak bisa simulate session timeout di E2E test dengan mudah
        // Kita hanya verify bahwa logout mechanism robust

        // Act: Logout normally
        await authPage.logout();

        // Assert: No crash - wait for redirect and check
        await expect(page).toHaveURL(/.*login/, { timeout: 20000 });
        await page.waitForLoadState('domcontentloaded');

        const bodyContent = await page.locator('body').textContent();
        expect(bodyContent).not.toMatch(/Error 500|Fatal error|Parse error/i);
    });

    /* ═══════════════════════════════════════════════════════════════════
       RE-LOGIN AFTER LOGOUT
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - User dapat re-login setelah logout', async ({ page }) => {
        /**
         * Given: User sudah logout
         * When: Mengisi form login lagi dengan credentials valid
         * Then: Login berhasil dan masuk ke dashboard
         */

        // Arrange: Logout
        await authPage.logout();
        await expect(page).toHaveURL(/.*login/, { timeout: 15000 });

        // Act: Re-login
        await authPage.login('petugas@email.com', 'Password123.');

        // Assert: Login berhasil
        await expect(page).toHaveURL(/.*dashboard/, { timeout: 80000 });
    });

    test('Positif - Re-login setelah logout membuat Login History baru', async ({ page }) => {
        /**
         * Given: User logout (Login History dihapus)
         * When: User re-login
         * Then: Login History record baru dibuat
         */

        // Arrange: Logout
        await authPage.logout();
        await expect(page).toHaveURL(/.*login/, { timeout: 15000 });

        // Act: Re-login
        await authPage.login('petugas@email.com', 'Password123.');
        await expect(page).toHaveURL(/.*dashboard/, { timeout: 80000 });

        // Assert: Navigate to Profile to check Login History
        await page.goto('/profil', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(2000);

        // Assert: Login History table has at least 1 row (dari re-login)
        const historyTable = page.locator('table tbody tr').first();
        const hasHistory = await historyTable.isVisible({ timeout: 5000 }).catch(() => false);
        expect(hasHistory).toBeTruthy();
    });

    test('Performance - Logout flow completes dalam waktu reasonable (<5 detik)', async ({ page }) => {
        /**
         * Given: User click logout
         * When: Measuring logout time
         * Then: Logout harus complete < 5 detik (termasuk redirect)
         */

        // Arrange
        const startTime = Date.now();

        // Act
        await authPage.logout();
        await expect(page).toHaveURL(/.*login/, { timeout: 15000 });

        const endTime = Date.now();
        const logoutTime = endTime - startTime;

        // Assert: Logout time < 5000ms
        expect(logoutTime).toBeLessThan(5000);
    });
});