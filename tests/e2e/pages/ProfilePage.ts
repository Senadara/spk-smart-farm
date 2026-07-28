import { Page, Locator, expect } from '@playwright/test';

export class ProfilePage {
    readonly page: Page;
    readonly pageTitle: Locator;
    readonly profileName: Locator;
    readonly profileEmail: Locator;
    readonly profilePhone: Locator;
    readonly profileRole: Locator;
    readonly roleBadge: Locator;
    readonly loginHistoryTitle: Locator;
    readonly loginHistoryTable: Locator;
    readonly loginHistoryRows: Locator;

    constructor(page: Page) {
        this.page = page;
        this.pageTitle = page.getByRole('heading', { name: 'Profil Saya' });
        this.profileName = page.locator('h2').filter({ hasText: /^[A-Za-z]/ }).first();
        this.profileEmail = page.locator('div').filter({ has: page.getByText('Email', { exact: true }) }).locator('span').last();
        this.profilePhone = page.locator('div').filter({ has: page.getByText('Telepon', { exact: true }) }).locator('span').last();
        this.profileRole = page.locator('div').filter({ has: page.getByText('Role', { exact: true }) }).locator('span').last();
        this.roleBadge = page.locator('[class*="badge"]');
        this.loginHistoryTitle = page.getByRole('heading', { name: 'Riwayat Login' });
        this.loginHistoryTable = page.locator('table');
        this.loginHistoryRows = page.locator('tbody tr');
    }

    async goto() {
        await this.page.goto('/profil', { waitUntil: 'domcontentloaded', timeout: 120000 });
        await expect(this.page).toHaveURL(/.*\/profil/);
    }

    async expectPageTitleVisible() {
        await expect(this.pageTitle).toBeVisible();
    }

    async expectProfileDetailsVisible() {
        await expect(this.profileName).toBeVisible();
        await expect(this.profileEmail).toBeVisible();
        await expect(this.roleBadge).toBeVisible();
    }

    async expectLoginHistoryVisible() {
        await expect(this.loginHistoryTitle).toBeVisible();
        await expect(this.loginHistoryTable).toBeVisible();
    }

    async getLoginHistoryRowCount() {
        return await this.loginHistoryRows.count();
    }

    async expectLoginHistoryHasRows(minRows: number) {
        const count = await this.getLoginHistoryRowCount();
        expect(count).toBeGreaterThanOrEqual(minRows);
    }
}