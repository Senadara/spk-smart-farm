import { test, expect } from '@playwright/test';
import { PeternakanPage } from '../pages/PeternakanPage.js';

/**
 * Modul Dashboard Peternakan — E2E (diperketat, selaras implementasi terbaru Nanda)
 * /peternakan (index): heading "Decision Support & Operations", filter komoditas, KPI cards,
 *   Barn Environment, Ringkasan SPK Hari Ini, Daftar Kandang, Daily Production Log.
 * /peternakan/{id} (show): detail kandang.
 * CATATAN: tombol "Run Full Evaluation" & panel Fuzzy Decision Engine sudah TIDAK ada di
 *   dashboard ini (redesign) — evaluasi penuh kini di halaman SPK Dashboard.
 */

test.describe('Modul Dashboard Peternakan - E2E Tests', () => {
    let peternakanPage: PeternakanPage;

    test.setTimeout(120000);

    test.beforeEach(async ({ page }) => {
        page.setDefaultNavigationTimeout(120000);
        page.setDefaultTimeout(30000);
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());
        peternakanPage = new PeternakanPage(page);
        await peternakanPage.goto();
    });

    // ── DASHBOARD INDEX ──────────────────────────────────────────────
    test('Positif - LOAD dashboard dengan komponen utama terlihat', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await expect(peternakanPage.heading).toBeVisible();
        await expect(peternakanPage.komoditasSelect).toBeVisible();
    });

    test('Positif - Kartu KPI ditampilkan', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        const body = await page.locator('body').textContent() || '';
        // KPI dashboard peternakan (kpi-card): HDP %, FCR, Egg Mass, Feed Intake, Mortality
        expect(body).toContain('HDP');
        expect(body).toContain('FCR');
        expect(body).toMatch(/Egg Mass|Feed Intake/);
    });

    test('Positif - Ringkasan SPK Hari Ini ditampilkan', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await expect(peternakanPage.spkSummaryHeading).toBeVisible();
        await expect(peternakanPage.bukaSpkLink).toBeVisible();
        // Status label SPK tampil (Aman/Perhatian/Kritis/Optimal/Baik/Waspada/Buruk)
        const body = await page.locator('body').textContent() || '';
        expect(body).toMatch(/Optimal|Baik|Waspada|Buruk|Aman|Perhatian|Kritis/);
    });

    test('Negatif - NO undefined/null values in KPI displays', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        const bodyText = await page.locator('body').textContent() || '';
        expect(bodyText).not.toContain('undefined%');
        expect(bodyText).not.toContain('undefined');
        expect(bodyText).not.toContain('NaN');
    });

    // ── KOMODITAS FILTER ─────────────────────────────────────────────
    test('Positif - Filter komoditas perbarui dashboard', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await expect(peternakanPage.komoditasSelect).toBeVisible();
        const optionCount = await peternakanPage.komoditasSelect.locator('option').count();
        expect(optionCount).toBeGreaterThanOrEqual(1);

        if (optionCount > 1) {
            // Pilih komoditas kedua → dashboard reload dengan ?komoditas=<id>
            await Promise.all([
                page.waitForNavigation({ url: /komoditas=/, timeout: 60000 }),
                peternakanPage.komoditasSelect.selectOption({ index: 1 }),
            ]);
            expect(page.url()).toContain('komoditas=');
            await expect(peternakanPage.heading).toBeVisible();
        }
    });

    // ── CHART ────────────────────────────────────────────────────────
    test('Positif - Grafik performa (canvas) terender', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await expect(peternakanPage.chartCanvas.first()).toBeVisible();
    });

    // ── BARN ENVIRONMENT ─────────────────────────────────────────────
    test('Positif - Section Barn Environment & pemilih kandang berfungsi', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await peternakanPage.expectBarnEnvironmentSection();
        const barnCount = await peternakanPage.getBarnButtonCount();
        expect(barnCount).toBeGreaterThanOrEqual(1);
        // Klik tombol kandang pertama tidak menyebabkan error
        await peternakanPage.barnButtons.first().click();
        await expect(peternakanPage.detailLink.first()).toBeVisible();
    });

    test('Positif - Label sensor Suhu, Kelembapan, Amonia tampil', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await peternakanPage.expectSensorLabels(['Suhu', 'Kelembapan', 'Amonia']);
    });

    // ── EVALUASI SPK (REDESIGN) ──────────────────────────────────────
    test('Positif - Ringkasan SPK menyediakan akses ke Analisa SPK & Penugasan', async ({ page }) => {
        /**
         * Setelah redesign, tombol "Run Full Evaluation" tidak lagi di dashboard peternakan.
         * Dashboard menyediakan ringkasan SPK + tautan "Buka Analisa SPK" dan "Lihat Penugasan".
         */
        await peternakanPage.expectToBeOnPeternakanPage();
        await expect(peternakanPage.bukaSpkLink).toBeVisible();
        await expect(page.getByRole('link', { name: /Lihat Penugasan/i })).toBeVisible();
    });

    // ── PRODUCTION LOG ───────────────────────────────────────────────
    test('Positif - Section Daily Production Log & pencarian', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await expect(peternakanPage.productionLogHeading).toBeVisible();
        await peternakanPage.searchProductionLog('test');
        const body = await page.locator('body').textContent() || '';
        expect(body).not.toContain('undefined');
    });

    // ── DAFTAR KANDANG ───────────────────────────────────────────────
    test('Positif - Daftar Kandang menampilkan kartu kandang', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await peternakanPage.expectBarnListSection();
        await peternakanPage.expectBarnCards();
    });

    // ── BARN DETAIL PAGE ─────────────────────────────────────────────
    test('Positif - Navigasi ke detail kandang', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        const kandangLink = peternakanPage.kandangLinks.first();
        await expect(kandangLink).toBeVisible();
        await kandangLink.click();
        await expect(page).toHaveURL(/.*\/peternakan\/[^/]+/);
    });

    test('Positif - Detail kandang menampilkan data sensor & tombol Kembali', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await peternakanPage.kandangLinks.first().click();
        await expect(page).toHaveURL(/.*\/peternakan\/[^/]+/);
        const body = await page.locator('body').textContent() || '';
        expect(body).toMatch(/Suhu|Kelembapan|Sensor/);
        await expect(page.getByRole('link', { name: /Kembali/i }).or(page.getByRole('button', { name: /Kembali/i })).first()).toBeVisible();
    });

    test('Positif - Detail kandang menampilkan analisis SPK', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await peternakanPage.kandangLinks.first().click();
        await expect(page).toHaveURL(/.*\/peternakan\/[^/]+/);
        const body = await page.locator('body').textContent() || '';
        expect(body).toMatch(/Lingkungan|Produktivitas|Kesehatan|SPK/);
    });

    test('Positif - Link kembali ke dashboard peternakan', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await peternakanPage.kandangLinks.first().click();
        await expect(page).toHaveURL(/.*\/peternakan\/[^/]+/);
        const back = page.getByRole('link', { name: /Kembali/i }).first();
        await expect(back).toBeVisible();
        await back.click();
        await expect(page).toHaveURL(/.*\/peternakan(\?|$)/);
    });

    // ── INTEGRATION ──────────────────────────────────────────────────
    test('Integration - Dashboard → Detail Kandang → Kembali', async ({ page }) => {
        await peternakanPage.expectToBeOnPeternakanPage();
        await expect(peternakanPage.heading).toBeVisible();
        await peternakanPage.kandangLinks.first().click();
        await expect(page).toHaveURL(/.*\/peternakan\/[^/]+/);
        const back = page.getByRole('link', { name: /Kembali/i }).first();
        await expect(back).toBeVisible();
        await back.click();
        await expect(page).toHaveURL(/.*\/peternakan(\?|$)/);
    });
});
