import { test, expect } from '@playwright/test';
import { SettingsPage } from '../pages/SettingsPage.js';

test.describe('Modul Halaman Pengaturan (Setting) - E2E QA', () => {
    let settingsPage: SettingsPage;

    test.beforeEach(async ({ page }) => {
        // Arrange
        // Blocker statis
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

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

    test('Positif - Navigasi sub-menu pengaturan (Data Master, IoT Device, IoT Config) dirender sempurna', async () => {
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

    test('Positif - Rute Navigasi: Menu Card Perangkat IoT bereaksi mendarat pada path Device', async ({ page }) => {
        /**
         * Given menu routing Devices terlihat di grid Pengaturan
         * When UI button Perangkat IoT ditekan
         * Then SPA router berhasil mengantarkan user ke scope URL /iot/devices
         */

        // Arrange
        const stringRouteDevices = /.*\/iot\/devices/;

        // Act
        await settingsPage.clickIotDevicesCard();
        
        // Assert
        await expect(page).toHaveURL(stringRouteDevices, { timeout: 15000 });
    });

    test('Positif - Rute Navigasi: Konfigurasi IoT Routing tidak terhambat fatal 404', async ({ page }) => {
        /**
         * Given parameter Konfigurasi IoT merupakan integrasi utama SPK Sensor
         * When user menekan entitas anchor terkait
         * Then routing berekspektasi tinggi menyetujui load payload URL /iot/config
         */

        // Arrange
        const configPathRegex = /.*\/iot\/config/;

        // Act
        await settingsPage.clickIotConfigCard();
        
        // Assert
        await expect(page).toHaveURL(configPathRegex, { timeout: 15000 });
    });
});
