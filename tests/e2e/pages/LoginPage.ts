import { Page, Locator, expect } from '@playwright/test';

export class LoginPage {
    readonly page: Page;
    readonly emailInput: Locator;
    readonly passwordInput: Locator;
    readonly loginButton: Locator;
    readonly errorMessage: Locator;

    constructor(page: Page) {
        this.page = page;
        // Robust Locators: Mengandalkan struktur accessibility (Label/Role) atau fallback ke attribut mutlak
        this.emailInput = page.locator('input[name="email"]');
        // Memperbaiki Strict Mode Violation pada password
        // Karena ada tombol hide/show password berlabel "Toggle password visibility",
        // kita paksa lokator hanya mengincar input dengan name="password" secara eksklusif
        this.passwordInput = page.locator('input[name="password"]');
        
        this.loginButton = page.getByRole('button', { name: /log in|masuk/i }).or(page.locator('button[type="submit"]'));
        
        // Asumsi struktur error laravel UI biasa memunculkan alert
        this.errorMessage = page.locator('.text-danger, [role="alert"], .invalid-feedback, .alert-danger, .alert, .error, .toast-error, .text-red-500, p.text-sm.text-red-600');
    }

    /**
     * Mengarahkan browser ke rute autentikasi
     */
    async goto() {
        await this.page.goto('/login', { waitUntil: 'commit' });
    }

    /**
     * Fungsi utama untuk melakukan input kredensial dan klik tombol
     */
    async login(email: string, password: string) {
        // Mekanisme auto-waiting bawaan Playwright
        await this.emailInput.fill(email);
        await this.passwordInput.fill(password);
        await this.loginButton.click({ force: true });
    }

    /**
     * Memastikan alert fail/error dirender oleh DOM
     */
    async expectErrorMessageToBeVisible() {
        await expect(this.errorMessage.first()).toBeVisible();
    }
}
