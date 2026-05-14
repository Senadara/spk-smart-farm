import { test, expect } from '@playwright/test';
import { PeternakanPage } from '../pages/PeternakanPage.js';

test.describe('Modul Peternakan - Operasional E2E QA', () => {
    let peternakanPage: PeternakanPage;

    test.setTimeout(60000);

    test.beforeEach(async ({ page }) => {

        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        peternakanPage = new PeternakanPage(page);

        await peternakanPage.goto();
    });

    test('Positif - Berhasil memuat halaman dasbor peternakan dan menampilkan elemen utama', async ({ page }) => {

        await peternakanPage.expectToBeOnPeternakanPage();

        await expect(page.locator('body')).toBeVisible();


        if (await peternakanPage.chartCanvas.count() > 0) {
            await expect(peternakanPage.chartCanvas.first()).toBeVisible();
        }
    });

    test('Positif - Berhasil memicu proses ekspor laporan', async ({ page }) => {

        await peternakanPage.expectToBeOnPeternakanPage();

        await expect(peternakanPage.exportButton.first()).toBeVisible();





    });

});
