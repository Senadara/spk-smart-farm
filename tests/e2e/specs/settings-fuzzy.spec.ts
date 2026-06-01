import { test, expect } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';
import { SettingsPage } from '../pages/SettingsPage.js';
import { FuzzyConfigPage } from '../pages/FuzzyConfigPage.js';

test.describe.serial('Modul Dashboard Konfigurasi Logic Fuzzy - E2E Pjwb QA', () => {
    let authPage: AuthPage;
    let settingsPage: SettingsPage;
    let fuzzyPage: FuzzyConfigPage;

    test.setTimeout(120000);

    test.beforeEach(async ({ page }) => {
        // Arrange
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        authPage = new AuthPage(page);
        settingsPage = new SettingsPage(page);
        fuzzyPage = new FuzzyConfigPage(page);

        // Pre-requisites Active Session System Role
        await authPage.loginAndWaitForDashboard('pjawab@email.com', 'Password123.');
        
        // Act
        await settingsPage.goto();
    });

    test('Positif - Identitas Menu Fuzzy Config UI Muncul Tepat Untuk Role Pjawab yang Diizinkan', async ({ page }) => {
        /**
         * Given user berhasil menatap menu root settings /settings
         * When otoritas mengevaluasi DOM layout dan route
         * Then card pemicu setelan 'Fuzzy' harus tampak dan mampu memindahkan view
         */

        // Arrange & Act
        await settingsPage.expectFuzzyCardVisible();
        await settingsPage.clickFuzzyCard();
        
        // Assert
        await fuzzyPage.expectPageReady();
        await expect(page).toHaveURL(/.*\/settings\/fuzzy/);
    });

    test('Positif - Eksekusi dinamis Menulis dan Menghapus Relasi Variabel Beserta Membership Logic Fuzzy', async ({ page }) => {
        /**
         * Given Pjawab memiliki otoritas mendaftar Variable Parameter
         * When Form Input Variable dan Set MF berturut-turut disubmit
         * Then Grid UI Variabel Fuzzy bereaksi instan merender data dan memutakhirkan state backend
         */

        // Arrange (Data Injection Parameter Set)
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const variableName = `e2e_var_${suffix}`;
        const setName = `e2e_set_${suffix}`;

        await settingsPage.clickFuzzyCard();
        await fuzzyPage.expectPageReady();
        
        // Act 1: Membangun Variable Root
        await fuzzyPage.clickVariablesTab();
        await fuzzyPage.createVariable({
            name: variableName,
            group: 'lingkungan',
            type: 'input',
            unit: 'ppm',
            description: `Variabel E2E ${suffix}`,
        });

        // Assert 1
        await expect(page.getByText('Variabel berhasil ditambahkan.')).toBeVisible({ timeout: 15000 });
        await fuzzyPage.expectVariableVisible(variableName);

        // Act 2: Membuat Child Membership Parameter (Relasi shape)
        await fuzzyPage.expandVariable(variableName);
        await fuzzyPage.createSetForVariable(variableName, {
            name: setName,
            shape: 'triangle',
            a: '1',
            b: '2',
            c: '3',
        });

        // Assert 2
        await expect(page.getByText('Membership function berhasil ditambahkan.')).toBeVisible({ timeout: 15000 });
        await fuzzyPage.expectVariableVisible(variableName);

        // Act 3: Tab Switching Verify
        await fuzzyPage.clickRulesTab();
        await expect(page.getByRole('heading', { name: /Aturan Inferensi/i })).toBeVisible();

        await fuzzyPage.clickSourcesTab();
        await expect(page.getByRole('heading', { name: /Sumber Data Input/i })).toBeVisible();

        // Act 4: Cleanup End of life Data / Penghancuran Variable
        await fuzzyPage.clickVariablesTab();
        await fuzzyPage.deleteVariable(variableName);
        
        // Assert Cleanup
        await expect(page.getByText(`Variabel '${variableName}' beserta`)).toBeVisible({ timeout: 15000 });
    });
});
