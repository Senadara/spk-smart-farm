import { Page, Locator, expect } from '@playwright/test';

export class PeternakanPage {
    readonly page: Page;
    readonly heading: Locator;
    readonly barnTabs: Locator;
    readonly chartCanvas: Locator;
    readonly exportButton: Locator;

    constructor(page: Page) {
        this.page = page;
        this.heading = page.locator('h1, h2').filter({ hasText: /Peternakan|Kandang/i });
        
        this.barnTabs = page.locator('.barn-tab, button').filter({ hasText: /Kandang/i });
        this.chartCanvas = page.locator('canvas');
        this.exportButton = page.locator('button').filter({ hasText: /Export/i });
    }

    async goto() {
        await this.page.goto('/peternakan');
    }

    async expectToBeOnPeternakanPage() {
        await expect(this.page).toHaveURL(/.*peternakan/);
        await expect(this.heading.first()).toBeVisible();
    }
    async downloadExportReport() {
        const downloadPromise = this.page.waitForEvent('download');
        await this.exportButton.first().click();
        const download = await downloadPromise;
        return download;
    }
}
