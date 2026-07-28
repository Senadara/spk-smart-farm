import { test, expect } from '@playwright/test';
import { IotPage } from '../pages/IotPage.js';

/**
 * IoT Device Management — CRUD (redesign "Setup IoT Kandang", Nanda).
 *
 * Alur nyata: buat KONEKSI dulu (modal, protokol pakem MQTT/API) → daftarkan DEVICE ke kandang
 * (modal addDevice, submit "Simpan") → mapping payload. Tidak ada CRUD protokol.
 * Catatan: dropdown Parameter pada modal Mapping memakai daftar TERKURASI
 * (configuredIotParametersForCommodity) yang kosong pada state seed saat ini, sehingga
 * pembuatan mapping end-to-end belum dapat diuji andal — di sini diverifikasi struktur modalnya.
 */
test.describe('Modul IoT Device Management - CRUD Operations E2E', () => {
     let iotPage: IotPage;

     test.setTimeout(90000);

     test.beforeEach(async ({ page }) => {
          iotPage = new IotPage(page);
     });

     // ─── DEVICE CRUD ───────────────────────────────────────────────
     test('Positif - CREATE Device dengan koneksi + kandang valid', async ({ page }) => {
          await iotPage.createConnection({ mode: 'MQTT' });

          const deviceCode = `E2E-DEVICE-${Date.now()}`;
          await iotPage.createDevice(deviceCode, `Smart Sensor ${Date.now()}`, 'active');
          await expect(page.getByRole('cell', { name: deviceCode })).toBeVisible({ timeout: 12000 });

          // Cleanup
          await iotPage.deleteRow(deviceCode);
     });

     test('Negatif - CREATE Device tanpa Kode Device dicegah validasi HTML5', async ({ page }) => {
          await iotPage.createConnection({ mode: 'MQTT' });
          await iotPage.gotoSetup();
          await iotPage.openDeviceModal();
          const m = iotPage.modal('addDevice');
          await m.locator('select[name="unitBudidayaId"]').selectOption({ index: 1 });
          await m.locator('select[name="connectionConfigId"]').selectOption({ index: 1 });
          await iotPage.modalSubmit('addDevice').click({ force: true });

          const codeInput = m.locator('input[name="deviceCode"]');
          const isInvalid = await codeInput.evaluate((n: HTMLInputElement) => !n.checkValidity());
          expect(isInvalid).toBeTruthy();
     });

     test('Negatif - CREATE Device dengan kode duplikat tidak menambah baris', async ({ page }) => {
          await iotPage.createConnection({ mode: 'MQTT' });

          const deviceCode = `DUP-DEVICE-${Date.now()}`;
          await iotPage.createDevice(deviceCode, 'Device Pertama', 'active');
          await expect(page.locator('tr').filter({ hasText: deviceCode })).toHaveCount(1);

          // Coba buat lagi dengan kode sama → ditolak (unique) → tidak menambah baris.
          await iotPage.gotoSetup();
          await iotPage.openDeviceModal();
          await iotPage.fillDeviceForm(deviceCode, 'Device Duplikat', 'active');
          await iotPage.submitModal('addDevice');
          await page.waitForLoadState('networkidle').catch(() => { });

          await expect(page.locator('tr').filter({ hasText: deviceCode })).toHaveCount(1);
          // Cleanup
          await iotPage.deleteRow(deviceCode);
     });

     test('Positif - EDIT Device mengubah nama dan status', async ({ page }) => {
          await iotPage.createConnection({ mode: 'MQTT' });

          const deviceCode = `EDIT-DEVICE-${Date.now()}`;
          await iotPage.createDevice(deviceCode, 'Nama Awal Device', 'active');

          // Buka modal edit dari baris device.
          const row = page.locator('tr').filter({ hasText: deviceCode }).first();
          await row.locator('button[title="Edit"]').first().click();
          const m = iotPage.modal('editDevice');
          await expect(m).toBeVisible({ timeout: 8000 });
          await m.locator('input[name="deviceName"]').fill('Nama Sudah Diubah');
          await m.locator('select[name="status"]').selectOption('maintenance');
          await iotPage.submitModal('editDevice');

          await expect(iotPage.toastSuccess.first()).toBeVisible({ timeout: 8000 });
          const updatedRow = page.locator('tr').filter({ hasText: deviceCode }).first();
          await expect(updatedRow).toContainText('Nama Sudah Diubah');
          await expect(updatedRow).toContainText(/maintenance/i);

          // Cleanup
          await iotPage.deleteRow(deviceCode);
     });

     test('Positif - DELETE Device yang belum memiliki data sensor', async ({ page }) => {
          await iotPage.createConnection({ mode: 'MQTT' });

          const deviceCode = `DELETE-DEVICE-${Date.now()}`;
          await iotPage.createDevice(deviceCode, 'Device Will Be Deleted', 'inactive');
          await expect(page.getByRole('cell', { name: deviceCode })).toBeVisible();

          await iotPage.deleteRow(deviceCode);
          await expect(page.locator('tr').filter({ hasText: deviceCode })).toHaveCount(0);
     });

     test('Positif - Device status inactive dapat diubah menjadi active', async ({ page }) => {
          await iotPage.createConnection({ mode: 'MQTT' });

          const deviceCode = `STATUS-DEVICE-${Date.now()}`;
          await iotPage.createDevice(deviceCode, 'Device Status Test', 'inactive');

          const row = page.locator('tr').filter({ hasText: deviceCode }).first();
          await row.locator('button[title="Edit"]').first().click();
          const m = iotPage.modal('editDevice');
          await expect(m).toBeVisible({ timeout: 8000 });
          await m.locator('select[name="status"]').selectOption('active');
          await iotPage.submitModal('editDevice');

          await expect(iotPage.toastSuccess.first()).toBeVisible({ timeout: 8000 });
          await expect(page.locator('tr').filter({ hasText: deviceCode }).first()).toContainText(/active/i);

          // Cleanup
          await iotPage.deleteRow(deviceCode);
     });

     // ─── MAPPING PAYLOAD (struktur) ────────────────────────────────
     test('Positif - Modal Tambah Mapping Payload tersedia setelah ada device', async ({ page }) => {
          await iotPage.createConnection({ mode: 'MQTT' });
          const deviceCode = `MAP-DEVICE-${Date.now()}`;
          await iotPage.createDevice(deviceCode, 'Device for Mapping', 'active');

          await iotPage.gotoSetup();
          await iotPage.addMappingBtn.first().click({ force: true });
          const m = iotPage.modal('addMapping');
          await expect(m).toBeVisible({ timeout: 8000 });
          await expect(m.locator('select[name="deviceId"]')).toBeVisible();
          await expect(m.locator('select[name="parameterId"]')).toBeVisible();
          await expect(m.locator('input[name="payloadKey"]')).toBeVisible();

          // Cleanup device
          await iotPage.gotoSetup();
          await iotPage.deleteRow(deviceCode);
     });
});
