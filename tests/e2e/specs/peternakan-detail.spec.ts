import { test } from '@playwright/test';
import { BarnDetailPage } from '../pages/BarnDetailPage.js';

/**
 * Detail Kandang (/peternakan/{id}) — diperketat (navigasi tegas, tanpa guard lunak).
 * Setiap test benar-benar membuka detail kandang pertama lalu memverifikasi section nyata.
 */
test.describe('Detail Kandang — Skenario', () => {
    let barnDetail: BarnDetailPage;

    test.setTimeout(90000);

    test.beforeEach(async ({ page }) => {
        page.setDefaultTimeout(30000);
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());
        barnDetail = new BarnDetailPage(page);
        await barnDetail.navigateToFirstBarn();
    });

    test('Positif - Header barn menampilkan tombol Kembali', async () => {
        await barnDetail.expectHeader();
    });

    test('Positif - Grid overview menampilkan field data kandang', async () => {
        await barnDetail.expectOverviewFields();
    });

    test('Positif - Kartu KPI kandang menampilkan metrik produksi', async () => {
        await barnDetail.expectKpiCards();
    });

    test('Positif - Dropdown filter sensor trend tersedia', async () => {
        await barnDetail.expectSensorFilter();
    });

    test('Positif - Kartu sensor langsung Suhu, Kelembapan, Amonia tampil', async () => {
        await barnDetail.expectSensorCards();
    });

    test('Positif - Grafik tren produktivitas dengan tombol range', async () => {
        await barnDetail.expectProdChart();
    });

    test('Positif - Dropdown filter metrik produktivitas tersedia', async () => {
        await barnDetail.expectProdFilter();
    });

    test('Positif - Section kualitas telur menampilkan distribusi', async () => {
        await barnDetail.expectEggQuality();
    });

    test('Positif - Persentase telur pecah dan telur kotor tampil', async () => {
        await barnDetail.expectEggRates();
    });

    test('Positif - Kartu pesan SPK Lingkungan, Produktivitas, Pakan, Kesehatan tampil', async () => {
        await barnDetail.expectSpkMessages();
    });

    test('Positif - Timeline aktivitas kandang tampil', async () => {
        await barnDetail.expectActivityLog();
    });

    test('Positif - Tombol export produktivitas (Settlement / PDF) tersedia', async () => {
        await barnDetail.expectExportButton();
    });
});
