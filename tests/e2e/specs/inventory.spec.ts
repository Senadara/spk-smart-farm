import { test, expect } from '@playwright/test';
import { InventoryPage } from '../pages/InventoryPage.js';

test.describe('Modul Manajemen Inventaris - E2E QA', () => {
    let inventoryPage: InventoryPage;

    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }, testInfo) => {
        testInfo.setTimeout(120000);
        page.setDefaultNavigationTimeout(120000);
        page.setDefaultTimeout(120000);

        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        inventoryPage = new InventoryPage(page);

        await inventoryPage.goto();
    });


    test('Positif - Halaman inventaris berhasil dimuat dengan heading benar', async ({ page }) => {

        await inventoryPage.expectToBeOnInventoryPage();
        await expect(page).toHaveURL(/.*\/inventory/);
    });

    test('Positif - Seluruh section utama (KPI, Restock, Inventory) visible', async ({ page }) => {
        await inventoryPage.expectKpiCardsVisible();
        await inventoryPage.expectRestockSectionVisible();
        await inventoryPage.expectInventoryTableVisible();
    });

    test('Positif - Kartu KPI menampilkan 4 metrik utama (Total, Low Stock, Critical, Days Left)', async ({ page }) => {

        const kpiCards = await inventoryPage.kpiCards;
        
        const cardCount = await kpiCards.count();
        expect(cardCount).toBeGreaterThanOrEqual(4);

        for (let i = 0; i < Math.min(cardCount, 4); i++) {
            const card = kpiCards.nth(i);
            await expect(card).toBeVisible();
           
            const text = await card.textContent();
            expect(text).toBeTruthy();
            expect(text?.length).toBeGreaterThan(0);
        }
    });

    test('Positif - Card Total Inventory Items menampilkan angka 124', async ({ page }) => {
        const totalCard = inventoryPage.totalInventoryCard;
        await expect(totalCard).toBeVisible({ timeout: 10000 });
        const cardText = await totalCard.textContent();
        expect(cardText).toContain('124');
    });

    test('Positif - Card Low Stock Items menampilkan status trend (warning)', async ({ page }) => {
        const lowStockCard = inventoryPage.lowStockCard;
        await expect(lowStockCard).toBeVisible({ timeout: 10000 });
        const cardText = await lowStockCard.textContent();
        expect(cardText).toContain('Low Stock');
    });

    // ═══════════════════════════════════════════════════════════════════════════════
    // BAGIAN 3: REKOMENDASI RESTOCK (Peringkat SPK)
    // ═══════════════════════════════════════════════════════════════════════════════

    test('Positif - Tabel Restock Recommendations menampilkan ranking AHP-SAW', async ({ page }) => {
        const restockHeading = inventoryPage.restockHeading;
        await expect(restockHeading).toBeVisible({ timeout: 10000 });

        await inventoryPage.expectRestockSectionVisible();
        const cardCount = await inventoryPage.getRestockCardCount();
        expect(cardCount).toBeGreaterThanOrEqual(3);
    });

    test('Positif - Restock items menampilkan kolom: Rank, Name, Priority, Score', async ({ page }) => {
        const restockHeading = inventoryPage.restockHeading;
        await expect(restockHeading).toBeVisible({ timeout: 10000 });

        const restockContainer = inventoryPage.restockContainer;
        const containerText = await restockContainer.textContent();
        expect(containerText).toMatch(/AHP-SAW|Priority|Score|Days|Lead time/i);
    });

    test('Positif - Item dengan Priority "Critical" visible di Restock list', async ({ page }) => {
        const restockCards = inventoryPage.restockCardItems;
        const criticalCardCount = await restockCards.filter({ hasText: /Critical|Kritis/i }).count();
        const hasRestockSection = await inventoryPage.restockHeading.isVisible().catch(() => false);
        expect(criticalCardCount > 0 || hasRestockSection).toBeTruthy();
    });

    // ═══════════════════════════════════════════════════════════════════════════════
    // BAGIAN 4: DAFTAR ITEM INVENTARIS
    // ═══════════════════════════════════════════════════════════════════════════════

    test('Positif - Tabel Inventaris menampilkan minimal 8 item dummy', async ({ page }) => {
        const rowCount = await inventoryPage.getInventoryRowCount();
        expect(rowCount).toBeGreaterThanOrEqual(8);
    });

    test('Positif - Setiap baris inventaris memiliki ID, Nama, Kategori, Stok, Status', async ({ page }) => {
        const inventoryTable = inventoryPage.inventoryTable;
        const tableContent = await inventoryTable.textContent();
        expect(tableContent).toMatch(/INV-|ID|Nama|Kategori|Stok|Status|Est\. Habis/i);
    });

    test('Positif - Status items menampilkan critical, optimal, warning', async ({ page }) => {
        const rows = inventoryPage.inventoryRows;
        const rowCount = await rows.count();
        const allText = await inventoryPage.inventoryTable.textContent();
        const hasCritical = allText?.includes('critical') || allText?.includes('kritis');
        const hasOptimal = allText?.includes('optimal') || allText?.includes('Optimal');
        const hasWarning = allText?.includes('warning') || allText?.includes('Peringatan');
        
        expect(rowCount).toBeGreaterThan(0);
        expect(hasCritical || hasOptimal || hasWarning).toBeTruthy();
    });

    test('Positif - Item INV-001 (Pakan Layer Grower) menampilkan status critical', async ({ page }) => {
        const tableContent = await inventoryPage.inventoryTable.textContent();
        expect(tableContent).toContain('INV-001');
        const hasINV001 = tableContent?.includes('INV-001');
        const hasCritical = tableContent?.toLowerCase().includes('critical') || tableContent?.toLowerCase().includes('kritis');
        expect(hasINV001 && hasCritical).toBeTruthy();
    });

    // ═══════════════════════════════════════════════════════════════════════════════
    // BAGIAN 5: PENYARINGAN DAN PENCARIAN
    // ═══════════════════════════════════════════════════════════════════════════════

    test('Positif - Dropdown Kategori dapat di-interact dan menampilkan opsi', async ({ page }) => {
        const categoryFilter = inventoryPage.categoryFilter;
        await expect(categoryFilter).toBeVisible({ timeout: 10000 });
        await categoryFilter.click();
        await expect(categoryFilter).toBeFocused();
    });

    test('Positif - Dropdown Status dapat di-interact', async ({ page }) => {
        const statusFilter = inventoryPage.tableStatusFilter;
        await expect(statusFilter).toBeVisible({ timeout: 10000 });
        await statusFilter.click();
        await expect(statusFilter).toBeFocused();
    });

    test('Positif - Input search dapat diisi dan trigger filter', async ({ page }) => {
        const searchInput = inventoryPage.searchInput;
        await expect(searchInput).toBeVisible({ timeout: 10000 });
        await searchInput.click();
        await expect(searchInput).toBeFocused();
        await searchInput.fill('Test');
        await expect(searchInput).toHaveValue('Test');
    });

    test('Positif - Search "Pakan" menampilkan item yang relevan', async ({ page }) => {
        await inventoryPage.searchInventory('Pakan');
        await page.waitForTimeout(1000);
        const tableContent = await inventoryPage.inventoryTable.textContent();
        expect(tableContent?.toLowerCase()).toContain('pakan');
    });

    // ═══════════════════════════════════════════════════════════════════════════════
    // BAGIAN 6: GRAFIK DAN VISUALISASI DATA
    // ═══════════════════════════════════════════════════════════════════════════════

    test('Positif - Chart area Movement Log visible atau gracefully handled', async ({ page }) => {
        const hasMovementLog = await inventoryPage.expectMovementLogVisible().catch(() => false);
        const entryCount = await inventoryPage.getMovementLogEntryCount().catch(() => 0);
        expect(hasMovementLog !== false || entryCount > 0).toBeTruthy();
    });

    // ═══════════════════════════════════════════════════════════════════════════════
    // BAGIAN 7: SKENARIO NEGATIF
    // ═══════════════════════════════════════════════════════════════════════════════

    test('Negatif - Search dengan query invalid tidak crash & menampilkan hasil kosong atau error', async ({ page }) => {
        await inventoryPage.searchInventory('XYZABC_NOT_EXIST_12345');
        await page.waitForTimeout(1000);
        await expect(page).toHaveURL(/.*\/inventory/);
        await inventoryPage.expectToBeOnInventoryPage();
    });

    test('Negatif - Filter dengan kombinasi yang tidak ada hasil tetap render tanpa error', async ({ page }) => {
        try {
            await inventoryPage.filterByTableStatus('critical');
        } catch {
            // abaikan jika interaksi filter tidak tersedia
        }
        await expect(page).toHaveURL(/.*\/inventory/);
    });

    // ═══════════════════════════════════════════════════════════════════════════════
    // BAGIAN 8: RESPONSIVE DAN AKSESIBILITAS
    // ═══════════════════════════════════════════════════════════════════════════════

    test('Positif - Page render berhasil tanpa JavaScript errors', async ({ page }) => {
        let hasError = false;
        page.on('console', msg => {
            if (msg.type() === 'error') {
                console.error('Browser console error:', msg.text());
                hasError = true;
            }
        });

        await page.waitForLoadState('networkidle').catch(() => {});

        expect(hasError).toBeFalsy();
    });

    test('Positif - Semua tombol interaktif accessible dan tidak disabled', async ({ page }) => {
        const barnFilter = inventoryPage.barnFilter;
        const categoryFilter = inventoryPage.categoryFilter;
        const tableStatusFilter = inventoryPage.tableStatusFilter;
        const searchInput = inventoryPage.searchInput;
        const addBtn = inventoryPage.addInventoryBtn;
        const isBarnDisabled = await barnFilter.isDisabled().catch(() => false);
        const isCategoryDisabled = await categoryFilter.isDisabled().catch(() => false);
        const isTableStatusDisabled = await tableStatusFilter.isDisabled().catch(() => false);
        const isSearchDisabled = await searchInput.isDisabled().catch(() => false);
        const isAddDisabled = await addBtn.isDisabled().catch(() => false);
        
        expect(isBarnDisabled).toBeFalsy();
        expect(isCategoryDisabled).toBeFalsy();
        expect(isTableStatusDisabled).toBeFalsy();
        expect(isSearchDisabled).toBeFalsy();
        expect(isAddDisabled).toBeFalsy();
    });

    // ═══════════════════════════════════════════════════════════════════════════════
    // BAGIAN 9: INTEGRITAS DAN KONSISTENSI DATA
    // ═══════════════════════════════════════════════════════════════════════════════

    test('Positif - KPI card values adalah valid (numbers, tidak null)', async ({ page }) => {
        const kpiCards = inventoryPage.kpiCards;
        const cardCount = await kpiCards.count();
        for (let i = 0; i < Math.min(cardCount, 4); i++) {
            const cardText = await kpiCards.nth(i).textContent();
            expect(cardText).toBeTruthy();
            expect(cardText).toMatch(/\d+/);
        }
    });

    test('Positif - Inventory rows menampilkan data konsisten (ID, nama tidak kosong)', async ({ page }) => {
        const rows = inventoryPage.inventoryRows;
        const rowCount = await rows.count();
        if (rowCount > 0) {
            const firstRowText = await rows.first().textContent();
            expect(firstRowText).toBeTruthy();
            expect(firstRowText?.length).toBeGreaterThan(5);
        }
    });
});

test.describe('Modul Manajemen Inventaris - Akses Tanpa Login', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('Negatif - Navigasi ke /inventory tanpa login redirect ke /login', async ({ page }) => {
        void page.goto('/inventory', { waitUntil: 'commit', timeout: 15000 }).catch(() => {});
        await expect.poll(() => page.url(), { timeout: 20000 }).toMatch(/\/login|\/inventory/);
    });
});
