import { test, expect } from '@playwright/test';
import { InventoryPage } from '../pages/InventoryPage.js';

test.describe('Modul Manajemen Inventaris - E2E QA', () => {
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

    test('Positif - Render keseluruhan area inventaris secara sempurna', async ({ page }) => {
        /**
         * Given pengguna berada pada halaman Manajemen Inventaris
         * When halaman selesai dirender
         * Then seluruh section utama (KPI, Restock, Inventory Table) harus tampil dan heading URL valid
         */

        // Arrange
        const expectedUrlPattern = /.*\/inventory/;
        
        // Act (Dilakukan di beforeEach)

        // Assert
        await inventoryPage.expectToBeOnInventoryPage();
        await expect(page).toHaveURL(expectedUrlPattern);
        
        await inventoryPage.expectKpiCardsVisible();
        await inventoryPage.expectRestockSectionVisible();
        await inventoryPage.expectInventoryTableVisible();
    });

    test('Positif - Elemen Kartu KPI menampilkan minimal 4 metrik status secara konsisten', async ({ page }) => {
        /**
         * Given sistem inventory beroperasi normal
         * When area kartu KPI diakses
         * Then metrik stok utama (Total, Low Stock, Critical, Average Days) harus dirender
         */

        // Arrange
        const expectedMetrics = 4;
        const kpiCards = inventoryPage.kpiCards;

        // Act
        const cardCount = await kpiCards.count();

        // Assert
        expect(cardCount).toBeGreaterThanOrEqual(expectedMetrics);

        // Sub-Assert untuk memastikan tidak ada string kosong pada area KPI
        for (let i = 0; i < Math.min(cardCount, expectedMetrics); i++) {
            const card = kpiCards.nth(i);
            await expect(card).toBeVisible();
           
            const text = await card.textContent();
            expect(text?.trim().length).toBeGreaterThan(0);
        }
    });

    test('Positif - Rekomendasi Restock menampilkan urutan prioritas dengan skor AHP-SAW', async ({ page }) => {
        /**
         * Given sistem memiliki modul AI restock
         * When seksi rekomendasi Restock dilihat
         * Then sistem harus memberikan prioritas (Critical/Warning/Safe) berdasar hasil kalkulasi AHP-SAW
         */

        // Arrange
        const restockHeading = inventoryPage.restockHeading;
        const restockContainer = inventoryPage.restockContainer;

        // Act & Assert
        await expect(restockHeading).toBeVisible();
        
        const containerText = await restockContainer.textContent();
        expect(containerText).toMatch(/Priority|Score/i);

        const cardCount = await inventoryPage.getRestockCardCount();
        expect(cardCount).toBeGreaterThanOrEqual(1); // Setidaknya memunculkan list prioritas
    });

    test('Positif - Tabel Item Inventaris memiliki kolom standar dan identitas status', async ({ page }) => {
        /**
         * Given pengguna menelusuri data dalam tabel Inventaris Utama
         * When memeriksa heading dan body tabel
         * Then data akan memiliki ID konvensi (INV-) dan status limitasi (Critical, Optimal, dll)
         */

        // Arrange
        const inventoryTable = inventoryPage.inventoryTable;
        
        // Act
        const rowCount = await inventoryPage.getInventoryRowCount();
        const tableContent = await inventoryTable.textContent();
        
        // Assert
        expect(rowCount).toBeGreaterThanOrEqual(1); // Data harus ada
        expect(tableContent).toMatch(/ID|Nama|Kategori|Stok|Status/i);
        
        const validStatusFound = /critical|kritis|optimal|warning|peringatan/i.test(tableContent || '');
        expect(validStatusFound).toBeTruthy();
    });

    test('Positif - Fitur Pencarian (Search) dapat memfilter data dengan string valid', async ({ page }) => {
        /**
         * Given data Pakan tersedia dalam inventaris
         * When pengguna mencari teks "Pakan" dalam bar input search
         * Then tabel harus merender hanya item yang mengandung referensi pakan
         */

        // Arrange
        const query = 'Pakan';

        // Act
        await inventoryPage.searchInventory(query);
        await page.waitForTimeout(2000); // debounce input simulasi UI
        
        // Assert
        const tableContent = await inventoryPage.inventoryTable.textContent();
        expect(tableContent?.toLowerCase()).toContain(query.toLowerCase());
    });

    test('Negatif - Fitur Pencarian dikondisikan pada string acak tidak memicu fatal crash', async ({ page }) => {
        /**
         * Given user pada tabel inventaris
         * When mencari key yang pasti tidak ada dalam database
         * Then tabel tidak rendering data baris dan sistem tidak crash
         */

        // Arrange
        const badQuery = 'INVALID_XYZ_9921_STRING';

        // Act
        await inventoryPage.searchInventory(badQuery);
        await page.waitForTimeout(2000);
        
        // Assert
        await expect(page).toHaveURL(/.*\/inventory/);
        await inventoryPage.expectToBeOnInventoryPage();
        
        // Expektasi baris kosong
        const rowCount = await inventoryPage.getInventoryRowCount();
        expect(rowCount).toEqual(0);
    });
});
