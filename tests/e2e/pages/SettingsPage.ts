import { Page, Locator, expect } from '@playwright/test';

/**
 * SettingsPage — halaman "/settings" (Pengaturan Sistem).
 * Desain sekarang: kartu teks sederhana (tanpa ikon/h3/gradient), grid 2 kolom:
 *  - Data Master (a -> /data-master)
 *  - IoT (a#iot-settings -> /iot/devices, "Buka Setup IoT")
 *  - Aturan SPK Kandang (a -> /settings/fuzzy, khusus pjawab)
 *  - Scheduler Indikasi Kesehatan (a -> /settings/health-scheduler)
 *  - DSS Supplier AHP-SAW (div) dengan tombol: "Atur Bobot" (config), "Ranking SAW" (dashboard), "Cari Barang" (products)
 */
export class SettingsPage {
    readonly page: Page;
    readonly pageTitle: Locator;
    readonly dataMasterCard: Locator;
    readonly iotCard: Locator;
    readonly iotDevicesCard: Locator;
    readonly fuzzyCard: Locator;
    readonly dssSection: Locator;
    readonly dssAturBobot: Locator;
    readonly dssRankingSaw: Locator;
    readonly dssCariBarang: Locator;

    constructor(page: Page) {
        this.page = page;
        this.pageTitle = page.locator('text=Pengaturan Sistem');
        this.dataMasterCard = page.locator('a[href*="/data-master"]').first();
        this.iotCard = page.locator('#iot-settings');
        this.iotDevicesCard = page.locator('#iot-settings');
        this.fuzzyCard = page.locator('a').filter({ hasText: /Aturan SPK Kandang/i });
        this.dssSection = page.locator('text=DSS Supplier AHP-SAW');
        this.dssAturBobot = page.locator('a[href*="/spk-suppliers/dss/config"]').first();
        this.dssRankingSaw = page.locator('a[href*="/spk-suppliers/dss/dashboard"]').first();
        this.dssCariBarang = page.locator('a[href*="/spk-suppliers/products"]').first();
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
        await expect(this.iotCard).toBeVisible();
    }

    async expectFuzzyCardVisible() {
        await expect(this.fuzzyCard).toBeVisible();
    }

    async clickDataMasterCard() {
        await this.dataMasterCard.click();
    }

    async clickIotCard() {
        await this.iotCard.click();
    }

    async clickFuzzyCard() {
        await this.fuzzyCard.click();
    }
}
