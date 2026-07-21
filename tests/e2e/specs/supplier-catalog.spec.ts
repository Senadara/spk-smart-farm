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
           authPage = new AuthPage(page);

           // Login as pjawab (authenticated user)
           await authPage.loginAndWaitForDashboard('pjawab@email.com', 'Password123.');
      });

     // ═══════════════════════════════════════════════════════════════
     // SUPPLIER INDEX (Katalog) - /spk-suppliers
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Memuat halaman katalog supplier', async ({ page }) => {
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

          // Assert - Category filters visible
          await expect(page.getByRole('link', { name: /Semua/i })).toBeVisible();
          await expect(page.getByRole('link', { name: /Pakan/i })).toBeVisible();
          await expect(page.getByRole('link', { name: /Obat/i })).toBeVisible();
          await expect(page.getByRole('link', { name: /Alat/i })).toBeVisible();

          // Assert - Quick links to DSS pages
          await expect(page.getByRole('link', { name: /Konfigurasi AHP/i })).toBeVisible();
          await expect(page.getByRole('link', { name: /Dashboard SAW/i })).toBeVisible();
          await expect(page.getByRole('link', { name: /Mode Banding Barang/i })).toBeVisible();
     });

     test('Positif - SEARCH suppliers by name', async ({ page }) => {
          /**
           * Given: Supplier catalog page loaded
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

     test('Positif - FILTER suppliers by category (pakan)', async ({ page }) => {
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

     test('Positif - FILTER suppliers by category (obat)', async ({ page }) => {
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

     test('Positif - DISPLAY supplier cards dengan complete information', async ({ page }) => {
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

     test('Positif - CLICK supplier card navigates to detail page', async ({ page }) => {
          /**
           * Given: Supplier cards rendered
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

     test('Positif - DISPLAY empty state when no suppliers match search', async ({ page }) => {
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
});
