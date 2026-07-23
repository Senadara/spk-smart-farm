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
        this.catalogHeading = page.getByRole('heading', { name: /Cari toko, pilih barang, pantau pesanan/i });
        this.productsHeading = page.getByRole('heading', { name: /^\s*Cari Barang\s*$/i });
        this.dssConfigHeading = page.getByRole('heading', { name: /Atur Bobot Kriteria Supplier/i });
        this.dssDashboardHeading = page.getByRole('heading', { name: /Ranking Supplier dengan SAW/i });
        this.configForm = page.locator('#ahp-form');
        this.rankingTable = page.getByRole('heading', { name: /Ranking Supplier/i });
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
        // Tombol submit form AHP: "Hitung & Simpan Bobot" (hanya tampil bila user resolved & >=2 kriteria).
        const btn = this.page.getByRole('button', { name: /Hitung.*Simpan Bobot/i });
        if (await btn.count() === 0) return false;
        await btn.first().click();
        return true;
    }

    async expectDssDashboardReady() {
        await expect(this.dssDashboardHeading).toBeVisible();
        // Dashboard SAW baru: pemilih produk selalu ada (tidak lagi chart pie/radar).
        await expect(this.page.locator('select[name="produk_id"]')).toBeVisible();
    }

    async openFirstSupplierFromProducts() {
        await this.page.getByRole('link', { name: /Buka Toko/i }).first().click();
    }
}