import { test, expect } from '@playwright/test';
import { DataMasterPage } from '../pages/DataMasterPage.js';

/**
 * Modul Data Master Ternak — Overview & Navigasi (REWRITE)
 * Halaman /data-master telah didesain ulang oleh Nanda menjadi
 * "Konfigurasi Data Master Ternak". Spec lama (tab Daftar Pengguna/Blok Kebun,
 * dummy users) sudah usang dan digantikan test ini agar sesuai implementasi nyata.
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

    test('Positif - Halaman "Konfigurasi Data Master Ternak" load dengan judul & breadcrumb', async ({ page }) => {
        await dm.expectPageReady();
        await expect(dm.pageTitle).toBeVisible();
        const body = await page.locator('body').textContent() || '';
        expect(body).toContain('Data Master');
        expect(body).toContain('Ternak');
        // Deskripsi menegaskan jenis ternak & kandang dari mobile
        expect(body).toMatch(/Jenis ternak dan kandang tetap dibuat dari mobile/i);
    });

    test('Positif - Tiga kartu statistik (Jenis Ternak, Siap, Perlu Setup) tampil', async ({ page }) => {
        await dm.expectPageReady();
        const body = await page.locator('body').textContent() || '';
        expect(body).toContain('Jenis Ternak');
        expect(body).toContain('Siap');
        expect(body).toContain('Perlu Setup');
    });

    test('Positif - Panel "Jenis Ternak dari Mobile" menampilkan daftar jenis ternak', async ({ page }) => {
        await dm.expectPageReady();
        await expect(dm.jenisTernakHeading).toBeVisible();
        // Seeder AyamPetelur menyediakan minimal 1 jenis ternak
        const count = await dm.jenisTernakLinks.count();
        expect(count).toBeGreaterThanOrEqual(1);
    });

    test('Positif - Klik jenis ternak memuat panel konfigurasi (URL ?jenis_budidaya_id=)', async ({ page }) => {
        await dm.expectPageReady();
        expect(await dm.hasTypes()).toBeTruthy();
        await dm.jenisTernakLinks.first().click();
        await expect(page).toHaveURL(/jenis_budidaya_id=/);
        // Panel konfigurasi (form parameter lingkungan) tampil
        await expect(dm.envSectionHeading).toBeVisible();
    });

    test('Positif - Panel jenis terpilih menyediakan tautan IoT Device & Fuzzy SPK', async ({ page }) => {
        await dm.selectFirstType();
        await expect(dm.iotDeviceLink).toBeVisible();
        await expect(dm.fuzzyLink).toBeVisible();
    });

    test('Positif - Ringkasan konfigurasi (Parameter Lingkungan, Fungsi Produktivitas, Kandang Mobile) tampil', async ({ page }) => {
        await dm.selectFirstType();
        const body = await page.locator('body').textContent() || '';
        expect(body).toContain('Parameter Lingkungan');
        expect(body).toContain('Fungsi Produktivitas');
        expect(body).toContain('Kandang Mobile');
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
