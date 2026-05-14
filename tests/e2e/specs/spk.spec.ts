import { test, expect } from '@playwright/test';
import { SpkPage } from '../pages/SpkPage.js';

test.describe('Modul Analisa SPK - E2E QA', () => {
    let spkPage: SpkPage;

    test.setTimeout(60000);

    test.beforeEach(async ({ page }) => {
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        spkPage = new SpkPage(page);
    });

    test('Positif - Berhasil memuat dasbor analisa SPK', async ({ page }) => {
        await spkPage.gotoSpkDashboard();
        
        await expect(spkPage.dashboardHeading).toBeVisible();
    });

    test('Positif - Berhasil memuat halaman daftar supplier SPK', async ({ page }) => {
        await spkPage.gotoSpkSuppliers();
        
        await expect(spkPage.suppliersHeading).toBeVisible();
    });

    test('Positif - Fitur filter SPK memuat ulang tabel hasil', async ({ page }) => {
        await spkPage.gotoSpkDashboard();
        
        if (await spkPage.komoditasSelect.count() > 0) {
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
                spkPage.komoditasSelect.selectOption({ index: 1 })
            ]);
            
            await expect(spkPage.spkResultTable).toBeVisible();
        }
    });

    test('Positif - Penelusuran di modul rekomendasi supplier berjalan', async ({ page }) => {
        await spkPage.gotoSpkSuppliers();
        await expect(spkPage.suppliersHeading).toBeVisible();
        
        if (await spkPage.searchInput.count() > 0) {
            await spkPage.searchInput.fill('Toko Pakan ABC');
            await spkPage.searchInput.press('Enter');
            await expect(page).toHaveURL(/search=Toko\+Pakan/);
        }
    });

    test('Negatif - Pencarian data yang tidak ditemukan tidak menyebabkan crash', async ({ page }) => {
        await page.goto('/spk-suppliers/products');
        
        if (await spkPage.searchInput.count() > 0) {
            await spkPage.searchInput.fill('BarangGhaibTidakAda123');
            await spkPage.searchInput.press('Enter');
            
            await expect(spkPage.emptyStateMessage.first()).toBeVisible();
        }
    });
});
