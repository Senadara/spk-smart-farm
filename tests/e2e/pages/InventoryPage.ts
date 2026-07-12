import { Page, Locator, expect } from '@playwright/test';

export class InventoryPage {
    readonly page: Page;
    
    // ─── Page Heading ──────────────────────────────────────────
    readonly inventoryHeading: Locator;

    // ─── KPI Cards (Metrics Row) ───────────────────────────────
    readonly kpiCards: Locator;
    readonly totalInventoryCard: Locator;
    readonly lowStockCard: Locator;
    readonly criticalStockCard: Locator;
    readonly avgDaysRemainingCard: Locator;

    // ─── Restock Recommendations Section (AHP-SAW) ─────────────
    // NOTE: Restock is NOT a table - it's a scrollable div-based card layout
    readonly restockHeading: Locator;
    readonly restockContainer: Locator;
    readonly restockCardItems: Locator;

    // ─── Inventory Detail Table Section ────────────────────────
    readonly inventoryTableHeading: Locator;
    readonly inventoryTable: Locator;
    readonly inventoryRows: Locator;

    // ─── Filters & Search ──────────────────────────────────────
    readonly barnFilter: Locator;
    readonly categoryFilter: Locator;
    readonly tableStatusFilter: Locator;
    readonly searchInput: Locator;

    // ─── Movement Log Section (Timeline) ──────────────────────
    readonly movementLogHeading: Locator;
    readonly movementLogTimeline: Locator;
    readonly movementLogEntries: Locator;

    // ─── Action Buttons ────────────────────────────────────────
    readonly addInventoryBtn: Locator;
    readonly adjustmentBtn: Locator;

    // ─── Status & Messages ────────────────────────────────────
    readonly toastSuccess: Locator;
    readonly toastError: Locator;

    constructor(page: Page) {
        this.page = page;
        
        // ─── Page Heading ──────────────────────────────────────
        this.inventoryHeading = page.locator('h1').filter({ hasText: /Manajemen Inventaris|Inventory/i });

        // ─── KPI Cards ─────────────────────────────────────────
        // KPI cards are rendered as x-peternakan.kpi-card components in a grid
        this.kpiCards = page.locator('[class*="grid"][class*="grid-cols"]').first().locator('[class*="border"][class*="rounded"]').filter({ hasText: /Total|Low|Critical|Days/i });
        // Get first grid container which has the KPI cards (grid grid-cols-2 lg:grid-cols-4)
        const kpiGridContainer = page.locator('div.grid[class*="grid-cols"]').first();
        this.totalInventoryCard = kpiGridContainer.locator('div').filter({ hasText: /Total Inventory Items/ }).first();
        this.lowStockCard = kpiGridContainer.locator('div').filter({ hasText: /Low Stock Items/ }).first();
        this.criticalStockCard = kpiGridContainer.locator('div').filter({ hasText: /Critical Stock/ }).first();
        this.avgDaysRemainingCard = kpiGridContainer.locator('div').filter({ hasText: /Avg\. Days Remaining/ }).first();

        // ─── Restock Recommendations (AHP-SAW Card) ────────────
        // This is NOT a table - it's a card-based layout with "Smart Restock AI" heading
        this.restockHeading = page.locator('h3').filter({ hasText: /Smart Restock AI/i });
        // The restock container is a specific card div with emerald-100 border
        this.restockContainer = page.locator('div[class*="lg:col-span-1"][class*="border-emerald-100"][class*="rounded-xl"]').first();
        // Restock items are shown as cards in a scrollable container with class "custom-scrollbar"
        this.restockCardItems = this.restockContainer.locator('[class*="border"][class*="rounded-lg"][class*="p-3"]').filter({ hasText: /Priority|Critical|Warning|Safe/i });

        // ─── Inventory Detail Table ────────────────────────────
        // Table heading: "Detail Inventaris"
        this.inventoryTableHeading = page.locator('h3').filter({ hasText: /Detail Inventaris/i });
        this.inventoryTable = page.locator('table').filter({ hasText: /Item & Kategori|Stok|Est\. Habis|Status/i });
        // Rows are in tbody with class "divide-y divide-gray-50"
        this.inventoryRows = this.inventoryTable.locator('tbody.divide-y > tr:visible');

        // ─── Filters & Search ──────────────────────────────────
        // Top level filters: Barn, Category
        this.barnFilter = page.locator('select').filter({ hasText: /Semua Kandang|Barn/i }).first();
        this.categoryFilter = page.locator('select').filter({ hasText: /Semua Kategori|Pakan|Obat/i }).first();
        // Table level filters: Status, Search
        this.tableStatusFilter = page.locator('select').filter({ hasText: /Semua Status|Optimal|Warning|Critical/i });
        this.searchInput = page.getByPlaceholder(/Cari item/i);

        // ─── Movement Log (Timeline) ──────────────────────────
        this.movementLogHeading = page.locator('h3').filter({ hasText: /Riwayat Pergerakan Stok/i });
        this.movementLogTimeline = page.locator('div').filter({ hasText: /Riwayat Pergerakan Stok/i }).locator('..').locator('[class*="border-l"]');
        this.movementLogEntries = this.movementLogTimeline.locator('div').filter({ hasText: /inflow|outflow|adjustment/i });

        // ─── Action Buttons ────────────────────────────────────
        this.addInventoryBtn = page.locator('button').filter({ hasText: /Tambah Inventaris/i });
        this.adjustmentBtn = page.locator('button').filter({ hasText: /Adjustment/i });

        // ─── Status Messages ───────────────────────────────────
        this.toastSuccess = page.locator('#toastContainer .toast-success, [class*="success"][role="alert"]');
        this.toastError = page.locator('#toastContainer .toast-error, [class*="error"][role="alert"]');
    }

