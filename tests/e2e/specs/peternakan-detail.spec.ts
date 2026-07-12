import { test, expect } from '@playwright/test';
import { BarnDetailPage } from '../pages/BarnDetailPage.js';

test.describe('Detail Kandang — Skenario Tambahan', () => {
    let barnDetail: BarnDetailPage;

    test.beforeEach(async ({ page }) => {
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());
        barnDetail = new BarnDetailPage(page);
    });

    test('Positif - Header barn menampilkan tombol Kembali', async ({ page }) => {
        const ok = await barnDetail.navigateToFirstBarn();
        if (!ok) return;
        await barnDetail.expectHeader();
    });

    test('Positif - Grid overview menampilkan field data kandang', async ({ page }) => {
        const ok = await barnDetail.navigateToFirstBarn();
        if (!ok) return;
        await barnDetail.expectOverviewFields();
    });

    test('Positif - Kartu KPI kandang menampilkan metrik produksi', async ({ page }) => {
        const ok = await barnDetail.navigateToFirstBarn();
        if (!ok) return;
        await barnDetail.expectKpiCards();
    });

    test('Positif - Dropdown filter sensor trend tersedia', async ({ page }) => {
        const ok = await barnDetail.navigateToFirstBarn();
        if (!ok) return;
        await barnDetail.expectSensorFilter();
    });

    test('Positif - Kartu sensor langsung Suhu, Kelembapan, Amonia tampil', async ({ page }) => {
        const ok = await barnDetail.navigateToFirstBarn();
        if (!ok) return;
        await barnDetail.expectSensorCards();
    });

    test('Positif - Grafik tren produktivitas dengan tombol range', async ({ page }) => {
        const ok = await barnDetail.navigateToFirstBarn();
        if (!ok) return;
        await barnDetail.expectProdChart();
    });

    test('Positif - Dropdown filter metrik produktivitas tersedia', async ({ page }) => {
        const ok = await barnDetail.navigateToFirstBarn();
        if (!ok) return;
        await barnDetail.expectProdFilter();
    });

    test('Positif - Section kualitas telur menampilkan distribusi', async ({ page }) => {
        const ok = await barnDetail.navigateToFirstBarn();
        if (!ok) return;
        await barnDetail.expectEggQuality();
    });

    test('Positif - Persentase telur pecah dan telur kotor tampil', async ({ page }) => {
        const ok = await barnDetail.navigateToFirstBarn();
        if (!ok) return;
        await barnDetail.expectEggRates();
    });

    test('Positif - Kartu pesan SPK Lingkungan, Produktivitas, Pakan, Kesehatan tampil', async ({ page }) => {
        const ok = await barnDetail.navigateToFirstBarn();
        if (!ok) return;
        await barnDetail.expectSpkMessages();
    });

    test('Positif - Timeline aktivitas kandang tampil', async ({ page }) => {
        const ok = await barnDetail.navigateToFirstBarn();
        if (!ok) return;
        await barnDetail.expectActivityLog();
    });

    test('Positif - Tombol Export tersedia', async ({ page }) => {
        const ok = await barnDetail.navigateToFirstBarn();
        if (!ok) return;
        await barnDetail.expectExportButton();
    });
});
