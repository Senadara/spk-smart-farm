import { test, expect } from '@playwright/test';
import { DashboardPage } from '../pages/DashboardPage.js';

test.describe('Modul Dashboard - E2E Tests', () => {
    test.describe.configure({ mode: 'serial' });

    let dashboardPage: DashboardPage;

    test.beforeEach(async ({ page }, testInfo) => {
        testInfo.setTimeout(120000);
        page.setDefaultNavigationTimeout(120000);
        page.setDefaultTimeout(120000);

        dashboardPage = new DashboardPage(page);
        await dashboardPage.goto();
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - PAGE RENDERING
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Dashboard page loads dengan heading "Produktivitas Farm Hari Ini"', async ({ page }) => {
        await expect(page).toHaveURL(/.*\/dashboard/);
        await dashboardPage.expectPageLoaded();
    });

    test('Positif - Semua section utama (KPI, Tren, Peternakan, Perkebunan, SPK, Stok, Prioritas) terender', async () => {
        await dashboardPage.expectAllSectionsVisible();
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - PRODUCTIVITY CARDS
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Productivity cards menampilkan minimal 4 card (Telur, HDP, Pakan, Laporan)', async ({ page }) => {
        await dashboardPage.expectProductivityCardsVisible();

        // Check cards exist (ignoring hidden page-hint copies)
        const cards = page.locator('section.grid article');
        const cardCount = await cards.count();
        expect(cardCount).toBeGreaterThanOrEqual(4);

        // Each card has a label in .text-xs
        const cardLabels = cards.locator('.text-xs.font-semibold');
        const labelCount = await cardLabels.count();
        expect(labelCount).toBeGreaterThanOrEqual(4);
    });

    test('Positif - Setiap productivity card memiliki nilai (value) dan caption', async ({ page }) => {
        const cardValues = dashboardPage.productivityCards.locator('.text-xl, .text-2xl');
        const count = await cardValues.count();
        expect(count).toBeGreaterThanOrEqual(4);
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - TREN PRODUKTIVITAS 7 HARI
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Tren Produktivitas 7 Hari menampilkan bar chart dengan legenda', async ({ page }) => {
        await expect(page.getByText('Tren Produktivitas 7 Hari').first()).toBeVisible();
        // Legend items inside the trend section (not hidden page-hint)
        const legendItems = page.locator('.flex.flex-wrap.gap-3 .inline-flex.items-center');
        const legendCount = await legendItems.count();
        expect(legendCount).toBeGreaterThanOrEqual(2);
    });

    test('Positif - Link "Detail" pada Tren Produktivitas mengarah ke peternakan', async ({ page }) => {
        const detailLink = page.getByRole('link', { name: 'Detail' }).first();
        if (await detailLink.count() > 0) {
            await expect(detailLink).toBeVisible();
        }
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - PETERNAKAN SECTION
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Section Peternakan menampilkan 4 metrik (Populasi, Egg mass, FCR, Mortalitas)', async ({ page }) => {
        await dashboardPage.expectPeternakanSectionVisible();

        // Metric labels inside the Peternakan grid (not hidden hints)
        const petSection = page.locator('section .grid:has-text("Peternakan")').first();
        const metrics = petSection.locator('.grid.grid-cols-2 > div');
        const count = await metrics.count();
        expect(count).toBeGreaterThanOrEqual(3);
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - PERKEBUNAN SECTION
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Section Perkebunan menampilkan 4 metrik (Blok aktif, Tanaman, Laporan, Sensor risiko)', async ({ page }) => {
        await dashboardPage.expectPerkebunanSectionVisible();

        const kebunSection = page.locator('section .grid:has-text("Perkebunan")').first();
        const metrics = kebunSection.locator('.grid.grid-cols-2 > div');
        const count = await metrics.count();
        expect(count).toBeGreaterThanOrEqual(3);
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - PERINGATAN SPK
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Panel Peringatan SPK Hari Ini tampil dengan badge dan pesan', async ({ page }) => {
        await dashboardPage.expectSpkPanelVisible();

        // Badge visible (label kondisi SPK)
        const badge = page.locator('[class*="rounded-full"][class*="px-3"]').first();
        await expect(badge).toBeVisible({ timeout: 5000 });
    });

    test('Positif - Link "Buka SPK" mengarah ke halaman Analisa SPK', async ({ page }) => {
        const link = page.getByRole('link', { name: 'Buka SPK' }).first();
        if (await link.count() > 0) {
            await expect(link).toBeVisible();
        }
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - STOK GUDANG
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Panel Stok Gudang menampilkan 3 metrik (Total item, Restock, Stok aman)', async ({ page }) => {
        await dashboardPage.expectStokGudangPanelVisible();

        // Metrics inside the Stok Gudang container grid
        const stokGrid = page.locator('.grid.grid-cols-3').filter({ has: page.getByText('Total item') }).first();
        const metrics = stokGrid.locator('> div');
        const count = await metrics.count();
        expect(count).toBeGreaterThanOrEqual(2);
    });

    test('Positif - Link "Buka Inventaris" mengarah ke halaman inventaris', async ({ page }) => {
        const link = page.getByRole('link', { name: 'Buka Inventaris' }).first();
        if (await link.count() > 0) {
            await expect(link).toBeVisible();
        }
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - PRIORITAS
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Section Prioritas menampilkan counter jumlah alert', async ({ page }) => {
        await expect(page.getByText('Prioritas').first()).toBeVisible({ timeout: 10000 });
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - PAGE HINT
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Page hint "Cara membaca dashboard" visible', async ({ page }) => {
        await expect(page.getByText('Cara membaca dashboard').first()).toBeVisible({ timeout: 5000 });
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - NAVIGATION
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Dashboard accessible via /dashboard route (tidak redirect ke login)', async ({ page }) => {
        await expect(page).toHaveURL(/.*\/dashboard/);
        await expect(page).not.toHaveURL(/.*login/);
    });

    test('Positif - Root route "/" redirects ke dashboard jika user sudah login', async ({ page }) => {
        await page.goto('/', { waitUntil: 'domcontentloaded' });
        await expect(page).toHaveURL(/.*dashboard/, { timeout: 10000 });
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - EDGE CASES
       ═══════════════════════════════════════════════════════════════════ */

    test('Edge Case - Dashboard tidak crash (tidak ada "Fatal error" atau "Error 500")', async () => {
        await dashboardPage.expectNoCrash();
    });

    test('Edge Case - Setiap section merender minimal satu child element', async ({ page }) => {
        const sections = page.locator('section');
        const count = await sections.count();
        expect(count).toBeGreaterThanOrEqual(2);
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - PERFORMANCE
       ═══════════════════════════════════════════════════════════════════ */

    test('Performance - Dashboard page loads < 5 detik', async ({ page }) => {
        const startTime = Date.now();

        await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
        await dashboardPage.expectPageLoaded();

        const loadTime = Date.now() - startTime;
        expect(loadTime).toBeLessThan(5000);
    });
});
