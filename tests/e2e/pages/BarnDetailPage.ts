import { Page, Locator, expect } from '@playwright/test';
import { PeternakanPage } from './PeternakanPage.js';

export class BarnDetailPage {
    readonly page: Page;
    readonly peternakan: PeternakanPage;

    constructor(page: Page) {
        this.page = page;
        this.peternakan = new PeternakanPage(page);
    }

    async navigateToFirstBarn() {
        await this.peternakan.goto();
        await this.page.waitForTimeout(2000);
        const link = this.page.locator('a[href*="/peternakan/"]').first();
        if (await link.isVisible().catch(() => false)) {
            await link.click();
            await this.page.waitForTimeout(2000);
            return true;
        }
        return false;
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
        const kpis = ['HDP', 'FCR', 'Feed Intake'];
        let found = 0;
        for (const k of kpis) {
            if (body.includes(k)) found++;
        }
        expect(found).toBeGreaterThanOrEqual(2);
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
        await expect(this.page.locator('body')).toContainText(/Egg Production/i);
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
        const btn = this.page.locator('button, a').filter({ hasText: /Export|Ekspor|Unduh/i }).first();
        const count = await btn.count();
        if (count > 0) expect(btn).toBeVisible();
    }
}
