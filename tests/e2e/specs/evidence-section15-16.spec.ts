import { test, expect, Page } from '@playwright/test';

/**
 * EVIDENCE - Section 15 (SPK Supplier AHP/SAW) & 16 (Katalog & Komparasi Supplier).
 * Sesi pjawab (storageState). 1 screenshot unik per skenario ke qa-evidence/AHP|SUPP/.
 * Hasil jujur (AHP003 & SUPP011 diketahui bermasalah — divalidasi ulang).
 */
async function cap(page: Page, rel: string) {
     try { await page.waitForLoadState('networkidle', { timeout: 15000 }); }
     catch { await page.waitForLoadState('domcontentloaded'); }
     await page.waitForTimeout(700);
     await page.screenshot({ path: `qa-evidence/${rel}`, fullPage: true });
}
async function openFirstStore(page: Page) {
     await page.goto('/spk-suppliers', { waitUntil: 'domcontentloaded' });
     const link = page.locator('a[href*="/spk-suppliers/"]').filter({ hasText: /.+/ });
     // Cari tautan detail toko (punya id angka)
     const href = await page.locator('a[href*="/spk-suppliers/"]').evaluateAll(
          (as: HTMLAnchorElement[]) => {
               const m = as.map(a => a.getAttribute('href') || '').find(h => /\/spk-suppliers\/\d+$/.test(h));
               return m || '';
          }
     );
     if (href) { await page.goto(href, { waitUntil: 'domcontentloaded' }); return true; }
     // fallback: klik "Buka Toko"
     const buka = page.getByRole('link', { name: /Buka Toko|Lihat|Detail/i }).first();
     if (await buka.count() > 0) { await buka.click(); return true; }
     return false;
}

