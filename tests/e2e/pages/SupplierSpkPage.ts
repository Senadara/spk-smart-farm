import { Locator, Page, expect } from '@playwright/test';

export class SupplierSpkPage {
    readonly page: Page;
    readonly catalogHeading: Locator;
    readonly productsHeading: Locator;
    readonly dssConfigHeading: Locator;
    readonly dssDashboardHeading: Locator;
    readonly configForm: Locator;
    readonly rankingTable: Locator;

    constructor(page: Page) {
        this.page = page;
        this.catalogHeading = page.getByRole('heading', { name: /Cari toko, pilih barang, pantau pesanan|Katalog Supplier Peternakan/i });
        this.productsHeading = page.getByRole('heading', { name: /Cari Barang|Perbandingan Produk Supplier/i });
        this.dssConfigHeading = page.getByRole('heading', { name: /Atur Bobot Kriteria Supplier|Bobot Kriteria \(AHP\)/i });
        this.dssDashboardHeading = page.getByRole('heading', { name: /Ranking supplier - SAW/i });
        this.configForm = page.locator('#ahp-form');
        this.rankingTable = page.getByRole('table').filter({ has: page.getByText('Peringkat Supplier (SAW)') });
    }

    async gotoCatalog() {
        await this.page.goto('/spk-suppliers');
        await expect(this.page).toHaveURL(/.*\/spk-suppliers$/);
    }

    async gotoProducts() {
        await this.page.goto('/spk-suppliers/products');
        await expect(this.page).toHaveURL(/.*\/spk-suppliers\/products/);
    }

    async gotoDssConfig() {
        await this.page.goto('/spk-suppliers/dss/config');
        await expect(this.page).toHaveURL(/.*\/spk-suppliers\/dss\/config/);
    }

    async gotoDssDashboard() {
        await this.page.goto('/spk-suppliers/dss/dashboard');
        await expect(this.page).toHaveURL(/.*\/spk-suppliers\/dss\/dashboard/);
    }

    async expectCatalogReady() {
        await expect(this.catalogHeading).toBeVisible();
    }

    async expectProductsReady() {
        await expect(this.productsHeading).toBeVisible();
    }

    async expectDssConfigReady() {
        await expect(this.dssConfigHeading).toBeVisible();
        await expect(this.configForm).toBeVisible();
    }

    async submitDefaultAhpConfig() {
        await this.page.getByRole('button', { name: /Hitung bobot & Validasi konsistensi/i }).click();
    }

    async expectDssDashboardReady() {
        await expect(this.dssDashboardHeading).toBeVisible();
        await expect(this.page.locator('#weightsPie')).toBeVisible();
        await expect(this.page.locator('#weightsRadar')).toBeVisible();
    }

    async openFirstSupplierFromProducts() {
        await this.page.getByRole('link', { name: /Buka Toko/i }).first().click();
    }
}