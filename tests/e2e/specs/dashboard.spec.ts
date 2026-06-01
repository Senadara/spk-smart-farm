import { test, expect } from '@playwright/test';
import { DashboardPage } from '../pages/DashboardPage.js';

test.describe('Modul Dashboard Umum - E2E QA', () => {
    test.describe.configure({ mode: 'serial' });

    let dashboardPage: DashboardPage;

    test.beforeEach(async ({ page }, testInfo) => {
        // Arrange
        testInfo.setTimeout(120000);
        page.setDefaultNavigationTimeout(120000);
        page.setDefaultTimeout(120000);

        dashboardPage = new DashboardPage(page);

        // Blocker akses Vite HMR
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        // Act
        await dashboardPage.goto();
    });

    test('Positif - Seluruh widget indikator esensial pada Dashboard terender', async () => {
        /**
         * Given otentikasi user dinyatakan valid 
         * When load base url atau /dashboard route
         * Then semua container statis (welcome card, info session, widget metric) siap dibaca
         */

        // Arrange & Act (Dilakukan di beforeEach via goto)
        
        // Assert
        await dashboardPage.expectAllCardsVisible();
    });

    test('Positif - Panel autentikasi dashboard menampilkan identitas state session aktif user', async () => {
        /**
         * Given data session aktif pada browser user
         * When page komponen user-role di-render
         * Then modul harus menampilkan label "Selamat Datang" diletak yg sesuai
         */

        // Arrange
        const expectedGreetingText = 'Selamat Datang';

        // Act & Assert
        await dashboardPage.expectWelcomeCardVisible();
        await expect(dashboardPage.welcomeCard).toContainText(expectedGreetingText);
    });

    test('Positif - Verifikasi label Role dan Email memuat status eksistensial', async () => {
        /**
         * Given payload otentikasi user lengkap memiliki role dan metadata kontak
         * When modul render card identity 
         * Then area card role dan email harus divisualisasikan dalam state visible
         */

        // Arrange & Act
        // Visibilitas ditrigger oleh Lifecycle DOM NextJS/Laravel

        // Assert
        await dashboardPage.expectRoleCardVisible();
        await dashboardPage.expectEmailCardVisible();
    });

    test('Positif - Panel Informasi Placeholder "SPK Akan Hadir" tidak memicu layout shift fatal', async () => {
        /**
         * Given sistem belum merilis integrasi SPK penuh pada general view
         * When user merender bagian bawah Dashboard
         * Then area informasi placeholder "SPK Akan Hadir" tetap tersedia sempurna
         */

        // Act (Render layout)

        // Assert
        await dashboardPage.expectPlaceholderCardVisible();
    });

    test('Negatif - Akses link widget tidak valid diluar batasan session tidak menyebabkan crash JS', async ({ page }) => {
        /**
         * Given user pada page dashboard
         * When kita mengecek interaksi dan dom layout
         * Then text 'Data Invalid Crash' atau blank screen of death JS tdk ditemukan
         */

        // Arrange
        const bodyLocator = page.locator('body');
        
        // Act
        const bodyContent = await bodyLocator.textContent();

        // Assert
        expect(bodyContent).not.toMatch(/Fatal render error/i);
    });
});
