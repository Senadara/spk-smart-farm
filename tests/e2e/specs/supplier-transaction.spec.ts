import { test, expect, Page } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';

/**
 * Modul Belanja Supplier - ALUR TRANSAKSI (Keranjang, Checkout, Pesanan, Batal, Rating)
 * Route prefix: /spk-suppliers (middleware role:pjawab,owner,admin)
 * Controller: SupplierRecommendationController
 *
 * Sesi default (global.setup) = pjawab@email.com (role pjawab) → diizinkan.
 *
 * Fakta implementasi yang diuji:
 *  - /spk-suppliers/products : grid kartu barang + aside "Keranjang".
 *      • Tambah ke keranjang = AJAX (feedback #cart-feedback, ringkasan #cart-summary-line,
 *        tombol #cart-checkout-button "Buat Pesanan"). Form per kartu = form[data-cart-form],
 *        tombol [data-cart-button] "Tambah".
 *      • Hapus item = AJAX DELETE, tombol [data-remove-cart-item] "Hapus".
 *      • Checkout = form POST → redirect /spk-suppliers/orders, flash
 *        "Pesanan dari keranjang dibuat. ...".
 *  - /spk-suppliers/orders : "Pesanan Saya", tab filter status (Semua/Menunggu/Diproses/
 *    Selesai/Ditolak/Dibatalkan/Kedaluwarsa), kartu <article> pesanan.
 *      • Status 'menunggu' → tombol "Batalkan Pesanan" (confirm) → flash "Pesanan berhasil dibatalkan."
 *      • Status 'selesai' → form rating (select[name=rating], textarea[name=note]).
 *  - /spk-suppliers/{id} : detail toko, "Barang Siap Dipesan", tombol "Pesan" (storeOrder)
 *    → flash "Pesanan dibuat. ...".
 *
 * Catatan kejujuran: order berstatus 'selesai' hanya ter-seed untuk buyer.demo (role user)
 * yang tidak bisa mengakses /spk-suppliers, sehingga rating happy-path diuji KONDISIONAL.
 */

const PRODUCTS_URL = '/spk-suppliers/products';
const ORDERS_URL = '/spk-suppliers/orders';

// Hapus semua isi keranjang agar state awal deterministik (keranjang berbasis sesi).
async function clearCart(page: Page) {
     await page.goto(PRODUCTS_URL, { waitUntil: 'domcontentloaded' });
     for (let i = 0; i < 25; i++) {
          const buttons = page.locator('#cart-items [data-remove-cart-item]');
          const count = await buttons.count();
          if (count === 0) break;
          await buttons.first().click();
          await expect(page.locator('#cart-items [data-remove-cart-item]')).toHaveCount(count - 1);
     }
}

// Tambah barang pertama ke keranjang dan tunggu keranjang ter-update (AJAX).
async function addFirstProductToCart(page: Page) {
     const addForm = page.locator('form[data-cart-form]').first();
     await expect(addForm).toBeVisible();
     await addForm.locator('[data-cart-button]').click();
     await expect(page.locator('#cart-checkout-button')).toBeEnabled();
     await expect(page.locator('#cart-items')).not.toContainText('Keranjang masih kosong');
}

