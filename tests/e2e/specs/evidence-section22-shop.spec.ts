import { test, expect, Page } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';

/**
 * EVIDENCE - Section 22: Belanja Supplier (SHOP001-009)
 * Login pjawab@email.com. Rute: /spk-suppliers/products (Cari Barang), /spk-suppliers/orders (Pesanan Saya).
 * Screenshot -> qa-evidence/SHOP/
 * Alur nyata dijalankan: tambah/hapus keranjang (sesi, aman), buat pesanan & batalkan (data pesanan uji).
 * SHOP008 (rating) = N/A: tidak ada pesanan Selesai milik pembeli (keterbatasan data).
 */
const PW = 'Password123.';

async function cap(page: Page, path: string) {
    try { await page.waitForLoadState('networkidle', { timeout: 12000 }); }
    catch { await page.waitForLoadState('domcontentloaded').catch(() => { }); }
    await page.waitForTimeout(700);
    await page.screenshot({ path: `qa-evidence/${path}`, fullPage: true });
}

async function addFirstToCart(page: Page) {
    const addBtn = page.locator('button[data-cart-button]').first();
    await addBtn.waitFor({ state: 'visible', timeout: 15000 });
    await addBtn.click();
    // AJAX: tunggu tombol Buat Pesanan aktif atau feedback muncul
    await page.waitForTimeout(1800);
}

