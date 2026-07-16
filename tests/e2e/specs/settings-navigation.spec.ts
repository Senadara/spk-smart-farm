import { test, expect } from '@playwright/test';
import { SettingsPage } from '../pages/SettingsPage.js';
import { AuthPage } from '../pages/AuthPage.js';

test.describe('Modul Halaman Pengaturan (Setting) - E2E QA', () => {
    let settingsPage: SettingsPage;

    test.beforeEach(async ({ page }) => {
        // Arrange
        settingsPage = new SettingsPage(page);

        // Act
        await settingsPage.goto();
    });

    test('Positif - UI Pengaturan memuat render elemen heading judul', async () => {
        /**
         * Given user berhasil akses ke halaman /settings
         * When komponen mem-build hierarchy DOM
         * Then subjudul dan title page statis harus divisualisasikan untuk orientasi UI
         */

        // Arrange & Act (Dilaksanakan oleh hook beforeEach goto)

        // Assert
        await settingsPage.expectPageTitleVisible();
    });

    test('Positif - Navigasi sub-menu pengaturan (Data Master, IoT) dirender sempurna', async () => {
        /**
         * Given page settings berhasil dimuat admin
         * When melihat menu root index konfigurasi
         * Then minimal 3 menu cards grid wajib eksis melingkupi porsi screen
         */

        // Arrange & Act

        // Assert
        await settingsPage.expectAllCardsVisible();
    });

    test('Positif - Rute Navigasi: Menu Card Data Master mengarahkan traffic referensi url persis', async ({ page }) => {
        /**
         * Given tombol/card Data Master terpampang
         * When pengguna execute event onClick pada entitas visual card tersebut
         * Then route push Next/Livewire merubah location window ke root /data-master
         */

        // Arrange
        const expectedURLRef = /.*\/data-master/;

        // Act
        await settingsPage.clickDataMasterCard();

        // Assert
        await expect(page).toHaveURL(expectedURLRef, { timeout: 15000 });
    });

    test('Positif - Rute Navigasi: Menu Card IoT bereaksi mendarat pada path Device', async ({ page }) => {
        /**
         * Given menu routing Devices terlihat di grid Pengaturan
         * When UI button IoT ditekan
         * Then router berhasil mengantarkan user ke scope URL /iot/devices
         */

        // Arrange
        const stringRouteDevices = /.*\/iot\/devices/;

        // Act
        await settingsPage.clickIotCard();

        // Assert
        await expect(page).toHaveURL(stringRouteDevices, { timeout: 15000 });
    });

    test('Positif - Halaman Settings dapat diakses setelah refresh browser', async ({ page }) => {
        /**
         * Given user berada di halaman settings
         * When user melakukan refresh halaman
         * Then halaman settings tetap dapat diakses dan semua card terlihat
         */

        // Arrange & Act
        await page.reload({ waitUntil: 'domcontentloaded' });

        // Assert
        await settingsPage.expectPageTitleVisible();
        await settingsPage.expectAllCardsVisible();
    });

    test('Positif - Semua card navigasi memiliki visual yang konsisten', async ({ page }) => {
        /**
         * Given user melihat halaman settings
         * When memeriksa semua card menu
         * Then setiap card harus visible dan memiliki struktur yang konsisten
         */

        // Arrange & Act
        const dataMasterCard = settingsPage.dataMasterCard;
        const iotDevicesCard = settingsPage.iotDevicesCard;
        const iotConfigCard = settingsPage.iotConfigCard;

        // Assert
        await expect(dataMasterCard).toBeVisible();
        await expect(iotDevicesCard).toBeVisible();
        await expect(iotConfigCard).toBeVisible();

        // Verifikasi bahwa card adalah link yang dapat diklik
        await expect(dataMasterCard).toHaveAttribute('href');
        await expect(iotDevicesCard).toHaveAttribute('href');
        await expect(iotConfigCard).toHaveAttribute('href');
    });

    test('Negatif - Navigasi ke route settings yang tidak valid menampilkan error 404', async ({ page }) => {
        /**
         * Given user mencoba mengakses sub-route settings yang tidak ada
         * When navigasi ke /settings/invalid-route
         * Then sistem harus menampilkan halaman 404 atau redirect ke settings utama
         */

        // Arrange
        const invalidRoute = '/settings/invalid-route-xyz-123';

        // Act
        await page.goto(invalidRoute, { waitUntil: 'domcontentloaded' });

        // Assert
        // Bisa jadi 404 atau redirect ke settings utama
        const currentUrl = page.url();
        const is404 = await page.locator('text=/404|not found/i').isVisible({ timeout: 5000 }).catch(() => false);
        const isRedirectedToSettings = /\/settings\/?$/.test(currentUrl);

        expect(is404 || isRedirectedToSettings).toBeTruthy();
    });

    test('Negatif - Akses halaman settings tanpa autentikasi harus redirect ke login', async ({ page, context }) => {
        /**
         * Given user belum login (tidak ada session)
         * When mencoba mengakses halaman /settings
         * Then sistem harus redirect ke halaman login
         */

        // Arrange: Clear cookies untuk simulasi user tanpa session
        await context.clearCookies();

        // Act
        await page.goto('/settings', { waitUntil: 'domcontentloaded' });

        // Assert
        await expect(page).toHaveURL(/.*login/, { timeout: 15000 });
    });

    test('Negatif - Card yang tidak ada tidak menyebabkan crash aplikasi', async ({ page }) => {
        /**
         * Given user berada di halaman settings
         * When mencoba mengakses card yang mungkin tidak ada untuk role tertentu
         * Then aplikasi tidak crash dan tetap menampilkan card yang tersedia
         */

        // Arrange & Act
        const bodyContent = await page.locator('body').textContent();

        // Assert
        expect(bodyContent).not.toMatch(/Fatal render error|undefined|null/i);
        await settingsPage.expectPageTitleVisible();
    });

    test('Edge Case - Navigasi cepat antar card tidak menyebabkan race condition', async ({ page }) => {
        /**
         * Given user berada di halaman settings
         * When user melakukan klik cepat berturut-turut pada berbagai card
         * Then sistem harus handle navigasi dengan baik tanpa error
         */

        // Arrange
        await settingsPage.expectAllCardsVisible();

        // Act: Klik Data Master
        await settingsPage.clickDataMasterCard();
        await expect(page).toHaveURL(/.*\/data-master/, { timeout: 10000 });

        // Kembali ke settings
        await page.goBack();
        await settingsPage.expectPageTitleVisible();

        // Klik IoT Devices
        await settingsPage.clickIotCard();
        await expect(page).toHaveURL(/.*\/iot\/devices/, { timeout: 10000 });

        // Kembali ke settings
        await page.goBack();
        await settingsPage.expectPageTitleVisible();

        // Klik IoT Config
        await settingsPage.clickIotConfigCard();
        await expect(page).toHaveURL(/.*\/iot\/config/, { timeout: 10000 });

        // Assert: Tidak ada error JavaScript
        const bodyContent = await page.locator('body').textContent();
        expect(bodyContent).not.toMatch(/error|crash/i);
    });

    test('Edge Case - Halaman settings dapat diakses dari berbagai entry point', async ({ page }) => {
        /**
         * Given user berada di berbagai halaman aplikasi
         * When user mengakses settings dari berbagai route
         * Then halaman settings selalu dapat dimuat dengan benar
         */

        // Arrange & Act 1: Dari dashboard
        await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
        await page.goto('/settings', { waitUntil: 'domcontentloaded' });

        // Assert 1
        await settingsPage.expectPageTitleVisible();

        // Act 2: Dari data-master
        await page.goto('/data-master', { waitUntil: 'domcontentloaded' });
        await page.goto('/settings', { waitUntil: 'domcontentloaded' });

        // Assert 2
        await settingsPage.expectPageTitleVisible();
        await settingsPage.expectAllCardsVisible();
    });

    /* ═══════════════════════════════════════════════════════════════════
       DSS SUPPLIER CARD (AHP-SAW)
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - DSS Supplier card (AHP-SAW) visible dengan gradient background', async ({ page }) => {
        /**
         * Given user berada di halaman settings
         * When melihat DSS supplier section
         * Then card dengan gradient background dan badge visible
         */

        // Assert: DSS card heading
        const dssHeading = page.locator('h3').filter({ hasText: /AHP bobot.*SAW peringkat/i });
        await expect(dssHeading).toBeVisible();

        // Assert: Hybrid badge
        const badge = page.locator('span').filter({ hasText: /Hybrid.*Rekomendasi supplier/i });
        await expect(badge).toBeVisible();
    });

    test('Positif - DSS Supplier card menampilkan description text (perbandingan berpasangan, validasi CR, normalisasi, skor)', async ({ page }) => {
        /**
         * Given DSS card rendered
         * When check card content
         * Then description dengan keywords CR dan normalisasi visible
         */

        // Assert: Description keywords
        const bodyText = await page.locator('body').textContent();
        expect(bodyText).toContain('perbandingan berpasangan');
        expect(bodyText).toContain('validasi CR');
        expect(bodyText).toMatch(/normalisasi|skor gabungan/i);
    });

    test('Positif - DSS Supplier card has 3 action buttons (Atur bobot AHP, Dashboard SAW, Komparasi produk)', async ({ page }) => {
        /**
         * Given DSS card displayed
         * When check action buttons
         * Then 3 buttons visible dengan correct text
         */

        // Assert: AHP Config button
        const ahpButton = page.locator('a').filter({ hasText: /Atur bobot.*AHP/i });
        await expect(ahpButton).toBeVisible();

        // Assert: Dashboard SAW button
        const dashboardButton = page.locator('a').filter({ hasText: /Dashboard SAW/i });
        await expect(dashboardButton).toBeVisible();

        // Assert: Komparasi produk button
        const komparasiButton = page.locator('a').filter({ hasText: /Komparasi produk/i });
        await expect(komparasiButton).toBeVisible();
    });

    test('Positif - DSS "Atur bobot AHP" button navigate ke /spk-suppliers/dss/config', async ({ page }) => {
        /**
         * Given DSS card visible
         * When click "Atur bobot AHP" button
         * Then navigate to DSS config page
         */

        // Arrange
        const ahpButton = page.locator('a').filter({ hasText: /Atur bobot.*AHP/i });

        // Act
        await ahpButton.click();

        // Assert
        await expect(page).toHaveURL(/.*\/spk-suppliers\/dss\/config/, { timeout: 15000 });
    });

    test('Positif - DSS "Dashboard SAW" button navigate ke /spk-suppliers/dss/dashboard', async ({ page }) => {
        /**
         * Given DSS card visible
         * When click "Dashboard SAW" button
         * Then navigate to DSS dashboard page
         */

        // Arrange
        const dashboardButton = page.locator('a').filter({ hasText: /Dashboard SAW/i });

        // Act
        await dashboardButton.click();

        // Assert
        await expect(page).toHaveURL(/.*\/spk-suppliers\/dss\/dashboard/, { timeout: 15000 });
    });

    test('Positif - DSS "Komparasi produk" button navigate ke /spk-suppliers/products', async ({ page }) => {
        /**
         * Given DSS card visible
         * When click "Komparasi produk" button
         * Then navigate to products comparison page
         */

        // Arrange
        const komparasiButton = page.locator('a').filter({ hasText: /Komparasi produk/i });

        // Act
        await komparasiButton.click();

        // Assert
        await expect(page).toHaveURL(/.*\/spk-suppliers\/products/, { timeout: 15000 });
    });

    /* ═══════════════════════════════════════════════════════════════════
       CARD VISUAL & STYLING VALIDATION
       ═══════════════════════════════════════════════════════════════════ */
});
