import { Page, Locator, expect } from '@playwright/test';

export class DashboardPage {
    readonly page: Page;
    readonly welcomeCard: Locator;
    readonly roleCard: Locator;
    readonly emailCard: Locator;
    readonly loginSinceCard: Locator;
    readonly placeholderCard: Locator;

    constructor(page: Page) {
        this.page = page;
        this.welcomeCard = page.locator('text=Selamat Datang');
        this.roleCard = page.locator('text=Role Anda').locator('..');
        this.emailCard = page.locator('text=Email').locator('..');
        this.loginSinceCard = page.locator('text=Login Sejak').locator('..');
        this.placeholderCard = page.locator('text=Fitur SPK Akan Hadir');
    }

    async goto() {
        await this.page.goto('/dashboard', { waitUntil: 'domcontentloaded', timeout: 120000 });
        await expect(this.page).toHaveURL(/.*\/dashboard/);
    }

    async expectWelcomeCardVisible() {
        await expect(this.welcomeCard).toBeVisible();
    }

    async expectRoleCardVisible() {
        await expect(this.roleCard).toBeVisible();
    }

    async expectEmailCardVisible() {
        await expect(this.emailCard).toBeVisible();
    }

    async expectLoginSinceCardVisible() {
        await expect(this.loginSinceCard).toBeVisible();
    }

    async expectPlaceholderCardVisible() {
        await expect(this.placeholderCard).toBeVisible();
    }

    async expectAllCardsVisible() {
        await expect(this.welcomeCard).toBeVisible();
        await expect(this.roleCard).toBeVisible();
        await expect(this.emailCard).toBeVisible();
        await expect(this.loginSinceCard).toBeVisible();
    }
}