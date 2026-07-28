import { test, expect } from '@playwright/test';
import { DataMasterPage } from '../pages/DataMasterPage.js';

/**
 * Modul Data Master Ternak — Overview & Navigasi (REWRITE untuk redesign Nanda 464c630)
 * Halaman /data-master kini bertab (Parameter Sensor / Ternak / Kategori Stok / Satuan Produk)
 * dengan default tab "Ternak" (livestock). Heading menjadi "Konfigurasi Data Master",
 * pemilih jenis ternak memakai dropdown <select id="jenis_budidaya_id"> (bukan lagi daftar link),
 * dan panel konfigurasi menautkan "Kelola Katalog Sensor". Spec disesuaikan agar cocok
 * dengan implementasi nyata terbaru.
 */
test.describe('Data Master Ternak - Overview & Navigasi', () => {
    let dm: DataMasterPage;

    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }, testInfo) => {
        testInfo.setTimeout(90000);
        page.setDefaultTimeout(30000);
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());
        dm = new DataMasterPage(page);
        await dm.goto();
    });

    test('Positif - Halaman "Konfigurasi Data Master" load dengan judul & breadcrumb', async ({ page }) => {
        await dm.expectPageReady();
        await expect(dm.pageTitle).toBeVisible();
        const body = await page.locator('body').textContent() || '';
        expect(body).toContain('Data Master');
        expect(body).toContain('Ternak');
        // Deskripsi section jenis ternak menegaskan data pilihan dibaca dari mobile
        expect(body).toMatch(/dibaca dari mobile/i);
    });

    test('Positif - Header statistik (Jenis, Siap, Setup) tampil', async ({ page }) => {
        await dm.expectPageReady();
        const body = await page.locator('body').textContent() || '';
        expect(body).toContain('Jenis');
        expect(body).toContain('Siap');
        expect(body).toContain('Setup');
    });

    test('Positif - Section "Jenis Ternak" menampilkan pemilih jenis (dropdown)', async ({ page }) => {
        await dm.expectPageReady();
        await expect(dm.jenisTernakHeading).toBeVisible();
        // Seeder AyamPetelur menyediakan minimal 1 jenis ternak -> dropdown punya opsi
        const count = await dm.typeOptions.count();
        expect(count).toBeGreaterThanOrEqual(1);
    });

    test('Positif - Memilih jenis ternak memuat panel konfigurasi (URL ?jenis_budidaya_id=)', async ({ page }) => {
        await dm.expectPageReady();
        expect(await dm.hasTypes()).toBeTruthy();
        await dm.selectFirstType();
        await expect(page).toHaveURL(/jenis_budidaya_id=/);
        // Panel konfigurasi (section parameter lingkungan) tampil
        await expect(dm.envSectionHeading).toBeVisible();
    });

    test('Positif - Panel jenis terpilih menyediakan tautan "Kelola Katalog Sensor"', async ({ page }) => {
        await dm.selectFirstType();
        await expect(dm.katalogSensorLink).toBeVisible();
        await expect(dm.saveButton).toBeVisible();
    });

    test('Positif - Ringkasan konfigurasi (Parameter Lingkungan, Produktivitas, Kandang) tampil', async ({ page }) => {
        await dm.selectFirstType();
        const body = await page.locator('body').textContent() || '';
        expect(body).toContain('Parameter Lingkungan');
        expect(body).toContain('Produktivitas');
        expect(body).toContain('Kandang');
    });

    test('Negatif - Akses dengan jenis_budidaya_id tidak valid tetap tampil tanpa error', async ({ page }) => {
        await dm.gotoInvalidType();
        await expect(dm.pageTitle).toBeVisible();
        const body = await page.locator('body').textContent() || '';
        expect(body).not.toContain('Fatal error');
        expect(body).not.toContain('SQLSTATE');
        expect(body).not.toContain('Server Error');
    });
});
