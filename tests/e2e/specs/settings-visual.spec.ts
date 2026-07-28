import { test, expect } from "@playwright/test";
import { SettingsPage } from "../pages/SettingsPage.js";

/**
 * Modul Settings - Visual & Konten.
 *
 * Desain halaman /settings sekarang sederhana: kartu teks (tanpa ikon berwarna/h3/gradient/col-span).
 * Kartu: Data Master, IoT (Setup IoT), Aturan SPK Kandang (pjawab), Scheduler Indikasi Kesehatan,
 * dan section DSS Supplier AHP-SAW (Atur Bobot / Ranking SAW / Cari Barang).
 */
test.describe("Modul Settings - Visual & Konten", () => {
    let settingsPage: SettingsPage;

    test.beforeEach(async ({ page }) => {
        settingsPage = new SettingsPage(page);
        await settingsPage.goto();
    });

    test('Positif - Judul halaman "Daftar Pengaturan" & heading "Pengaturan Sistem"', async ({ page }) => {
        expect(await page.title()).toContain('Daftar Pengaturan');
        await expect(page.getByRole('heading', { name: /Pengaturan Sistem/i })).toBeVisible();
    });

    test('Positif - Kartu Data Master tampil sebagai link dengan deskripsi relevan', async ({ page }) => {
        await expect(settingsPage.dataMasterCard).toBeVisible();
        await expect(settingsPage.dataMasterCard).toHaveAttribute('href', /\/data-master/);
        const bodyText = await page.locator('body').textContent();
        expect(bodyText).toContain('Data Master');
        expect(bodyText).toMatch(/komoditas|kandang|jenis budidaya/i);
    });

    test('Positif - Kartu IoT (Setup IoT) tampil sebagai link ke /iot/devices', async ({ page }) => {
        await expect(settingsPage.iotCard).toBeVisible();
        await expect(settingsPage.iotCard).toHaveAttribute('href', /\/iot\/devices/);
        const bodyText = await page.locator('body').textContent();
        expect(bodyText).toMatch(/Buka Setup IoT/i);
        expect(bodyText).toMatch(/koneksi|device|mapping|parameter sensor/i);
    });

    test('Positif - Kartu Aturan SPK Kandang (role pjawab) tampil dengan deskripsi fuzzy', async ({ page }) => {
        const visible = await settingsPage.fuzzyCard.isVisible({ timeout: 5000 }).catch(() => false);
        if (!visible) {
            test.skip(true, 'Kartu Aturan SPK hanya untuk role pjawab.');
            return;
        }
        await expect(settingsPage.fuzzyCard).toHaveAttribute('href', /\/settings\/fuzzy/);
        const bodyText = await page.locator('body').textContent();
        expect(bodyText).toMatch(/variabel|himpunan fuzzy|rule Mamdani/i);
    });

    test('Positif - Section DSS Supplier AHP-SAW dengan 3 tombol aksi', async ({ page }) => {
        await expect(settingsPage.dssSection).toBeVisible();
        await expect(settingsPage.dssAturBobot).toBeVisible();
        await expect(settingsPage.dssRankingSaw).toBeVisible();
        await expect(settingsPage.dssCariBarang).toBeVisible();
    });

    test('Positif - Halaman memakai layout grid responsif', async ({ page }) => {
        const gridContainer = page.locator('div.grid').filter({ has: settingsPage.dataMasterCard });
        await expect(gridContainer.first()).toBeVisible();
        const containerHtml = await gridContainer.first().evaluate(el => el.className);
        expect(containerHtml).toMatch(/grid-cols-1|md:grid-cols-2/);
    });

    test('Positif - Kartu navigasi utama adalah elemen <a> dengan href', async () => {
        await expect(settingsPage.dataMasterCard).toHaveAttribute('href');
        await expect(settingsPage.iotCard).toHaveAttribute('href');
    });

    test('Positif - Subtitle menyebut konfigurasi data/IoT/SPK/supplier', async ({ page }) => {
        const bodyText = await page.locator('body').textContent();
        expect(bodyText).toMatch(/data dasar|IoT|aturan SPK|supplier/i);
    });

    test('Performance - Halaman Settings dimuat < 3 detik', async ({ page }) => {
        const start = Date.now();
        await page.goto('/settings', { waitUntil: 'domcontentloaded' });
        await settingsPage.expectPageTitleVisible();
        await settingsPage.expectAllCardsVisible();
        expect(Date.now() - start).toBeLessThan(3000);
    });
});
