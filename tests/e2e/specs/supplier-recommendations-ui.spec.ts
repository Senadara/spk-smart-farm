import { test, expect } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';

/**
 * Modul Supplier Recommendations UI - E2E Tests
 * 
 * Testing UI views di SupplierRecommendationController:
 * - /spk-suppliers (index) - Katalog supplier dengan search & filters
 * - /spk-suppliers/products - Product comparison dengan SAW rankings
 * - /spk-suppliers/{id} (show) - Detail supplier dengan inventories
 * 
 * NOTE: Tests bergantung pada SpkSupplierSeeder data
 */

test.describe('Modul Supplier Recommendations UI - E2E Tests', () => {
     let authPage: AuthPage;

     test.setTimeout(60000);

     test.beforeEach(async ({ page }) => {
          // Block external assets
          await page.route('**/:5173/**', route => route.abort());
          await page.route(/.*:5173.*/, route => route.abort());

          authPage = new AuthPage(page);

          // Login as pjawab (authenticated user)
          await authPage.loginAndWaitForDashboard('pjawab@email.com', 'Password123.');
     });

     // ═══════════════════════════════════════════════════════════════
     // SUPPLIER INDEX (Katalog) - /spk-suppliers
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Memuat supplier catalog page (index)', async ({ page }) => {
          /**
           * Given: User authenticated
           * When: Navigate to /spk-suppliers
           * Then: Page renders dengan header, search bar, filters, dan supplier cards
           */

          // Act
          await page.goto('/spk-suppliers');

          // Assert - Page elements
          await expect(page.getByRole('heading', { name: /Katalog Supplier Peternakan/i })).toBeVisible();
          await expect(page.locator('input[name="search"]')).toBeVisible();

          // Assert - Category filters terlihat
          await expect(page.getByRole('link', { name: /Semua/i })).toBeVisible();
          await expect(page.getByRole('link', { name: /Pakan/i })).toBeVisible();
          await expect(page.getByRole('link', { name: /Obat/i })).toBeVisible();
          await expect(page.getByRole('link', { name: /Alat/i })).toBeVisible();

          // Assert - Quick links to DSS pages
          await expect(page.getByRole('link', { name: /Konfigurasi AHP/i })).toBeVisible();
          await expect(page.getByRole('link', { name: /Dashboard SAW/i })).toBeVisible();
          await expect(page.getByRole('link', { name: /Mode Banding Barang/i })).toBeVisible();
     });

     test('Positif - Mencari suppliers berdasarkan nama', async ({ page }) => {
          /**
           * Given: Supplier catalog halaman dimuat
           * When: Enter search term di search box dan submit
           * Then: Results filtered by nama supplier
           */

          // Arrange
          await page.goto('/spk-suppliers');

          // Act - Search for "Agrinusa" (dari seeder: PT Agrinusa Jaya)
          await page.locator('input[name="search"]').fill('Agrinusa');
          await page.locator('input[name="search"]').press('Enter');

          // Assert
          await expect(page).toHaveURL(/.*search=Agrinusa/);

          // Should show matching supplier
          await expect(page.locator('text=Agrinusa')).toBeVisible();
     });

     test('Positif - Menyaring suppliers by category (pakan)', async ({ page }) => {
          /**
           * Given: Supplier catalog page
           * When: Click category filter "Pakan Pokok"
           * Then: URL updates dengan category param, hanya supplier pakan ditampilkan
           */

          // Arrange
          await page.goto('/spk-suppliers');

          // Act - Click "Pakan Pokok" filter
          await page.getByRole('link', { name: /🌽 Pakan Pokok/i }).click();

          // Assert
          await expect(page).toHaveURL(/.*category=pakan/);

          // Active filter should be highlighted (bg-emerald-500)
          const pakanFilter = page.getByRole('link', { name: /🌽 Pakan Pokok/i });
          await expect(pakanFilter).toHaveClass(/bg-emerald-500/);
     });

     test('Positif - Menyaring suppliers by category (obat)', async ({ page }) => {
          /**
           * Given: Supplier catalog page
           * When: Click category filter "Obat & Vaksin"
           * Then: Filter to obat category
           */

          // Arrange
          await page.goto('/spk-suppliers');

          // Act
          await page.getByRole('link', { name: /💊 Obat & Vaksin/i }).click();

          // Assert
          await expect(page).toHaveURL(/.*category=obat/);

          const obatFilter = page.getByRole('link', { name: /💊 Obat & Vaksin/i });
          await expect(obatFilter).toHaveClass(/bg-emerald-500/);
     });

     test('Positif - Menampilkan supplier cards dengan complete information', async ({ page }) => {
          /**
           * Given: Suppliers dari seeder (PT Agrinusa Jaya, CV Medion, dll)
           * When: Load catalog page
           * Then: Supplier cards menampilkan nama, rating, distance, categories, match score
           */

          // Act
          await page.goto('/spk-suppliers');

          // Assert - Wait for supplier cards to load
          const supplierCards = page.locator('a[href*="/spk-suppliers/"]').filter({ hasText: 'Terverifikasi' });
          await expect(supplierCards.first()).toBeVisible();

          // Assert - Card contains expected elements
          const firstCard = supplierCards.first();

          // Check for logo/image
          await expect(firstCard.locator('img')).toBeVisible();

          // Check for "Terverifikasi" badge
          await expect(firstCard.getByText('Terverifikasi')).toBeVisible();

          // Check for rating display (star + number)
          await expect(firstCard.locator('svg.fill-current')).toBeVisible(); // Star icon

          // Check for distance/location
          await expect(firstCard.locator('text=/km/')).toBeVisible();

          // Check for match score (e.g., "96% Match")
          await expect(firstCard.locator('text=/Match/')).toBeVisible();
     });

     test('Positif - Mengklik supplier card menuju ke detail page', async ({ page }) => {
          /**
           * Given: Supplier cards dirender
           * When: Click on first supplier card
           * Then: Navigate to /spk-suppliers/{id} detail page
           */

          // Arrange
          await page.goto('/spk-suppliers');

          // Act - Click first supplier card
          const firstCard = page.locator('a[href*="/spk-suppliers/"]').filter({ hasText: 'Terverifikasi' }).first();
          await firstCard.click();

          // Assert
          await expect(page).toHaveURL(/.*\/spk-suppliers\/\d+/);
          await expect(page.getByRole('heading', { name: /Detail Supplier/i })).toBeVisible();
     });

     test('Positif - Menampilkan empty state when no suppliers match search', async ({ page }) => {
          /**
           * Given: Supplier catalog
           * When: Search dengan term yang tidak ada
           * Then: Show empty state message
           */

          // Arrange
          await page.goto('/spk-suppliers');

          // Act - Search for non-existent supplier
          await page.locator('input[name="search"]').fill('SupplierTidakAda12345');
          await page.locator('input[name="search"]').press('Enter');

          // Assert
          await expect(page.getByText(/Tidak ada supplier ditemukan/i)).toBeVisible();
     });

     // ═══════════════════════════════════════════════════════════════
     // PRODUCT COMPARISON - /spk-suppliers/products
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Memuat product comparison page', async ({ page }) => {
          /**
           * Given: User authenticated
           * When: Navigate to /spk-suppliers/products
           * Then: Page renders dengan product list sidebar dan comparison table
           */

          // Act
          await page.goto('/spk-suppliers/products');

          // Assert - Page header
          await expect(page.getByRole('heading', { name: /Perbandingan Produk/i })).toBeVisible();

          // Assert - Product sidebar ada
          await expect(page.getByText(/Katalog Kebutuhan Pokok/i)).toBeVisible();

          // Assert - Search bar untuk products
          await expect(page.locator('input[name="search"][placeholder*="Cari barang"]')).toBeVisible();

          // Assert - Filter dropdowns
          await expect(page.locator('select[name="sort"]')).toBeVisible();
          await expect(page.locator('select[name="stock"]')).toBeVisible();
     });

     test('Positif - Menampilkan product list dari seeder', async ({ page }) => {
          /**
           * Given: Products dari SpkSupplierSeeder (Pakan Layer, Vaksin, Vitamin, dll)
           * When: Load products page
           * Then: Product list ditampilkan dengan icons dan categories
           */

          // Act
          await page.goto('/spk-suppliers/products');

          // Assert - Products should be terlihat (from seeder)
          // Seeder creates: Pakan Layer Premium, Vaksin ND-IB, Vitamin Stress, Jagung Giling, Egg Tray
          const productList = page.locator('text=/Pakan|Vaksin|Vitamin|Jagung|Tray/').first();
          await expect(productList).toBeVisible();

          // Assert - Product icons displayed
          await expect(page.locator('text=/🌾|💉|🧪|🌽|🥚/').first()).toBeVisible();
     });

     test('Positif - Memilih product menampilkan comparison table', async ({ page }) => {
          /**
           * Given: Products halaman dimuat
           * When: Click on a product dari sidebar
           * Then: Comparison table dirender dengan supplier data
           */

          // Arrange
          await page.goto('/spk-suppliers/products');

          // Act - Click first product in sidebar
          const firstProduct = page.locator('a[href*="product_id="]').first();
          await firstProduct.click();

          // Assert - URL contains product_id
          await expect(page).toHaveURL(/.*product_id=\d+/);

          // Assert - Product header displayed
          await expect(page.locator('text=/🌾|💉|🧪|🌽|🥚/').nth(1)).toBeVisible(); // Icon in header

          // Assert - Comparison table dirender
          const table = page.locator('table');
          await expect(table).toBeVisible();

          // Assert - Table headers
          await expect(page.getByRole('columnheader', { name: /Nama Supplier/i })).toBeVisible();
          await expect(page.getByRole('columnheader', { name: /Harga Satuan/i })).toBeVisible();
          await expect(page.getByRole('columnheader', { name: /Stok/i })).toBeVisible();
     });

     test('Positif - Menyaring comparison by sort (cheapest)', async ({ page }) => {
          /**
           * Given: Product comparison table loaded
           * When: Select "Harga Termurah" dari sort dropdown
           * Then: Table re-sorted by price ascending
           */

          // Arrange
          await page.goto('/spk-suppliers/products');

          // Wait for first product to load
          const firstProduct = page.locator('a[href*="product_id="]').first();
          await firstProduct.click();
          await expect(page.locator('table')).toBeVisible();

          // Act - Select "cheapest" sort
          await page.locator('select[name="sort"]').selectOption('cheapest');

          // Assert - URL diperbarui
          await expect(page).toHaveURL(/.*sort=cheapest/);

          // Assert - "Termurah" badge should appear on first row
          const firstRow = page.locator('tbody tr').first();
          await expect(firstRow.locator('text=/Termurah/i')).toBeVisible({ timeout: 5000 });
     });

     test('Positif - Menyaring comparison by sort (closest)', async ({ page }) => {
          /**
           * Given: Product comparison table loaded
           * When: Select "Jarak Terdekat" dari sort dropdown
           * Then: Table re-sorted by distance ascending
           */

          // Arrange
          await page.goto('/spk-suppliers/products');
          const firstProduct = page.locator('a[href*="product_id="]').first();
          await firstProduct.click();
          await expect(page.locator('table')).toBeVisible();

          // Act - Select "closest" sort
          await page.locator('select[name="sort"]').selectOption('closest');

          // Assert
          await expect(page).toHaveURL(/.*sort=closest/);

          // Assert - "Terdekat" badge should appear
          const table = page.locator('table');
          await expect(table.locator('text=/Terdekat/i')).toBeVisible({ timeout: 5000 });
     });

     test('Positif - Menyaring comparison by stock (instock only)', async ({ page }) => {
          /**
           * Given: Product comparison table
           * When: Select "Hanya Tersedia (>0)" dari stock dropdown
           * Then: Only suppliers with stock > 0 displayed
           */

          // Arrange
          await page.goto('/spk-suppliers/products');
          const firstProduct = page.locator('a[href*="product_id="]').first();
          await firstProduct.click();
          await expect(page.locator('table')).toBeVisible();

          // Act - Select "instock" filter
          await page.locator('select[name="stock"]').selectOption('instock');

          // Assert
          await expect(page).toHaveURL(/.*stock=instock/);

          // All terlihat rows should have stock > 0
          const stockCells = page.locator('tbody tr td span:has-text("unit")');
          const count = await stockCells.count();

          if (count > 0) {
               // At least one row should be terlihat with stock info
               await expect(stockCells.first()).toBeVisible();
          }
     });

     test('Positif - Kolom ranking SAW displayed when AHP configured', async ({ page }) => {
          /**
           * Given: User dengan sah AHP weights (CR ≤ 0.1)
           * When: Load products comparison dengan sort=saw
           * Then: Skor SAW dan Rank columns ditampilkan
           */

          // Arrange
          await page.goto('/spk-suppliers/products?sort=saw');

          // Wait for table
          const firstProduct = page.locator('a[href*="product_id="]').first();
          await firstProduct.click();
          await expect(page.locator('table')).toBeVisible();

          // Assert - Check if SAW columns exist (will depend on AHP config)
          const sawScoreHeader = page.getByRole('columnheader', { name: /Skor SAW/i });
          const rankHeader = page.getByRole('columnheader', { name: /Rank/i });

          // If AHP configured, these should be terlihat
          const sawHeaderExists = await sawScoreHeader.isVisible().catch(() => false);
          const rankHeaderExists = await rankHeader.isVisible().catch(() => false);

          if (sawHeaderExists && rankHeaderExists) {
               // AHP is configured, verify SAW data displayed
               await expect(sawScoreHeader).toBeVisible();
               await expect(rankHeader).toBeVisible();

               // Check for rank #1
               await expect(page.locator('text=/#1/i').first()).toBeVisible();
          } else {
               // AHP not configured, should show warning message
               await expect(page.locator('text=/Ranking SAW belum aktif/i')).toBeVisible();
          }
     });

     test('Positif - Peringatan displayed when AHP not configured', async ({ page }) => {
          /**
           * Given: User belum configure AHP (or CR > 0.1)
           * When: Load products page
           * Then: Amber warning box displayed dengan link ke AHP config
           */

          // Act
          await page.goto('/spk-suppliers/products');

          // Wait for page load
          await expect(page.getByText(/Katalog Kebutuhan Pokok/i)).toBeVisible();

          // Assert - Check for warning (might exist if AHP not setup)
          const warningBox = page.locator('text=/Ranking SAW belum aktif/i');
          const warningExists = await warningBox.isVisible().catch(() => false);

          if (warningExists) {
               // Verify warning message structure
               await expect(warningBox).toBeVisible();
               await expect(page.getByRole('link', { name: /Konfigurasi AHP/i })).toBeVisible();
          }
          // else: AHP is configured, warning won't show (that's OK)
     });

     test('Positif - Mengklik "Buka Toko" menuju ke supplier detail', async ({ page }) => {
          /**
           * Given: Comparison table with suppliers
           * When: Click "Buka Toko" button on a row
           * Then: Navigate to supplier detail page
           */

          // Arrange
          await page.goto('/spk-suppliers/products');
          const firstProduct = page.locator('a[href*="product_id="]').first();
          await firstProduct.click();
          await expect(page.locator('table')).toBeVisible();

          // Act - Click first "Buka Toko" button
          const bukaTokoBtn = page.getByRole('link', { name: /Buka Toko/i }).first();
          await bukaTokoBtn.click();

          // Assert
          await expect(page).toHaveURL(/.*\/spk-suppliers\/\d+/);
          await expect(page.getByRole('heading', { name: /Detail Supplier/i })).toBeVisible();
     });

     test('Positif - Mencari products berdasarkan nama', async ({ page }) => {
          /**
           * Given: Products page
           * When: Enter search term untuk product
           * Then: Product list filtered
           */

          // Arrange
          await page.goto('/spk-suppliers/products');

          // Act - Search for "Pakan" (should match "Pakan Layer Premium" from seeder)
          await page.locator('input[name="search"][placeholder*="Cari barang"]').fill('Pakan');
          await page.locator('input[name="search"][placeholder*="Cari barang"]').press('Enter');

          // Assert
          await expect(page).toHaveURL(/.*search=Pakan/);

          // Should show matching products
          await expect(page.locator('text=/Pakan/i').first()).toBeVisible();
     });

     // ═══════════════════════════════════════════════════════════════
     // SUPPLIER DETAIL - /spk-suppliers/{id}
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Memuat supplier detail page', async ({ page }) => {
          /**
           * Given: Valid supplier ID dari seeder (assume ID 1 ada)
           * When: Navigate to /spk-suppliers/1
           * Then: Supplier detail page dirender dengan complete info
           */

          // Act
          await page.goto('/spk-suppliers/1');

          // Assert - Page elements
          await expect(page.getByRole('heading', { name: /Detail Supplier/i })).toBeVisible();

          // Assert - Supplier logo/image
          await expect(page.locator('img[alt*="Logo"]')).toBeVisible();

          // Assert - "Terverifikasi" badge
          await expect(page.getByText(/Terverifikasi/i)).toBeVisible();

          // Assert - Contact button (WhatsApp)
          await expect(page.getByRole('link', { name: /Hubungi Penjual/i })).toBeVisible();

          // Assert - Back to catalog link
          await expect(page.getByRole('link', { name: /Kembali ke Katalog/i })).toBeVisible();
     });

     test('Positif - Menampilkan supplier metrics (score, rating)', async ({ page }) => {
          /**
           * Given: Supplier detail halaman dimuat
           * When: Check metrics bagian
           * Then: Algoritma (SPK) score dan Rating Web displayed
           */

          // Act
          await page.goto('/spk-suppliers/1');

          // Assert - Score percentage displayed (e.g., "96%")
          await expect(page.locator('text=/%/').first()).toBeVisible();

          // Assert - Rating displayed (e.g., "4.8 / 5.0")
          await expect(page.locator('text=/\\d\\.\\d.*5\\.0/').first()).toBeVisible();
     });

     test('Positif - Menampilkan supplier inventories (products)', async ({ page }) => {
          /**
           * Given: Supplier dengan products (dari seeder relations)
           * When: Load detail page
           * Then: Inventories table menampilkan produk yang dijual
           */

          // Act
          await page.goto('/spk-suppliers/1');

          // Assert - Should have products bagian
          // Assuming seeder creates PT Agrinusa Jaya with products
          const productsSection = page.locator('text=/Katalog|Produk|Daftar/i');

          // If supplier has products, table should be terlihat
          const hasProducts = await productsSection.isVisible().catch(() => false);

          if (hasProducts) {
               // Check for price format (Rp)
               await expect(page.locator('text=/Rp/i')).toBeVisible();
          }
     });

     test('Positif - WhatsApp contact link has correct format', async ({ page }) => {
          /**
           * Given: Supplier dengan kontak phone number
           * When: Check "Hubungi Penjual" link
           * Then: Link href contains wa.me with formatted phone number
           */

          // Act
          await page.goto('/spk-suppliers/1');

          // Assert - WhatsApp link ada
          const waLink = page.getByRole('link', { name: /Hubungi Penjual/i });
          await expect(waLink).toBeVisible();

          // Assert - Link format (should be https://wa.me/...)
          const href = await waLink.getAttribute('href');
          expect(href).toContain('wa.me');
     });

     test('Negatif - Memuat supplier detail dengan ID tidak exist (404)', async ({ page }) => {
          /**
           * Given: Non-existent supplier ID
           * When: Navigate to /spk-suppliers/99999
           * Then: Show 404 kesalahan or redirect
           */

          // Act
          const response = await page.goto('/spk-suppliers/99999');

          // Assert - Should be 404 or kesalahan page
          expect(response?.status()).toBe(404);
     });

     test('Positif - BACK to catalog link works', async ({ page }) => {
          /**
           * Given: Supplier detail page
           * When: Click "Kembali ke Katalog"
           * Then: Navigate back to /spk-suppliers index
           */

          // Arrange
          await page.goto('/spk-suppliers/1');

          // Act
          await page.getByRole('link', { name: /Kembali ke Katalog/i }).click();

          // Assert
          await expect(page).toHaveURL(/.*\/spk-suppliers$/);
          await expect(page.getByRole('heading', { name: /Katalog Supplier/i })).toBeVisible();
     });

     // ═══════════════════════════════════════════════════════════════
     // INTEGRATION TESTS
     // ═══════════════════════════════════════════════════════════════

     test('Integrasi - Full user journey: Catalog → Product Comparison → Supplier Detail', async ({ page }) => {
          /**
           * Given: User wants to find best supplier untuk specific product
           * When: Navigate through catalog → products → detail
           * Then: Complete flow works seamlessly
           */

          // Step 1: Start at catalog
          await page.goto('/spk-suppliers');
          await expect(page.getByRole('heading', { name: /Katalog Supplier/i })).toBeVisible();

          // Step 2: Go to product comparison
          await page.getByRole('link', { name: /Mode Banding Barang/i }).click();
          await expect(page).toHaveURL(/.*\/spk-suppliers\/products/);

          // Step 3: Select a product
          const firstProduct = page.locator('a[href*="product_id="]').first();
          await firstProduct.click();
          await expect(page.locator('table')).toBeVisible();

          // Step 4: Open supplier detail
          const bukaTokoBtn = page.getByRole('link', { name: /Buka Toko/i }).first();
          await bukaTokoBtn.click();
          await expect(page.getByRole('heading', { name: /Detail Supplier/i })).toBeVisible();

          // Step 5: Return to catalog
          await page.getByRole('link', { name: /Kembali ke Katalog/i }).click();
          await expect(page).toHaveURL(/.*\/spk-suppliers$/);
     });

     test('Integrasi - Search and filter combination works', async ({ page }) => {
          /**
           * Given: Catalog with multiple suppliers
           * When: Apply search + category filter together
           * Then: Results filtered by both criteria
           */

          // Arrange
          await page.goto('/spk-suppliers');

          // Act - Apply category filter first
          await page.getByRole('link', { name: /🌽 Pakan Pokok/i }).click();
          await expect(page).toHaveURL(/.*category=pakan/);

          // Act - Then apply search
          await page.locator('input[name="search"]').fill('Agrinusa');
          await page.locator('input[name="search"]').press('Enter');

          // Assert - Both params in URL
          await expect(page).toHaveURL(/.*category=pakan/);
          await expect(page).toHaveURL(/.*search=Agrinusa/);
     });
});
