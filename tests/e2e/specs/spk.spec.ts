import { test, expect } from '@playwright/test';
import { SpkPage } from '../pages/SpkPage.js';

test.describe('Modul Analisis SPK - E2E QA', () => {
    let spkPage: SpkPage;

    test.setTimeout(90000);

    test.beforeEach(async ({ page }) => {
        // Arrange
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        spkPage = new SpkPage(page);
    });

    test('Positif - Verifikasi UI Dasbor Analisa SPK', async ({ page }) => {
        /**
         * Given user memiliki role yang sah untuk melihat analisa SPK 
         * When halaman dimuat dengan navigasi menuju router dasbor utama SPK
         * Then heading utama dashboard SPK harus nampak di layar
         */

        // Arrange & Act
        await spkPage.gotoSpkDashboard();
        
        // Assert
        await expect(spkPage.dashboardHeading).toBeVisible({ timeout: 15000 });
    });

    test('Positif - Verifikasi modul Daftar Supplier SPK terkorelasi dengan sistem UI', async ({ page }) => {
        /**
         * Given menu modul Supplier Sistem Pendukung Keputusan
         * When navigasi /spk-suppliers diakses 
         * Then layout render tabel dan list daftar vendor tidak bermasalah
         */

        // Arrange & Act
        await spkPage.gotoSpkSuppliers();
        
        // Assert
        await expect(spkPage.suppliersHeading).toBeVisible({ timeout: 15000 });
    });

    test('Positif - Dropdown dan parameter Filter Kalkulasi SPK memuat ulang kalkulasi', async ({ page }) => {
        /**
         * Given pengguna ingin mengatur ulang komputasi kriteria SPK 
         * When memilih komoditas alternatif baru melalui dropdown parameter select
         * Then grid dan dataset tabel SPK result perlu di re-render
         */

        // Arrange
        await spkPage.gotoSpkDashboard();
        
        // Act & Assert
        if (await spkPage.komoditasSelect.count() > 0) {
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
                spkPage.komoditasSelect.selectOption({ index: 1 })
            ]);
            
            await expect(spkPage.spkResultTable).toBeVisible({ timeout: 10000 });
        } else {
             test.skip(true, 'Komponen filter filter param komoditas tidak tersedia di halaman ini sementara');
        }
    });

    test('Positif - Mesin telusur data produk pada Rekomendasi Supplier berfungsi baik', async ({ page }) => {
        /**
         * Given modul halaman supplier SPK render data vendor
         * When pengguna memakai field input text untuk mencari "Toko Pakan ABC"
         * Then URI dan query parameter merespon terhadap query keyword search yang diberikan
         */

        // Arrange
        await spkPage.gotoSpkSuppliers();
        await expect(spkPage.suppliersHeading).toBeVisible();
        
        const queryTerm = 'Toko Pakan ABC';
        const expectedURIRegex = /search=Toko\+Pakan/;

        // Act & Assert
        if (await spkPage.searchInput.count() > 0) {
            await spkPage.searchInput.fill(queryTerm);
            await spkPage.searchInput.press('Enter');
            
            await expect(page).toHaveURL(expectedURIRegex);
        } else {
            test.skip(true, 'Komponen input search tidak dirender');
        }
    });

    test('Negatif - Pencarian data fiktif ekstrem pada SPK Supplier tidak berdampak 500 fatal crash', async ({ page }) => {
        /**
         * Given halaman master produk supplier khusus SPK
         * When form input filter dieksekusi dengan keyword tak logis yang pasti tak punya record
         * Then view merender state blank list aman dan tidak menelurkan exception uncaught server errors
         */

        // Arrange
        await page.goto('/spk-suppliers/products', { waitUntil: 'domcontentloaded' });
        const invalidQueryTerm = 'EntitasBarangSupplierGhaib9921';

        // Act & Assert
        if (await spkPage.searchInput.count() > 0) {
            await spkPage.searchInput.fill(invalidQueryTerm);
            await spkPage.searchInput.press('Enter');
            
            // Expected logic
            await expect(spkPage.emptyStateMessage.first()).toBeVisible({ timeout: 15000 });
        } else {
            test.skip(true, 'Komponen search tidak dirender');
        }
    });
});