    // ─── Navigation ────────────────────────────────────────────
    async goto() {
        await this.page.goto('/inventory', { waitUntil: 'domcontentloaded', timeout: 120000 });
        await this.expectToBeOnInventoryPage();
    }

    // ─── Expectations ──────────────────────────────────────────
    async expectToBeOnInventoryPage() {
        await expect(this.inventoryHeading).toBeVisible({ timeout: 15000 });
    }

    async expectKpiCardsVisible() {
        await expect(this.kpiCards.first()).toBeVisible({ timeout: 10000 });
    }

    async expectRestockSectionVisible() {
        await expect(this.restockHeading).toBeVisible({ timeout: 10000 });
    }

    async expectInventoryTableVisible() {
        await expect(this.inventoryTable).toBeVisible({ timeout: 10000 });
    }

    async expectMovementLogVisible() {
        await expect(this.movementLogHeading).toBeVisible({ timeout: 10000 });
    }

    // ─── Interactions ──────────────────────────────────────────
    async filterByBarn(barn: string) {
        await this.barnFilter.selectOption(barn);
        await this.page.waitForLoadState('networkidle').catch(() => {});
    }

    async filterByCategory(category: string) {
        await this.categoryFilter.selectOption(category);
        await this.page.waitForLoadState('networkidle').catch(() => {});
    }

    async filterByTableStatus(status: string) {
        await this.tableStatusFilter.selectOption(status);
        await this.page.waitForLoadState('networkidle').catch(() => {});
    }

    async searchInventory(query: string) {
        await this.searchInput.fill(query);
        await this.searchInput.press('Enter');
        await this.page.waitForLoadState('networkidle').catch(() => {});
    }

    async getInventoryRowCount(): Promise<number> {
        return await this.inventoryRows.count();
    }

    async getRestockCardCount(): Promise<number> {
        return await this.restockCardItems.count();
    }

    async getMovementLogEntryCount(): Promise<number> {
        return await this.movementLogEntries.count();
    }

    async getInventoryItemById(itemId: string): Promise<Locator> {
        return this.inventoryTable.locator(`tr:has-text("${itemId}")`);
    }
}