test.describe('Belanja Supplier - Alur Transaksi (role pjawab)', () => {
     test.setTimeout(90000);

     test('Positif - Halaman Cari Barang & keranjang tampil', async ({ page }) => {
          await page.goto(PRODUCTS_URL, { waitUntil: 'domcontentloaded' });

          await expect(page.getByRole('heading', { name: /^\s*Cari Barang\s*$/i })).toBeVisible();
          await expect(page.locator('input[name="search"]')).toBeVisible();
          await expect(page.locator('select[name="category"]')).toBeVisible();
          await expect(page.locator('select[name="sort"]')).toBeVisible();
          await expect(page.getByRole('heading', { name: /^\s*Keranjang\s*$/i })).toBeVisible();
          await expect(page.locator('#cart-checkout-button')).toBeVisible();
          await expect(page.getByRole('link', { name: /Pesanan Saya/i }).first()).toBeVisible();
     });

     test('Positif - Tambah barang ke keranjang (AJAX)', async ({ page }) => {
          await clearCart(page);

          // Keranjang kosong → tombol Buat Pesanan nonaktif.
          await expect(page.locator('#cart-checkout-button')).toBeDisabled();

          await addFirstProductToCart(page);

          // Feedback sukses + subtotal terisi (bukan Rp 0).
          await expect(page.locator('#cart-feedback')).toContainText(/keranjang/i, { timeout: 8000 });
          await expect(page.locator('#cart-subtotal')).not.toHaveText(/^\s*Rp\s*0\s*$/);
     });

     test('Positif - Hapus barang dari keranjang + empty state', async ({ page }) => {
          await clearCart(page);
          await addFirstProductToCart(page);

          // Hapus satu-satunya item → keranjang kosong lagi, checkout nonaktif.
          await page.locator('#cart-items [data-remove-cart-item]').first().click();
          await expect(page.locator('#cart-items')).toContainText('Keranjang masih kosong');
          await expect(page.locator('#cart-checkout-button')).toBeDisabled();
     });

     test('Positif - Checkout keranjang membuat pesanan (status Menunggu)', async ({ page }) => {
          await clearCart(page);
          await addFirstProductToCart(page);

          await Promise.all([
               page.waitForURL(/\/spk-suppliers\/orders/),
               page.locator('#cart-checkout-button').click(),
          ]);

          await expect(page.getByText(/Pesanan dari keranjang dibuat/i).first()).toBeVisible();
          // Minimal satu kartu pesanan dengan badge "Menunggu" tampil.
          await expect(page.getByText('Menunggu', { exact: false }).first()).toBeVisible();
     });

     test('Positif - Halaman Pesanan Saya render + filter status', async ({ page }) => {
          await page.goto(ORDERS_URL, { waitUntil: 'domcontentloaded' });

          await expect(page.getByRole('heading', { name: /^\s*Pesanan Saya\s*$/i })).toBeVisible();
          await expect(page.getByRole('link', { name: /^\s*Semua\s*$/i }).first()).toBeVisible();
          await expect(page.getByRole('link', { name: /Menunggu/i }).first()).toBeVisible();

          await page.getByRole('link', { name: /Menunggu/i }).first().click();
          await expect(page).toHaveURL(/.*status=menunggu/);
     });

     test('Positif - Batalkan pesanan berstatus Menunggu', async ({ page }) => {
          // Buat pesanan baru agar pasti ada order "menunggu" milik user ini.
          await clearCart(page);
          await addFirstProductToCart(page);
          await Promise.all([
               page.waitForURL(/\/spk-suppliers\/orders/),
               page.locator('#cart-checkout-button').click(),
          ]);

          page.once('dialog', (dialog) => dialog.accept());
          const cancelBtn = page.getByRole('button', { name: /Batalkan Pesanan/i }).first();
          await expect(cancelBtn).toBeVisible();
          await cancelBtn.click();

          await expect(page.getByText('Pesanan berhasil dibatalkan.').first()).toBeVisible();
          await expect(page.getByText('Dibatalkan', { exact: false }).first()).toBeVisible();
     });

     test('Positif - Pesan langsung dari detail toko (storeOrder)', async ({ page }) => {
          await page.goto(PRODUCTS_URL, { waitUntil: 'domcontentloaded' });

          const bukaToko = page.getByRole('link', { name: /Buka Toko/i }).first();
          if (await bukaToko.count() === 0) {
               test.skip(true, 'Tidak ada tautan "Buka Toko" (produk tanpa supplier_id) — tidak dapat menguji storeOrder dari detail.');
          }
          await bukaToko.click();

          await expect(page.getByRole('heading', { name: /Barang Siap Dipesan/i })).toBeVisible();
          const pesanBtn = page.getByRole('button', { name: /^\s*Pesan\s*$/i }).first();
          if (await pesanBtn.count() === 0) {
               test.skip(true, 'Toko tidak punya katalog web (tidak ada tombol Pesan).');
          }

          await Promise.all([
               page.waitForURL(/\/spk-suppliers\/orders/),
               pesanBtn.click(),
          ]);
          await expect(page.getByText(/Pesanan dibuat/i).first()).toBeVisible();
     });

     test('Kondisional - Beri rating pesanan berstatus Selesai', async ({ page }) => {
          await page.goto(`${ORDERS_URL}?status=selesai`, { waitUntil: 'domcontentloaded' });

          const ratingForm = page.locator('form[action*="/rating"]').first();
          const hasSelesai = await ratingForm.count();
          test.skip(
               hasSelesai === 0,
               'Tidak ada pesanan berstatus Selesai milik akun belanja (pjawab). Order selesai ter-seed hanya untuk buyer.demo (role user) yang tidak bisa mengakses /spk-suppliers — keterbatasan data seed, bukan cacat fitur.'
          );

          await ratingForm.locator('select[name="rating"]').selectOption('5');
          await ratingForm.locator('textarea[name="note"]').fill('Pesanan sesuai, kualitas bagus (uji QA).');
          await ratingForm.getByRole('button', { name: /Simpan Rating|Perbarui Rating/i }).click();

          await expect(page.getByText(/Rating.*disimpan/i)).toBeVisible();
     });
});

test.describe('Belanja Supplier - Guard role tidak diizinkan (petugas)', () => {
     test.use({ storageState: { cookies: [], origins: [] } });
     test.setTimeout(90000);

     test('Negatif - Petugas tidak bisa mengakses Belanja Supplier', async ({ page }) => {
          const auth = new AuthPage(page);
          await auth.loginAndWaitForDashboard('petugas@email.com', 'Password123.');

          await page.goto(PRODUCTS_URL, { waitUntil: 'domcontentloaded' });

          // Middleware role:pjawab,owner,admin → petugas ditolak.
          await expect(page.getByRole('heading', { name: /^\s*Cari Barang\s*$/i })).toHaveCount(0);
          await expect(
               page.locator('body')
          ).toContainText(/forbidden|tidak diizinkan|tidak memiliki akses|access denied|\b403\b/i);
     });
});
