import { test, expect, Page } from '@playwright/test';
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

          // Assert - Page elements (desain terbaru: judul "Cari toko, pilih barang, pantau pesanan")
          await expect(page.getByRole('heading', { name: /Cari toko, pilih barang, pantau pesanan/i })).toBeVisible();
          await expect(page.locator('input[name="search"]')).toBeVisible();

          // Assert - Category filters visible (Semua / Pakan / Obat & Vaksin / Peralatan)
          await expect(page.getByRole('link', { name: 'Semua', exact: true })).toBeVisible();
          await expect(page.getByRole('link', { name: 'Pakan', exact: true })).toBeVisible();
          await expect(page.getByRole('link', { name: /Obat & Vaksin/i })).toBeVisible();
          await expect(page.getByRole('link', { name: /Peralatan/i })).toBeVisible();

          // Assert - Quick links (Bandingkan Barang, Atur bobot SPK, Lihat ranking SAW)
          await expect(page.getByRole('link', { name: /Bandingkan Barang/i })).toBeVisible();
          await expect(page.getByRole('link', { name: /Atur bobot SPK/i })).toBeVisible();
          await expect(page.getByRole('link', { name: /Lihat ranking SAW/i })).toBeVisible();
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

          // Act - Click "Pakan" filter
          await page.getByRole('link', { name: 'Pakan', exact: true }).click();

          // Assert
          await expect(page).toHaveURL(/.*category=pakan/);

          // Active filter should be highlighted (bg-emerald-600)
          const pakanFilter = page.getByRole('link', { name: 'Pakan', exact: true });
          await expect(pakanFilter).toHaveClass(/bg-emerald-600/);
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
          await page.getByRole('link', { name: /Obat & Vaksin/i }).click();

          // Assert
          await expect(page).toHaveURL(/.*category=obat/);

          const obatFilter = page.getByRole('link', { name: /Obat & Vaksin/i });
          await expect(obatFilter).toHaveClass(/bg-emerald-600/);
     });

     test('Positif - DISPLAY supplier cards dengan complete information', async ({ page }) => {
          /**
           * Given: Suppliers dari seeder (PT Agrinusa Jaya, CV Medion, dll)
           * When: Load catalog page
           * Then: Supplier cards menampilkan nama, rating, distance, categories, match score
           */

          // Act
          await page.goto('/spk-suppliers');

          // Assert - kartu supplier (desain terbaru: <article> dengan CTA "Lihat Barang")
          const supplierCards = page.locator('article').filter({ has: page.getByRole('link', { name: /Lihat Barang/i }) });
          await expect(supplierCards.first()).toBeVisible();

          const firstCard = supplierCards.first();
          // Logo toko
          await expect(firstCard.locator('img')).toBeVisible();
          // Nama toko (h2)
          await expect(firstCard.locator('h2')).toBeVisible();
          // Info jarak
          await expect(firstCard.getByText(/Jarak Info/i)).toBeVisible();
          // CTA menuju detail toko
          await expect(firstCard.getByRole('link', { name: /Lihat Barang/i })).toBeVisible();
     });

     test('Positif - CLICK supplier card navigates to detail page', async ({ page }) => {
          /**
           * Given: Supplier cards rendered
           * When: Click on first supplier card
           * Then: Navigate to /spk-suppliers/{id} detail page
           */

          // Arrange
          await page.goto('/spk-suppliers');

          // Act - Klik CTA "Lihat Barang" pada kartu pertama
          const firstCard = page.locator('article').filter({ has: page.getByRole('link', { name: /Lihat Barang/i }) }).first();
          await firstCard.getByRole('link', { name: /Lihat Barang/i }).click();

          // Assert - menuju halaman detail toko (/spk-suppliers/{id})
          await expect(page).toHaveURL(/\/spk-suppliers\/[\w-]+/);
          await expect(page.getByText('Toko Supplier', { exact: false }).first()).toBeVisible();
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

          // Assert - empty state (desain terbaru: "Tidak ada toko ditemukan")
          await expect(page.getByText(/Tidak ada toko ditemukan/i)).toBeVisible();
     });

     // ═══════════════════════════════════════════════════════════════
     // PRODUCT COMPARISON - /spk-suppliers/products
     // ═══════════════════════════════════════════════════════════════
});


// ============================================================
// Uji Fungsional Mendalam - digabung dari func-shop.spec.ts (sebelumnya section 26.8)
// ============================================================

const PW = 'Password123.';

