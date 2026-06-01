import { Page, Locator, expect } from '@playwright/test';

export class SettingsPage {
    readonly page: Page;
    readonly pageTitle: Locator;
    readonly dataMasterCard: Locator;
    readonly iotDevicesCard: Locator;
    readonly iotConfigCard: Locator;
    readonly fuzzyCard: Locator;

    constructor(page: Page) {
        this.page = page;
        this.pageTitle = page.locator('text=Pengaturan Sistem');
        this.dataMasterCard = page.locator('a').filter({ hasText: 'Data Master' });
        this.iotDevicesCard = page.locator('a').filter({ hasText: 'Perangkat IoT' });
        this.iotConfigCard = page.locator('a').filter({ hasText: 'Konfigurasi IoT' });
        this.fuzzyCard = page.locator('a').filter({ hasText: 'Fuzzy Mamdani' });
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
        await expect(this.iotDevicesCard).toBeVisible();
        await expect(this.iotConfigCard).toBeVisible();
    }

    async expectFuzzyCardVisible() {
        await expect(this.fuzzyCard).toBeVisible();
    }

    async clickDataMasterCard() {
        await this.dataMasterCard.click();
    }

    async clickIotDevicesCard() {
        await this.iotDevicesCard.click();
    }

    async clickIotConfigCard() {
        await this.iotConfigCard.click();
    }

    async clickFuzzyCard() {
        await this.fuzzyCard.click();
    }
}