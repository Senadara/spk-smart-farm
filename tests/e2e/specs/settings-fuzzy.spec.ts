import { test, expect, Page } from '@playwright/test';
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
        // Nama variabel wajib lowercase + underscore saja (pattern [a-z_]+) — tanpa angka.
        const variableName = `eqa_var_${suffix}`;
        const setName = `eqa_set_${suffix}`;

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
        await expect(page.getByText('Variabel berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });
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
        await expect(page.getByText('Membership function berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });
        await fuzzyPage.expectVariableVisible(variableName);

        // Act 3: Tab Switching Verify
        await fuzzyPage.clickRulesTab();
        await expect(page.getByRole('heading', { name: /Rule IF-THEN/i })).toBeVisible();

        await fuzzyPage.clickSourcesTab();
        await expect(page.getByRole('heading', { name: /Sumber Data/i }).first()).toBeVisible();

        // Act 4: Cleanup End of life Data / Penghancuran Variable
        await fuzzyPage.clickVariablesTab();
        await fuzzyPage.deleteVariable(variableName);

        // Assert Cleanup
        await expect(page.getByText(/berhasil dihapus/i).first()).toBeVisible({ timeout: 15000 });
    });

    test('Positif - Membuat Variabel dengan Membership Function Trapezoid (4 Parameter)', async ({ page }) => {
        /**
         * Given user berada di tab Variabel & MF
         * When user membuat variabel baru dengan membership function berbentuk trapezoid
         * Then sistem harus menerima 4 parameter (a, b, c, d) dan menyimpan dengan sukses
         */

        // Arrange
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const variableName = `eqa_trap_${suffix}`;
        const setName = `eqa_trapset_${suffix}`;

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
        await expect(page.getByText('Variabel berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });

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
        await expect(page.getByText('Membership function berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });

        // Cleanup
        await fuzzyPage.deleteVariable(variableName);
        await expect(page.getByText(/berhasil dihapus/i).first()).toBeVisible({ timeout: 15000 });
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
        await expect(page.getByRole('heading', { name: /Rule IF-THEN/i })).toBeVisible();
        await expect(fuzzyPage.addRuleButton).toBeVisible();

        // Act & Assert 3: Tab Sources
        await fuzzyPage.clickSourcesTab();
        await expect(page.getByRole('heading', { name: /Sumber Data/i }).first()).toBeVisible();

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
        const variableName = `eqa_invalid_${suffix}`;

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

        await expect(page.getByText('Variabel berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });

        // Act: Coba buat MF dengan parameter invalid
        await fuzzyPage.expandVariable(variableName);
        const section = page.locator('[data-var-id]').filter({ hasText: variableName }).first();
        await section.getByRole('button', { name: /Tambah Set/i }).click();

        const modal = page.locator('h3', { hasText: 'Tambah Himpunan Fuzzy' })
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
        // Tutup modal set dulu (Alpine tidak menutup via Escape) agar backdrop tidak memblok klik hapus.
        await modal.getByRole('button', { name: /Batal/i }).click().catch(() => { });
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
        const variableName = `eqa_dup_${suffix}`;

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

        await expect(page.getByText('Variabel berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });

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
        const variableName = `eqa_special_${suffix}`;

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
        const successToast = page.getByText('Variabel berhasil ditambahkan.').first();
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
        const variableName = `eqa_extreme_${suffix}`;
        const setName = `eqa_extremeset_${suffix}`;

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

        await expect(page.getByText('Variabel berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });

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
        const successToast = page.getByText('Membership function berhasil ditambahkan.').first();
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
        const variableName = `eqa_persist_${suffix}`;

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

        await expect(page.getByText('Variabel berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });
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

        // Act: isi nama kapital (melanggar pattern [a-z_]+). Tidak pakai helper createVariable
        // karena submit diblok HTML5 (modal tidak tertutup).
        await fuzzyPage.addVariableButton.click();
        const modal = page.locator('h3', { hasText: 'Tambah Variabel' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();
        await expect(modal).toBeVisible({ timeout: 10000 });
        await modal.locator('input[name="name"]').fill('InvalidCapitalName');
        await modal.locator('select[name="group"]').selectOption('lingkungan');
        await modal.locator('select[name="type"]').selectOption('input');
        await modal.locator('input[name="unit"]').fill('test');
        await modal.locator('input[name="description"]').fill('Test invalid name');
        await modal.getByRole('button', { name: /Simpan/i }).click();

        // Assert: pattern [a-z_]+ memblokir submit (client-side) → input invalid & modal tetap terbuka.
        // (Backend juga menolak dengan pesan "lowercase/huruf kecil" bila client-side dilewati.)
        const nameInput = modal.locator('input[name="name"]');
        const isInvalid = await nameInput.evaluate((el: HTMLInputElement) => !el.checkValidity()).catch(() => false);
        const modalStillVisible = await modal.isVisible({ timeout: 3000 }).catch(() => false);
        const backendError = await page.locator('.bg-red-50, .toast-error, [role="alert"]').first()
            .isVisible({ timeout: 2000 }).catch(() => false);
        expect(isInvalid || modalStillVisible || backendError).toBeTruthy();
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

        // Act: isi nama dengan spasi (melanggar pattern [a-z_]+). Submit diblok HTML5.
        await fuzzyPage.addVariableButton.click();
        const modal = page.locator('h3', { hasText: 'Tambah Variabel' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();
        await expect(modal).toBeVisible({ timeout: 10000 });
        await modal.locator('input[name="name"]').fill(`invalid name ${suffix}`);
        await modal.locator('select[name="group"]').selectOption('lingkungan');
        await modal.locator('select[name="type"]').selectOption('input');
        await modal.locator('input[name="unit"]').fill('test');
        await modal.locator('input[name="description"]').fill('Test invalid name with space');
        await modal.getByRole('button', { name: /Simpan/i }).click();

        // Assert: pattern [a-z_]+ memblokir submit (client-side) → input invalid & modal tetap terbuka.
        const nameInput = modal.locator('input[name="name"]');
        const isInvalid = await nameInput.evaluate((el: HTMLInputElement) => !el.checkValidity()).catch(() => false);
        const modalStillVisible = await modal.isVisible({ timeout: 3000 }).catch(() => false);
        const backendError = await page.locator('.bg-red-50, .toast-error, [role="alert"]').first()
            .isVisible({ timeout: 2000 }).catch(() => false);
        expect(isInvalid || modalStillVisible || backendError).toBeTruthy();
    });

    test('Negatif - Membuat Membership Function trapezoid tanpa parameter d harus ditolak', async ({ page }) => {
        /**
         * Given user telah membuat variabel dan memilih shape trapezoid
         * When user tidak mengisi parameter d (yang wajib untuk trapezoid)
         * Then sistem harus menampilkan pesan error validasi
         */

        // Arrange: Buat variabel dulu
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const variableName = `eqa_trapnod_${suffix}`;

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

        await expect(page.getByText('Variabel berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });

        // Act: Coba buat MF trapezoid tanpa parameter d
        await fuzzyPage.expandVariable(variableName);
        const section = page.locator('[data-var-id]').filter({ hasText: variableName }).first();
        await section.getByRole('button', { name: /Tambah Set/i }).click();

        const modal = page.locator('h3', { hasText: 'Tambah Himpunan Fuzzy' })
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

        // Cleanup — tutup modal set (Alpine tidak menutup via Escape) lalu hapus variabel
        await modal.getByRole('button', { name: /Batal/i }).click().catch(() => { });
        await page.waitForTimeout(500);
        await fuzzyPage.deleteVariable(variableName);
    });
});


// ============================================================
// Uji Fungsional Mendalam - digabung dari func-fuzzy.spec.ts (sebelumnya section 26.2 - Rule & Set)
// ============================================================

const PW = 'Password123.';

async function cap(page: Page, path: string) {
    try { await page.waitForLoadState('networkidle', { timeout: 10000 }); }
    catch { await page.waitForLoadState('domcontentloaded').catch(() => { }); }
    await page.waitForTimeout(500);
    await page.screenshot({ path: `qa-evidence/${path}`, fullPage: true });
}
function rid() { return Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 5) || 'abcde'; }

test.describe('FUNC Fuzzy - Konfigurasi Fuzzy (pjawab)', () => {
    let fuzzy: FuzzyConfigPage;
    test.setTimeout(180000);
    test.beforeEach(async ({ page }) => {
        await page.route('**/:5173/**', (r) => r.abort());
        await page.route(/.*:5173.*/, (r) => r.abort());
        const auth = new AuthPage(page);
        fuzzy = new FuzzyConfigPage(page);
        await auth.loginAndWaitForDashboard('pjawab@email.com', PW);
        await fuzzy.goto();
        await fuzzy.expectPageReady();
    });

    test('FUZVF001 - Edit Rule (ubah diagnosis) submit VALID -> tersimpan, lalu dikembalikan', async ({ page }) => {
        await fuzzy.clickRulesTab();
        await page.locator('button[title="Edit rule"]').first().click();
        const modal = page.locator('h3', { hasText: 'Edit Rule IF-THEN' }).locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();
        await expect(modal).toBeVisible({ timeout: 10000 });
        const diag = modal.locator('input[name="diagnosis"]');
        const original = await diag.inputValue();
        const baru = (original + ' [QA]').slice(0, 120);
        await diag.fill(baru);
        // BUG #21 (terdokumentasi): dropdown Set tiap kondisi TIDAK ter-preselect saat modal Edit dibuka,
        // sehingga menyimpan gagal kecuali set dipilih ulang. Replikasi workaround pengguna: pilih ulang set.
        const setSelects = modal.locator('select[name^="conditions"][name$="[set_id]"]');
        const setCount = await setSelects.count();
        for (let i = 0; i < setCount; i++) {
            await setSelects.nth(i).selectOption({ index: 1 }).catch(() => { });
        }
        await modal.getByRole('button', { name: /^Simpan$/i }).click();
        await page.waitForTimeout(1500);
        let body = await page.locator('body').innerText();
        const ok = /Rule berhasil diperbarui/i.test(body);
        console.log('FUZVF001:: editSukses=' + ok + ' original="' + original + '"');
        expect(ok).toBeTruthy();
        await cap(page, 'RULE/RULEF001_edit_rule_submit.png');
        // Revert ke diagnosis semula
        await fuzzy.clickRulesTab().catch(() => { });
        await page.locator('button[title="Edit rule"]').first().click();
        const modal2 = page.locator('h3', { hasText: 'Edit Rule IF-THEN' }).locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();
        await expect(modal2).toBeVisible({ timeout: 10000 });
        await modal2.locator('input[name="diagnosis"]').fill(original);
        await modal2.getByRole('button', { name: /^Simpan$/i }).click();
        await page.waitForTimeout(1200);
        body = await page.locator('body').innerText();
        console.log('FUZVF001_revert:: ok=' + /Rule berhasil diperbarui/i.test(body));
    });

    test('FUZVF002 - Edit Set/Membership submit VALID -> tersimpan', async ({ page }) => {
        const name = `qa_editset_${rid()}`;
        await fuzzy.clickVariablesTab();
        await fuzzy.createVariable({ name, group: 'lingkungan', type: 'input', unit: 'C', description: 'var edit set func' });
        await expect(page.getByText('Variabel berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });
        await fuzzy.expandVariable(name);
        await fuzzy.createSetForVariable(name, { name: 'qa_set1', shape: 'triangle', a: '10', b: '15', c: '20' });
        await expect(page.getByText('Membership function berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });
        await fuzzy.expandVariable(name);
        const section = page.locator('[data-var-id]').filter({ hasText: name }).first();
        const editBtn = section.locator('button[title="Edit set"]').first();
        await editBtn.waitFor({ state: 'visible', timeout: 15000 });
        await editBtn.click();
        const modal = page.locator('h3', { hasText: /Edit Himpunan Fuzzy/i }).locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();
        await expect(modal).toBeVisible({ timeout: 10000 });
        // ubah parameter c (tetap valid: a<=b<=c) 20 -> 22
        await modal.locator('input[name="c"]').fill('22');
        await modal.getByRole('button', { name: /^Simpan$/i }).click();
        await page.waitForTimeout(1500);
        const body = await page.locator('body').innerText();
        const ok = /Membership function berhasil diperbarui/i.test(body);
        console.log('FUZVF002:: editSetSukses=' + ok);
        expect(ok).toBeTruthy();
        await cap(page, 'FUZV/FUZVF002_edit_set_submit.png');
        await fuzzy.deleteVariable(name).catch(() => { });
    });

    test('FUZVF003 - Tambah Rule VALID (submit) lalu Hapus Rule (cleanup) + verifikasi', async ({ page }) => {
        await fuzzy.clickRulesTab();
        const diagText = 'QA rule func ' + rid();
        await page.getByRole('button', { name: /Tambah Rule/i }).first().click();
        const modal = page.locator('h3', { hasText: 'Tambah Rule IF-THEN' }).locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();
        await expect(modal).toBeVisible({ timeout: 10000 });
        await modal.locator('select[name="group"]').selectOption('lingkungan');
        await modal.locator('select[name="operator"]').selectOption('AND');
        await modal.locator('select[name="output_set_id"]').selectOption({ index: 1 });
        await modal.locator('input[name="diagnosis"]').fill(diagText);
        // Kondisi 0
        const var0 = modal.locator('select[name="conditions[0][variable_id]"]');
        await var0.selectOption({ index: 1 });
        await page.waitForTimeout(500);
        await modal.locator('select[name="conditions[0][set_id]"]').selectOption({ index: 1 });
        // Kondisi 1 (variabel berbeda)
        const var1 = modal.locator('select[name="conditions[1][variable_id]"]');
        await var1.selectOption({ index: 2 });
        await page.waitForTimeout(500);
        await modal.locator('select[name="conditions[1][set_id]"]').selectOption({ index: 1 });
        await modal.getByRole('button', { name: /Simpan Rule/i }).click();
        await page.waitForTimeout(1800);
        let body = await page.locator('body').innerText();
        const createOk = /Rule berhasil ditambahkan/i.test(body);
        const appears = body.includes(diagText);
        console.log('FUZVF003_create:: sukses=' + createOk + ' tampil=' + appears);
        await cap(page, 'RULE/RULEF003_tambah_rule_submit.png');
        expect(createOk).toBeTruthy();
        // Cleanup: cari baris rule dgn diagnosis tsb lalu hapus
        await fuzzy.clickRulesTab().catch(() => { });
        await page.locator('input[x-model="ruleSearch"]').fill(diagText).catch(() => { });
        await page.waitForTimeout(600);
        // .last() = elemen baris terdalam yang memuat diagText + tombol hapus (bukan kontainer besar)
        const ruleRow = page.locator('div,tr,li').filter({ hasText: diagText }).filter({ has: page.locator('button[title="Hapus rule"]') }).last();
        const delBtn = ruleRow.locator('button[title="Hapus rule"]').first();
        try {
            if (await delBtn.count() > 0) {
                page.once('dialog', (d) => d.accept());
                await delBtn.scrollIntoViewIfNeeded().catch(() => { });
                await delBtn.click({ timeout: 10000 });
                await page.waitForTimeout(1500);
                body = await page.locator('body').innerText();
                console.log('FUZVF003_delete:: hapusSukses=' + /Rule berhasil dihapus/i.test(body));
            } else {
                console.log('FUZVF003_delete:: tombol hapus rule qa tidak ditemukan (cleanup dilewati)');
            }
        } catch (e) {
            // Cleanup bersifat best-effort; kegagalan hapus tidak membatalkan hasil uji create.
            console.log('FUZVF003_delete:: cleanup best-effort gagal -> ' + (e as Error).message);
        }
    });
});

// ============================================================
// Diagnostik BUG #21 - digabung dari func-fuzzy-diag.spec.ts (PW dari blok di atas dipakai ulang)
// ============================================================

async function bodyTextDiag(page: Page): Promise<string> {
    return (await page.locator('body').innerText().catch(() => '')) || '';
}

test.describe('DIAG Fuzzy edit rule', () => {
    let fuzzy: FuzzyConfigPage;
    test.setTimeout(150000);
    test.beforeEach(async ({ page }) => {
        await page.route('**/:5173/**', (r) => r.abort());
        await page.route(/.*:5173.*/, (r) => r.abort());
        const auth = new AuthPage(page);
        fuzzy = new FuzzyConfigPage(page);
        await auth.loginAndWaitForDashboard('pjawab@email.com', PW);
        await fuzzy.goto();
        await fuzzy.expectPageReady();
        await fuzzy.clickRulesTab();
    });

    test('DIAG-A: buka edit rule, submit TANPA ubah apa pun', async ({ page }) => {
        await page.locator('button[title="Edit rule"]').first().click();
        const modal = page.locator('h3', { hasText: 'Edit Rule IF-THEN' }).locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();
        await modal.waitFor({ state: 'visible', timeout: 10000 });
        // baca nilai select kondisi sebelum submit
        const c0v = await modal.locator('select[name="conditions[0][variable_id]"]').inputValue().catch(() => 'N/A');
        const c0s = await modal.locator('select[name="conditions[0][set_id]"]').inputValue().catch(() => 'N/A');
        const outv = await modal.locator('select[name="output_set_id"]').inputValue().catch(() => 'N/A');
        console.log('DIAGA_PRE:: cond0_var=' + c0v + ' cond0_set=' + c0s + ' output=' + outv);
        await modal.getByRole('button', { name: /^Simpan$/i }).click();
        await page.waitForTimeout(1500);
        const body = await bodyTextDiag(page);
        const ok = /Rule berhasil diperbarui/i.test(body);
        const errKondisi = /kondisi wajib|Set kondisi|Variabel kondisi|minimal 2 kondisi|profile fuzzy yang sama|duplikat/i.test(body);
        console.log('DIAGA_POST:: sukses=' + ok + ' adaErrorKondisi=' + errKondisi);
        await page.screenshot({ path: 'qa-evidence/RULE/DIAG_A_edit_tanpa_ubah.png', fullPage: true });
    });

    test('DIAG-B: buka edit rule, RE-SELECT set tiap kondisi, lalu submit', async ({ page }) => {
        await page.locator('button[title="Edit rule"]').first().click();
        const modal = page.locator('h3', { hasText: 'Edit Rule IF-THEN' }).locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();
        await modal.waitFor({ state: 'visible', timeout: 10000 });
        // re-trigger x-model dengan memilih ulang variable lalu set pada tiap kondisi
        const condVars = modal.locator('select[name^="conditions"][name$="[variable_id]"]');
        const n = await condVars.count();
        console.log('DIAGB_COND_COUNT::' + n);
        for (let i = 0; i < n; i++) {
            const vSel = modal.locator(`select[name="conditions[${i}][variable_id]"]`);
            const curV = await vSel.inputValue().catch(() => '');
            if (curV) { await vSel.selectOption(curV).catch(() => { }); await page.waitForTimeout(300); }
            const sSel = modal.locator(`select[name="conditions[${i}][set_id]"]`);
            // pilih opsi set pertama yang non-empty
            await sSel.selectOption({ index: 1 }).catch(() => { });
        }
        await modal.getByRole('button', { name: /^Simpan$/i }).click();
        await page.waitForTimeout(1500);
        const body = await bodyTextDiag(page);
        const ok = /Rule berhasil diperbarui/i.test(body);
        console.log('DIAGB_POST:: sukses=' + ok);
        await page.screenshot({ path: 'qa-evidence/RULE/DIAG_B_edit_reselect.png', fullPage: true });
    });
});