test.describe('Evidence Section 15-16 - SPK Supplier (Katalog/Komparasi/DSS)', () => {
     test.setTimeout(120000);
     test.beforeEach(async ({ page }) => {
          await page.route('**/:5173/**', (route) => route.abort());
          await page.route(/.*:5173.*/, (route) => route.abort());
     });

     // ── Section 15: AHP / SAW ──
     test('AHP001 - Dashboard peringkat supplier (SAW) tampil', async ({ page }) => {
          await page.goto('/spk-suppliers/dss/dashboard', { waitUntil: 'domcontentloaded' });
          await cap(page, 'AHP/AHP001_dashboard_saw.png');
     });
     test('AHP002 - Halaman konfigurasi kriteria (benefit/cost)', async ({ page }) => {
          await page.goto('/spk-suppliers/dss/config', { waitUntil: 'domcontentloaded' });
          const body = await page.locator('body').innerText();
          console.log('AHP002_KRITERIA::' + ['Harga', 'Kualitas', 'Kecepatan', 'Pengiriman'].filter(s => body.includes(s)).join(','));
          await cap(page, 'AHP/AHP002_konfigurasi_kriteria.png');
     });
     test('AHP003 - Tabel ranking supplier SAW', async ({ page }) => {
          await page.goto('/spk-suppliers/dss/dashboard', { waitUntil: 'domcontentloaded' });
          const body = await page.locator('body').innerText();
          console.log('AHP003_WARNING::' + /Bobot AHP belum valid|tidak terhubung|belum valid/i.test(body));
          console.log('AHP003_HAS_RANK::' + /Peringkat|Rank|Skor Vi/i.test(body));
          await cap(page, 'AHP/AHP003_ranking_saw.png');
     });
     test('AHP004 - Filter produk pada dashboard SAW', async ({ page }) => {
          await page.goto('/spk-suppliers/dss/dashboard', { waitUntil: 'domcontentloaded' });
          await cap(page, 'AHP/AHP004_filter_produk.png');
     });
     test('AHP005 - Halaman komparasi produk tetap stabil', async ({ page }) => {
          await page.goto('/spk-suppliers/products', { waitUntil: 'domcontentloaded' });
          const body = await page.locator('body').textContent() || '';
          expect(body).not.toMatch(/Error 500|Fatal/i);
          await cap(page, 'AHP/AHP005_komparasi_stabil.png');
     });
     test('AHP006 - Grid produk & pencarian pada dashboard SAW', async ({ page }) => {
          await page.goto('/spk-suppliers/dss/dashboard', { waitUntil: 'domcontentloaded' });
          const s = page.getByPlaceholder(/Cari nama produk/i);
          if (await s.count() > 0) { await s.fill('Pakan'); await page.waitForTimeout(400); }
          await cap(page, 'AHP/AHP006_grid_produk.png');
     });

     // ── Section 16: Katalog & Komparasi ──
     test('SUPP001 - Katalog supplier + pencarian', async ({ page }) => {
          await page.goto('/spk-suppliers', { waitUntil: 'domcontentloaded' });
          await cap(page, 'SUPP/SUPP001_katalog_supplier.png');
     });
     test('SUPP002 - Halaman perbandingan produk supplier', async ({ page }) => {
          await page.goto('/spk-suppliers/products', { waitUntil: 'domcontentloaded' });
          await cap(page, 'SUPP/SUPP002_komparasi_produk.png');
     });
     test('SUPP003 - Pencarian supplier berdasarkan nama', async ({ page }) => {
          await page.goto('/spk-suppliers', { waitUntil: 'domcontentloaded' });
          const s = page.locator('input[name="search"]').first();
          if (await s.count() > 0) { await s.fill('pakan'); await s.press('Enter'); await page.waitForTimeout(600); }
          await cap(page, 'SUPP/SUPP003_pencarian_supplier.png');
     });
     test('SUPP004 - Filter supplier berdasarkan kategori', async ({ page }) => {
          await page.goto('/spk-suppliers?category=pakan', { waitUntil: 'domcontentloaded' });
          await cap(page, 'SUPP/SUPP004_filter_kategori.png');
     });
     test('SUPP005 - Halaman detail supplier', async ({ page }) => {
          const ok = await openFirstStore(page);
          console.log('SUPP005_OPENED::' + ok);
          await cap(page, 'SUPP/SUPP005_detail_supplier.png');
     });
     test('SUPP006 - Opsi urut produk pada komparasi', async ({ page }) => {
          await page.goto('/spk-suppliers/products', { waitUntil: 'domcontentloaded' });
          console.log('SUPP006_SORT::' + await page.locator('select[name="sort"]').count());
          await cap(page, 'SUPP/SUPP006_sort_produk.png');
     });
     test('SUPP008 - Filter stok pada komparasi produk', async ({ page }) => {
          await page.goto('/spk-suppliers/products', { waitUntil: 'domcontentloaded' });
          console.log('SUPP008_STOCK::' + await page.locator('select[name="stock"]').count());
          await cap(page, 'SUPP/SUPP008_filter_stok.png');
     });
     test('SUPP009 - Empty state komparasi saat pencarian tanpa hasil', async ({ page }) => {
          await page.goto('/spk-suppliers/products?search=zzzznotfound', { waitUntil: 'domcontentloaded' });
          const body = await page.locator('body').textContent() || '';
          expect(body).not.toMatch(/Error 500|Fatal/i);
          await cap(page, 'SUPP/SUPP009_empty_komparasi_search.png');
     });
     test('SUPP010 - Navigasi "Bandingkan Barang" ke komparasi produk', async ({ page }) => {
          await page.goto('/spk-suppliers', { waitUntil: 'domcontentloaded' });
          const link = page.getByRole('link', { name: /Bandingkan Barang|Mode Banding|Komparasi/i }).first();
          if (await link.count() > 0) { await link.click(); await page.waitForTimeout(600); }
          console.log('SUPP010_URL::' + page.url());
          await cap(page, 'SUPP/SUPP010_nav_banding_barang.png');
     });
     test('SUPP011 - Tombol Google Maps di detail supplier', async ({ page }) => {
          await openFirstStore(page);
          const gm = page.getByRole('link', { name: /Google Maps/i }).or(page.getByRole('button', { name: /Google Maps/i }));
          const cnt = await gm.count();
          let href = '';
          if (cnt > 0) href = (await gm.first().getAttribute('href')) || '';
          console.log('SUPP011_GM_COUNT::' + cnt + ' HREF::' + href);
          await cap(page, 'SUPP/SUPP011_google_maps.png');
     });
     test('SUPP012 - Tombol Hubungi Penjual (WhatsApp) di detail supplier', async ({ page }) => {
          await openFirstStore(page);
          const wa = page.getByRole('link', { name: /WhatsApp|Hubungi/i }).first();
          const href = await wa.count() > 0 ? (await wa.getAttribute('href')) || '' : '';
          console.log('SUPP012_WA_HREF::' + href);
          await cap(page, 'SUPP/SUPP012_hubungi_penjual.png');
     });
     test('SUPP013 - Kartu statistik di detail supplier', async ({ page }) => {
          await openFirstStore(page);
          const body = await page.locator('body').innerText();
          console.log('SUPP013_CARDS::' + ['Skor', 'SPK', 'Rating', 'Estimasi', 'Kontak'].filter(s => body.includes(s)).join(','));
          await cap(page, 'SUPP/SUPP013_kartu_statistik.png');
     });
     test('SUPP014 - Empty state inventaris supplier (kondisional)', async ({ page }) => {
          await openFirstStore(page);
          const body = await page.locator('body').innerText();
          console.log('SUPP014_EMPTY::' + /Belum ada daftar barang|Katalog web toko ini belum tersedia/i.test(body));
          await cap(page, 'SUPP/SUPP014_empty_inventaris.png');
     });
     test('SUPP015 - Empty state komparasi (produk tanpa supplier)', async ({ page }) => {
          await page.goto('/spk-suppliers/products?search=zzznoproduk', { waitUntil: 'domcontentloaded' });
          await cap(page, 'SUPP/SUPP015_empty_komparasi_produk.png');
     });
     test('SUPP016 - Halaman komparasi tetap berfungsi (peringatan user)', async ({ page }) => {
          await page.goto('/spk-suppliers/products', { waitUntil: 'domcontentloaded' });
          const body = await page.locator('body').textContent() || '';
          expect(body).not.toMatch(/Error 500|Fatal/i);
          await cap(page, 'SUPP/SUPP016_komparasi_peringatan_user.png');
     });
     test('SUPP017 - Filter kategori "Peralatan Kandang" (alat)', async ({ page }) => {
          await page.goto('/spk-suppliers?category=alat', { waitUntil: 'domcontentloaded' });
          console.log('SUPP017_URL::' + page.url());
          await cap(page, 'SUPP/SUPP017_filter_alat.png');
     });
     test('SUPP018 - Empty state katalog saat pencarian tanpa hasil', async ({ page }) => {
          await page.goto('/spk-suppliers?search=zzzznotfoundsupplier', { waitUntil: 'domcontentloaded' });
          const body = await page.locator('body').innerText();
          console.log('SUPP018_EMPTY::' + /Tidak ada supplier|tidak ditemukan|Belum ada/i.test(body));
          await cap(page, 'SUPP/SUPP018_empty_katalog.png');
     });
});
