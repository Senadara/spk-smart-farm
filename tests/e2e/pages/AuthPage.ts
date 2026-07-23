import { Page, Locator, expect } from '@playwright/test';

export class AuthPage {
    readonly page: Page;

    // Locator untuk Keperluan Login
    readonly emailInput: Locator;
    readonly passwordInput: Locator;
    readonly loginButton: Locator;
    readonly errorMessage: Locator;

    // Locator untuk Keperluan Logout
    readonly logoutForm: Locator;
    readonly logoutButton: Locator;

    constructor(page: Page) {
        this.page = page;

        // Inisialisasi Locator Login
        this.emailInput = page.locator('input[name="email"]');
        this.passwordInput = page.locator('input[name="password"]');
        this.loginButton = page.getByRole('button', { name: /log in|masuk/i }).or(page.locator('button[type="submit"]'));
        this.errorMessage = page.locator('#toastContainer .toast-error').or(page.locator('[data-autohide].toast-error'));

        // Inisialisasi Locator Logout
        this.logoutForm = page.locator('form[action$="logout"]');
        this.logoutButton = this.logoutForm.locator('button[type="submit"]');
    }

    async gotoLogin() {
        await this.page.goto('/login', { waitUntil: 'domcontentloaded' });
    }

    async login(email: string, password: string) {
        await this.emailInput.waitFor({ state: 'visible', timeout: 30000 });
        await this.emailInput.fill(email);
        await this.passwordInput.fill(password);
        await this.loginButton.click();
    }

    async loginAndWaitForDashboard(email: string, password: string) {
        // clear cookies to ensure a clean login attempt when needed
        await this.page.context().clearCookies();
        await this.gotoLogin();

        // If the app redirected to dashboard (already authenticated via storageState), skip login
        const currentUrl = this.page.url();
        if (/dashboard/.test(currentUrl)) {
            return;
        }

        // Otherwise wait for the email input and perform login.
        // Halaman /login kadang lambat render (server sibuk, workers:1) -> reload sekali sbg fallback
        // agar tidak men-cascade kegagalan pada blok test serial.
        try {
            await this.emailInput.waitFor({ state: 'visible', timeout: 20000 });
        } catch {
            await this.gotoLogin();
            await this.emailInput.waitFor({ state: 'visible', timeout: 30000 });
        }
        await this.emailInput.fill(email);
        await this.passwordInput.fill(password);
        await this.loginButton.click();
        await expect(this.page).toHaveURL(/.*dashboard/, { timeout: 120000 });
    }

    async expectErrorMessageToBeVisible() {
        await expect(this.errorMessage.first()).toBeVisible();
    }

    async gotoDashboard() {
        await this.page.goto('/dashboard');
        await expect(this.page).toHaveURL(/.*\/dashboard/);
    }

    async logout() {
        await this.logoutButton.click();
    }
}