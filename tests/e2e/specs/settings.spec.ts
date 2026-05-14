import { test, expect } from '@playwright/test';
import { SettingsPage } from '../pages/SettingsPage.js';

test.describe('Pengujian Halaman Pengaturan', () => {
    let settingsPage: SettingsPage;

    test.beforeEach(async ({ page }) => {
        settingsPage = new SettingsPage(page);
        await settingsPage.goto();
    });

    test('Positif - Halaman pengaturan berhasil dimuat dengan judul benar', async () => {
        await settingsPage.expectPageTitleVisible();
    });

    test('Positif - Ketiga menu card pengaturan visible dan dapat diklik', async () => {
        await settingsPage.expectAllCardsVisible();
    });

    test('Positif - Card Data Master dapat di-klik dan navigate ke halaman data master', async ({ page }) => {
        await settingsPage.clickDataMasterCard();
        await expect(page).toHaveURL(/.*\/data-master/, { timeout: 10000 });
    });

    test('Positif - Card Perangkat IoT dapat di-klik dan navigate ke halaman IoT devices', async ({ page }) => {
        await settingsPage.clickIotDevicesCard();
        await expect(page).toHaveURL(/.*\/iot\/devices/, { timeout: 10000 });
    });

    test('Positif - Card Konfigurasi IoT dapat di-klik dan navigate ke halaman IoT config', async ({ page }) => {
        await settingsPage.clickIotConfigCard();
        await expect(page).toHaveURL(/.*\/iot\/config/, { timeout: 10000 });
    });
});