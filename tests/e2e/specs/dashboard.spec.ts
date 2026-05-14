import { test, expect } from '@playwright/test';
import { DashboardPage } from '../pages/DashboardPage.js';

test.describe('Pengujian Halaman Dashboard', () => {
    test.describe.configure({ mode: 'serial' });

    let dashboardPage: DashboardPage;

    test.beforeEach(async ({ page }, testInfo) => {
        testInfo.setTimeout(120000);
        page.setDefaultNavigationTimeout(120000);
        page.setDefaultTimeout(120000);
        dashboardPage = new DashboardPage(page);
        await dashboardPage.goto();
    });

    test('Positif - Halaman dashboard berhasil dimuat dengan semua elemen visible', async () => {
        await dashboardPage.expectAllCardsVisible();
    });

    test('Positif - Welcome card menampilkan pesan sambutan dengan nama user', async () => {
        await dashboardPage.expectWelcomeCardVisible();
        await expect(dashboardPage.welcomeCard).toContainText('Selamat Datang');
    });

    test('Positif - Info card untuk Role menampilkan dengan benar', async () => {
        await dashboardPage.expectRoleCardVisible();
    });

    test('Positif - Info card untuk Email menampilkan dengan benar', async () => {
        await dashboardPage.expectEmailCardVisible();
    });

    test('Positif - Info card untuk Login Sejak menampilkan dengan benar', async () => {
        await dashboardPage.expectLoginSinceCardVisible();
    });

    test('Positif - Placeholder card untuk SPK Akan Hadir menampilkan dengan benar', async () => {
        await dashboardPage.expectPlaceholderCardVisible();
    });
});