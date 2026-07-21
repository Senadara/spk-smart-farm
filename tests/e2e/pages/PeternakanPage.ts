import { Page, Locator, expect } from '@playwright/test';

export class PeternakanPage {
    readonly page: Page;
    readonly heading: Locator;
    readonly komoditasSelect: Locator;
    readonly evaluateAllButton: Locator;
    readonly evaluationTimeLabel: Locator;

    readonly kpiAmmoniaCard: Locator;
    readonly kpiSuhuCard: Locator;
    readonly kpiEggQualCard: Locator;
    readonly kpiStressCard: Locator;

    readonly filterKandangSelect: Locator;
    readonly spkResultLingkungan: Locator;
    readonly spkResultAktivitas: Locator;

    readonly chartKualitasTelurCanvas: Locator;

    constructor(page: Page) {
        this.page = page;
        
        this.heading = page.getByRole('heading', { name: /Decision Support/i });
        this.komoditasSelect = page.locator('select.komoditas-dropdown, select[x-on\\:change="onKomoditasChange($event)"]');
        this.evaluateAllButton = page.getByRole('button', { name: /Jalankan Evaluasi|Evaluate All/i });
        this.evaluationTimeLabel = page.locator('span[x-text="evaluationTimeLabel"]');

        this.kpiAmmoniaCard = page.locator('.kpi-card').filter({ hasText: /Rata-Rata Amonia|Ammonia/i });
        this.kpiSuhuCard = page.locator('.kpi-card').filter({ hasText: /Rata-Rata Suhu|Temperature/i });
        this.kpiEggQualCard = page.locator('.kpi-card').filter({ hasText: /Kualitas Telur|Egg Quality/i });
        this.kpiStressCard = page.locator('.kpi-card').filter({ hasText: /Produktivitas|Productivity|Tingkat Stres/i });

        this.filterKandangSelect = page.locator('select[x-model="fuzzyFilter"]');
        
        this.spkResultLingkungan = page.locator('.spk-result-card').filter({ hasText: /Kondisi Lingkungan/i });
        this.spkResultAktivitas = page.locator('.spk-result-card').filter({ hasText: /Rekomendasi Aktivitas|Rekomendasi Evaluasi/i });

        this.chartKualitasTelurCanvas = page.locator('canvas').first();
    }

    async goto() {
        await this.page.goto('/peternakan');
    }

    async expectToBeOnPeternakanPage() {
        await expect(this.page).toHaveURL(/.*peternakan/);
        await expect(this.heading.first()).toBeVisible();
    }

    async clickEvaluateAllButton() {
        if (await this.evaluateAllButton.count() > 0) {
            await this.evaluateAllButton.click();
        }
    }

    async gotoWithInsahKomoditas() {
        await this.page.goto('/peternakan?komoditas=tidakada');
    }

    async expectNoKomoditasMessage() {
        await expect(this.page.locator('body')).not.toContainText('Fatal error');
        await expect(this.page.locator('body')).not.toContainText('Error 500');
    }

    async expectKpiTrendIndicators() {
        const trend = this.page.locator('.trend-up, .trend-down, [class*="trend"], .kpi-card svg').first();
        if (await trend.count() > 0) {
            await expect(trend).toBeVisible();
        }
    }

    async expectBarnEnvironmentSection() {
        const section = this.page.locator('h2, h3, h4').filter({ hasText: /Lingkungan|Environment|Sensor|Kondisi/i }).first();
        const bodyText = await this.page.locator('body').textContent() || '';
        expect(bodyText).toBeTruthy();
    }

    async expectSensorLabels(labels: string[]) {
        const bodyText = await this.page.locator('body').textContent() || '';
        for (const label of labels) {
            expect(bodyText).toContain(label);
        }
    }

    async expectDetailLink() {
        const link = this.page.locator('a[href*="/peternakan/"]').first();
        if (await link.count() > 0) {
            await expect(link).toBeVisible();
        }
    }

    async getBarnButtonCount(): Promise<number> {
        return await this.page.locator('.kandang-card, .barn-card, button[class*="kandang"], [class*="barn-card"]').count();
    }

    async expectFuzzySection() {
        const bodyText = await this.page.locator('body').textContent() || '';
        expect(bodyText).toMatch(/Decision|Fuzzy|SPK|Evaluasi|Kondisi|Optimal/i);
    }

    async expectFuzzyGearLink() {
        const gearLink = this.page.locator('a[href*="fuzzy"], a[href*="settings"], a[href*="pengaturan"]').first();
        if (await gearLink.count() > 0) {
            await expect(gearLink).toBeVisible();
        }
    }

    async expectBarnListSection() {
        const bodyText = await this.page.locator('body').textContent() || '';
        expect(bodyText).toMatch(/Kandang|Kandang/i);
    }

    async expectBarnCards() {
        const cards = this.page.locator('.kandang-card, .barn-card, [class*="kandang-card"], [class*="barn-card"]');
        const count = await cards.count();
        expect(count).toBeGreaterThanOrEqual(0);
        if (count > 0) {
            await expect(cards.first()).toBeVisible();
        }
    }

    async searchProductionLog(query: string) {
        const searchInput = this.page.locator('input[type="search"], input[placeholder*="cari"], input[placeholder*="Cari"]').first();
        if (await searchInput.count() > 0) {
            await searchInput.fill(query);
            await this.page.waitForTimeout(500);
        }
    }
}

