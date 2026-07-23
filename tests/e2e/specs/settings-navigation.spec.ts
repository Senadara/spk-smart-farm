import { test, expect, Page } from '@playwright/test';
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

        // Arrange & Act (desain baru: kartu Data Master, IoT, dan section DSS Supplier)
        const dataMasterCard = settingsPage.dataMasterCard;
        const iotDevicesCard = settingsPage.iotDevicesCard;

        // Assert
        await expect(dataMasterCard).toBeVisible();
        await expect(iotDevicesCard).toBeVisible();
        await expect(settingsPage.dssSection).toBeVisible();

        // Verifikasi bahwa card navigasi adalah link yang dapat diklik
        await expect(dataMasterCard).toHaveAttribute('href');
        await expect(iotDevicesCard).toHaveAttribute('href');
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
        const response = await page.goto(invalidRoute, { waitUntil: 'domcontentloaded' });

        // Assert: status 404, ATAU tampil teks 404, ATAU dialihkan ke /settings
        const status = response?.status();
        const currentUrl = page.url();
        const is404Text = await page.locator('text=/404|not found|Not Found/i').first().isVisible({ timeout: 3000 }).catch(() => false);
        const isRedirectedToSettings = /\/settings\/?$/.test(currentUrl);

        expect(status === 404 || is404Text || isRedirectedToSettings).toBeTruthy();
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

        // Klik DSS "Atur Bobot" (menuju konfigurasi AHP)
        await settingsPage.dssAturBobot.click();
        await expect(page).toHaveURL(/.*\/spk-suppliers\/dss\/config/, { timeout: 10000 });

        // Assert: Tidak ada crash render
        const bodyContent = await page.locator('body').textContent();
        expect(bodyContent).not.toMatch(/Fatal render error/i);
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

    test('Positif - Kartu DSS Supplier AHP-SAW tampil di halaman Settings', async ({ page }) => {
        /**
         * Given user berada di halaman settings
         * When melihat section DSS supplier
         * Then judul kartu "DSS Supplier AHP-SAW" visible
         */

        await expect(settingsPage.dssSection).toBeVisible();
    });

    test('Positif - Kartu DSS Supplier menampilkan deskripsi bobot AHP & ranking SAW', async ({ page }) => {
        /**
         * Given DSS card rendered
         * When check card content
         * Then deskripsi menyebut bobot AHP, ranking SAW, dan bandingkan supplier
         */

        const bodyText = await page.locator('body').textContent();
        expect(bodyText).toMatch(/bobot AHP/i);
        expect(bodyText).toMatch(/ranking SAW/i);
        expect(bodyText).toMatch(/bandingkan supplier/i);
    });

    test('Positif - Kartu DSS Supplier memiliki 3 tombol aksi (Atur Bobot, Ranking SAW, Cari Barang)', async ({ page }) => {
        /**
         * Given DSS card displayed
         * When check action buttons
         * Then 3 tombol tautan visible menuju config/dashboard/products
         */

        await expect(settingsPage.dssAturBobot).toBeVisible();
        await expect(settingsPage.dssRankingSaw).toBeVisible();
        await expect(settingsPage.dssCariBarang).toBeVisible();
    });

    test('Positif - Tombol "Atur Bobot" navigate ke /spk-suppliers/dss/config', async ({ page }) => {
        await settingsPage.dssAturBobot.click();
        await expect(page).toHaveURL(/.*\/spk-suppliers\/dss\/config/, { timeout: 15000 });
    });

    test('Positif - Tombol "Ranking SAW" navigate ke /spk-suppliers/dss/dashboard', async ({ page }) => {
        await settingsPage.dssRankingSaw.click();
        await expect(page).toHaveURL(/.*\/spk-suppliers\/dss\/dashboard/, { timeout: 15000 });
    });

    test('Positif - Tombol "Cari Barang" navigate ke /spk-suppliers/products', async ({ page }) => {
        await settingsPage.dssCariBarang.click();
        await expect(page).toHaveURL(/.*\/spk-suppliers\/products/, { timeout: 15000 });
    });

    /* ═══════════════════════════════════════════════════════════════════
       CARD VISUAL & STYLING VALIDATION
       ═══════════════════════════════════════════════════════════════════ */
});


// ============================================================
// Uji Fungsional Mendalam - Health Scheduler (digabung dari func-misc.spec.ts, sebelumnya section 26.9)
// ============================================================

const PW_HS = 'Password123.';

async function capHS(page: Page, path: string) {
    try { await page.waitForLoadState('networkidle', { timeout: 10000 }); }
    catch { await page.waitForLoadState('domcontentloaded').catch(() => { }); }
    await page.waitForTimeout(500);
    await page.screenshot({ path: `qa-evidence/${path}`, fullPage: true });
}
async function bodyTextHS(page: Page): Promise<string> {
    return (await page.locator('body').innerText().catch(() => '')) || '';
}

test.describe('FUNC Health Scheduler / Penjadwal Kesehatan (pjawab)', () => {
    test.setTimeout(160000);
    test.beforeEach(async ({ page }) => {
        await page.route(/.*:5173.*/, (r) => r.abort());
        const auth = new AuthPage(page);
        await auth.loginAndWaitForDashboard('pjawab@email.com', PW_HS);
    });

    test('HSF001/002 - Health Scheduler simpan VALID lalu INVALID', async ({ page }) => {
        await page.goto('/settings/health-scheduler', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(800);

        // VALID
        await page.locator('textarea[name="schedule_times"]').fill('07:00, 12:30');
        await page.locator('select[name="days"]').selectOption('14');
        await page.locator('input[name="threshold_percent"]').fill('40');
        await page.locator('select[name="target_role"]').selectOption('petugas');
        await page.getByRole('button', { name: /Simpan Scheduler/i }).click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(1500);
        const bodyV = await bodyTextHS(page);
        const saved = /Scheduler indikasi kesehatan berhasil disimpan/i.test(bodyV);
        console.log('HSF001:: saved=' + saved);
        expect(saved).toBeTruthy();
        await capHS(page, 'MISC/HSF001_scheduler_valid.png');

        // INVALID (jam tidak valid)
        await page.goto('/settings/health-scheduler', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(600);
        await page.locator('textarea[name="schedule_times"]').fill('abc-bukan-jam');
        await page.getByRole('button', { name: /Simpan Scheduler/i }).click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(1500);
        const bodyI = await bodyTextHS(page);
        const rejected = /Isi minimal satu jam valid/i.test(bodyI);
        console.log('HSF002:: rejected=' + rejected);
        expect(rejected).toBeTruthy();
        await capHS(page, 'MISC/HSF002_scheduler_invalid.png');
    });

    test('HSF003 - Health Scheduler Jalankan Sekarang (best-effort node)', async ({ page }) => {
        await page.goto('/settings/health-scheduler', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(800);
        await page.getByRole('button', { name: /Jalankan Sekarang/i }).click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(2500);
        const body = await bodyTextHS(page);
        const ok = /Uji scheduler selesai/i.test(body);
        const err = /Gagal menjalankan scheduler/i.test(body);
        console.log('HSF003:: sukses=' + ok + ' gagalNode=' + err);
        await capHS(page, 'MISC/HSF003_scheduler_run.png');
        // best-effort: salah satu pesan harus muncul (sukses ATAU error node yang jujur)
        expect(ok || err).toBeTruthy();
    });
});
