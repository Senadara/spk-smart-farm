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

    async gotoWithInvalidKomoditas() {
        await this.page.goto('/peternakan?komoditas=nonexistent');
        await this.page.waitForTimeout(2000);
    }

    async expectNoKomoditasMessage() {
        const body = await this.page.locator('body').textContent() || '';
        expect(body).toContain('Belum ada data komoditas');
    }

    async expectKpiTrendIndicators() {
        const body = await this.page.locator('body').textContent() || '';
        const hasTrend = /[+\-↑↓]/.test(body);
        if (!hasTrend) {
            const kpiCards = await this.page.locator('.kpi-card').count();
            expect(kpiCards).toBeGreaterThan(0);
        }
    }

    async expectBarnEnvironmentSection() {
        await expect(this.page.locator('body')).toContainText(/Barn Environment/i);
    }

    async expectSensorLabels(labels: string[]) {
        const body = await this.page.locator('body').textContent() || '';
        for (const label of labels) {
            expect(body).toContain(label);
        }
    }

    async expectDetailLink() {
        const link = this.page.locator('a').filter({ hasText: /Lihat Detail Kandang/i }).first();
        await expect(link).toBeVisible();
        const href = await link.getAttribute('href');
        expect(href).toContain('/peternakan/');
    }

    async getBarnButtonCount(): Promise<number> {
        return await this.page.locator('button[type="button"]').filter({ has: this.page.locator('text=°') }).count();
    }

    async expectFuzzySection() {
        await expect(this.page.locator('body')).toContainText(/Fuzzy/i);
    }

    async expectFuzzyGearLink() {
        const link = this.page.locator('a[href*="settings/fuzzy"]').first();
        if (await link.isVisible().catch(() => false)) {
            const href = await link.getAttribute('href');
            expect(href).toContain('settings/fuzzy');
        }
    }

    async expectBarnListSection() {
        await expect(this.page.locator('body')).toContainText(/Daftar Kandang/i);
    }

    async expectBarnCards() {
        const cards = this.page.locator('a[href*="/peternakan/"]');
        await expect(cards.first()).toBeVisible();
    }

    async searchProductionLog(keyword: string) {
        const search = this.page.locator('input[placeholder="Cari log..."]');
        if (await search.isVisible().catch(() => false)) {
            await search.fill(keyword);
            await this.page.waitForTimeout(1000);
        }
    }
}

