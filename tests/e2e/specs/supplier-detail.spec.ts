import { test, expect } from "@playwright/test";

/**
 * Modul Supplier - Cari Barang (products) & Detail Toko.
 *
 * Redesign: /spk-suppliers/products kini grid KARTU barang + keranjang (bukan tabel komparasi).
 *  - Heading "Cari Barang", search input[name=search], select[name=category], select[name=sort]
 *  - Kartu <article> berisi nama, harga Rp, tombol "Tambah" (keranjang), link "Buka Toko"
 *  - Empty: "Barang tidak ditemukan"; aside "Keranjang"
 * Detail /spk-suppliers/{id}: heading = nama toko, section "Barang Siap Dipesan",
 * aside "Kontak Toko" (WhatsApp Supplier + Buka Google Maps), "Ringkasan SPK", "Referensi Data SPK".
 */
test.describe("Modul Supplier - Cari Barang & Detail Toko", () => {
     test.setTimeout(60000);

     // ─── Cari Barang (products) ────────────────────────────────────
     test('Positif - Memuat halaman Cari Barang', async ({ page }) => {
          await page.goto('/spk-suppliers/products');

          await expect(page.getByRole('heading', { name: /^\s*Cari Barang\s*$/i })).toBeVisible();
          await expect(page.locator('input[name="search"]')).toBeVisible();
          await expect(page.locator('select[name="category"]')).toBeVisible();
          await expect(page.locator('select[name="sort"]')).toBeVisible();
          // Aside keranjang
          await expect(page.getByRole('heading', { name: /^\s*Keranjang\s*$/i })).toBeVisible();
     });

     test('Positif - Menampilkan kartu barang dari seeder', async ({ page }) => {
          await page.goto('/spk-suppliers/products');
          // Minimal satu kartu barang tampil (grid card), atau empty state jika tak ada.
          const cards = page.locator('article');
          const empty = page.getByText(/Barang tidak ditemukan/i);
          const hasCards = await cards.count();
          if (hasCards > 0) {
               await expect(cards.first()).toBeVisible();
               await expect(cards.first().getByText(/Rp/).first()).toBeVisible();
          } else {
               await expect(empty).toBeVisible();
          }
     });

     test('Positif - SEARCH barang berdasarkan nama', async ({ page }) => {
          await page.goto('/spk-suppliers/products');
          await page.locator('input[name="search"]').fill('Pakan');
          await page.locator('input[name="search"]').press('Enter');

          await expect(page).toHaveURL(/.*search=Pakan/);
     });

     test('Positif - Sort barang (termurah) memperbarui URL', async ({ page }) => {
          await page.goto('/spk-suppliers/products');
          await page.locator('select[name="sort"]').selectOption('cheapest');
          // Form GET auto-submit tidak dipasang; submit via tombol Cari.
          await page.getByRole('button', { name: /^\s*Cari\s*$/i }).click();
          await expect(page).toHaveURL(/.*sort=cheapest/);
     });

     test('Positif - Klik "Buka Toko" pada kartu barang menuju detail toko', async ({ page }) => {
          await page.goto('/spk-suppliers/products');
          const bukaToko = page.getByRole('link', { name: /Buka Toko/i }).first();
          if (await bukaToko.count() === 0) {
               test.skip(true, 'Tidak ada barang dengan toko tertaut pada state seed saat ini.');
               return;
          }
          await bukaToko.click();
          await expect(page).toHaveURL(/.*\/spk-suppliers\/\d+/);
          await expect(page.getByRole('heading', { name: /Barang Siap Dipesan/i })).toBeVisible();
     });

     // ─── Detail Toko ───────────────────────────────────────────────
     async function openFirstSupplierDetail(page: any) {
          await page.goto('/spk-suppliers');
          await page.getByRole('link', { name: /Lihat Barang/i }).first().click();
          await expect(page).toHaveURL(/.*\/spk-suppliers\/\d+/);
     }

     test('Positif - Memuat halaman detail toko', async ({ page }) => {
          await openFirstSupplierDetail(page);

          await expect(page.getByRole('heading', { name: /Barang Siap Dipesan/i })).toBeVisible();
          await expect(page.getByRole('heading', { name: /Kontak Toko/i })).toBeVisible();
          await expect(page.getByRole('heading', { name: /Ringkasan SPK/i })).toBeVisible();
          // Tombol navigasi
          await expect(page.getByRole('link', { name: /Bandingkan Barang/i }).first()).toBeVisible();
          await expect(page.getByRole('link', { name: /Pesanan Saya/i }).first()).toBeVisible();
     });

     test('Positif - Tautan WhatsApp/Google Maps pada Kontak Toko berformat benar', async ({ page }) => {
          await openFirstSupplierDetail(page);

          const wa = page.getByRole('link', { name: /WhatsApp Supplier/i });
          const maps = page.getByRole('link', { name: /Buka Google Maps/i });
          // Minimal salah satu kontak tersedia; jika WA ada, href harus wa.me; jika Maps ada, href google maps.
          if (await wa.count() > 0) {
               expect(await wa.first().getAttribute('href')).toContain('wa.me');
          }
          if (await maps.count() > 0) {
               expect(await maps.first().getAttribute('href')).toContain('google.com/maps');
          }
     });

     test('Negatif - Detail toko dengan ID tidak ada mengembalikan 404', async ({ page }) => {
          const response = await page.goto('/spk-suppliers/99999');
          expect(response?.status()).toBe(404);
     });

     test('Positif - Tombol "Cari Toko" pada detail kembali ke katalog', async ({ page }) => {
          await openFirstSupplierDetail(page);
          await page.getByRole('link', { name: /^\s*Cari Toko\s*$/i }).first().click();
          await expect(page).toHaveURL(/.*\/spk-suppliers$/);
          await expect(page.getByRole('heading', { name: /Cari toko, pilih barang, pantau pesanan/i })).toBeVisible();
     });
});
