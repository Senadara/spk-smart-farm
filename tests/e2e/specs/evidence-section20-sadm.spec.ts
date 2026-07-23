import { test, expect, Page } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';

/**
 * EVIDENCE - Section 20: Super Admin - Manajemen Mitra Supplier (SADM001-009)
 * Login admin@email.com. Route: /super-admin/suppliers (tab via ?status=pending|active|reject).
 * Screenshot -> qa-evidence/SADM/
 * Prinsip jujur: operasi yang benar-benar mengubah data global (buat supplier, tolak/setujui) TIDAK
 * dieksekusi agar data tidak terpolusi; evidence menampilkan form/kontrol. Uji negatif (tanpa kategori)
 * dijalankan penuh karena aman (validasi menahan simpan).
 */
const PW = 'Password123.';

async function cap(page: Page, path: string) {
    try { await page.waitForLoadState('networkidle', { timeout: 12000 }); }
    catch { await page.waitForLoadState('domcontentloaded').catch(() => { }); }
    await page.waitForTimeout(700);
    await page.screenshot({ path: `qa-evidence/${path}`, fullPage: true });
}

test.describe('Evidence Section 20 - Super Admin Mitra Supplier (admin)', () => {
    let auth: AuthPage;
    test.setTimeout(150000);

    test.beforeEach(async ({ page }) => {
        await page.route('**/:5173/**', (r) => r.abort());
        await page.route(/.*:5173.*/, (r) => r.abort());
        auth = new AuthPage(page);
        await auth.loginAndWaitForDashboard('admin@email.com', PW);
    });

    test('SADM001 - Halaman Manajemen Mitra Supplier tampil lengkap', async ({ page }) => {
        await page.goto('/super-admin/suppliers', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Manajemen Mitra Supplier/i })).toBeVisible({ timeout: 20000 });
        await cap(page, 'SADM/SADM001_halaman_manajemen.png');
    });

    test('SADM002 - Berpindah antar tab status', async ({ page }) => {
        await page.goto('/super-admin/suppliers?status=active', { waitUntil: 'domcontentloaded' });
        await expect(page.getByText(/Status saat ini/i)).toBeVisible({ timeout: 20000 });
        await cap(page, 'SADM/SADM002_tab_status_aktif.png');
    });

    test('SADM003 - Master Supplier Manual + tombol Edit', async ({ page }) => {
        await page.goto('/super-admin/suppliers', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Master Supplier Manual/i })).toBeVisible({ timeout: 20000 });
        await page.getByRole('heading', { name: /Master Supplier Manual/i }).scrollIntoViewIfNeeded();
        await cap(page, 'SADM/SADM003_master_supplier_edit.png');
    });

    test('SADM004 - Pencarian toko supplier', async ({ page }) => {
        await page.goto('/super-admin/suppliers?status=active&search=pakan', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Manajemen Mitra Supplier/i })).toBeVisible({ timeout: 20000 });
        await cap(page, 'SADM/SADM004_pencarian.png');
    });

    test('SADM005 - Form Tambah Supplier Manual (terisi)', async ({ page }) => {
        await page.goto('/super-admin/suppliers/create', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Tambah mitra supplier baru/i })).toBeVisible({ timeout: 20000 });
        await page.locator('input[name="nama"]').fill('QA Toko Uji Evidence');
        await page.locator('input[name="whatsapp"]').fill('6281234567890');
        await page.locator('textarea[name="alamat"]').fill('Jl. Uji QA No. 1, Malang');
        await page.locator('input[name="kategori[]"]').first().check().catch(() => { });
        await page.locator('textarea[name="deskripsi"]').fill('Supplier uji untuk evidence (tidak disimpan).').catch(() => { });
        await cap(page, 'SADM/SADM005_tambah_supplier_form.png');
    });

    test('SADM006 - Form Edit supplier manual', async ({ page }) => {
        await page.goto('/super-admin/suppliers', { waitUntil: 'domcontentloaded' });
        const editLink = page.getByRole('link', { name: /^Edit$/i }).first();
        const cnt = await editLink.count();
        console.log('SADM006_EDIT_LINK::' + cnt);
        if (cnt > 0) {
            await editLink.click();
            await expect(page.getByRole('heading', { name: /Edit data mitra supplier/i })).toBeVisible({ timeout: 20000 });
        }
        await cap(page, 'SADM/SADM006_edit_supplier.png');
    });

    test('SADM007 - Tambah supplier tanpa kategori (ditolak)', async ({ page }) => {
        await page.goto('/super-admin/suppliers/create', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Tambah mitra supplier baru/i })).toBeVisible({ timeout: 20000 });
        await page.locator('input[name="nama"]').fill('QA Tanpa Kategori');
        await page.locator('input[name="whatsapp"]').fill('6281200001111');
        await page.locator('textarea[name="alamat"]').fill('Jl. Negatif QA No. 7, Malang');
        // JANGAN centang kategori apa pun
        await page.getByRole('button', { name: /Tambah Mitra|Simpan/i }).first().click();
        await page.waitForTimeout(1500);
        // tetap di form / muncul error kategori
        const stillForm = await page.getByRole('heading', { name: /Tambah mitra supplier baru/i }).isVisible().catch(() => false);
        const errCat = await page.locator('.text-red-600').first().isVisible().catch(() => false);
        expect(stillForm || errCat).toBeTruthy();
        await cap(page, 'SADM/SADM007_tanpa_kategori.png');
    });

    test('SADM008 - Kontrol Tolak/Setujui pada toko supplier', async ({ page }) => {
        await page.goto('/super-admin/suppliers?status=active', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Manajemen Mitra Supplier/i })).toBeVisible({ timeout: 20000 });
        const tolak = await page.getByRole('button', { name: /^Tolak$/i }).count();
        const setujui = await page.getByRole('button', { name: /^Setujui$/i }).count();
        console.log('SADM008_TOLAK::' + tolak + ' SETUJUI::' + setujui);
        await cap(page, 'SADM/SADM008_tolak_setujui.png');
    });
});

test.describe('Evidence Section 20 - Guard akses non-admin', () => {
    test.setTimeout(120000);
    test('SADM009 - Penanggung Jawab tidak bisa buka Super Admin', async ({ page }) => {
        await page.route('**/:5173/**', (r) => r.abort());
        await page.route(/.*:5173.*/, (r) => r.abort());
        const auth = new AuthPage(page);
        await auth.loginAndWaitForDashboard('pjawab@email.com', PW);
        const resp = await page.goto('/super-admin/suppliers', { waitUntil: 'domcontentloaded' });
        console.log('SADM009_STATUS::' + (resp?.status() ?? 'n/a') + ' URL::' + page.url());
        // konten Super Admin tidak boleh tampil
        const hasAdminHeading = await page.getByRole('heading', { name: /Manajemen Mitra Supplier/i }).isVisible().catch(() => false);
        expect(hasAdminHeading).toBeFalsy();
        await cap(page, 'SADM/SADM009_akses_ditolak.png');
    });
});
