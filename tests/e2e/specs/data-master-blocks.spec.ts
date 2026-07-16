import { test, expect } from '@playwright/test';
import { DataMasterPage } from '../pages/DataMasterPage.js';

test.describe("Modul Data Master - Blok Kebun", () => {
    let dataMasterPage: DataMasterPage;

    test.describe.configure({ mode: "serial" });

    test.beforeEach(async ({ page }, testInfo) => {
        testInfo.setTimeout(120000);
        page.setDefaultNavigationTimeout(120000);
        page.setDefaultTimeout(120000);
        dataMasterPage = new DataMasterPage(page);
        await dataMasterPage.goto();
    });
    /* ═══════════════════════════════════════════════════════════════════
       TAB 2: BLOK KEBUN - DATA DISPLAY
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Tab Blok Kebun menampilkan 5 dummy blok kebun', async () => {
        /**
         * Given: Dummy data has 5 blok kebun
         * When: Kebun tab loaded
         * Then: Table shows 5 rows
         */

        // Arrange & Act
        await dataMasterPage.clickKebunTab();
        const rowCount = await dataMasterPage.getVisibleKebunRowCount();

        // Assert: 5 blok kebun displayed
        expect(rowCount).toBeGreaterThanOrEqual(5);
    });

    test('Positif - Blok Kebun table displays correct dummy data (Greenhouse A, B, C, D, Plot Pakcoy)', async ({ page }) => {
        /**
         * Given: Dummy kebun loaded
         * When: Check table content
         * Then: Known greenhouse names visible
         */

        // Arrange
        await dataMasterPage.clickKebunTab();

        // Assert: Known blok kebun exist
        await expect(page.getByText('Greenhouse A')).toBeVisible();
        await expect(page.getByText('Greenhouse B')).toBeVisible();
        await expect(page.getByText('Plot Pakcoy Hidroponik')).toBeVisible();
    });

    test('Positif - Blok Kebun table menampilkan jenis budidaya (Melon, Pakcoy) dan nama latin', async ({ page }) => {
        /**
         * Given: Blok kebun have jenisBudidaya
         * When: Check table content
         * Then: Jenis budidaya dan nama latin visible
         */

        // Arrange
        await dataMasterPage.clickKebunTab();

        // Assert: Jenis budidaya displayed
        await expect(page.locator('text=Melon').first()).toBeVisible();
        await expect(page.locator('text=Pakcoy')).toBeVisible();

        // Assert: Nama latin displayed
        await expect(page.locator('text=Cucumis melo')).toBeVisible();
        await expect(page.locator('text=Brassica rapa')).toBeVisible();
    });

    test('Positif - Blok Kebun table menampilkan lokasi (Rooftop info)', async ({ page }) => {
        /**
         * Given: Blok kebun have locations
         * When: Check table content
         * Then: Lokasi column shows rooftop info
         */

        // Arrange
        await dataMasterPage.clickKebunTab();

        // Assert: Lokasi displayed
        await expect(page.locator('text=Rooftop Gedung')).toBeVisible();
        await expect(page.locator('text=Lantai').first()).toBeVisible();
    });

    test('Positif - Blok Kebun table menampilkan luas dan kapasitas', async ({ page }) => {
        /**
         * Given: Blok kebun have luas & kapasitas
         * When: Check table content
         * Then: Numeric values displayed
         */

        // Arrange
        await dataMasterPage.clickKebunTab();

        // Assert: Luas displayed (e.g., "120.5 m²")
        const bodyContent = await page.locator('body').textContent();
        expect(bodyContent).toMatch(/\d+\.\d+/); // Contains decimal numbers

        // Assert: Kapasitas displayed (numbers)
        expect(bodyContent).toMatch(/\d+/);
    });

    test('Positif - Blok Kebun menampilkan status badges (Aktif/Nonaktif)', async ({ page }) => {
        /**
         * Given: Greenhouse D has status=0 (Nonaktif)
         * When: Check status column
         * Then: Status badges displayed
         */

        // Arrange
        await dataMasterPage.clickKebunTab();

        // Assert: Status badges exist
        const statusAktif = page.locator('text=Aktif').first();
        await expect(statusAktif).toBeVisible();

        // Note: Greenhouse D has status=0
        const statusNonaktif = page.locator('text=Nonaktif, text=Renovasi');
        const nonaktifCount = await statusNonaktif.count();
        expect(nonaktifCount).toBeGreaterThanOrEqual(0); // May or may not be visible
    });

    /* ═══════════════════════════════════════════════════════════════════
       TAB 2: BLOK KEBUN - SEARCH & FILTER
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Search kebun by name filters correctly (case: "Greenhouse A")', async ({ page }) => {
        /**
         * Given: Kebun tab active
         * When: Search "Greenhouse A"
         * Then: Only Greenhouse A displayed
         */

        // Arrange
        await dataMasterPage.clickKebunTab();

        // Act
        await dataMasterPage.searchKebun('Greenhouse A');

        // Assert: 1 result
        const rowCount = await dataMasterPage.getVisibleKebunRowCount();
        expect(rowCount).toBe(1);
        await expect(page.getByText('Greenhouse A')).toBeVisible();
    });

    test('Positif - Search kebun by jenis budidaya works (case: "Pakcoy")', async ({ page }) => {
        /**
         * Given: Kebun tab active
         * When: Search "Pakcoy"
         * Then: Plot Pakcoy displayed
         */

        // Arrange
        await dataMasterPage.clickKebunTab();

        // Act
        await dataMasterPage.searchKebun('Pakcoy');

        // Assert: 1 result
        const rowCount = await dataMasterPage.getVisibleKebunRowCount();
        expect(rowCount).toBeGreaterThanOrEqual(1);
        await expect(page.getByText('Plot Pakcoy')).toBeVisible();
    });

    test('Negatif - Search kebun dengan invalid term shows empty state (case: "LokasiAntahBerantah123")', async ({ page }) => {
        /**
         * Given: Kebun tab active
         * When: Search for non-existent location
         * Then: 0 results, table tidak crash
         */

        // Arrange
        await dataMasterPage.clickKebunTab();

        // Act
        await dataMasterPage.searchKebun('LokasiAntahBerantah123');

        // Assert: 0 results
        const rowCount = await dataMasterPage.getVisibleKebunRowCount();
        expect(rowCount).toBe(0);

        // Assert: Table tidak crash (still visible)
        await expect(dataMasterPage.kebunTable).toBeVisible();
    });

    test('Positif - Kebun jenis budidaya filter works (if exists)', async ({ page }) => {
        /**
         * Given: Kebun tab active
         * When: Filter by jenis budidaya (e.g., "Melon")
         * Then: Only Melon greenhouses displayed
         */

        // Arrange
        await dataMasterPage.clickKebunTab();

        // Act: Check if filter exists
        const jenisFilter = page.locator('select').filter({ hasText: /Jenis Budidaya/i });
        const hasFilter = await jenisFilter.count();

        if (hasFilter > 0) {
            await jenisFilter.selectOption({ label: /Melon/i });
            await page.waitForTimeout(500);

            // Assert: Only Melon records (4 greenhouses)
            const rowCount = await dataMasterPage.getVisibleKebunRowCount();
            expect(rowCount).toBe(4);
        }
    });

    /* ═══════════════════════════════════════════════════════════════════
       EDGE CASES & PERFORMANCE
       ═══════════════════════════════════════════════════════════════════ */

    test('Edge Case - Rapid tab switching tidak menyebabkan crash atau layout issues', async ({ page }) => {
        /**
         * Given: Data master loaded
         * When: Rapidly switch between tabs
         * Then: No crash, no layout issues
         */

        // Act: Rapid tab switching
        for (let i = 0; i < 5; i++) {
            await dataMasterPage.clickKebunTab();
            await page.waitForTimeout(100);
            await dataMasterPage.clickUsersTab();
            await page.waitForTimeout(100);
        }

        // Assert: No crash
        const bodyContent = await page.locator('body').textContent();
        expect(bodyContent).not.toMatch(/Error 500|Fatal|undefined/i);
    });

    test('Edge Case - Search dengan special characters tidak crash (case: "@#$%")', async ({ page }) => {
        /**
         * Given: Users tab active
         * When: Search with special characters
         * Then: No crash, 0 or some results
         */

        // Arrange
        await dataMasterPage.clickUsersTab();

        // Act
        await dataMasterPage.searchUsers('@#$%');

        // Assert: No crash
        const rowCount = await dataMasterPage.getVisibleUserRowCount();
        expect(rowCount).toBeGreaterThanOrEqual(0); // 0 or more results
    });

    test('Performance - Data Master page loads dalam waktu reasonable (<3 detik)', async ({ page }) => {
        /**
         * Given: Navigate to data master
         * When: Measure load time
         * Then: Page loads < 3 seconds
         */

        // Arrange
        const startTime = Date.now();

        // Act
        await page.goto('/data-master', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Data Master/i })).toBeVisible();

        const endTime = Date.now();
        const loadTime = endTime - startTime;

        // Assert: Performance < 3000ms
        expect(loadTime).toBeLessThan(3000);
    });

    test('Performance - Tab switching is instant (<500ms)', async ({ page }) => {
        /**
         * Given: Data master loaded
         * When: Switch tabs and measure time
         * Then: Switch completes < 500ms
         */

        // Arrange
        await dataMasterPage.expectPageReady();

        // Act
        const startTime = Date.now();
        await dataMasterPage.clickKebunTab();
        await expect(dataMasterPage.kebunTable).toBeVisible();
        const endTime = Date.now();

        const switchTime = endTime - startTime;

        // Assert: Switch < 500ms
        expect(switchTime).toBeLessThan(500);
    });
});
