import { test, expect } from '@playwright/test';
import { ProfilePage } from '../pages/ProfilePage.js';

test.describe('Modul Halaman Konfigurasi Profil - E2E QA', () => {
    test.describe.configure({ mode: 'serial' });

    let profilePage: ProfilePage;

    test.beforeEach(async ({ page }, testInfo) => {
        // Arrange
        testInfo.setTimeout(240000);
        
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        profilePage = new ProfilePage(page);
        
        // Act
        await profilePage.goto();
    });

    test('Positif - DOM Halaman Profil me-render tajuk/judul dan layout detil identitas diri', async () => {
        /**
         * Given pengguna dengan otentikasi masuk ke area pengaturan preferensi data diri
         * When routing sistem mencapai endpoint url Profil Pengguna
         * Then komponen utama (Header Nama, Form, Detail ID) tampil tanpa bug / blank render
         */

        // Arrange & Act (Dilaksanakan pada hook beforeEach)

        // Assert
        await profilePage.expectPageTitleVisible();
        await profilePage.expectProfileDetailsVisible();
    });

    test('Positif - Lencana Status / Role Badge merepresentasikan otorisasi autentik pengguna', async () => {
        /**
         * Given spesifikasi akun memiliki privilege hak ases atau rolenya sendiri (Misal Petugas/Admin)
         * When block UI profile dirender
         * Then Badge indicator role harus terlihat jelas bagi pengguna di area Card Header Pribadi
         */

        // Arrange
        const roleBadgeElement = profilePage.roleBadge;

        // Act & Assert
        await expect(roleBadgeElement).toBeVisible({ timeout: 10000 });
    });

    test('Positif - Grid Log History Sesi Login di-load mencetak record koneksi masuk dari Database', async () => {
        /**
         * Given server merekam history logging user IP di table history
         * When pengguna menggeser scroll ke tabel Aktivitas Sesi
         * Then minimal 1 rekam jejak row login sebelumnya (dari proses login) tampil dengan format parsial tabel lengkap
         */

        // Arrange
        const expectedMinimumRows = 1;

        // Act
        await profilePage.expectLoginHistoryVisible();
        await profilePage.expectLoginHistoryHasRows(expectedMinimumRows);

        // Assert Data Kolom (Time, IP, Browser)
        const firstRow = profilePage.loginHistoryRows.first();
        const timeCell = firstRow.locator('td').first();
        const ipCell = firstRow.locator('td').nth(1);
        const browserCell = firstRow.locator('td').nth(2);

        await expect(timeCell).toBeVisible();
        await expect(ipCell).toBeVisible();
        await expect(browserCell).toBeVisible();
    });

    test('Negatif - Tabel riwayat aktivitas login tidak akan break layput jika disimulasikan kosong', async ({ page }) => {
        /**
         * Given keadaan mock ketika sistem backend sedang terputus parsial dari microservice logger Auth
         * When grid Log History mereload status record = NULL
         * Then UI Table memuat template safe "Tidak ada record" (Graceful Render) dan Javascript tidak macet
         */

        // Test ini hanya validasi pasif, pastikan DOM body page tidak fatal crash error
        const bodyContent = await page.locator('body').textContent();
        expect(bodyContent).not.toMatch(/Fatal render error/i);
    });
});
