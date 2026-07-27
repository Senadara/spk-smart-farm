import { Page, Locator, expect } from '@playwright/test';

/**
 * Page Object — Dashboard Peternakan (/peternakan)
 * Diselaraskan dengan implementasi terbaru Nanda (dashboard redesign):
 *  - Heading: "Decision Support & Operations"
 *  - Filter komoditas (satu-satunya <select> di header)
 *  - KPI cards (HDP %, FCR, Egg Mass, Feed Intake, Mortality, Umur Biologis)
 *  - Section "Barn Environment" (tombol kandang + sensor Suhu/Kelembapan/Amonia/Lux + "Lihat Detail Kandang")
 *  - Section "Ringkasan SPK Hari Ini" (spider chart, indikator, "Buka Analisa SPK", "Lihat Penugasan")
 *  - Section "Daftar Kandang (Unit Budidaya)" (kartu kandang link ke /peternakan/{id})
 *  - Section "Daily Production Log" (tabel + input "Cari log...")
 * CATATAN: tombol "Run Full Evaluation" & panel Fuzzy Decision Engine sudah TIDAK ada
 * di dashboard ini (dipindah ke SPK Dashboard); lihat komponen fuzzy-decision-engine.
 */
export class PeternakanPage {
    readonly page: Page;
    readonly heading: Locator;
    readonly komoditasSelect: Locator;
    readonly barnButtons: Locator;
    readonly detailLink: Locator;
    readonly spkSummaryHeading: Locator;
    readonly bukaSpkLink: Locator;
    readonly daftarKandangHeading: Locator;
    readonly kandangLinks: Locator;
    readonly productionLogHeading: Locator;
    readonly productionLogSearch: Locator;
    readonly chartCanvas: Locator;

    constructor(page: Page) {
        this.page = page;
        this.heading = page.getByRole('heading', { name: /Decision Support & Operations/i });
        this.komoditasSelect = page.locator('select').first();
        this.barnButtons = page.locator('button[title="Klik untuk melihat sensor kandang ini"]');
        this.detailLink = page.getByRole('link', { name: /Lihat Detail Kandang/i });
        this.spkSummaryHeading = page.getByRole('heading', { name: /Ringkasan SPK Hari Ini/i });
        this.bukaSpkLink = page.getByRole('link', { name: /Buka Analisa SPK/i });
        this.daftarKandangHeading = page.getByRole('heading', { name: /Daftar Kandang/i });
        this.kandangLinks = page.locator('a[href*="/peternakan/"]');
        this.productionLogHeading = page.getByRole('heading', { name: /Daily Production Log/i });
        this.productionLogSearch = page.getByPlaceholder('Cari log...');
        this.chartCanvas = page.locator('canvas');
    }

    async goto() {
        await this.page.goto('/peternakan', { waitUntil: 'domcontentloaded' });
    }

    async gotoWithInsahKomoditas() {
        await this.page.goto('/peternakan?komoditas=tidakada', { waitUntil: 'domcontentloaded' });
    }

    async expectToBeOnPeternakanPage() {
        await expect(this.page).toHaveURL(/.*peternakan/);
        await expect(this.heading).toBeVisible();
    }

    async expectNoKomoditasMessage() {
        const body = await this.page.locator('body').textContent() || '';
        expect(body).not.toContain('Fatal error');
        expect(body).not.toContain('Error 500');
        expect(body).not.toContain('SQLSTATE');
    }

    async expectKpiTrendIndicators() {
        // KPI cards dirender oleh komponen x-peternakan.kpi-card
        const body = await this.page.locator('body').textContent() || '';
        expect(body).toMatch(/HDP|FCR|Egg Mass|Feed Intake|Mortal/i);
    }

    async expectBarnEnvironmentSection() {
        await expect(this.page.getByRole('heading', { name: /Barn Environment/i })).toBeVisible();
    }

    async expectSensorLabels(labels: string[]) {
        // Panel "Barn Environment" harus tampil
        await this.expectBarnEnvironmentSection();
        // Pilih kandang pertama agar ringkasan sensor terisi (jika ada tombol kandang)
        if (await this.barnButtons.count() > 0) {
            await this.barnButtons.first().click().catch(() => { });
            await this.page.waitForTimeout(800);
        }
        const body = await this.page.locator('body').textContent() || '';
        const anyLabel = labels.some(l => body.includes(l));
        // Bila data sensor belum tersedia, panel menampilkan notice "belum aktif dari Data Master"
        const belumAktif = /Parameter lingkungan belum aktif/i.test(body);
        if (!anyLabel) {
            console.log('SENSOR_LABELS:: label sensor tidak tampil; state=' + (belumAktif ? 'belum aktif dari Data Master (tanpa data sensor)' : 'tidak diketahui'));
        }
        expect(anyLabel || belumAktif).toBeTruthy();
    }

    async expectDetailLink() {
        await expect(this.detailLink.first()).toBeVisible();
    }

    async getBarnButtonCount(): Promise<number> {
        return await this.barnButtons.count();
    }

    async expectFuzzySection() {
        // Setelah redesign, ringkasan SPK di dashboard = "Ringkasan SPK Hari Ini"
        await expect(this.spkSummaryHeading).toBeVisible();
    }

    async expectFuzzyGearLink() {
        // Link menuju konfigurasi SPK / analisa
        await expect(this.bukaSpkLink).toBeVisible();
    }

    async expectBarnListSection() {
        await expect(this.daftarKandangHeading).toBeVisible();
    }

    async expectBarnCards() {
        await expect(this.kandangLinks.first()).toBeVisible();
        const count = await this.kandangLinks.count();
        expect(count).toBeGreaterThanOrEqual(1);
    }

    async searchProductionLog(query: string) {
        await expect(this.productionLogSearch).toBeVisible();
        await this.productionLogSearch.fill(query);
        await this.page.waitForTimeout(400);
    }
}
