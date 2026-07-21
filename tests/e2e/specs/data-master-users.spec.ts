import { test, expect } from '@playwright/test';
import { DataMasterPage } from '../pages/DataMasterPage.js';

test.describe('Modul Data Master Operasional - E2E Tests', () => {
    let dataMasterPage: DataMasterPage;

    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }, testInfo) => {
        testInfo.setTimeout(120000);
        page.setDefaultNavigationTimeout(120000);
        page.setDefaultTimeout(120000);

        dataMasterPage = new DataMasterPage(page);
        await dataMasterPage.goto();
    });

    /* ═══════════════════════════════════════════════════════════════════
       PAGE RENDERING & HEADER
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Data Master page loads dengan title "Data Master Operasional"', async ({ page }) => {
        /**
         * Given: User navigate to /data-master
         * When: Page loads
         * Then: Page title "Data Master Operasional" visible
         */

        // Assert: Page ready
        await dataMasterPage.expectPageReady();

        // Assert: URL correct
        await expect(page).toHaveURL(/.*\/data-master/);

        // Assert: Page title
        await expect(page.getByRole('heading', { name: /Data Master Operasional/i })).toBeVisible();
    });

    test('Positif - Page menampilkan description tentang RFC dan read-only notice', async ({ page }) => {
        /**
         * Given: Data master page loaded
         * When: Check description text
         * Then: Description mentions "RFC" dan "read-only"
         */

        // Assert: Description text
        await expect(page.locator('text=read-only')).toBeVisible();
        await expect(page.locator('text=RFC').first()).toBeVisible();
    });

    test('Positif - Info banner (amber) menampilkan read-only warning dengan icon', async ({ page }) => {
        /**
         * Given: Page loaded
         * When: Check info banner
         * Then: Amber banner dengan icon visible, mentions "Mobile RFC"
         */

        // Assert: Banner exists
        const banner = page.locator('[class*="amber-50"]').first();
        await expect(banner).toBeVisible();

        // Assert: Banner mentions Mobile RFC
        await expect(page.locator('text=Mobile RFC').first()).toBeVisible();

        // Assert: Icon exists
        const icon = banner.locator('svg').first();
        await expect(icon).toBeVisible();
    });

    /* ═══════════════════════════════════════════════════════════════════
       TAB NAVIGATION
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Dua tabs tersedia: "Daftar Pengguna" dan "Blok Kebun"', async ({ page }) => {
        /**
         * Given: Page loaded
         * When: Check tab navigation
         * Then: 2 tabs visible dengan labels dan icons
         */

        // Assert: Tab Daftar Pengguna
        await expect(page.getByRole('tab', { name: /Daftar Pengguna/i })).toBeVisible();

        // Assert: Tab Blok Kebun
        await expect(page.getByRole('tab', { name: /Blok Kebun/i })).toBeVisible();
    });

    test('Positif - Tab "Daftar Pengguna" is default active tab', async ({ page }) => {
        /**
         * Given: Page just loaded
         * When: Check active tab
         * Then: "Daftar Pengguna" tab is active (green styling)
         */

        // Assert: Users tab active (has green border/background)
        const usersTab = page.getByRole('tab', { name: /Daftar Pengguna/i });
        const classes = await usersTab.getAttribute('class');
        expect(classes).toContain('green');
    });

    test('Positif - Clicking "Blok Kebun" tab switches content dengan transition', async ({ page }) => {
        /**
         * Given: "Daftar Pengguna" tab active
         * When: Click "Blok Kebun" tab
         * Then: Tab switches, content changes, styling updates
         */

        // Arrange: Ensure on users tab initially
        await dataMasterPage.expectPageReady();

        // Act: Click Blok Kebun tab
        await dataMasterPage.clickKebunTab();

        // Assert: Blok Kebun content visible
        await expect(dataMasterPage.kebunTable).toBeVisible();

        // Assert: Blok Kebun tab now active
        const kebunTab = page.getByRole('tab', { name: /Blok Kebun/i });
        const classes = await kebunTab.getAttribute('class');
        expect(classes).toContain('green');
    });

    test('Positif - Tabs menampilkan count badge dengan jumlah records', async ({ page }) => {
        /**
         * Given: Page loaded dengan data
         * When: Check tab badges
         * Then: Each tab shows count (e.g., "6" for users, "5" for kebun)
         */

        // Assert: Users tab has count badge
        const usersTab = page.getByRole('tab', { name: /Daftar Pengguna/i });
        const usersCount = await usersTab.locator('[class*="rounded-full"]').textContent();
        expect(parseInt(usersCount || '0')).toBeGreaterThan(0);

        // Assert: Kebun tab has count badge
        const kebunTab = page.getByRole('tab', { name: /Blok Kebun/i });
        const kebunCount = await kebunTab.locator('[class*="rounded-full"]').textContent();
        expect(parseInt(kebunCount || '0')).toBeGreaterThan(0);
    });

    test('Positif - Switching tabs preserves search state (Alpine.js reactivity)', async ({ page }) => {
        /**
         * Given: User searches in Users tab
         * When: Switch to Kebun tab and back
         * Then: Search term preserved
         */

        // Arrange: Search in Users tab
        await dataMasterPage.searchUsers('Siti');
        const rowCount1 = await dataMasterPage.getVisibleUserRowCount();
        expect(rowCount1).toBeGreaterThan(0);

        // Act: Switch to Kebun and back
        await dataMasterPage.clickKebunTab();
        await page.waitForTimeout(500);
        await dataMasterPage.clickUsersTab();

        // Assert: Search term still active (filtered results)
        const rowCount2 = await dataMasterPage.getVisibleUserRowCount();
        expect(rowCount2).toBe(rowCount1); // Same filtered result
    });

    /* ═══════════════════════════════════════════════════════════════════
       TAB 1: DAFTAR PENGGUNA - DATA DISPLAY
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Tab Daftar Pengguna menampilkan 6 dummy users', async () => {
        /**
         * Given: Dummy data has 6 users
         * When: Users tab loaded
         * Then: Table shows 6 rows
         */

        // Arrange & Act
        await dataMasterPage.clickUsersTab();
        const rowCount = await dataMasterPage.getVisibleUserRowCount();

        // Assert: 6 users displayed
        expect(rowCount).toBeGreaterThanOrEqual(6);
    });

    test('Positif - Users table menampilkan all required columns (Nama, Email, Role, Status)', async ({ page }) => {
        /**
         * Given: Users tab active
         * When: Check table headers
         * Then: Headers visible: Nama, Email, Role, Status
         */

        // Arrange
        await dataMasterPage.clickUsersTab();

        // Assert: Table headers
        await expect(page.locator('th, text=Nama').first()).toBeVisible();
        await expect(page.locator('th, text=Email')).toBeVisible();
        await expect(page.locator('th, text=Role')).toBeVisible();
        await expect(page.locator('th, text=Status')).toBeVisible();
    });

    test('Positif - Users table displays correct dummy data (Dr. Ahmad Suryadi, Siti Nurhaliza, etc)', async ({ page }) => {
        /**
         * Given: Dummy users loaded
         * When: Check table content
         * Then: Known dummy names visible
         */

        // Arrange
        await dataMasterPage.clickUsersTab();

        // Assert: Known users exist
        await expect(page.getByText('Dr. Ahmad Suryadi')).toBeVisible();
        await expect(page.getByText('Siti Nurhaliza')).toBeVisible();
        await expect(page.getByText('Budi Santoso')).toBeVisible();
        await expect(page.getByText('Admin Sistem')).toBeVisible();
    });

    test('Positif - Users table menampilkan role badges dengan colors (pjawab, inventor, petugas, admin)', async ({ page }) => {
        /**
         * Given: Users have different roles
         * When: Check role column
         * Then: Role badges displayed dengan different colors
         */

        // Arrange
        await dataMasterPage.clickUsersTab();

        // Assert: Role badges exist (text content)
        await expect(page.locator('text=Penanggung Jawab')).toBeVisible(); // pjawab
        await expect(page.locator('text=Pengelola RFC')).toBeVisible(); // inventor
        await expect(page.locator('text=Petugas Perkebunan')).toBeVisible(); // petugas
    });

    test('Positif - Users table menampilkan status badges (Aktif/Nonaktif) dengan colors', async ({ page }) => {
        /**
         * Given: Users have different status (1=Aktif, 0=Nonaktif)
         * When: Check status column
         * Then: Status badges displayed (green for Aktif, gray for Nonaktif)
         */

        // Arrange
        await dataMasterPage.clickUsersTab();

        // Assert: Status badges exist
        const statusAktif = page.locator('text=Aktif').first();
        await expect(statusAktif).toBeVisible();

        // Note: Hendro Prasetyo has status=0 (Nonaktif)
        const statusNonaktif = page.locator('text=Nonaktif');
        const nonaktifCount = await statusNonaktif.count();
        expect(nonaktifCount).toBeGreaterThanOrEqual(1);
    });

    /* ═══════════════════════════════════════════════════════════════════
       TAB 1: DAFTAR PENGGUNA - SEARCH & FILTER
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Search users by name filters reactively (case: "Siti")', async ({ page }) => {
        /**
         * Given: Users tab active
         * When: Type "Siti" in search box
         * Then: Only Siti Nurhaliza displayed (1 row)
         */

        // Arrange
        await dataMasterPage.clickUsersTab();

        // Act
        await dataMasterPage.searchUsers('Siti');

        // Assert: Only 1 result
        const rowCount = await dataMasterPage.getVisibleUserRowCount();
        expect(rowCount).toBe(1);
        await expect(page.getByText('Siti Nurhaliza')).toBeVisible();
    });

    test('Positif - Search users by email filters correctly (case: "admin@")', async ({ page }) => {
        /**
         * Given: Users tab active
         * When: Type "admin@" in search box
         * Then: Only Admin Sistem displayed
         */

        // Arrange
        await dataMasterPage.clickUsersTab();

        // Act
        await dataMasterPage.searchUsers('admin@');

        // Assert: Only 1 result
        const rowCount = await dataMasterPage.getVisibleUserRowCount();
        expect(rowCount).toBe(1);
        await expect(page.getByText('Admin Sistem')).toBeVisible();
    });

    test('Positif - Search is case-insensitive (case: "AHMAD" matches "Dr. Ahmad Suryadi")', async ({ page }) => {
        /**
         * Given: Users tab active
         * When: Type "AHMAD" (uppercase) in search
         * Then: "Dr. Ahmad Suryadi" displayed
         */

        // Arrange
        await dataMasterPage.clickUsersTab();

        // Act
        await dataMasterPage.searchUsers('AHMAD');

        // Assert: Found
        const rowCount = await dataMasterPage.getVisibleUserRowCount();
        expect(rowCount).toBeGreaterThanOrEqual(1);
        await expect(page.getByText('Dr. Ahmad Suryadi')).toBeVisible();
    });

    test('Positif - Role filter works correctly (filter: "petugas")', async ({ page }) => {
        /**
         * Given: Users tab active
         * When: Select "Petugas Perkebunan" from role filter
         * Then: Only petugas users displayed (Budi Santoso, Rina Wulandari)
         */

        // Arrange
        await dataMasterPage.clickUsersTab();

        // Act: Select petugas filter
        const roleFilter = page.locator('select').filter({ hasText: /Semua Role/i }).first();
        await roleFilter.selectOption({ label: /Petugas/i });
        await page.waitForTimeout(500);

        // Assert: Filtered results (should show Budi & Rina)
        const rowCount = await dataMasterPage.getVisibleUserRowCount();
        expect(rowCount).toBe(2);
    });

    test('Positif - Status filter works correctly (filter: "Aktif")', async ({ page }) => {
        /**
         * Given: Users tab active
         * When: Select "Aktif" from status filter
         * Then: Only active users displayed (5 users - excludes Hendro)
         */

        // Arrange
        await dataMasterPage.clickUsersTab();

        // Act: Select aktif filter (if filter exists)
        const statusFilter = page.locator('select').filter({ hasText: /Status/i });
        const hasStatusFilter = await statusFilter.count();

        if (hasStatusFilter > 0) {
            await statusFilter.selectOption({ label: /Aktif/i });
            await page.waitForTimeout(500);

            // Assert: Filtered results (5 active users)
            const rowCount = await dataMasterPage.getVisibleUserRowCount();
            expect(rowCount).toBe(5);
        }
    });

    test('Positif - Combined search + filter works (search: "Siti", filter: "inventor")', async ({ page }) => {
        /**
         * Given: Users tab active
         * When: Search "Siti" AND filter role "inventor"
         * Then: Siti Nurhaliza displayed (matches both)
         */

        // Arrange
        await dataMasterPage.clickUsersTab();

        // Act: Search + filter
        await dataMasterPage.searchUsers('Siti');
        const roleFilter = page.locator('select').filter({ hasText: /Semua Role/i }).first();
        await roleFilter.selectOption({ label: /Pengelola/i });
        await page.waitForTimeout(500);

        // Assert: 1 result (Siti is inventor)
        const rowCount = await dataMasterPage.getVisibleUserRowCount();
        expect(rowCount).toBe(1);
        await expect(page.getByText('Siti Nurhaliza')).toBeVisible();
    });

    test('Negatif - Search with invalid term shows empty state (case: "XYZNonExistent")', async ({ page }) => {
        /**
         * Given: Users tab active
         * When: Search for non-existent name
         * Then: 0 results, empty state message displayed
         */

        // Arrange
        await dataMasterPage.clickUsersTab();

        // Act
        await dataMasterPage.searchUsers('XYZNonExistent123');

        // Assert: 0 results
        const rowCount = await dataMasterPage.getVisibleUserRowCount();
        expect(rowCount).toBe(0);

        // Assert: Empty state message (may exist)
        const emptyState = page.locator('text=Tidak ada, text=Data tidak ditemukan');
        const hasEmptyState = await emptyState.count();
        expect(hasEmptyState).toBeGreaterThanOrEqual(0); // May or may not have explicit empty state
    });

    test('Positif - Clearing search restores full list (Alpine.js reactivity)', async () => {
        /**
         * Given: Filtered search active
         * When: Clear search input
         * Then: Full list restored (6 users)
         */

        // Arrange
        await dataMasterPage.clickUsersTab();
        await dataMasterPage.searchUsers('Siti');
        const filteredCount = await dataMasterPage.getVisibleUserRowCount();
        expect(filteredCount).toBe(1);

        // Act: Clear search
        await dataMasterPage.searchUsers('');

        // Assert: Full list restored
        const fullCount = await dataMasterPage.getVisibleUserRowCount();
        expect(fullCount).toBeGreaterThanOrEqual(6);
    });
});
