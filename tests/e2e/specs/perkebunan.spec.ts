import { test, expect } from '@playwright/test';
import { PerkebunanPage } from '../pages/PerkebunanPage.js';

test.describe('Modul Perkebunan - E2E QA', () => {
    let perkebunanPage: PerkebunanPage;

    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }, testInfo) => {
        // Arrange
        testInfo.setTimeout(120000);
        page.setDefaultNavigationTimeout(120000);
        page.setDefaultTimeout(120000);

        // Blocker akses Vite HMR
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        perkebunanPage = new PerkebunanPage(page);
        
        // Act
        await perkebunanPage.goto();
    });

    test('Positif - Verifikasi render komponen utama Manajemen Perkebunan', async ({ page }) => {
        /**
         * Given user memiliki role yang sah untuk mengakses modul Perkebunan
         * When halaman dimuat dengan instruksi routing /perkebunan
         * Then heading utama, area alert, menu rank blok, dan section utama harus dirender
         */
        
        // Arrange
        const expectedUrlPattern = /.*\/perkebunan/;
        
        // Act & Assert
        await expect(page).toHaveURL(expectedUrlPattern);
        await perkebunanPage.expectMainSectionsVisible();
        await perkebunanPage.expectSummaryVisible();
    });

    test('Positif - Ranking blok kebun dapat mengkalkulasi minimal 3 baris blok area', async ({ page }) => {
        /**
         * Given sub-sistem perankingan telah dieksekusi di backend
         * When pengguna melirik tabel ranking blok evaluasi
         * Then UI merender baris list ranking dengan metrik minimal 3 buah data referensial
         */
         
        // Arrange
        const minExpectedRows = 3; 

        // Act
        const rowCount = await perkebunanPage.getRankingRowCount();
        
        // Assert
        expect(rowCount).toBeGreaterThanOrEqual(minExpectedRows);
    });

    test('Positif - Greenhouse A dievaluasi dan menempati ranking visibel dengan stat "Disetujui"', async ({ page }) => {
        /**
         * Given Greenhouse A memiliki skor prioritas tinggi
         * When melihat posisi pertama pada data tabel
         * Then harus mencantumkan teks "Greenhouse A" dan info aproval "Disetujui"
         */
         
        // Arrange
        const targetRow = perkebunanPage.rankingRows.first();

        // Act
        const tableTextContext = await targetRow.textContent();

        // Assert
        expect(tableTextContext).toContain('Greenhouse A');
        expect(tableTextContext).toMatch(/Disetujui/i);
    });

    test('Positif - Kartu parameter sensor vital mendeteksi node Greenhouse A, B, dan C', async ({ page }) => {
        /**
         * Given koneksi IoT Sensor beroperasi normal
         * When memuat section Kartu Sensor UI
         * Then representasi data Greenhouse A, B, C dapat dilihat jelas
         */

        // Arrange
        const greenhouseA = page.getByRole('heading', { name: 'Greenhouse A' });
        const greenhouseB = page.getByRole('heading', { name: 'Greenhouse B' });
        const greenhouseC = page.getByRole('heading', { name: 'Greenhouse C' });

        // Act & Assert
        await expect(greenhouseA).toBeVisible({ timeout: 15000 });
        await expect(greenhouseB).toBeVisible();
        await expect(greenhouseC).toBeVisible();
    });

    test('Negatif - Alert notification pada Perkebunan tetap merender jika terdapat anomali iklim parah', async ({ page }) => {
        /**
         * Given terdapat exception suhu melebihi toleransi di node IoT (contoh suhu lebih dari 32 derajat)
         * When menu Alert di render
         * Then pesan exception 'suhu... melebihi' dapat dilihat admin user Perkebunan
         */

        // Arrange
        const expectedAlertTextPattern = /Suhu Greenhouse B melebihi 32/i;
        
        // Act
        const alertSectionContent = await page.textContent('body'); // or scoping to alert card area
        
        // Assert
        // Pastikan UI tidak throw 500 error ketika render warning kritis
        expect(alertSectionContent).toMatch(expectedAlertTextPattern);
    });
});
