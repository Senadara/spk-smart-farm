import { test, expect } from '@playwright/test';
import { IotPage } from '../pages/IotPage.js';

/**
 * Smoke test modul IoT — disesuaikan dengan redesign "Setup IoT Kandang" (Nanda).
 * Protokol FIXED (API/Antares + MQTT), semua aksi via modal, submit "Simpan".
 */
test.describe('Modul IoT dan Monitoring - E2E Smoke Tests', () => {
    let iotPage: IotPage;

    test.setTimeout(90000);

    test.beforeEach(async ({ page }) => {
        iotPage = new IotPage(page);
    });

    test('Positif - Dashboard IoT (Monitoring Sensor) tampil dengan tautan Setup IoT', async () => {
        await iotPage.gotoDashboard();
        await iotPage.expectToBeOnDashboard();
        await expect(iotPage.setupIotLink.first()).toBeVisible();
    });

    test('Positif - Halaman Setup IoT Kandang menampilkan 3 langkah + konfigurasi lanjutan', async ({ page }) => {
        await iotPage.gotoSetup();
        await iotPage.expectToBeOnSetupPage();

        // 3 langkah alur setup
        await expect(page.getByText('Langkah 1 - Koneksi Tersedia')).toBeVisible();
        await expect(page.getByText('Langkah 2 - Device per Kandang')).toBeVisible();
        await expect(page.getByText('Langkah 3 - Mapping Payload')).toBeVisible();

        // Konfigurasi lanjutan (advanced-iot-config): Parameter Sensor & Threshold Sensor Ternak
        // (REDESIGN: heading "Threshold Komoditas" -> "Threshold Sensor Ternak"; keduanya read-only,
        //  dikelola dari Data Master)
        await expect(page.getByRole('heading', { name: 'Parameter Sensor' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Threshold Sensor Ternak' })).toBeVisible();

        // Tombol utama alur
        await expect(iotPage.mulaiSetupBtn.first()).toBeVisible();
    });

    test('Positif - Tombol "Mulai Setup" membuka modal Tambah Koneksi', async ({ page }) => {
        await iotPage.gotoSetup();
        await iotPage.mulaiSetupBtn.first().click({ force: true });

        const modal = iotPage.modal('addConnection');
        await expect(modal).toBeVisible({ timeout: 8000 });
        await expect(modal.getByText('Langkah 1: Tambah Koneksi IoT')).toBeVisible();
        // Dua kartu protokol pakem tersedia
        await expect(modal.locator('button').filter({ hasText: /API \/ Antares/i }).first()).toBeVisible();
        await expect(modal.locator('button').filter({ hasText: /Laravel subscribe broker MQTT/i }).first()).toBeVisible();
    });

    test('Negatif - Submit device tanpa Kode Device dicegah validasi HTML5', async ({ page }) => {
        // Prasyarat: minimal satu koneksi agar tombol Tambah Device aktif.
        await iotPage.createConnection({ mode: 'MQTT' });

        await iotPage.openDeviceModal();
        const m = iotPage.modal('addDevice');
        // Isi field lain kecuali deviceCode
        await m.locator('select[name="unitBudidayaId"]').selectOption({ index: 1 });
        await m.locator('select[name="connectionConfigId"]').selectOption({ index: 1 });
        await iotPage.modalSubmit('addDevice').click({ force: true });

        const codeInput = m.locator('input[name="deviceCode"]');
        const isInvalid = await codeInput.evaluate((node: HTMLInputElement) => !node.checkValidity());
        expect(isInvalid).toBeTruthy();
    });

    test('Positif - Registrasi device baru (koneksi -> device) tersimpan dan tampil di tabel', async ({ page }) => {
        // Arrange: buat koneksi lebih dulu.
        await iotPage.createConnection({ mode: 'MQTT' });

        const deviceCode = `TEST-DEV-${Date.now()}`;
        const deviceName = `Sensor E2E ${Date.now()}`;

        // Act
        await iotPage.createDevice(deviceCode, deviceName, 'active');

        // Assert
        await expect(page.getByRole('cell', { name: deviceCode })).toBeVisible({ timeout: 12000 });

        // Cleanup
        await iotPage.deleteRow(deviceCode);
    });
});
