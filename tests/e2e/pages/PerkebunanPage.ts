import { Page, Locator, expect } from '@playwright/test';

export class PerkebunanPage {
    readonly page: Page;
    readonly pageTitle: Locator;
    readonly liveStatus: Locator;
    readonly statCardLabels: Locator;
    readonly evaluationHeading: Locator;
    readonly rankingHeading: Locator;
    readonly rankingTable: Locator;
    readonly rankingRows: Locator;
    readonly sensorHeading: Locator;
    readonly sensorCards: Locator;
    readonly alertHeading: Locator;

    constructor(page: Page) {
        this.page = page;
        this.pageTitle = page.getByRole('heading', { name: 'Monitoring Perkebunan Melon' });
        this.liveStatus = page.getByText('Live monitoring');
        this.statCardLabels = page.getByText(/Blok Kebun Aktif|Total Tanaman|Evaluasi Terakhir|Alert Aktif/);
        this.evaluationHeading = page.getByRole('heading', { name: 'Evaluasi SPK Terbaru' });
        this.rankingHeading = page.getByRole('heading', { name: 'Ranking Blok Kebun Terbaru' });
        this.rankingTable = page.locator('table').filter({ hasText: /Blok Kebun|Skor Preferensi|Status Keputusan/i });
        this.rankingRows = this.rankingTable.locator('tbody tr');
        this.sensorHeading = page.getByRole('heading', { name: 'Data Sensor Terkini' });
        this.sensorCards = page.getByText(/Greenhouse A|Greenhouse B|Greenhouse C/);
        this.alertHeading = page.getByRole('heading', { name: 'Alert Aktif' });
    }

    async goto() {
        await this.page.goto('/perkebunan', { waitUntil: 'domcontentloaded', timeout: 120000 });
        await expect(this.page).toHaveURL(/.*\/perkebunan/);
    }

    async expectMainSectionsVisible() {
        await expect(this.pageTitle).toBeVisible();
        await expect(this.evaluationHeading).toBeVisible();
        await expect(this.rankingHeading).toBeVisible();
        await expect(this.sensorHeading).toBeVisible();
    }

    async expectSummaryVisible() {
        await expect(this.statCardLabels.first()).toBeVisible();
        await expect(this.alertHeading).toBeVisible();
    }

    async getRankingRowCount() {
        return await this.rankingRows.count();
    }
}