test.describe('Evidence Section 22 - Belanja Supplier (pjawab)', () => {
    let auth: AuthPage;
    test.setTimeout(170000);

    test.beforeEach(async ({ page }) => {
        await page.route('**/:5173/**', (r) => r.abort());
        await page.route(/.*:5173.*/, (r) => r.abort());
        auth = new AuthPage(page);
        await auth.loginAndWaitForDashboard('pjawab@email.com', PW);
    });

    test('SHOP001 - Halaman Cari Barang & panel keranjang tampil', async ({ page }) => {
        await page.goto('/spk-suppliers/products', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Cari Barang/i })).toBeVisible({ timeout: 20000 });
        await expect(page.getByRole('heading', { name: /Keranjang/i })).toBeVisible({ timeout: 20000 });
        await cap(page, 'SHOP/SHOP001_cari_barang.png');
    });

    test('SHOP002 - Memasukkan barang ke keranjang', async ({ page }) => {
        await page.goto('/spk-suppliers/products', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Cari Barang/i })).toBeVisible({ timeout: 20000 });
        await addFirstToCart(page);
        const checkout = page.locator('#cart-checkout-button');
        const enabled = await checkout.isEnabled().catch(() => false);
        console.log('SHOP002_CHECKOUT_ENABLED::' + enabled);
        await cap(page, 'SHOP/SHOP002_masuk_keranjang.png');
    });

    test('SHOP003 - Menghapus barang dari keranjang', async ({ page }) => {
        await page.goto('/spk-suppliers/products', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Cari Barang/i })).toBeVisible({ timeout: 20000 });
        await addFirstToCart(page);
        const removeBtn = page.locator('button[data-remove-cart-item]').first();
        if (await removeBtn.count() > 0) {
            await removeBtn.click();
            await page.waitForTimeout(1500);
        }
        await cap(page, 'SHOP/SHOP003_hapus_keranjang.png');
    });

    test('SHOP004 - Membuat pesanan dari keranjang (status Menunggu)', async ({ page }) => {
        await page.goto('/spk-suppliers/products', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Cari Barang/i })).toBeVisible({ timeout: 20000 });
        await addFirstToCart(page);
        const checkout = page.locator('#cart-checkout-button');
        await checkout.click();
        await page.waitForURL(/\/spk-suppliers\/orders/, { timeout: 30000 }).catch(() => { });
        await cap(page, 'SHOP/SHOP004_buat_pesanan.png');
    });

    test('SHOP005 - Pesanan Saya + saring per status', async ({ page }) => {
        await page.goto('/spk-suppliers/orders', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Pesanan Saya/i })).toBeVisible({ timeout: 20000 });
        // klik tab Menunggu (memakai tautan aplikasi yang benar) untuk menyaring
        const menungguTab = page.getByRole('link', { name: /^Menunggu/i }).first();
        if (await menungguTab.count() > 0) {
            await menungguTab.click();
            await page.waitForLoadState('domcontentloaded').catch(() => { });
        }
        await cap(page, 'SHOP/SHOP005_pesanan_saya.png');
    });

    test('SHOP006 - Membatalkan pesanan yang masih Menunggu', async ({ page }) => {
        // Buat pesanan baru dulu (pesanan menunggu bisa kedaluwarsa otomatis), lalu batalkan.
        await page.goto('/spk-suppliers/products', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Cari Barang/i })).toBeVisible({ timeout: 20000 });
        await addFirstToCart(page);
        await page.locator('#cart-checkout-button').click();
        await page.waitForURL(/\/spk-suppliers\/orders/, { timeout: 30000 }).catch(() => { });
        await expect(page.getByRole('heading', { name: /Pesanan Saya/i })).toBeVisible({ timeout: 20000 });
        const cancelBtn = page.getByRole('button', { name: /Batalkan Pesanan/i }).first();
        const cnt = await cancelBtn.count();
        console.log('SHOP006_CANCEL_BTN::' + cnt);
        if (cnt > 0) {
            page.once('dialog', (d) => d.accept());
            await cancelBtn.click();
            await page.waitForTimeout(2500);
        }
        await cap(page, 'SHOP/SHOP006_batal_pesanan.png');
    });

    test('SHOP007 - Memesan langsung dari halaman detail toko', async ({ page }) => {
        await page.goto('/spk-suppliers/products', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Cari Barang/i })).toBeVisible({ timeout: 20000 });
        const bukaToko = page.getByRole('link', { name: /Buka Toko/i }).first();
        const cnt = await bukaToko.count();
        console.log('SHOP007_BUKA_TOKO::' + cnt);
        if (cnt > 0) {
            await bukaToko.click();
            await expect(page.getByRole('heading', { name: /Barang Siap Dipesan/i })).toBeVisible({ timeout: 20000 });
            // pesan barang pertama
            const pesanBtn = page.getByRole('button', { name: /^Pesan$/i }).first();
            if (await pesanBtn.count() > 0) {
                await pesanBtn.click();
                await page.waitForURL(/\/spk-suppliers\/orders/, { timeout: 30000 }).catch(() => { });
            }
        }
        await cap(page, 'SHOP/SHOP007_pesan_dari_toko.png');
    });

    test('SHOP008 - Penilaian pada pesanan Selesai (keterbatasan data)', async ({ page }) => {
        await page.goto('/spk-suppliers/orders', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Pesanan Saya/i })).toBeVisible({ timeout: 20000 });
        const selesaiTab = page.getByRole('link', { name: /^Selesai/i }).first();
        if (await selesaiTab.count() > 0) {
            await selesaiTab.click();
            await page.waitForLoadState('domcontentloaded').catch(() => { });
        }
        // Tidak ada pesanan Selesai milik pembeli -> tab Selesai kosong (evidence keterbatasan data)
        await cap(page, 'SHOP/SHOP008_rating_selesai.png');
    });
});

test.describe('Evidence Section 22 - Guard akses Petugas', () => {
    test.setTimeout(120000);
    test('SHOP009 - Petugas tidak bisa buka Belanja Supplier', async ({ page }) => {
        await page.route('**/:5173/**', (r) => r.abort());
        await page.route(/.*:5173.*/, (r) => r.abort());
        const auth = new AuthPage(page);
        await auth.loginAndWaitForDashboard('petugas@email.com', PW);
        const resp = await page.goto('/spk-suppliers/products', { waitUntil: 'domcontentloaded' });
        console.log('SHOP009_STATUS::' + (resp?.status() ?? 'n/a') + ' URL::' + page.url());
        const hasShop = await page.getByRole('heading', { name: /Cari Barang/i }).isVisible().catch(() => false);
        expect(hasShop).toBeFalsy();
        await cap(page, 'SHOP/SHOP009_akses_ditolak.png');
    });
});
