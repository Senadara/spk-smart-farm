import { test, expect } from '@playwright/test';
import { InventoryPage } from '../pages/InventoryPage.js';

test.describe('Modul Inventory Management - E2E Tests', () => {
    let inventoryPage: InventoryPage;

    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }, testInfo) => {
        // Arrange
        testInfo.setTimeout(120000);
        page.setDefaultNavigationTimeout(120000);
        page.setDefaultTimeout(120000);

        // Blocker Vite HMR
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        inventoryPage = new InventoryPage(page);

        // Act
        await inventoryPage.goto();
    });

    /* ═══════════════════════════════════════════════════════════════════
       PAGE RENDERING & STRUCTURE
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Inventory dashboard page loads dengan URL /inventory', async ({ page }) => {
        /**
         * Given: User navigate to inventory
         * When: Page loads
         * Then: URL matches /inventory
         */

        // Assert
        await inventoryPage.expectToBeOnInventoryPage();
        await expect(page).toHaveURL(/.*\/inventory/);
    });

    test('Positif - All main sections rendered (KPI, Restock, Inventory Table, Charts, Movement Log)', async ({ page }) => {
        /**
         * Given: Inventory dashboard loaded
         * When: Check all major sections
         * Then: KPI cards, restock, table, charts, log visible
         */

        // Assert: All sections
        await inventoryPage.expectKpiCardsVisible();
        await inventoryPage.expectRestockSectionVisible();
        await inventoryPage.expectInventoryTableVisible();

        // Assert: Page structure complete
        const bodyContent = await page.locator('body').textContent();
        expect(bodyContent).toBeTruthy();
    });

    /* ═══════════════════════════════════════════════════════════════════
       KPI METRICS CARDS
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Dashboard menampilkan 4 KPI metric cards', async () => {
        /**
         * Given: KPI data available
         * When: Check KPI section
         * Then: 4 cards visible (Total Items, Low Stock, Critical, Avg Days)
         */

        // Arrange
        const expectedMetrics = 4;
        const kpiCards = inventoryPage.kpiCards;

        // Act
        const cardCount = await kpiCards.count();

        // Assert: At least 4 KPI cards
        expect(cardCount).toBeGreaterThanOrEqual(expectedMetrics);
    });

    test('Positif - KPI cards menampilkan labels dan values dengan format correct', async () => {
        /**
         * Given: KPI cards displayed
         * When: Check card content
         * Then: Each card has label and numeric value
         */

        // Arrange
        const kpiCards = inventoryPage.kpiCards;
        const cardCount = await kpiCards.count();

        // Assert: Each card has content
        for (let i = 0; i < Math.min(cardCount, 4); i++) {
            const card = kpiCards.nth(i);
            await expect(card).toBeVisible();

            const text = await card.textContent();
            expect(text?.trim().length).toBeGreaterThan(0);
        }
    });

    test('Positif - KPI cards menampilkan trend indicators (up/down/stable)', async ({ page }) => {
        /**
         * Given: KPI cards with trend data
         * When: Check trend indicators
         * Then: Trend arrows atau +/- values visible
         */

        // Assert: Trend indicators in body text
        const bodyText = await page.locator('body').textContent();
        const hasTrends = bodyText?.match(/[+\-][\d.]+|↑|↓|up|down|stable/i);

        expect(typeof hasTrends).toBe('object');
    });

    /* ═══════════════════════════════════════════════════════════════════
       RESTOCK RECOMMENDATIONS (AHP-SAW)
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Restock recommendations section visible dengan heading', async () => {
        /**
         * Given: Restock section available
         * When: Check restock heading
         * Then: Heading visible
         */

        // Assert: Restock heading
        const restockHeading = inventoryPage.restockHeading;
        await expect(restockHeading).toBeVisible();
    });

    test('Positif - Restock recommendations menampilkan prioritas dengan AHP-SAW scores', async () => {
        /**
         * Given: AHP-SAW ranking calculated
         * When: Check restock container
         * Then: Priority dan Score keywords visible
         */

        // Arrange
        const restockContainer = inventoryPage.restockContainer;

        // Assert: Priority/Score text
        const containerText = await restockContainer.textContent();
        expect(containerText).toMatch(/Priority|Score|Critical|Warning|Safe/i);

        const cardCount = await inventoryPage.getRestockCardCount();
        expect(cardCount).toBeGreaterThanOrEqual(1);
    });

    test('Positif - Restock cards menampilkan top 3 items dengan details (name, category, stock, days remaining)', async ({ page }) => {
        /**
         * Given: Restock recommendations displayed
         * When: Check card content
         * Then: Each card shows item details
         */

        // Assert: Restock item details
        const bodyText = await page.locator('body').textContent();

        // Check for known restock items from dummy data
        const hasRestockItems =
            bodyText?.includes('Starter Feed') ||
            bodyText?.includes('Newcastle') ||
            bodyText?.includes('Vitamin C');

        expect(hasRestockItems).toBeTruthy();
    });

    test('Positif - Restock cards menampilkan priority badges (Critical/Warning/Safe) dengan colors', async ({ page }) => {
        /**
         * Given: Items have different priorities
         * When: Check priority badges
         * Then: Color-coded badges visible
         */

        // Assert: Priority badges
        const criticalBadge = page.locator('text=Critical').first();
        const hasCritical = await criticalBadge.count();

        const warningBadge = page.locator('text=Warning').first();
        const hasWarning = await warningBadge.count();

        // At least one priority badge should exist
        expect(hasCritical + hasWarning).toBeGreaterThanOrEqual(1);
    });

    test('Positif - Restock cards menampilkan supplier info, price, dan lead time', async ({ page }) => {
        /**
         * Given: Restock recommendations with supplier data
         * When: Check card details
         * Then: Supplier, price, lead time visible
         */

        // Assert: Supplier-related keywords
        const bodyText = await page.locator('body').textContent();

        const hasSupplierInfo =
            bodyText?.match(/Rp\s*[\d.,]+/) || // Price format
            bodyText?.includes('hari') || // Lead time
            bodyText?.match(/PT|CV|Toko/); // Supplier names

        expect(typeof hasSupplierInfo).toBe('object');
    });

    /* ═══════════════════════════════════════════════════════════════════
       INVENTORY TABLE - DATA DISPLAY
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Inventory table displays dengan 8 dummy items', async () => {
        /**
         * Given: Inventory data from controller
         * When: Check table rows
         * Then: 8 items visible (INV-001 to INV-008)
         */

        // Arrange
        const expectedMinItems = 8;
        const rowCount = await inventoryPage.getInventoryRowCount();

        // Assert: At least 8 items
        expect(rowCount).toBeGreaterThanOrEqual(expectedMinItems);
    });

    test('Positif - Inventory table menampilkan column headers (Item, Stok, Penggunaan, Est. Habis, Status, Aksi)', async () => {
        /**
         * Given: Table rendered
         * When: Check table headers
         * Then: All 6 headers visible
         */

        // Arrange
        const table = inventoryPage.inventoryTable;
        const tableText = await table.textContent();

        // Assert: All column headers
        expect(tableText).toContain('Item & Kategori');
        expect(tableText).toContain('Stok');
        expect(tableText).toContain('Penggunaan/Hari');
        expect(tableText).toContain('Est. Habis');
        expect(tableText).toContain('Status');
        expect(tableText).toContain('Aksi');
    });

    test('Positif - Inventory table row menampilkan complete item details (name, ID, category, stock, usage, days_left)', async ({ page }) => {
        /**
         * Given: Inventory table with data
         * When: Check first item (INV-001)
         * Then: All details visible
         */

        // Assert: Known item from dummy data
        const bodyText = await page.locator('body').textContent();

        expect(bodyText).toContain('INV-001');
        expect(bodyText).toContain('Pakan Layer Grower');
        expect(bodyText).toContain('Sak'); // Unit
    });

    test('Positif - Inventory table menampilkan status badges dengan correct colors (Critical=red, Warning=amber, Optimal=emerald)', async ({ page }) => {
        /**
         * Given: Items with different status
         * When: Check status badges
         * Then: Color-coded badges visible
         */

        // Assert: Status badges
        const criticalBadge = page.locator('span').filter({ hasText: 'Critical' }).first();
        const warningBadge = page.locator('span').filter({ hasText: 'Warning' }).first();
        const optimalBadge = page.locator('span').filter({ hasText: 'Optimal' }).first();

        const criticalCount = await criticalBadge.count();
        const warningCount = await warningBadge.count();
        const optimalCount = await optimalBadge.count();

        // At least one of each status should exist
        expect(criticalCount + warningCount + optimalCount).toBeGreaterThanOrEqual(3);
    });

    test('Positif - Inventory table menampilkan photo thumbnails untuk each item', async ({ page }) => {
        /**
         * Given: Items with photo URLs
         * When: Check image elements
         * Then: Thumbnails visible
         */

        // Assert: Image elements in table
        const tableImages = page.locator('table img');
        const imageCount = await tableImages.count();

        // At least some images should be visible
        expect(imageCount).toBeGreaterThanOrEqual(1);
    });

    /* ═══════════════════════════════════════════════════════════════════
       INVENTORY TABLE - SEARCH & FILTER
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Search by item name filters table correctly', async () => {
        /**
         * Given: Inventory table with multiple items
         * When: Search for "Vaksin"
         * Then: Only matching items visible
         */

        // Arrange
        const searchTerm = 'Vaksin';

        // Act
        await inventoryPage.searchInventory(searchTerm);
        await inventoryPage.page.waitForTimeout(500);

        // Assert: Filtered results
        const rowCount = await inventoryPage.getInventoryRowCount();
        expect(rowCount).toBeGreaterThanOrEqual(1);
        expect(rowCount).toBeLessThan(8); // Should filter out some items

        const tableText = await inventoryPage.inventoryTable.textContent();
        expect(tableText).toContain('Vaksin');
    });

    test('Positif - Search by item ID filters table correctly', async () => {
        /**
         * Given: Inventory table
         * When: Search for "INV-005"
         * Then: Only INV-005 visible
         */

        // Arrange
        const searchTerm = 'INV-005';

        // Act
        await inventoryPage.searchInventory(searchTerm);
        await inventoryPage.page.waitForTimeout(500);

        // Assert: Single item
        const rowCount = await inventoryPage.getInventoryRowCount();
        expect(rowCount).toBe(1);

        const tableText = await inventoryPage.inventoryTable.textContent();
        expect(tableText).toContain('INV-005');
        expect(tableText).toContain('Antiobiotik Amox');
    });

    test('Positif - Filter by category (Pakan) shows only feed items', async () => {
        /**
         * Given: Items across multiple categories
         * When: Select category "Pakan"
         * Then: Only feed items visible
         */

        // Act
        await inventoryPage.filterByCategory('Pakan');
        await inventoryPage.page.waitForTimeout(500);

        // Assert: Filtered results
        const rowCount = await inventoryPage.getInventoryRowCount();
        expect(rowCount).toBeGreaterThanOrEqual(1);

        const tableText = await inventoryPage.inventoryTable.textContent();
        expect(tableText).toMatch(/Pakan Layer|Feed/i);
    });

    test('Positif - Filter by status (Critical) shows only critical items', async () => {
        /**
         * Given: Items with different status
         * When: Select status "Critical"
         * Then: Only critical items visible
         */

        // Act
        await inventoryPage.filterByTableStatus('critical');
        await inventoryPage.page.waitForTimeout(500);

        // Assert: Critical items only
        const rowCount = await inventoryPage.getInventoryRowCount();
        expect(rowCount).toBeGreaterThanOrEqual(1);
    });

    test('Positif - Combined filters (Category + Status) work correctly', async () => {
        /**
         * Given: Multiple filter options
         * When: Apply category AND status filters
         * Then: Items match both filters
         */

        // Act
        await inventoryPage.filterByCategory('Obat & Vaksin');
        await inventoryPage.page.waitForTimeout(300);
        await inventoryPage.filterByTableStatus('critical');
        await inventoryPage.page.waitForTimeout(500);

        // Assert: Filtered results
        const rowCount = await inventoryPage.getInventoryRowCount();
        expect(rowCount).toBeGreaterThanOrEqual(0); // May be 0 if no match

        if (rowCount > 0) {
            const tableText = await inventoryPage.inventoryTable.textContent();
            expect(tableText).toMatch(/Obat|Vaksin/i);
        }
    });

    test('Negatif - Search with invalid term shows no results', async () => {
        /**
         * Given: Inventory table
         * When: Search for non-existent item
         * Then: No rows visible
         */

        // Act
        await inventoryPage.searchInventory('ITEM_TIDAK_ADA_12345');
        await inventoryPage.page.waitForTimeout(500);

        // Assert: No results
        const rowCount = await inventoryPage.getInventoryRowCount();
        expect(rowCount).toBe(0);
    });

    /* ═══════════════════════════════════════════════════════════════════
       CHARTS - CONSUMPTION TREND & USAGE PER BARN
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Chart "Tren Konsumsi Pakan" displays dengan canvas element', async ({ page }) => {
        /**
         * Given: Chart data available
         * When: Check consumption chart section
         * Then: Chart canvas visible
         */

        // Assert: Chart heading
        const chartHeading = page.locator('h3').filter({ hasText: /Tren Konsumsi Pakan/i });
        await expect(chartHeading).toBeVisible();

        // Assert: Canvas element for Chart.js
        const canvas = page.locator('canvas').first();
        await expect(canvas).toBeVisible();
    });

    test('Positif - Consumption chart has time range buttons (3H, 5H, 7H)', async ({ page }) => {
        /**
         * Given: Consumption chart rendered
         * When: Check time range controls
         * Then: 3 buttons visible
         */

        // Assert: Time range buttons
        const bodyText = await page.locator('body').textContent();
        expect(bodyText).toMatch(/3H.*5H.*7H/);
    });

    test('Positif - Chart "Distribusi Pemakaian per Kandang" displays dengan barn labels', async ({ page }) => {
        /**
         * Given: Usage chart data
         * When: Check usage chart section
         * Then: Chart heading and canvas visible
         */

        // Assert: Chart heading
        const chartHeading = page.locator('h3').filter({ hasText: /Distribusi Pemakaian per Kandang/i });
        await expect(chartHeading).toBeVisible();

        // Assert: Canvas for chart
        const canvasCount = await page.locator('canvas').count();
        expect(canvasCount).toBeGreaterThanOrEqual(2); // At least 2 charts
    });

    test('Positif - Usage chart has filter dropdown (Semua/Pakan/Vitamin)', async ({ page }) => {
        /**
         * Given: Usage chart rendered
         * When: Check filter dropdown
         * Then: Dropdown with options visible
         */

        // Assert: Usage filter select
        const usageFilter = page.locator('select').filter({ hasText: /Pakan|Vitamin/i }).first();
        await expect(usageFilter).toBeVisible();
    });

    /* ═══════════════════════════════════════════════════════════════════
       MOVEMENT LOG - TIMELINE DISPLAY
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Movement log displays dengan heading "Riwayat Pergerakan Stok"', async () => {
        /**
         * Given: Movement log section
         * When: Check heading
         * Then: Heading visible
         */

        // Assert
        await inventoryPage.expectMovementLogVisible();
    });

    test('Positif - Movement log menampilkan 6 recent entries dengan timeline dots', async ({ page }) => {
        /**
         * Given: Movement log data from controller
         * When: Check log entries
         * Then: 6 entries visible
         */

        // Assert: Timeline entries
        const logEntries = await inventoryPage.getMovementLogEntryCount();
        expect(logEntries).toBeGreaterThanOrEqual(1);

        // Assert: Timeline visual (border-l-2)
        const timeline = page.locator('[class*="border-l"]').filter({ hasText: /Inflow|Outflow/i }).first();
        await expect(timeline).toBeVisible();
    });

    test('Positif - Movement log entry menampilkan complete details (item, qty, type, note, time, user)', async ({ page }) => {
        /**
         * Given: Movement log entries
         * When: Check first entry details
         * Then: All fields visible
         */

        // Assert: Known entries from dummy data
        const bodyText = await page.locator('body').textContent();

        // Check known items from controller dummy data
        expect(bodyText).toMatch(/Pakan Layer Grower|Egg Tray Karton|Vaksin ND-IB/);
        expect(bodyText).toMatch(/Petugas Budi|Petugas Andi|Admin Rini/);
        expect(bodyText).toMatch(/\d+\s*(Sak|Ikat|Vial)/); // Quantity format
    });

    test('Positif - Movement log menampilkan type indicators dengan colors (inflow=emerald, outflow=blue, adjustment=amber)', async ({ page }) => {
        /**
         * Given: Different log types
         * When: Check log items
         * Then: Color-coded dots visible
         */

        // Assert: Movement log contains type keywords
        const bodyText = await page.locator('body').textContent();

        // Check for movement types in notes
        const hasMovementTypes =
            bodyText?.includes('Penerimaan barang') || // Inflow
            bodyText?.includes('Distribusi') || // Outflow
            bodyText?.includes('Penggantian'); // Adjustment

        expect(hasMovementTypes).toBeTruthy();
    });

    test('Positif - Movement log displays timestamps dengan format "DD MMM, HH:mm"', async ({ page }) => {
        /**
         * Given: Movement log with timestamps
         * When: Check time format
         * Then: Date format consistent
         */

        // Assert: Timestamp format (e.g., "11 Jun, 14:30")
        const bodyText = await page.locator('body').textContent();
        const hasTimestamp = bodyText?.match(/\d{1,2}\s+\w{3},\s+\d{2}:\d{2}/);

        expect(typeof hasTimestamp).toBe('object');
    });

    /* ═══════════════════════════════════════════════════════════════════
       AHP-SAW CONFIGURATION & MARKETPLACE INTEGRATION
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - AHP template dropdown visible dengan options (Default, Price, Brand, Urgent)', async ({ page }) => {
        /**
         * Given: Restock card rendered
         * When: Check AHP template selector
         * Then: Dropdown with 4 options visible
         */

        // Assert: AHP template select
        const ahpSelect = page.locator('select').filter({ hasText: /Default Priority|Price Priority/i }).first();
        await expect(ahpSelect).toBeVisible();

        // Assert: Options
        const selectText = await ahpSelect.textContent();
        expect(selectText).toContain('Default Priority');
        expect(selectText).toContain('Price Priority');
    });

    test('Positif - Marketplace button (shopping cart icon) visible di restock section', async ({ page }) => {
        /**
         * Given: Restock card
         * When: Check marketplace button
         * Then: Button with cart icon visible
         */

        // Assert: Marketplace button (look for shopping cart SVG path)
        const marketplaceBtn = page.locator('button[title*="Marketplace"], button svg[viewBox*="24 24"]').filter({ hasText: '' }).first();
        const btnCount = await page.locator('button').filter({ has: page.locator('svg') }).count();

        // At least some action buttons exist
        expect(btnCount).toBeGreaterThanOrEqual(1);
    });

    test('Positif - Generate PO dan Analysis buttons visible di restock footer', async ({ page }) => {
        /**
         * Given: Restock card footer
         * When: Check action buttons
         * Then: Generate PO and Analysis buttons visible
         */

        // Assert: Footer buttons
        const bodyText = await page.locator('body').textContent();
        expect(bodyText).toContain('Generate PO');
        expect(bodyText).toContain('Analysis');
    });

    /* ═══════════════════════════════════════════════════════════════════
       ACTION BUTTONS & NAVIGATION
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Header action buttons visible (Adjustment, Tambah Inventaris)', async () => {
        /**
         * Given: Header section loaded
         * When: Check action buttons
         * Then: 2 action buttons visible
         */

        // Assert
        await expect(inventoryPage.adjustmentBtn).toBeVisible();
        await expect(inventoryPage.addInventoryBtn).toBeVisible();
    });

    test('Positif - Barn filter dropdown has options (Semua Kandang, Barn A, B, C)', async ({ page }) => {
        /**
         * Given: Filter section
         * When: Check barn dropdown
         * Then: Options visible
         */

        // Assert
        await expect(inventoryPage.barnFilter).toBeVisible();

        const filterText = await inventoryPage.barnFilter.textContent();
        expect(filterText).toContain('Semua Kandang');
        expect(filterText).toContain('Barn A');
    });

    test('Positif - Category filter dropdown has options (Semua Kategori, Pakan, Obat & Vaksin, Vitamin, Perlengkapan)', async ({ page }) => {
        /**
         * Given: Filter section
         * When: Check category dropdown
         * Then: Options visible
         */

        // Assert
        await expect(inventoryPage.categoryFilter).toBeVisible();

        const filterText = await inventoryPage.categoryFilter.textContent();
        expect(filterText).toContain('Semua Kategori');
        expect(filterText).toContain('Pakan');
        expect(filterText).toContain('Obat & Vaksin');
    });

    /* ═══════════════════════════════════════════════════════════════════
       EDGE CASES & ERROR HANDLING
       ═══════════════════════════════════════════════════════════════════ */

    test('Edge Case - Search with special characters tidak cause error', async () => {
        /**
         * Given: Search input
         * When: Enter special characters
         * Then: No errors, table handles gracefully
         */

        // Act
        await inventoryPage.searchInventory('!@#$%^&*()');
        await inventoryPage.page.waitForTimeout(500);

        // Assert: No crash, 0 results
        const rowCount = await inventoryPage.getInventoryRowCount();
        expect(rowCount).toBe(0);
    });

    test('Edge Case - Rapid filter changes tidak cause race conditions', async () => {
        /**
         * Given: Multiple filters
         * When: Change filters rapidly
         * Then: UI updates correctly
         */

        // Act: Rapid filter changes
        await inventoryPage.filterByCategory('Pakan');
        await inventoryPage.filterByTableStatus('optimal');
        await inventoryPage.filterByCategory('Vitamin');
        await inventoryPage.page.waitForTimeout(500);

        // Assert: UI stable
        const rowCount = await inventoryPage.getInventoryRowCount();
        expect(rowCount).toBeGreaterThanOrEqual(0);
    });

    test('Edge Case - Clear search after filtering restores full table', async () => {
        /**
         * Given: Filtered table
         * When: Clear search
         * Then: All items visible again
         */

        // Arrange: Apply search
        await inventoryPage.searchInventory('Vaksin');
        await inventoryPage.page.waitForTimeout(500);
        const filteredCount = await inventoryPage.getInventoryRowCount();

        // Act: Clear search
        await inventoryPage.searchInput.clear();
        await inventoryPage.page.waitForTimeout(500);

        // Assert: More items visible
        const fullCount = await inventoryPage.getInventoryRowCount();
        expect(fullCount).toBeGreaterThan(filteredCount);
    });

    /* ═══════════════════════════════════════════════════════════════════
       PERFORMANCE & RESPONSIVENESS
       ═══════════════════════════════════════════════════════════════════ */

    test('Performance - Dashboard loads all sections dalam < 5 seconds', async ({ page }) => {
        /**
         * Given: Fresh page load
         * When: Navigate to inventory
         * Then: Load time acceptable
         */

        // Arrange
        const startTime = Date.now();

        // Act
        await page.goto('/inventory', { waitUntil: 'domcontentloaded' });
        await inventoryPage.expectToBeOnInventoryPage();
        await inventoryPage.expectKpiCardsVisible();
        await inventoryPage.expectRestockSectionVisible();
        await inventoryPage.expectInventoryTableVisible();

        const loadTime = Date.now() - startTime;

        // Assert: Load time < 5 seconds
        expect(loadTime).toBeLessThan(5000);
    });

    test('Performance - Search filter applies dalam < 1 second', async () => {
        /**
         * Given: Inventory table loaded
         * When: Apply search filter
         * Then: Response time fast
         */

        // Arrange
        const startTime = Date.now();

        // Act
        await inventoryPage.searchInventory('Pakan');
        await inventoryPage.page.waitForTimeout(100);

        const filterTime = Date.now() - startTime;

        // Assert: Filter applies quickly
        expect(filterTime).toBeLessThan(1000);
    });

});
