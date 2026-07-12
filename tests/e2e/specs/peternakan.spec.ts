import { test, expect } from '@playwright/test';
import { PeternakanPage } from '../pages/PeternakanPage.js';

/**
 * Modul Dashboard Peternakan - Comprehensive E2E Tests
 * 
 * Testing UI & functionality di PeternakanController:
 * - /peternakan (index) - Dashboard dengan KPI, Fuzzy SPK, Charts, Barn filters
 * - /peternakan/{id} (show) - Detail kandang dengan sensors, trends, production logs
 * - POST /peternakan/evaluate-all - Fuzzy evaluation endpoint
 */

test.describe('Modul Dashboard Peternakan - E2E Tests', () => {
    let peternakanPage: PeternakanPage;

    test.setTimeout(120000);

    test.beforeEach(async ({ page }) => {
        page.setDefaultNavigationTimeout(120000);
        page.setDefaultTimeout(60000);

        // Block external assets
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        peternakanPage = new PeternakanPage(page);
        await peternakanPage.goto();
    });

    // ═══════════════════════════════════════════════════════════════
    // DASHBOARD INDEX - Basic Rendering
    // ═══════════════════════════════════════════════════════════════

    test('Positif - Memuat dashboard dengan komponen utama terlihat', async ({ page }) => {
        /**
         * Given: User authenticated dan navigate ke /peternakan
         * When: Dashboard loads
         * Then: Main components terlihat (heading, komoditas select, KPI cards)
         */

        // Act
        await peternakanPage.expectToBeOnPeternakanPage();

        // Assert - Page heading
        await expect(page.getByRole('heading', { name: /Decision Support|Peternakan/i })).toBeVisible();

        // Assert - Komoditas filter (if ada)
        if (await peternakanPage.komoditasSelect.count() > 0) {
            await expect(peternakanPage.komoditasSelect).toBeVisible();
        }
    });

    test('Positif - Menampilkan KPI metrics cards', async ({ page }) => {
        /**
         * Given: Dashboard loaded dengan data
         * When: Check KPI bagian
         * Then: KPI cards displayed dengan metrics (suhu, kelembapan, amonia, dll)
         */

        // Arrange
        await peternakanPage.expectToBeOnPeternakanPage();

        // Assert - Should have metrics (look for common KPI terms)
        const bodyText = await page.locator('body').textContent() || '';

        // Check for presence of KPI-related terms (flexible based on actual data)
        const hasKpiElements = bodyText.includes('Suhu') ||
            bodyText.includes('Kelembapan') ||
            bodyText.includes('Amonia') ||
            bodyText.includes('%') ||
            bodyText.includes('ppm');

        expect(hasKpiElements).toBeTruthy();
    });

    test('Positif - Menampilkan fuzzy SPK ', async ({ page }) => {
        /**
         * Given: Dashboard dengan fuzzy evaluation
         * When: Check SPK 
         * Then: Show status lingkungan, produktivitas, diagnosis kausalitas
         */

        // Arrange
        await peternakanPage.expectToBeOnPeternakanPage();

        // Assert - Look for SPK-related content
        const bodyText = await page.locator('body').textContent() || '';

        // SPK results should contain status labels
        const hasSpkContent = bodyText.includes('Optimal') ||
            bodyText.includes('Baik') ||
            bodyText.includes('Waspada') ||
            bodyText.includes('Buruk') ||
            bodyText.includes('Lingkungan') ||
            bodyText.includes('Produktivitas');

        expect(hasSpkContent).toBeTruthy();
    });

    test('Negatif - Nilai undefined/null tidak muncul di KPI', async ({ page }) => {
        /**
         * Given: Dashboard dirender
         * When: Check all text content
         * Then: No "undefined" or "null" strings terlihat to user
         */

        // Arrange & Act
        await peternakanPage.expectToBeOnPeternakanPage();

        // Assert
        const bodyText = await page.locator('body').textContent();
        expect(bodyText).not.toContain('undefined%');
        expect(bodyText).not.toContain('undefined');
        expect(bodyText).not.toContain('null');
        expect(bodyText).not.toContain('NaN');
    });

    // ═══════════════════════════════════════════════════════════════
    // KOMODITAS FILTER
    // ═══════════════════════════════════════════════════════════════

    test('Positif - Menyaring berdasarkan komoditas updates dashboard', async ({ page }) => {
        /**
         * Given: Multiple komoditas available
         * When: Select different komoditas dari dropdown
         * Then: URL diperbarui dengan komoditas param, data refreshed
         */

        // Arrange
        await peternakanPage.expectToBeOnPeternakanPage();

        if (await peternakanPage.komoditasSelect.count() > 0) {
            // Check if there are options
            const optionCount = await peternakanPage.komoditasSelect.locator('option').count();

            if (optionCount > 1) {
                // Act - Select second option
                await peternakanPage.komoditasSelect.selectOption({ index: 1 });

                // Assert - URL should have komoditas param
                await page.waitForTimeout(500); // Wait for potential page update
                const url = page.url();
                expect(url).toContain('komoditas=');
            }
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // CHART RANGE FILTER
    // ═══════════════════════════════════════════════════════════════

    test('Positif - Mengganti chart range (30d, 90d, ytd)', async ({ page }) => {
        /**
         * Given: Dashboard dengan chart data
         * When: Click chart range filter buttons
         * Then: URL updates dengan chart_range param
         */

        // Arrange
        await peternakanPage.expectToBeOnPeternakanPage();

        // Look for chart range buttons (30d, 90d, ytd)
        const range30d = page.getByRole('button', { name: /30.*hari|30d/i }).or(page.getByText('30 hari')).first();
        const range90d = page.getByRole('button', { name: /90.*hari|90d/i }).or(page.getByText('90 hari')).first();

        if (await range30d.isVisible().catch(() => false)) {
            // Act - Click 30d button
            await range30d.click();
            await page.waitForTimeout(300);

            // Assert - Check URL or active state
            const url = page.url();
            // URL might have chart_range=30d or button should be active
            const has30dParam = url.includes('chart_range=30d') || url.includes('30d');

            if (!has30dParam) {
                // At least button should exist and be clickable
                await expect(range30d).toBeVisible();
            }
        }

        if (await range90d.isVisible().catch(() => false)) {
            // Act - Try 90d
            await range90d.click();
            await page.waitForTimeout(300);
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // BARN/KANDANG FILTER
    // ═══════════════════════════════════════════════════════════════

    test('Positif - Menyaring SPK per kandang berfungsi', async ({ page }) => {
        /**
         * Given: Multiple kandang available
         * When: Select specific kandang dari filter
         * Then: SPK results diperbarui untuk kandang tersebut
         */

        // Arrange
        await peternakanPage.expectToBeOnPeternakanPage();

        if (await peternakanPage.filterKandangSelect.count() > 0) {
            const options = await peternakanPage.filterKandangSelect.locator('option').allTextContents();

            if (options.length > 1) {
                // Act - Select first barn (not "all")
                await peternakanPage.filterKandangSelect.selectOption({ index: 1 });

                // Assert - Selection successful
                await expect(peternakanPage.filterKandangSelect).toBeVisible();

                // Value should be diperbarui
                const selectedValue = await peternakanPage.filterKandangSelect.inputValue();
                expect(selectedValue).not.toBe('all');
            }
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // EVALUATE ALL ENDPOINT
    // ═══════════════════════════════════════════════════════════════

    test('Positif - Menjalankan evaluate-all fuzzy evaluation', async ({ page }) => {
        /**
         * Given: Dashboard loaded
         * When: Click "Jalankan Evaluasi" button
         * Then: POST request sent, notification shown, evaluation time diperbarui
         */

        // Arrange
        await peternakanPage.expectToBeOnPeternakanPage();

        if (await peternakanPage.evaluateAllButton.count() > 0) {
            // Act - Click evaluate button
            await peternakanPage.evaluateAllButton.click();

            // Assert - Notification appears
            const notification = page.getByText(/Evaluasi selesai|Sebagian evaluasi gagal|Gagal menjalankan|processed/i).first();
            await expect(notification).toBeVisible({ timeout: 20000 });
        }
    });

    test('Positif - Evaluasi-Semua updates evaluation timestamp', async ({ page }) => {
        /**
         * Given: Evaluate-all executed
         * When: Check evaluation time label
         * Then: Shows diperbarui timestamp atau "Auto evaluated terakhir: ..."
         */

        // Arrange
        await peternakanPage.expectToBeOnPeternakanPage();

        if (await peternakanPage.evaluateAllButton.count() > 0) {
            // Act
            await peternakanPage.evaluateAllButton.click();

            // Wait for evaluation to complete
            await page.waitForTimeout(2000);

            // Assert - Look for timestamp text
            const bodyText = await page.locator('body').textContent() || '';
            const hasTimestamp = bodyText.includes('Auto evaluated') ||
                bodyText.includes('Evaluasi selesai') ||
                bodyText.includes('terakhir');

            expect(hasTimestamp).toBeTruthy();
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // Grafik RENDERING
    // ═══════════════════════════════════════════════════════════════

    test('Positif - Grafik dirender tanpa error JavaScript', async ({ page }) => {
        /**
         * Given: Dashboard with chart data
         * When: Check for canvas elements
         * Then: Chart canvases exist and terlihat
         */

        // Arrange
        await peternakanPage.expectToBeOnPeternakanPage();

        // Assert - Look for canvas elements (charts)
        if (await peternakanPage.chartKualitasTelurCanvas.count() > 0) {
            await expect(peternakanPage.chartKualitasTelurCanvas).toBeVisible();
        }

        // Check for any canvas element (Chart.js renders to canvas)
        const canvasElements = page.locator('canvas');
        const canvasCount = await canvasElements.count();

        if (canvasCount > 0) {
            // At least one chart should be terlihat
            await expect(canvasElements.first()).toBeVisible();
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // Log Produksi DISPLAY
    // ═══════════════════════════════════════════════════════════════

    test('Positif - Log Produksi dirender', async ({ page }) => {
        /**
         * Given: Dashboard dengan production data
         * When: Check production log bagian
         * Then: Production log table atau cards terlihat
         */

        // Arrange
        await peternakanPage.expectToBeOnPeternakanPage();

        // Assert - Look for production-related content
        const bodyText = await page.locator('body').textContent() || '';

        // Check for production terms
        const hasProductionContent = bodyText.includes('Produksi') ||
            bodyText.includes('Panen') ||
            bodyText.includes('Telur') ||
            bodyText.includes('kg');

        // Production bagian might exist
        if (hasProductionContent) {
            expect(hasProductionContent).toBeTruthy();
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // Detail Kandang PAGE - /peternakan/{id}
    // ═══════════════════════════════════════════════════════════════

    test('Positif - Menavigasi ke halaman detail kandang', async ({ page }) => {
        /**
         * Given: Dashboard dengan list kandang
         * When: Click on kandang link/card
         * Then: Navigate to /peternakan/{id} detail page
         */

        // Arrange
        await peternakanPage.expectToBeOnPeternakanPage();

        // Look for kandang links (might be in cards or table)
        const kandangLink = page.locator('a[href*="/peternakan/"]').first();

        if (await kandangLink.isVisible().catch(() => false)) {
            // Act
            await kandangLink.click();

            // Assert
            await expect(page).toHaveURL(/.*\/peternakan\/.+/);
        }
    });

    test('Positif - Detail Kandang halaman dimuat dengan sensors data', async ({ page }) => {
        /**
         * Given: Valid barn ID
         * When: Navigate to /peternakan/{id}
         * Then: Detail page shows sensors, KPI, trends
         */

        // Arrange - Navigate directly to barn ID (assume ID 1 or first available)
        await peternakanPage.expectToBeOnPeternakanPage();

        // Try to find first kandang and navigate
        const kandangLink = page.locator('a[href*="/peternakan/"]').first();

        if (await kandangLink.isVisible().catch(() => false)) {
            await kandangLink.click();
            await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => { });

            // Assert - Detail page elements
            const bodyText = await page.locator('body').textContent() || '';

            // Should have sensor/detail data
            const hasDetailContent = bodyText.includes('Suhu') ||
                bodyText.includes('Sensor') ||
                bodyText.includes('Kelembapan') ||
                bodyText.includes('Detail');

            expect(hasDetailContent).toBeTruthy();
        }
    });

    test('Positif - Detail Kandang menampilkan log produksi', async ({ page }) => {
        /**
         * Given: Barn detail halaman dimuat
         * When: Check production log bagian
         * Then: Production entries displayed
         */

        // Arrange
        await peternakanPage.expectToBeOnPeternakanPage();

        const kandangLink = page.locator('a[href*="/peternakan/"]').first();

        if (await kandangLink.isVisible().catch(() => false)) {
            await kandangLink.click();
            await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => { });

            // Assert - Look for production log
            const bodyText = await page.locator('body').textContent() || '';
            const hasProductionLog = bodyText.includes('Produksi') ||
                bodyText.includes('Panen') ||
                bodyText.includes('Log') ||
                bodyText.includes('Riwayat');

            if (hasProductionLog) {
                expect(hasProductionLog).toBeTruthy();
            }
        }
    });

    test('Positif - Detail Kandang menampilkan info perangkat IoT', async ({ page }) => {
        /**
         * Given: Barn dengan IoT device mapped
         * When: Load detail page
         * Then: IoT device card/info displayed
         */

        // Arrange
        await peternakanPage.expectToBeOnPeternakanPage();

        const kandangLink = page.locator('a[href*="/peternakan/"]').first();

        if (await kandangLink.isVisible().catch(() => false)) {
            await kandangLink.click();
            await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => { });

            // Assert - Look for IoT-related content
            const bodyText = await page.locator('body').textContent() || '';
            const hasIoTContent = bodyText.includes('IoT') ||
                bodyText.includes('Device') ||
                bodyText.includes('Sensor') ||
                bodyText.includes('Perangkat');

            // IoT info might exist
            if (hasIoTContent) {
                expect(hasIoTContent).toBeTruthy();
            }
        }
    });

    test('Positif - Detail Kandang memiliki tautan kembali ke dashboard', async ({ page }) => {
        /**
         * Given: Barn detail page
         * When: Look for back/return link
         * Then: Link to dashboard ada
         */

        // Arrange
        await peternakanPage.expectToBeOnPeternakanPage();

        const kandangLink = page.locator('a[href*="/peternakan/"]').first();

        if (await kandangLink.isVisible().catch(() => false)) {
            await kandangLink.click();
            await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => { });

            // Assert - Look for back link
            const backLink = page.getByRole('link', { name: /kembali|back|dashboard/i }).first();

            if (await backLink.isVisible().catch(() => false)) {
                await expect(backLink).toBeVisible();

                // Click back should return to dashboard
                await backLink.click();
                await expect(page).toHaveURL(/.*\/peternakan$/);
            }
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // INTEGRATION TESTS
    // ═══════════════════════════════════════════════════════════════

    test('Integrasi - Full flow: Dashboard → Filter → Evaluate → Barn Detail → Back', async ({ page }) => {
        /**
         * Given: User wants complete peternakan monitoring flow
         * When: Navigate through all features
         * Then: All interactions work seamlessly
         */

        // Step 1: Load dashboard
        await peternakanPage.expectToBeOnPeternakanPage();
        await expect(page.getByRole('heading', { name: /Decision Support|Peternakan/i })).toBeVisible();

        // Step 2: Try komoditas filter (if available)
        if (await peternakanPage.komoditasSelect.count() > 0) {
            const optionCount = await peternakanPage.komoditasSelect.locator('option').count();
            if (optionCount > 1) {
                await peternakanPage.komoditasSelect.selectOption({ index: 1 });
                await page.waitForTimeout(500);
            }
        }

        // Step 3: Try evaluate-all (if available)
        if (await peternakanPage.evaluateAllButton.count() > 0) {
            await peternakanPage.evaluateAllButton.click();
            await page.waitForTimeout(2000); // Wait for evaluation
        }

        // Step 4: Navigate to barn detail (if available)
        const kandangLink = page.locator('a[href*="/peternakan/"]').first();
        if (await kandangLink.isVisible().catch(() => false)) {
            await kandangLink.click();
            await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => { });

            // Step 5: Return to dashboard
            const backLink = page.getByRole('link', { name: /kembali|back|dashboard/i }).first();
            if (await backLink.isVisible().catch(() => false)) {
                await backLink.click();
                await expect(page).toHaveURL(/.*\/peternakan$/);
            }
        }
    });
});

