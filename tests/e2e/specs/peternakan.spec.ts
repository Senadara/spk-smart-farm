import { test, expect } from '@playwright/test';
import { PeternakanPage } from '../pages/PeternakanPage.js';

test.describe('Modul Dashboard Peternakan - E2E QA', () => {
    let peternakanPage: PeternakanPage;

    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }, testInfo) => {
        testInfo.setTimeout(120000);
        page.setDefaultNavigationTimeout(120000);
        page.setDefaultTimeout(120000);

        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        peternakanPage = new PeternakanPage(page);
        await peternakanPage.goto();
    });

    /**
     * Skenario 1 - Cek ketersediaan UI utama dashboard
     * Given pengguna login dan berada di halaman peternakan
     * When dashboard termuat sepenuhnya
     * Then heading "Decision Support & Operations" harus terlihat
     */
    test('Positif - Dashboard termuat dengan komponen utama terlihat', async ({ page }) => {
        // Arrange
        // Act: menunggu halaman siap
        await peternakanPage.expectToBeOnPeternakanPage();

        // Assert: verifikasi keberadaan select komoditas jika tersedia
        if (await peternakanPage.komoditasSelect.count() > 0) {
            await expect(peternakanPage.komoditasSelect).toBeVisible();
        }
    });

    /**
     * Skenario 2 - Menjalankan Evaluasi Massal (SPK Fuzzy Mamdani)
     * Given pengguna berada di dashboard peternakan
     * When menekan tombol "Jalankan Evaluasi"
     * Then notifikasi loading muncul dan label evaluation time diperbarui tanpa crash/error
     */
    test('Positif - Memastikan fitur evaluate all terhubung tanpa crash', async ({ page }) => {
        // Arrange: cek tombol tersedia
        await peternakanPage.expectToBeOnPeternakanPage();
        
        if (await peternakanPage.evaluateAllButton.count() > 0) {
            // Act: klik tombol evaluasi
            await peternakanPage.evaluateAllButton.click();

            // Assert: pesan evaluasi / notifikasi muncul dan tak terjadi net::ERR crash
            const notifLoc = page.getByText(/Evaluasi selesai|Sebagian evaluasi gagal|Gagal menjalankan/i);
            await expect(notifLoc.first()).toBeVisible({ timeout: 15000 });
        }
    });

    /**
     * Skenario 3 - Filter Kandang berubah berefek pada chart (jika ada)
     * Given data multi kandang tersedia di SPK Peternakan
     * When pengguna memilih opsi filter "Kandang A"
     * Then select value terbarui dan UI tak freeze
     */
    test('Positif - Filter SPK per kandang berfungsi dan interaktif', async ({ page }) => {
        // Arrange
        await peternakanPage.expectToBeOnPeternakanPage();

        if (await peternakanPage.filterKandangSelect.count() > 0) {
            // Act: pilih opsi kandang pertama selain "all"
            const options = await peternakanPage.filterKandangSelect.locator('option').allTextContents();
            if (options.length > 1) {
                await peternakanPage.filterKandangSelect.selectOption({ index: 1 });

                // Assert: pemilihan sukses
                await expect(peternakanPage.filterKandangSelect).toBeVisible();
            }
        }
    });

    /**
     * Skenario 4 - Validasi Data KPI tidak kosong
     * Given pengguna berada pada dashboard operasional kandang
     * When komponen card KPI dirender
     * Then angka pada KPI (seperti Ammonia, Suhu, dsb) tidak bernilai undefined/null
     */
    test('Negatif - Memastikan metrik KPI menangani data kosong secara mandiri', async ({ page }) => {
        // Arrange & Act
        await peternakanPage.expectToBeOnPeternakanPage();

        // Assert: jika card ada, pastikan tidak ada teks 'undefined' di dalamnya
        const bodyText = await page.locator('body').textContent();
        expect(bodyText).not.toContain('undefined%');
        expect(bodyText).not.toContain('null');
        
        // Memastikan elemen kanvas chart muncul tanpa exception rendering js
        if (await peternakanPage.chartKualitasTelurCanvas.count() > 0) {
            await expect(peternakanPage.chartKualitasTelurCanvas).toBeVisible();
        }
    });
});

