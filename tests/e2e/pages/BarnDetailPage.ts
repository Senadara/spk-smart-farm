import { Page, Locator, expect } from '@playwright/test';
import { PeternakanPage } from './PeternakanPage.js';

export class BarnDetailPage {
    readonly page: Page;
    readonly peternakan: PeternakanPage;

    constructor(page: Page) {
        this.page = page;
        this.peternakan = new PeternakanPage(page);
    }

    /** Navigasi tegas ke detail kandang pertama; gagal bila tidak ada kandang. */
    async navigateToFirstBarn() {
        await this.peternakan.goto();
        const link = this.page.locator('a[href*="/peternakan/"]').first();
        await expect(link).toBeVisible({ timeout: 30000 });
        await link.click();
        await expect(this.page).toHaveURL(/.*\/peternakan\/[^/]+/, { timeout: 30000 });
        await this.page.waitForLoadState('domcontentloaded');
        return true;
    }

    async expectHeader() {
        const body = await this.page.locator('body').textContent() || '';
        expect(body).toContain('Kembali');
    }

    async expectOverviewFields() {
        const body = await this.page.locator('body').textContent() || '';
        const fields = ['Lokasi', 'Breed', 'Tanggal Masuk', 'Populasi'];
        let found = 0;
        for (const f of fields) {
            if (body.includes(f)) found++;
        }
        expect(found).toBeGreaterThanOrEqual(2);
    }

    async expectKpiCards() {
        const body = await this.page.locator('body').textContent() || '';
        const kpis = ['HDP', 'FCR', 'Feed Intake', 'Egg Mass', 'Mortal'];
        let found = 0;
        for (const k of kpis) {
            if (body.includes(k)) found++;
        }
        // KPI produktivitas kandang bergantung konfigurasi Pengaturan Fuzzy; bila belum aktif
        // halaman menampilkan notice. Terima kedua kondisi secara jujur.
        const inactive = /belum aktif|belum dikonfigurasi/i.test(body);
        if (found < 2) {
            console.log('KPI_KANDANG:: metrik produktivitas belum aktif (Pengaturan Fuzzy belum dikonfigurasi)');
        }
        expect(found >= 2 || inactive).toBeTruthy();
    }

    async expectSensorFilter() {
        const select = this.page.locator('select[x-model="sensorFilter"]');
        await expect(select).toBeVisible();
        const count = await select.locator('option').count();
        expect(count).toBeGreaterThanOrEqual(3);
    }

    async expectSensorCards() {
        const body = await this.page.locator('body').textContent() || '';
        const sensors = ['Suhu', 'Kelembapan', 'Amonia'];
        let found = 0;
        for (const s of sensors) {
            if (body.includes(s)) found++;
        }
        expect(found).toBeGreaterThanOrEqual(2);
    }

    async expectProdChart() {
        const body = await this.page.locator('body').textContent() || '';
        expect(body).toContain('7H');
        const body2 = await this.page.locator('body').textContent() || '';
        expect(body2).toContain('14H');
    }

    async expectProdFilter() {
        const select = this.page.locator('select[x-model="prodFilter"]');
        await expect(select).toBeVisible();
        const count = await select.locator('option').count();
        expect(count).toBeGreaterThanOrEqual(1);
    }

    async expectEggQuality() {
        // Section kualitas telur: "Egg Production"/"Distribusi"/"Produksi Telur" bila ada data,
        // atau notice belum aktif bila produktivitas belum dikonfigurasi.
        const body = await this.page.locator('body').textContent() || '';
        const hasSection = /Egg Production|Produksi Telur|Distribusi|Kualitas Telur/i.test(body);
        const inactive = /belum aktif|belum dikonfigurasi/i.test(body);
        if (!hasSection) {
            console.log('EGG_QUALITY:: section distribusi telur belum aktif (produktivitas belum dikonfigurasi)');
        }
        expect(hasSection || inactive).toBeTruthy();
    }

    async expectEggRates() {
        const body = await this.page.locator('body').textContent() || '';
        const hasRate = body.includes('Pecah') || body.includes('Kotor') || body.includes('Reject');
        expect(hasRate).toBeTruthy();
    }

    async expectSpkMessages() {
        const body = await this.page.locator('body').textContent() || '';
        const items = ['Lingkungan', 'Produktivitas', 'Pakan', 'Kesehatan'];
        let found = 0;
        for (const s of items) {
            if (body.includes(s)) found++;
        }
        expect(found).toBeGreaterThanOrEqual(2);
    }

    async expectActivityLog() {
        await expect(this.page.locator('body')).toContainText(/Aktivitas/i);
    }

    async expectExportButton() {
        // Tombol export produktivitas di header detail kandang berlabel "Settlement / PDF"
        // (link ke route peternakan.settlement → PDF), hanya tampil untuk role pjawab/owner/admin.
        const btn = this.page.locator('a[href*="settlement"], button, a')
            .filter({ hasText: /Settlement|PDF|Export|Ekspor|Unduh/i }).first();
        await expect(btn).toBeVisible();
    }
}