async function cap(page: Page, path: string) {
     try { await page.waitForLoadState('networkidle', { timeout: 10000 }); }
     catch { await page.waitForLoadState('domcontentloaded').catch(() => { }); }
     await page.waitForTimeout(500);
     await page.screenshot({ path: `qa-evidence/${path}`, fullPage: true });
}
async function bodyText(page: Page): Promise<string> {
     return (await page.locator('body').innerText().catch(() => '')) || '';
}
function parsePrices(texts: string[]): number[] {
     return texts.map(t => parseInt((t.match(/[\d.]+/)?.[0] || '0').replace(/\./g, ''), 10) || 0);
}

test.describe('FUNC Belanja Supplier - Cari Barang (pjawab)', () => {
     test.setTimeout(150000);
     test.beforeEach(async ({ page }) => {
          await page.route(/.*:5173.*/, (r) => r.abort());
          const auth = new AuthPage(page);
          await auth.loginAndWaitForDashboard('pjawab@email.com', PW);
          await page.goto('/spk-suppliers/products', { waitUntil: 'domcontentloaded' });
          await page.waitForTimeout(1000);
     });

     test('SHOPF001 - Pencarian barang: kata kunci valid & kata kunci tidak ada', async ({ page }) => {
          const cards = page.locator('section article');
          const total = await cards.count();
          console.log('SHOPF001_total::' + total);
          expect(total).toBeGreaterThan(0);
          // ambil kata kunci dari nama produk pertama
          const firstName = (await cards.first().locator('h2').innerText().catch(() => '')) || '';
          const keyword = (firstName.split(/\s+/).find(w => w.length >= 4) || firstName.slice(0, 4)).trim();
          const searchInput = page.locator('input[name="search"]');
          await searchInput.fill(keyword);
          await page.getByRole('button', { name: /^Cari$/ }).click();
          await page.waitForLoadState('domcontentloaded').catch(() => { });
          await page.waitForTimeout(1000);
          const validCount = await page.locator('section article').count();
          console.log('SHOPF001_valid:: keyword=' + keyword + ' hasil=' + validCount);
          await cap(page, 'SHOP/SHOPF001a_search_valid.png');
          expect(validCount).toBeGreaterThan(0);

          // kata kunci tidak ada
          await page.goto('/spk-suppliers/products?search=zzz-barang-tidak-ada-999', { waitUntil: 'domcontentloaded' });
          await page.waitForTimeout(800);
          const bodyN = await bodyText(page);
          const kosong = /Barang tidak ditemukan/i.test(bodyN);
          console.log('SHOPF001_none:: kosong=' + kosong);
          await cap(page, 'SHOP/SHOPF001b_search_kosong.png');
          expect(kosong).toBeTruthy();
     });

     test('SHOPF002 - Sorting Termurah -> harga urut menaik', async ({ page }) => {
          await page.goto('/spk-suppliers/products?sort=cheapest', { waitUntil: 'domcontentloaded' });
          await page.waitForTimeout(1000);
          const priceTexts = await page.locator('section article p.text-lg.font-black').allInnerTexts();
          const prices = parsePrices(priceTexts);
          console.log('SHOPF002_prices::' + JSON.stringify(prices.slice(0, 10)));
          expect(prices.length).toBeGreaterThan(0);
          let ascending = true;
          for (let i = 1; i < prices.length; i++) {
               if (prices[i] < prices[i - 1]) { ascending = false; break; }
          }
          console.log('SHOPF002:: urutMenaik=' + ascending);
          await cap(page, 'SHOP/SHOPF002_sort_termurah.png');
          expect(ascending).toBeTruthy();
     });

     test('SHOPF003 - Filter kategori menyaring hasil', async ({ page }) => {
          // ambil opsi kategori kedua (indeks 1) dari select
          const options = await page.locator('select[name="category"] option').all();
          expect(options.length).toBeGreaterThan(1);
          const catValue = (await options[1].getAttribute('value')) || 'all';
          await page.goto('/spk-suppliers/products?category=' + encodeURIComponent(catValue), { waitUntil: 'domcontentloaded' });
          await page.waitForTimeout(1000);
          const selected = await page.locator('select[name="category"]').inputValue();
          const count = await page.locator('section article').count();
          const bodyF = await bodyText(page);
          const kosong = /Barang tidak ditemukan/i.test(bodyF);
          console.log('SHOPF003:: kategori=' + catValue + ' terpilih=' + selected + ' hasil=' + count + ' kosong=' + kosong);
          await cap(page, 'SHOP/SHOPF003_filter_kategori.png');
          // filter diterapkan: nilai kategori terpilih sesuai, dan halaman menampilkan hasil ATAU empty state (keduanya valid)
          expect(selected).toBe(catValue);
          expect(count > 0 || kosong).toBeTruthy();
     });
});
