import { Page, Locator, expect } from '@playwright/test';

export class SettingsPage {
    readonly page: Page;
    readonly pageTitle: Locator;
    readonly dataMasterCard: Locator;
    readonly iotCard: Locator;
    readonly fuzzyCard: Locator;
    readonly dssSection: Locator;

    constructor(page: Page) {
        this.page = page;
        this.pageTitle = page.locator('text=Pengaturan Sistem');
        this.dataMasterCard = page.locator('a').filter({ hasText: 'Data Master' });
        this.iotCard = page.locator('#iot-settings');
        this.fuzzyCard = page.locator('a').filter({ hasText: /Aturan SPK Kandang/i });
        this.dssSection = page.locator('text=DSS Supplier AHP-SAW');
    }

    async goto() {
        await this.page.goto('/settings');
        await expect(this.page).toHaveURL(/.*\/settings/);
    }

    async expectPageTitleVisible() {
        await expect(this.pageTitle).toBeVisible();
    }

    async expectAllCardsVisible() {
        await expect(this.dataMasterCard).toBeVisible();
        await expect(this.iotCard).toBeVisible();
    }

    async expectFuzzyCardVisible() {
        await expect(this.fuzzyCard).toBeVisible();
    }

    async clickDataMasterCard() {
        await this.dataMasterCard.click();
    }

    async clickIotCard() {
        await this.iotCard.click();
    }

    async clickFuzzyCard() {
        await this.fuzzyCard.click();
    }
}