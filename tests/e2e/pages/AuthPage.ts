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
        await this.emailInput.waitFor({ state: 'visible', timeout: 30000 });
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