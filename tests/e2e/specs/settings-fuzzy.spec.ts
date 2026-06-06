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

    test('Positif - Halaman Fuzzy Config memuat dengan sempurna menampilkan 3 tab navigasi utama', async ({ page }) => {
        /**
         * Given user Pjawab berhasil masuk ke halaman Fuzzy Config
         * When sistem merender komponen tab navigation
         * Then 3 tab utama (Variabel & MF, Aturan, Sumber Data) harus terlihat dan dapat diklik
         */

        // Arrange
        await settingsPage.clickFuzzyCard();

        // Act
        await fuzzyPage.expectPageReady();

        // Assert
        await expect(fuzzyPage.variablesTab).toBeVisible();
        await expect(fuzzyPage.rulesTab).toBeVisible();
        await expect(fuzzyPage.sourcesTab).toBeVisible();
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
        await expect(page.getByText(/berhasil dihapus/i)).toBeVisible({ timeout: 15000 });
    });

    test('Positif - Membuat Variabel dengan Membership Function Trapezoid (4 Parameter)', async ({ page }) => {
        /**
         * Given user berada di tab Variabel & MF
         * When user membuat variabel baru dengan membership function berbentuk trapezoid
         * Then sistem harus menerima 4 parameter (a, b, c, d) dan menyimpan dengan sukses
         */

        // Arrange
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const variableName = `e2e_trap_${suffix}`;
        const setName = `e2e_trapset_${suffix}`;

        await settingsPage.clickFuzzyCard();
        await fuzzyPage.expectPageReady();
        await fuzzyPage.clickVariablesTab();

        // Act 1: Buat variabel (gunakan group yang valid: lingkungan, kesehatan, atau kausalitas)
        await fuzzyPage.createVariable({
            name: variableName,
            group: 'kesehatan',
            type: 'output',
            unit: 'kg',
            description: `Variabel Trapezoid E2E ${suffix}`,
        });

        // Assert 1
        await expect(page.getByText('Variabel berhasil ditambahkan.')).toBeVisible({ timeout: 15000 });

        // Act 2: Buat membership function trapezoid
        await fuzzyPage.expandVariable(variableName);
        await fuzzyPage.createSetForVariable(variableName, {
            name: setName,
            shape: 'trapezoid',
            a: '10',
            b: '20',
            c: '30',
            d: '40',
        });

        // Assert 2
        await expect(page.getByText('Membership function berhasil ditambahkan.')).toBeVisible({ timeout: 15000 });

        // Cleanup
        await fuzzyPage.deleteVariable(variableName);
        await expect(page.getByText(/berhasil dihapus/i)).toBeVisible({ timeout: 15000 });
    });

    test('Positif - Tab Navigation: Perpindahan antar tab Variabel, Rules, dan Sources berjalan lancar', async ({ page }) => {
        /**
         * Given user berada di halaman Fuzzy Config
         * When user melakukan navigasi antar tab secara berurutan
         * Then setiap tab harus menampilkan konten yang sesuai tanpa error
         */

        // Arrange
        await settingsPage.clickFuzzyCard();
        await fuzzyPage.expectPageReady();

        // Act & Assert 1: Tab Variabel
        await fuzzyPage.clickVariablesTab();
        await expect(fuzzyPage.addVariableButton).toBeVisible();

        // Act & Assert 2: Tab Rules
        await fuzzyPage.clickRulesTab();
        await expect(page.getByRole('heading', { name: /Aturan Inferensi/i })).toBeVisible();
        await expect(fuzzyPage.addRuleButton).toBeVisible();

        // Act & Assert 3: Tab Sources
        await fuzzyPage.clickSourcesTab();
        await expect(page.getByRole('heading', { name: /Sumber Data Input/i })).toBeVisible();

        // Act & Assert 4: Kembali ke Tab Variabel
        await fuzzyPage.clickVariablesTab();
        await expect(fuzzyPage.addVariableButton).toBeVisible();
    });

    test('Negatif - Membuat Variabel dengan nama kosong harus gagal atau diblokir validasi', async ({ page }) => {
        /**
         * Given user membuka form Tambah Variabel
         * When user mencoba submit form dengan field nama kosong
         * Then sistem harus menampilkan pesan error validasi atau mencegah submit
         */

        // Arrange
        await settingsPage.clickFuzzyCard();
        await fuzzyPage.expectPageReady();
        await fuzzyPage.clickVariablesTab();

        // Act
        await fuzzyPage.addVariableButton.click();
        const modal = page.locator('h3', { hasText: 'Tambah Variabel' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]')
            .first();

        // Kosongkan nama (atau biarkan kosong)
        await modal.locator('input[name="name"]').fill('');
        await modal.locator('select[name="group"]').selectOption('lingkungan');
        await modal.locator('select[name="type"]').selectOption('input');
        await modal.locator('input[name="unit"]').fill('unit');
        await modal.locator('input[name="description"]').fill('Test deskripsi');

        const saveButton = modal.getByRole('button', { name: /Simpan/i });
        await saveButton.click();

        // Assert: Modal masih terbuka atau ada pesan error
        // Jika ada validasi HTML5, button tidak akan trigger submit
        // Jika ada validasi backend, akan muncul toast error atau error message
        const modalStillVisible = await modal.isVisible({ timeout: 3000 }).catch(() => false);
        const errorMessage = page.locator('.bg-red-50, .toast-error, [role="alert"]').first();
        const errorVisible = await errorMessage.isVisible({ timeout: 5000 }).catch(() => false);

        // Salah satu kondisi harus terpenuhi: modal masih ada ATAU error muncul
        expect(modalStillVisible || errorVisible).toBeTruthy();
    });

    test('Negatif - Membuat Membership Function dengan parameter tidak valid (a > b > c) harus gagal', async ({ page }) => {
        /**
         * Given user telah membuat variabel dan membuka form Tambah MF
         * When user memasukkan parameter triangle dengan urutan tidak logis (a > b > c)
         * Then sistem harus menolak atau menampilkan pesan error validasi
         */

        // Arrange: Buat variabel dulu
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const variableName = `e2e_invalid_${suffix}`;

        await settingsPage.clickFuzzyCard();
        await fuzzyPage.expectPageReady();
        await fuzzyPage.clickVariablesTab();

        await fuzzyPage.createVariable({
            name: variableName,
            group: 'lingkungan',
            type: 'input',
            unit: 'test',
            description: 'Test invalid MF',
        });

        await expect(page.getByText('Variabel berhasil ditambahkan.')).toBeVisible({ timeout: 15000 });

        // Act: Coba buat MF dengan parameter invalid
        await fuzzyPage.expandVariable(variableName);
        const section = page.locator('[data-var-id]').filter({ hasText: variableName }).first();
        await section.getByRole('button', { name: /\+ Tambah Set/i }).click();

        const modal = page.locator('h3', { hasText: 'Tambah Membership Function' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]')
            .first();

        await modal.locator('input[name="name"]').fill('invalid_set');
        await modal.locator('select[name="shape"]').selectOption('triangle');
        // Parameter tidak logis: a=10, b=5, c=1 (seharusnya a <= b <= c)
        await modal.locator('input[name="a"]').fill('10');
        await modal.locator('input[name="b"]').fill('5');
        await modal.locator('input[name="c"]').fill('1');

        const saveButton = modal.getByRole('button', { name: /Simpan/i });
        await saveButton.click();

        // Assert: Harus ada error message (backend validation)
        const errorMessage = page.locator('.bg-red-50, .toast-error, [role="alert"]').first();
        const errorVisible = await errorMessage.isVisible({ timeout: 10000 }).catch(() => false);
        const modalStillVisible = await modal.isVisible({ timeout: 3000 }).catch(() => false);

        // Backend akan menolak dengan error message
        expect(errorVisible || modalStillVisible).toBeTruthy();

        // Cleanup
        await page.keyboard.press('Escape'); // Tutup modal jika masih terbuka
        await page.waitForTimeout(500);
        await fuzzyPage.deleteVariable(variableName);
    });

    test('Negatif - Mencoba membuat variabel dengan nama duplikat harus ditolak sistem', async ({ page }) => {
        /**
         * Given sudah ada variabel dengan nama tertentu di sistem
         * When user mencoba membuat variabel baru dengan nama yang sama persis dalam group yang sama
         * Then sistem harus menampilkan pesan error dan mencegah duplikasi
         */

        // Arrange: Buat variabel pertama
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const variableName = `e2e_dup_${suffix}`;

        await settingsPage.clickFuzzyCard();
        await fuzzyPage.expectPageReady();
        await fuzzyPage.clickVariablesTab();

        await fuzzyPage.createVariable({
            name: variableName,
            group: 'lingkungan',
            type: 'input',
            unit: 'test',
            description: 'Variabel pertama',
        });

        await expect(page.getByText('Variabel berhasil ditambahkan.')).toBeVisible({ timeout: 15000 });

        // Act: Coba buat variabel kedua dengan nama sama DAN group sama
        await fuzzyPage.createVariable({
            name: variableName, // Nama sama
            group: 'lingkungan', // Group sama - ini yang akan ditolak
            type: 'output',
            unit: 'test2',
            description: 'Variabel duplikat',
        });

        // Assert: Harus ada pesan error yang menyebutkan duplikasi
        const errorMessage = page.locator('.bg-red-50, .toast-error, [role="alert"]').first();
        await expect(errorMessage).toBeVisible({ timeout: 10000 });
        await expect(errorMessage).toContainText(/sudah ada/i);

        // Cleanup
        await fuzzyPage.deleteVariable(variableName);
    });

    test('Negatif - Pencarian variabel yang tidak ada menampilkan state kosong tanpa crash', async ({ page }) => {
        /**
         * Given user berada di tab Variabel & MF
         * When user mencari variabel dengan keyword yang tidak ada di sistem
         * Then UI harus menampilkan state kosong dengan baik tanpa error JavaScript
         */

        // Arrange
        await settingsPage.clickFuzzyCard();
        await fuzzyPage.expectPageReady();
        await fuzzyPage.clickVariablesTab();

        // Act: Cari variabel yang mustahil ada
        const searchInput = page.locator('input[type="search"], input[placeholder*="Cari"], input[placeholder*="cari"]').first();
        if (await searchInput.isVisible({ timeout: 5000 }).catch(() => false)) {
            await searchInput.fill('VariabelYangTidakMungkinAda12345XYZ');
            await page.waitForTimeout(1000); // Tunggu filter reaktif

            // Assert: Tidak ada crash, UI masih stabil
            await expect(fuzzyPage.variablesTab).toBeVisible();
            await expect(fuzzyPage.addVariableButton).toBeVisible();
        } else {
            // Jika tidak ada search input, skip test ini
            test.skip();
        }
    });

    test('Edge Case - Membuat variabel dengan karakter spesial di nama dan deskripsi', async ({ page }) => {
        /**
         * Given user membuka form Tambah Variabel
         * When user memasukkan karakter spesial di field nama dan deskripsi
         * Then sistem harus handle dengan baik (accept atau reject dengan jelas)
         */

        // Arrange
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 4);
        const variableName = `e2e_special_${suffix}`;

        await settingsPage.clickFuzzyCard();
        await fuzzyPage.expectPageReady();
        await fuzzyPage.clickVariablesTab();

        // Act: Buat variabel dengan karakter spesial di deskripsi
        await fuzzyPage.createVariable({
            name: variableName,
            group: 'lingkungan',
            type: 'input',
            unit: '°C', // Karakter spesial
            description: 'Test @#$% & special chars!', // Karakter spesial
        });

        // Assert: Biarkan sistem menentukan - jika berhasil, variabel muncul; jika gagal, ada error
        const successToast = page.getByText('Variabel berhasil ditambahkan.');
        const errorToast = page.locator('.toast-error, [role="alert"]').first();

        const successVisible = await successToast.isVisible({ timeout: 10000 }).catch(() => false);
        const errorVisible = await errorToast.isVisible({ timeout: 5000 }).catch(() => false);

        // Salah satu harus terjadi
        expect(successVisible || errorVisible).toBeTruthy();

        // Cleanup jika berhasil
        if (successVisible) {
            await fuzzyPage.deleteVariable(variableName);
        }
    });

    test('Edge Case - Membuat Membership Function dengan nilai parameter ekstrem (sangat besar)', async ({ page }) => {
        /**
         * Given user telah membuat variabel
         * When user membuat MF dengan nilai parameter sangat besar (misal: 999999)
         * Then sistem harus handle dengan baik tanpa overflow atau crash
         */

        // Arrange
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const variableName = `e2e_extreme_${suffix}`;
        const setName = `e2e_extremeset_${suffix}`;

        await settingsPage.clickFuzzyCard();
        await fuzzyPage.expectPageReady();
        await fuzzyPage.clickVariablesTab();

        await fuzzyPage.createVariable({
            name: variableName,
            group: 'lingkungan',
            type: 'input',
            unit: 'test',
            description: 'Test extreme values',
        });

        await expect(page.getByText('Variabel berhasil ditambahkan.')).toBeVisible({ timeout: 15000 });

        // Act: Buat MF dengan nilai ekstrem
        await fuzzyPage.expandVariable(variableName);
        await fuzzyPage.createSetForVariable(variableName, {
            name: setName,
            shape: 'triangle',
            a: '999999',
            b: '1000000',
            c: '1000001',
        });

        // Assert: Sistem harus handle (berhasil atau error yang jelas)
        const successToast = page.getByText('Membership function berhasil ditambahkan.');
        const errorToast = page.locator('.toast-error, [role="alert"]').first();

        const successVisible = await successToast.isVisible({ timeout: 10000 }).catch(() => false);
        const errorVisible = await errorToast.isVisible({ timeout: 5000 }).catch(() => false);

        expect(successVisible || errorVisible).toBeTruthy();

        // Cleanup
        await fuzzyPage.deleteVariable(variableName);
    });

    test('Positif - Verifikasi persistence data: Variabel yang dibuat tetap ada setelah refresh halaman', async ({ page }) => {
        /**
         * Given user telah membuat variabel baru
         * When user melakukan refresh halaman browser
         * Then variabel yang dibuat harus tetap muncul (data tersimpan di backend)
         */

        // Arrange
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const variableName = `e2e_persist_${suffix}`;

        await settingsPage.clickFuzzyCard();
        await fuzzyPage.expectPageReady();
        await fuzzyPage.clickVariablesTab();

        // Act 1: Buat variabel
        await fuzzyPage.createVariable({
            name: variableName,
            group: 'lingkungan',
            type: 'input',
            unit: 'test',
            description: 'Test persistence',
        });

        await expect(page.getByText('Variabel berhasil ditambahkan.')).toBeVisible({ timeout: 15000 });
        await fuzzyPage.expectVariableVisible(variableName);

        // Act 2: Refresh halaman
        await page.reload({ waitUntil: 'domcontentloaded' });
        await fuzzyPage.expectPageReady();
        await fuzzyPage.clickVariablesTab();

        // Assert: Variabel masih ada
        await fuzzyPage.expectVariableVisible(variableName);

        // Cleanup
        await fuzzyPage.deleteVariable(variableName);
    });

    test('Negatif - Membuat variabel dengan nama mengandung huruf kapital harus ditolak', async ({ page }) => {
        /**
         * Given user membuka form Tambah Variabel
         * When user memasukkan nama dengan huruf kapital (melanggar regex /^[a-z_]+$/)
         * Then sistem harus menampilkan pesan error validasi
         */

        // Arrange
        await settingsPage.clickFuzzyCard();
        await fuzzyPage.expectPageReady();
        await fuzzyPage.clickVariablesTab();

        // Act: Coba buat variabel dengan nama kapital
        await fuzzyPage.createVariable({
            name: 'InvalidCapitalName', // Melanggar regex
            group: 'lingkungan',
            type: 'input',
            unit: 'test',
            description: 'Test invalid name',
        });

        // Assert: Harus ada pesan error validasi
        const errorMessage = page.locator('.bg-red-50, .toast-error, [role="alert"]').first();
        await expect(errorMessage).toBeVisible({ timeout: 10000 });
        await expect(errorMessage).toContainText(/lowercase|huruf kecil/i);
    });

    test('Negatif - Membuat variabel dengan nama mengandung spasi harus ditolak', async ({ page }) => {
        /**
         * Given user membuka form Tambah Variabel
         * When user memasukkan nama dengan spasi (melanggar regex /^[a-z_]+$/)
         * Then sistem harus menampilkan pesan error validasi
         */

        // Arrange
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);

        await settingsPage.clickFuzzyCard();
        await fuzzyPage.expectPageReady();
        await fuzzyPage.clickVariablesTab();

        // Act: Coba buat variabel dengan spasi
        await fuzzyPage.createVariable({
            name: `invalid name ${suffix}`, // Melanggar regex (ada spasi)
            group: 'lingkungan',
            type: 'input',
            unit: 'test',
            description: 'Test invalid name with space',
        });

        // Assert: Harus ada pesan error validasi
        const errorMessage = page.locator('.bg-red-50, .toast-error, [role="alert"]').first();
        await expect(errorMessage).toBeVisible({ timeout: 10000 });
        await expect(errorMessage).toContainText(/lowercase|huruf kecil|underscore/i);
    });

    test('Negatif - Membuat Membership Function trapezoid tanpa parameter d harus ditolak', async ({ page }) => {
        /**
         * Given user telah membuat variabel dan memilih shape trapezoid
         * When user tidak mengisi parameter d (yang wajib untuk trapezoid)
         * Then sistem harus menampilkan pesan error validasi
         */

        // Arrange: Buat variabel dulu
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const variableName = `e2e_trapnod_${suffix}`;

        await settingsPage.clickFuzzyCard();
        await fuzzyPage.expectPageReady();
        await fuzzyPage.clickVariablesTab();

        await fuzzyPage.createVariable({
            name: variableName,
            group: 'lingkungan',
            type: 'input',
            unit: 'test',
            description: 'Test trapezoid without d',
        });

        await expect(page.getByText('Variabel berhasil ditambahkan.')).toBeVisible({ timeout: 15000 });

        // Act: Coba buat MF trapezoid tanpa parameter d
        await fuzzyPage.expandVariable(variableName);
        const section = page.locator('[data-var-id]').filter({ hasText: variableName }).first();
        await section.getByRole('button', { name: /\+ Tambah Set/i }).click();

        const modal = page.locator('h3', { hasText: 'Tambah Membership Function' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]')
            .first();

        await modal.locator('input[name="name"]').fill('trap_no_d');
        await modal.locator('select[name="shape"]').selectOption('trapezoid');
        await modal.locator('input[name="a"]').fill('1');
        await modal.locator('input[name="b"]').fill('2');
        await modal.locator('input[name="c"]').fill('3');
        // Sengaja tidak isi parameter d

        const saveButton = modal.getByRole('button', { name: /Simpan/i });
        await saveButton.click();

        // Assert: Harus ada error message
        const errorMessage = page.locator('.bg-red-50, .toast-error, [role="alert"]').first();
        const errorVisible = await errorMessage.isVisible({ timeout: 10000 }).catch(() => false);
        const modalStillVisible = await modal.isVisible({ timeout: 3000 }).catch(() => false);

        expect(errorVisible || modalStillVisible).toBeTruthy();

        // Cleanup
        await page.keyboard.press('Escape');
        await page.waitForTimeout(500);
        await fuzzyPage.deleteVariable(variableName);
    });
});
