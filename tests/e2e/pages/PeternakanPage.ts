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
}

