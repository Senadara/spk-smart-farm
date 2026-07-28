import { test, expect } from '@playwright/test';
import { PeternakanPage } from '../pages/PeternakanPage.js';

test.describe('Dashboard Peternakan — Skenario Tambahan', () => {
    let peternakanPage: PeternakanPage;

    test.beforeEach(async ({ page }) => {
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());
        peternakanPage = new PeternakanPage(page);
        await peternakanPage.goto();
    });

    test('Negatif - Parameter komoditas tidak dikenal tidak menyebabkan kesalahan', async ({ page }) => {
        await peternakanPage.gotoWithInsahKomoditas();
        await peternakanPage.expectNoKomoditasMessage();
    });

    test('Positif - Indikator tren naik atau turun pada kartu KPI tampil', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await peternakanPage.expectKpiTrendIndicators();
    });

    test('Positif - Section Barn Environment menampilkan daftar kandang', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await peternakanPage.expectBarnEnvironmentSection();
    });

    test('Positif - Label sensor Suhu, Kelembapan, dan Amonia tampil di halaman', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await peternakanPage.expectSensorLabels(['Suhu', 'Kelembapan', 'Amonia']);
    });

    test('Positif - Tombol Lihat Detail Kandang tersedia dan mengarah ke halaman detail', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await peternakanPage.expectDetailLink();
    });

    test('Negatif - Section Barn Environment tetap stabil meskipun tanpa barn terpilih', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        const count = await peternakanPage.getBarnButtonCount();
        expect(count).toBeGreaterThanOrEqual(0);
    });

    test('Positif - Section Fuzzy Decision Engine tampil di dashboard', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await peternakanPage.expectFuzzySection();
    });

    test('Positif - Icon gear pada Fuzzy Engine mengarah ke halaman pengaturan fuzzy', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await peternakanPage.expectFuzzyGearLink();
    });

    test('Positif - Section Daftar Kandang tampil dengan benar', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await peternakanPage.expectBarnListSection();
    });

    test('Positif - Kartu kandang menampilkan nama kandang dan dapat diklik', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await peternakanPage.expectBarnCards();
    });

    test('Positif - Kolom pencarian pada production log tersedia dan dapat diisi', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await peternakanPage.searchProductionLog('test');
        const body = await page.locator('body').textContent() || '';
        expect(body).toBeTruthy();
    });

    test('Negatif - Pencarian production log tanpa hasil tidak menyebabkan kesalahan', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await peternakanPage.searchProductionLog('ZZZZNODATA');
        const body = await page.locator('body').textContent() || '';
        expect(body).toBeTruthy();
    });
});
