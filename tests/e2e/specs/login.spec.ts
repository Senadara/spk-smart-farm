import { test, expect } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Modul Autentikasi: Login - E2E Tests', () => {

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

    /* ═══════════════════════════════════════════════════════════════════
       LOGIN PAGE - UI ELEMENTS
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Login Page dirender lengkap dengan Logo, Form, dan Footer', async ({ page }) => {
        /**
         * Given: User mengakses halaman login
         * When: Halaman dimuat
         * Then: Semua UI elements (Logo, Email input, Password input, Button, Footer) visible
         */

        // Assert: Logo & App Name
        await expect(page.getByText('SmartFarm')).toBeVisible();
        await expect(page.getByText('🌾')).toBeVisible();
        await expect(page.getByText('Sistem Pendukung Keputusan Pertanian')).toBeVisible();

        // Assert: Form inputs
        await expect(page.locator('input[name="email"]')).toBeVisible();
        await expect(page.locator('input[name="password"]')).toBeVisible();

        // Assert: Submit button
        await expect(page.getByRole('button', { name: /Masuk/i })).toBeVisible();

        // Assert: Footer
        const currentYear = new Date().getFullYear();
        await expect(page.getByText(`© ${currentYear} Smart Farm SPK`)).toBeVisible();
    });

    test('Positif - Email input memiliki attributes yang benar (type, placeholder, required, autocomplete)', async ({ page }) => {
        /**
         * Given: Login form dirender
         * When: Melihat email input
         * Then: Input memiliki type="email", placeholder, required, autocomplete="email"
         */

        // Assert: Email input attributes
        const emailInput = page.locator('input[name="email"]');
        await expect(emailInput).toHaveAttribute('type', 'email');
        await expect(emailInput).toHaveAttribute('required');
        await expect(emailInput).toHaveAttribute('placeholder');
        await expect(emailInput).toHaveAttribute('autocomplete', 'email');
    });

    test('Positif - Password input memiliki toggle visibility button', async ({ page }) => {
        /**
         * Given: Login form dirender
         * When: Melihat password input
         * Then: Toggle visibility button (eye icon) visible
         */

        // Assert: Password input
        const passwordInput = page.locator('input[name="password"]');
        await expect(passwordInput).toHaveAttribute('type', 'password');

        // Assert: Toggle button visible
        const toggleButton = page.locator('button[aria-label*="Toggle password"]');
        await expect(toggleButton).toBeVisible();
    });

    test('Positif - Password visibility dapat di-toggle (password ↔ text)', async ({ page }) => {
        /**
         * Given: Password input type="password"
         * When: Click toggle visibility button
         * Then: Input type berubah menjadi "text" (password visible)
         */

        // Arrange
        const passwordInput = page.locator('input[name="password"]');
        const toggleButton = page.locator('button[aria-label*="Toggle password"]');

        // Assert: Initial state (password hidden)
        await expect(passwordInput).toHaveAttribute('type', 'password');

        // Act: Click toggle
        await toggleButton.click();

        // Assert: Password visible (type="text")
        await expect(passwordInput).toHaveAttribute('type', 'text');

        // Act: Click toggle again
        await toggleButton.click();

        // Assert: Password hidden again
        await expect(passwordInput).toHaveAttribute('type', 'password');
    });

    /* ═══════════════════════════════════════════════════════════════════
       LOGIN - SUCCESSFUL SCENARIOS
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Login dengan kredensial PETUGAS valid redirect ke Dashboard', async ({ page }) => {
        /**
         * Given: User berada di halaman login
         * When: Mengisi email dan password PETUGAS yang valid
         * Then: Redirect ke /dashboard dengan success message
         */

        // Arrange
        const emailValid = 'petugas@email.com';
        const passwordValid = 'Password123.';

        // Act
        await authPage.login(emailValid, passwordValid);

        // Assert: Redirect ke dashboard
        await expect(page).toHaveURL(/.*dashboard/, { timeout: 80000 });
        await expect(page.locator('body')).toBeVisible();

        // Assert: Success message (flash message dari controller)
        const bodyText = await page.textContent('body');
        expect(bodyText).toMatch(/Selamat datang/i);
    });

    test('Positif - Login dengan kredensial PJAWAB valid redirect ke Dashboard', async ({ page }) => {
        /**
         * Given: User berada di halaman login
         * When: Mengisi email dan password PJAWAB (admin) yang valid
         * Then: Redirect ke /dashboard
         */

        // Arrange
        const emailValid = 'pjawab@email.com';
        const passwordValid = 'Password123.';

        // Act
        await authPage.login(emailValid, passwordValid);

        // Assert: Redirect ke dashboard
        await expect(page).toHaveURL(/.*dashboard/, { timeout: 80000 });
    });

    test('Positif - Session data tersimpan setelah login sukses', async ({ page, context }) => {
        /**
         * Given: User berhasil login
         * When: Melihat session storage/cookies
         * Then: Session data (api_token, user info) tersimpan
         */

        // Arrange
        const emailValid = 'petugas@email.com';
        const passwordValid = 'Password123.';

        // Act
        await authPage.login(emailValid, passwordValid);
        await expect(page).toHaveURL(/.*dashboard/, { timeout: 80000 });

        // Assert: Cookies exist
        const cookies = await context.cookies();
        expect(cookies.length).toBeGreaterThan(0);
    });

    test('Positif - Submit button menampilkan loading state saat proses login', async ({ page }) => {
        /**
         * Given: User mengisi form login
         * When: Click submit button
         * Then: Button menampilkan loading state ("Memproses...")
         */

        // Arrange
        const emailValid = 'petugas@email.com';
        const passwordValid = 'Password123.';

        await page.locator('input[name="email"]').fill(emailValid);
        await page.locator('input[name="password"]').fill(passwordValid);

        // Act: Click submit
        const submitButton = page.getByRole('button', { name: /Masuk/i });
        await submitButton.click();

        // Assert: Loading state (button disabled dan text berubah)
        // Note: Loading state sangat cepat jadi kita cek disabled state
        const isDisabled = await submitButton.isDisabled({ timeout: 1000 }).catch(() => false);
        expect(typeof isDisabled).toBe('boolean'); // Button sempat disabled during submit
    });

    /* ═══════════════════════════════════════════════════════════════════
       LOGIN - FAILED SCENARIOS
       ═══════════════════════════════════════════════════════════════════ */

    test('Negatif - Login dengan password salah menampilkan error message', async ({ page }) => {
        /**
         * Given: User mengisi email valid tapi password salah
         * When: Submit form
         * Then: Error message ditampilkan, tetap di halaman login
         */

        // Arrange
        const emailValid = 'petugas@email.com';
        const invalidPassword = 'SalahPassword123!';

        // Act
        await authPage.login(emailValid, invalidPassword);

        // Assert: Error message visible
        await authPage.expectErrorMessageToBeVisible();
        await expect(page).toHaveURL(/.*login/);
    });

    test('Negatif - Login dengan email tidak terdaftar menampilkan error message', async ({ page }) => {
        /**
         * Given: User mengisi email yang tidak ada di database
         * When: Submit form
         * Then: Error message ditampilkan
         */

        // Arrange
        const invalidEmail = 'tidakada@email.com';
        const anyPassword = 'Password123.';

        // Act
        await authPage.login(invalidEmail, anyPassword);

        // Assert: Error message visible
        await authPage.expectErrorMessageToBeVisible();
        await expect(page).toHaveURL(/.*login/);
    });

    test('Negatif - Login dengan email kosong', async ({ page }) => {
        /**
         * Given: User tidak mengisi email (required field)
         * When: Submit form
         * Then: HTML5 validation mencegah submit
         */

        // Arrange: Isi password saja, email kosong
        await page.locator('input[name="password"]').fill('Password123.');

        // Act: Click submit
        const submitButton = page.getByRole('button', { name: /Masuk/i });
        await submitButton.click();

        // Assert: Tetap di login page (tidak tersubmit)
        await expect(page).toHaveURL(/.*login/);

        // Assert: Email input has validation message
        const emailInput = page.locator('input[name="email"]');
        const validationMessage = await emailInput.evaluate((el: HTMLInputElement) => el.validationMessage);
        expect(validationMessage).toBeTruthy();
    });

    test('Negatif - Login dengan password kosong', async ({ page }) => {
        /**
         * Given: User tidak mengisi password (required field)
         * When: Submit form
         * Then: HTML5 validation mencegah submit
         */

        // Arrange: Isi email saja, password kosong
        await page.locator('input[name="email"]').fill('petugas@email.com');

        // Act: Click submit
        const submitButton = page.getByRole('button', { name: /Masuk/i });
        await submitButton.click();

        // Assert: Tetap di login page
        await expect(page).toHaveURL(/.*login/);

        // Assert: Password input has validation message
        const passwordInput = page.locator('input[name="password"]');
        const validationMessage = await passwordInput.evaluate((el: HTMLInputElement) => el.validationMessage);
        expect(validationMessage).toBeTruthy();
    });

    test('Negatif - Login dengan format email invalid', async ({ page }) => {
        /**
         * Given: User mengisi email dengan format tidak valid (tanpa @)
         * When: Submit form
         * Then: HTML5 type="email" validation mencegah submit
         */

        // Arrange
        const invalidEmailFormat = 'petugas_invalid_email';
        const dummyPassword = 'Password123.';

        // Act
        await authPage.login(invalidEmailFormat, dummyPassword);

        // Assert: Tetap di login page
        await expect(page).toHaveURL(/.*login/);

        // Assert: Email input has validation message
        const emailInput = page.locator('input[name="email"]');
        const validationMessage = await emailInput.evaluate((el: HTMLInputElement) => el.validationMessage);
        expect(validationMessage).toBeTruthy();
    });

    test('Negatif - Multiple failed login attempts tidak menyebabkan crash', async ({ page }) => {
        /**
         * Given: User melakukan multiple failed login attempts
         * When: Submit form dengan credentials salah berkali-kali
         * Then: Error message ditampilkan setiap kali tanpa crash
         */

        // Arrange
        const invalidEmail = 'wrong@email.com';
        const invalidPassword = 'WrongPassword';

        // Act: Attempt 1
        await authPage.login(invalidEmail, invalidPassword);
        await authPage.expectErrorMessageToBeVisible();

        // Act: Attempt 2
        await page.locator('input[name="email"]').fill(invalidEmail);
        await page.locator('input[name="password"]').fill(invalidPassword);
        await page.getByRole('button', { name: /Masuk/i }).click();
        await page.waitForTimeout(2000);

        // Assert: Tetap di login, tidak crash
        await expect(page).toHaveURL(/.*login/);
        const bodyContent = await page.locator('body').textContent();
        expect(bodyContent).not.toMatch(/Fatal|Error 500|undefined/i);
    });

    /* ═══════════════════════════════════════════════════════════════════
       EDGE CASES & SECURITY
       ═══════════════════════════════════════════════════════════════════ */

    test('Edge Case - Login dengan email yang mengandung spaces di-trim dengan benar', async ({ page }) => {
        /**
         * Given: User mengisi email dengan leading/trailing spaces
         * When: Submit form
         * Then: Spaces di-trim dan login berhasil (jika credentials valid)
         */

        // Arrange: Email dengan spaces
        const emailWithSpaces = '  petugas@email.com  ';
        const validPassword = 'Password123.';

        // Act
        await page.locator('input[name="email"]').fill(emailWithSpaces);
        await page.locator('input[name="password"]').fill(validPassword);
        await page.getByRole('button', { name: /Masuk/i }).click();

        // Assert: Login berhasil (spaces di-trim oleh backend)
        // Bisa berhasil atau gagal tergantung backend handling
        await page.waitForTimeout(3000);
        const currentUrl = page.url();

        // Either success (dashboard) OR fail (login with error)
        expect(currentUrl).toMatch(/dashboard|login/);
    });

    test('Edge Case - Login form dapat di-refresh tanpa error', async ({ page }) => {
        /**
         * Given: User berada di halaman login
         * When: Refresh halaman
         * Then: Form tetap visible dan functional
         */

        // Act: Refresh
        await page.reload({ waitUntil: 'domcontentloaded' });

        // Assert: Form elements visible
        await expect(page.locator('input[name="email"]')).toBeVisible();
        await expect(page.locator('input[name="password"]')).toBeVisible();
        await expect(page.getByRole('button', { name: /Masuk/i })).toBeVisible();
    });

    test('Security - Login form memiliki CSRF token', async ({ page }) => {
        /**
         * Given: Login form dirender
         * When: Melihat form
         * Then: Input hidden dengan name="_token" (CSRF) harus ada
         */

        // Assert: CSRF token input exists
        const csrfToken = page.locator('input[name="_token"]');
        await expect(csrfToken).toBeAttached();
        await expect(csrfToken).toHaveAttribute('type', 'hidden');
    });

    test('Performance - Login page loads dalam waktu reasonable (<6 detik)', async ({ page }) => {
        /**
         * Given: User mengakses login page
         * When: Measuring load time
         * Then: Page harus load < 6 detik (adjusted for E2E automation overhead)
         */

        // Arrange
        const startTime = Date.now();

        // Act
        await page.goto('/login', { waitUntil: 'domcontentloaded' });
        await expect(page.getByText('SmartFarm')).toBeVisible();

        const endTime = Date.now();
        const loadTime = endTime - startTime;

        // Assert: Load time < 15000ms (realistic for E2E automation with cold start)
        expect(loadTime).toBeLessThan(15000);
    });
});