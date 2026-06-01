import { test, expect } from '@playwright/test';
import { DataMasterPage } from '../pages/DataMasterPage.js';

test.describe('Modul Data Master - E2E QA', () => {
    let dataMasterPage: DataMasterPage;

    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }, testInfo) => {
        testInfo.setTimeout(120000);
        page.setDefaultNavigationTimeout(120000);
        page.setDefaultTimeout(120000);

        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        dataMasterPage = new DataMasterPage(page);
        await dataMasterPage.goto();
    });

    /**
     * Skenario 1 - Cek ketersediaan halaman Data Master
     * Given pengguna berada di dashboard
     * When pengguna membuka halaman data master
     * Then banner read-only dan tab utama harus ter-render
     */
    test('Positif - Halaman data master berhasil dimuat dengan banner read-only', async ({ page }) => {
        // Arrange & Act
        await dataMasterPage.expectPageReady();
        
        // Assert
        await expect(page).toHaveURL(/.*\/data-master/);
    });

    /**
     * Skenario 2 - Memeriksa daftar tab pengguna
     * Given halaman data master dimuat sempurna
     * When pengguna melihat tab daftar pengguna
     * Then tabel harus setidaknya memuat 6 pengguna (data dummy default)
     */
    test('Positif - Tab daftar pengguna menampilkan data dummy yang lengkap', async () => {
        // Arrange & Act
        const rowCount = await dataMasterPage.getVisibleUserRowCount();
        
        // Assert
        expect(rowCount).toBeGreaterThanOrEqual(6);
    });

    /**
     * Skenario 3 - Fungsi pencarian pada tab pengguna
     * Given pengguna melihat daftar seluruh manajer dan petugas
     * When mengisi form search dengan keyword 'Siti'
     * Then tabel secara reaktif menampilkan hanya data terkait
     */
    test('Positif - Pencarian nama pengguna memfilter hasil secara reaktif', async () => {
        // Arrange
        await dataMasterPage.clickUsersTab();

        // Act
        await dataMasterPage.searchUsers('Siti');

        // Assert
        const rowCount = await dataMasterPage.getVisibleUserRowCount();
        expect(rowCount).toBe(1);
        await expect(dataMasterPage.page.getByText('Siti Nurhaliza')).toBeVisible();
    });

    /**
     * Skenario 4 - Navigasi ke Data Kebun
     * Given pengguna berada di Data Master Operasional
     * When pengguna berganti tab ke Blok Kebun
     * Then daftar lokasi Blok Kebun (contoh: Greenhouse A) ditampilkan
     */
    test('Positif - Tab blok kebun menampilkan daftar blok dan data jenis budidaya', async () => {
        // Arrange
        await dataMasterPage.expectPageReady();

        // Act
        await dataMasterPage.clickKebunTab();
        
        // Assert
        await expect(dataMasterPage.kebunTable).toBeVisible();
        await expect(dataMasterPage.page.getByText('Greenhouse A')).toBeVisible();
    });

    /**
     * Skenario 5 - Pencarian Kebun Negatif (Unhappy Path)
     * Given pengguna melihat daftar Blok Kebun
     * When pengguna mencari blok kebun yang mustahil/tidak ada (contoh: 'LokasiAntahBerantah')
     * Then tabel menampilkan status kosong tanpa fatal error/crash JavaScript
     */
    test('Negatif - Pencarian blok kebun invalid di-handle dengan baik tanpa crash', async ({ page }) => {
        // Arrange
        await dataMasterPage.clickKebunTab();

        // Act
        await dataMasterPage.searchKebun('LokasiAntahBerantah123');
        
        // Assert
        const rowCount = await dataMasterPage.getVisibleKebunRowCount();
        expect(rowCount).toBe(0);
        await expect(dataMasterPage.kebunTable).toBeVisible(); // Tabel tidak crash/hilang
    });
});
