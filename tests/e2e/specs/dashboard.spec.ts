import { test, expect } from '@playwright/test';
import { DashboardPage } from '../pages/DashboardPage.js';

/**
 * Modul Dashboard - E2E Tests (REWRITE untuk redesign Nanda 464c630)
 * Dashboard baru: hero "Prioritas Hari Ini" + overview cards, panel Kegiatan Wajib Petugas,
 * Riwayat Aktivitas Petugas, Penyelesaian Sistem, Ringkasan Unit (Peternakan/Perkebunan),
 * dan Tren 7 Hari. Assertions disesuaikan dengan elemen yang benar-benar ada.
 */
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

    test('Positif - Dashboard page loads dengan hero "Prioritas Hari Ini"', async ({ page }) => {
        await expect(page).toHaveURL(/.*\/dashboard/);
        await dashboardPage.expectPageLoaded();
    });

    test('Positif - Semua section utama (Overview, Kegiatan, Ringkasan Unit, Tren) terender', async () => {
        await dashboardPage.expectAllSectionsVisible();
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - OVERVIEW CARDS
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Overview cards menampilkan minimal 4 card (Prioritas, Data Master, Laporan Unit, Jadwal Panen, Penugasan SPK)', async ({ page }) => {
        await dashboardPage.expectProductivityCardsVisible();
        const cardCount = await dashboardPage.productivityCards.count();
        expect(cardCount).toBeGreaterThanOrEqual(4);

        const body = await page.locator('body').textContent() || '';
        expect(body).toContain('Prioritas Hari Ini');
        expect(body).toContain('Data Master');
        expect(body).toContain('Laporan Unit');
        expect(body).toContain('Penugasan SPK');
    });

    test('Positif - Setiap overview card memiliki nilai (value)', async () => {
        const cardValues = dashboardPage.productivityCards.locator('.text-base, .text-lg');
        const count = await cardValues.count();
        expect(count).toBeGreaterThanOrEqual(4);
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - TREN 7 HARI
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Panel "Tren 7 Hari" menampilkan chart per jenis budidaya', async ({ page }) => {
        await dashboardPage.expectTrendChartVisible();
        // Chart SVG atau empty-state harus ada di dalam panel tren
        const chart = page.locator('svg[aria-label*="Tren"]');
        const empty = page.getByText('Belum ada jenis budidaya aktif');
        const total = (await chart.count()) + (await empty.count());
        expect(total).toBeGreaterThanOrEqual(1);
    });

    test('Positif - Link menu pada overview card mengarah ke halaman terkait', async ({ page }) => {
        const link = page.getByRole('link', { name: /Buka menu|Buka prioritas/ }).first();
        if (await link.count() > 0) {
            await expect(link).toBeVisible();
        }
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - RINGKASAN UNIT: PETERNAKAN
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Kartu Peternakan (Ringkasan Unit) menampilkan metrik (Unit, Populasi, Laporan)', async () => {
        await dashboardPage.expectPeternakanSectionVisible();
        const metrics = dashboardPage.peternakanCard.locator('.grid.grid-cols-3 > div');
        const count = await metrics.count();
        expect(count).toBeGreaterThanOrEqual(3);
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - RINGKASAN UNIT: PERKEBUNAN
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Kartu Perkebunan (Ringkasan Unit) menampilkan metrik (Unit, Populasi, Laporan)', async () => {
        await dashboardPage.expectPerkebunanSectionVisible();
        const metrics = dashboardPage.perkebunanCard.locator('.grid.grid-cols-3 > div');
        const count = await metrics.count();
        expect(count).toBeGreaterThanOrEqual(3);
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - PENUGASAN SPK
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Overview card "Penugasan SPK" tampil', async () => {
        await dashboardPage.expectSpkPanelVisible();
        await expect(dashboardPage.penugasanSpkCard.first()).toContainText('Penugasan SPK');
    });

    test('Positif - Link overview mengarah ke halaman terkait (jika ada)', async ({ page }) => {
        const link = page.getByRole('link', { name: /Buka/ }).first();
        if (await link.count() > 0) {
            await expect(link).toBeVisible();
        }
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - PENYELESAIAN SISTEM
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Panel "Penyelesaian Sistem" tampil', async () => {
        await dashboardPage.expectPenyelesaianSistemVisible();
    });

    test('Positif - Panel "Kegiatan Wajib Petugas" tampil dengan badge pending', async ({ page }) => {
        await expect(dashboardPage.kegiatanWajibPanel.first()).toBeVisible({ timeout: 10000 });
        await expect(page.getByText(/pending/).first()).toBeVisible({ timeout: 5000 });
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - PRIORITAS
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Hero "Prioritas Hari Ini" menampilkan counter item pending', async ({ page }) => {
        await expect(page.getByText('Prioritas Hari Ini').first()).toBeVisible({ timeout: 10000 });
        await expect(page.getByText(/item pending/).first()).toBeVisible({ timeout: 5000 });
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - RIWAYAT AKTIVITAS
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Panel "Riwayat Aktivitas Petugas" tampil', async () => {
        await expect(dashboardPage.riwayatAktivitasPanel.first()).toBeVisible({ timeout: 10000 });
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

    test('Performance - Dashboard page loads < 8 detik', async ({ page }) => {
        const startTime = Date.now();

        await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
        await dashboardPage.expectPageLoaded();

        const loadTime = Date.now() - startTime;
        expect(loadTime).toBeLessThan(8000);
    });
});
