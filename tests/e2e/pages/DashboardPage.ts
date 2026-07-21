import { Page, Locator, expect } from '@playwright/test';

export class DashboardPage {
    readonly page: Page;
    readonly heading: Locator;
    readonly productivityCards: Locator;
    readonly trendChart: Locator;
    readonly peternakanSection: Locator;
    readonly perkebunanSection: Locator;
    readonly spkPanel: Locator;
    readonly stokGudangPanel: Locator;
    readonly prioritasSection: Locator;

    constructor(page: Page) {
        this.page = page;
        this.heading = page.getByText('Produktivitas Farm Hari Ini');
        this.productivityCards = page.locator('section.grid.grid-cols-2.md\\:grid-cols-3.xl\\:grid-cols-6 article');
        this.trendChart = page.getByText('Tren Produktivitas 7 Hari');
        this.peternakanSection = page.getByText('Peternakan').locator('..');
        this.perkebunanSection = page.getByText('Perkebunan').locator('..');
        this.spkPanel = page.getByText('Peringatan SPK Hari Ini');
        this.stokGudangPanel = page.getByText('Stok Gudang');
        this.prioritasSection = page.getByText('Prioritas');
    }

    async goto() {
        await this.page.goto('/dashboard', { waitUntil: 'domcontentloaded', timeout: 120000 });
        await expect(this.page).toHaveURL(/.*\/dashboard/);
    }

    async expectPageLoaded() {
        await expect(this.heading.first()).toBeVisible({ timeout: 15000 });
    }

    async expectProductivityCardsVisible() {
        const count = await this.productivityCards.count();
        expect(count).toBeGreaterThanOrEqual(4);
    }

    async expectTrendChartVisible() {
        await expect(this.trendChart.first()).toBeVisible({ timeout: 10000 });
    }

    async expectPeternakanSectionVisible() {
        await expect(this.peternakanSection.first()).toBeVisible({ timeout: 10000 });
    }

    async expectPerkebunanSectionVisible() {
        await expect(this.perkebunanSection.first()).toBeVisible({ timeout: 10000 });
    }

    async expectSpkPanelVisible() {
        await expect(this.spkPanel.first()).toBeVisible({ timeout: 10000 });
    }

    async expectStokGudangPanelVisible() {
        await expect(this.stokGudangPanel.first()).toBeVisible({ timeout: 10000 });
    }

    async expectNoCrash() {
        const bodyContent = await this.page.locator('body').textContent();
        expect(bodyContent).not.toMatch(/Fatal render error|undefined/i);
        expect(bodyContent).not.toMatch(/Error 500/i);
    }

    async expectAllSectionsVisible() {
        await this.expectPageLoaded();
        await this.expectProductivityCardsVisible();
        await this.expectTrendChartVisible();
        await expect(this.page.getByText('Populasi').first()).toBeVisible();
        await expect(this.page.getByText('Blok aktif').first()).toBeVisible();
    }
}
