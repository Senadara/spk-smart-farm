import { test, expect } from '@playwright/test';
import { IotPage } from '../pages/IotPage.js';

async function setupDevicePrerequisites(iotPage: IotPage) {
     const protocolName = `DEV-PROTO-${Date.now()}`;
     const mqttBroker = `mqtt://device-test-${Date.now()}.local:1883`;

     await iotPage.createProtocol(protocolName, 'Protocol for device testing');
     await iotPage.createConnectionConfig({
          protocolName,
          mqttBrokerUrl: mqttBroker,
          authType: 'none',
     });

     return {
          protocolName,
          mqttBroker,
          connectionLabel: `${protocolName} — ${mqttBroker}`,
     };
}

test.describe('Modul IoT Device Management - CRUD Operations E2E', () => {
     let iotPage: IotPage;

     test.setTimeout(90000);

     test.beforeEach(async ({ page }) => {
          await page.route('**/:5173/**', route => route.abort());
          await page.route(/.*:5173.*/, route => route.abort());

          iotPage = new IotPage(page);
     });

     // ═══════════════════════════════════════════════════════════════
     // DEVICE CRUD TESTS
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Menambah Device dengan semua field sah', async ({ page }) => {
          /**
           * Given: Ada protocol dan connection config yang siap
           * When: Admin mendaftarkan device baru dengan data lengkap
           * Then: Device berhasil disimpan dan muncul di tabel
           */

          // Arrange
          const { connectionLabel } = await setupDevicePrerequisites(iotPage);
          await iotPage.gotoDevices();
          await iotPage.addDeviceBtn.click();
          await expect(iotPage.deviceCodeInput.first()).toBeVisible({ timeout: 10000 });

          const deviceCode = `E2E-DEVICE-${Date.now()}`;
          const deviceName = `Smart Sensor ${Date.now()}`;

          // Act
          await iotPage.deviceCodeInput.first().fill(deviceCode);
          await iotPage.deviceNameInput.first().fill(deviceName);
          await iotPage.unitBudidayaSelect.first().selectOption({ index: 1 });
          await iotPage.connectionConfigSelect.first().selectOption({ label: connectionLabel });
          await iotPage.statusSelect.first().selectOption('active');
          await iotPage.deviceSubmitBtn.click();

          // Assert
          await expect(page.getByRole('cell', { name: deviceCode })).toBeVisible({ timeout: 15000 });
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 });

          // Cleanup
          const row = page.locator('tr').filter({ hasText: deviceCode });
          const deleteBtn = row.locator('form').filter({ hasText: /hapus|delete/i }).locator('button');

          if (await deleteBtn.count() > 0) {
               page.once('dialog', dialog => dialog.accept());
               await deleteBtn.first().click();
               await expect(page.getByRole('cell', { name: deviceCode })).toBeHidden({ timeout: 10000 });
          }
     });

     test('Negatif - Menambah Device dengan kode duplikat', async ({ page }) => {
          /**
           * Given: Sudah ada device dengan kode tertentu
           * When: Coba daftarkan device baru dengan kode yang sama
           * Then: Error sahasi duplikat muncul
           */

          // Arrange
          const { connectionLabel } = await setupDevicePrerequisites(iotPage);
          await iotPage.gotoDevices();

          const deviceCode = `DUPLIKAT-DEVICE-${Date.now()}`;

          // Buat device pertama
          await iotPage.addDeviceBtn.click();
          await expect(iotPage.deviceCodeInput.first()).toBeVisible({ timeout: 10000 });
          await iotPage.deviceCodeInput.first().fill(deviceCode);
          await iotPage.deviceNameInput.first().fill('Device Pertama');
          await iotPage.unitBudidayaSelect.first().selectOption({ index: 1 });
          await iotPage.connectionConfigSelect.first().selectOption({ label: connectionLabel });
          await iotPage.statusSelect.first().selectOption('active');
          await iotPage.deviceSubmitBtn.click();
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 });

          // Act - Coba buat device kedua dengan kode sama
          await iotPage.addDeviceBtn.click();
          await expect(iotPage.deviceCodeInput.first()).toBeVisible({ timeout: 10000 });
          await iotPage.deviceCodeInput.first().fill(deviceCode);
          await iotPage.deviceNameInput.first().fill('Device Duplikat');
          await iotPage.unitBudidayaSelect.first().selectOption({ index: 1 });
          await iotPage.connectionConfigSelect.first().selectOption({ label: connectionLabel });
          await iotPage.statusSelect.first().selectOption('active');
          await iotPage.deviceSubmitBtn.click();

          // Assert
          await expect(page.locator('text=/sudah terdaftar|already registered|duplicate/i')).toBeVisible({ timeout: 5000 });

          // Cleanup
          await page.reload();
          await iotPage.clickDeleteButtonInRow(deviceCode);
     });

     test('Negatif - Menambah Device tanpa deviceCode (mandatory field)', async ({ page }) => {
          /**
           * Given: Admin di form tambah device
           * When: Submit tanpa mengisi kode device
           * Then: HTML5 sahation mencegah submit
           */

          // Arrange
          const { connectionLabel } = await setupDevicePrerequisites(iotPage);
          await iotPage.gotoDevices();
          await iotPage.addDeviceBtn.click();
          await expect(iotPage.deviceCodeInput.first()).toBeVisible({ timeout: 10000 });

          // Act - Isi semua field kecuali deviceCode
          await iotPage.deviceNameInput.first().fill('Device Tanpa Kode');
          await iotPage.unitBudidayaSelect.first().selectOption({ index: 1 });
          await iotPage.connectionConfigSelect.first().selectOption({ label: connectionLabel });
          await iotPage.statusSelect.first().selectOption('active');
          await iotPage.deviceSubmitBtn.click();

          // Assert
          const codeInput = iotPage.deviceCodeInput.first();
          const isInsah = await codeInput.evaluate((node: HTMLInputElement) => !node.checkValidity());
          expect(isInsah).toBeTruthy();
     });

     test('Positif - EDIT Device mengubah nama dan status', async ({ page }) => {
          /**
           * Given: Sudah ada device terdaftar
           * When: Admin edit device dan ubah nama serta status
           * Then: Perubahan berhasil disimpan
           */

          // Arrange
          const { connectionLabel } = await setupDevicePrerequisites(iotPage);
          await iotPage.gotoDevices();

          const deviceCode = `EDIT-DEVICE-${Date.now()}`;
          const originalName = 'Nama Awal Device';

          await iotPage.addDeviceBtn.click();
          await expect(iotPage.deviceCodeInput.first()).toBeVisible({ timeout: 10000 });
          await iotPage.deviceCodeInput.first().fill(deviceCode);
          await iotPage.deviceNameInput.first().fill(originalName);
          await iotPage.unitBudidayaSelect.first().selectOption({ index: 1 });
          await iotPage.connectionConfigSelect.first().selectOption({ label: connectionLabel });
          await iotPage.statusSelect.first().selectOption('active');
          await iotPage.deviceSubmitBtn.click();
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 });

          // Act - Edit device
          await iotPage.clickEditButtonInRow(deviceCode);
          await page.waitForTimeout(500);

          const newName = 'Nama Sudah Diubah';
          await iotPage.deviceNameInput.first().clear();
          await iotPage.deviceNameInput.first().fill(newName);
          await iotPage.statusSelect.first().selectOption('maintenance');
          await iotPage.deviceSubmitBtn.click();

          // Assert
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 });
          const row = page.locator('tr').filter({ hasText: deviceCode });
          await expect(row.locator('text=/Nama Sudah Diubah/i')).toBeVisible({ timeout: 5000 });
          await expect(row.locator('text=/maintenance/i')).toBeVisible({ timeout: 5000 });

          // Cleanup
          await iotPage.clickDeleteButtonInRow(deviceCode);
     });

     test('Positif - Menghapus Device yang tidak memiliki sensor data', async ({ page }) => {
          /**
           * Given: Ada device yang belum memiliki data sensor
           * When: Admin hapus device
           * Then: Device berhasil dihapus
           */

          // Arrange
          const { connectionLabel } = await setupDevicePrerequisites(iotPage);
          await iotPage.gotoDevices();

          const deviceCode = `DELETE-DEVICE-${Date.now()}`;

          await iotPage.addDeviceBtn.click();
          await expect(iotPage.deviceCodeInput.first()).toBeVisible({ timeout: 10000 });
          await iotPage.deviceCodeInput.first().fill(deviceCode);
          await iotPage.deviceNameInput.first().fill('Device Will Be Deleted');
          await iotPage.unitBudidayaSelect.first().selectOption({ index: 1 });
          await iotPage.connectionConfigSelect.first().selectOption({ label: connectionLabel });
          await iotPage.statusSelect.first().selectOption('inactive');
          await iotPage.deviceSubmitBtn.click();
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 });

          // Act
          await iotPage.clickDeleteButtonInRow(deviceCode);

          // Assert
          await expect(page.getByRole('cell', { name: deviceCode })).toBeHidden({ timeout: 10000 });
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 5000 });
     });

     test('Positif - Device status inactive dapat diubah menjadi active', async ({ page }) => {
          /**
           * Given: Ada device dengan status inactive
           * When: Admin edit dan ubah status ke active
           * Then: Status berhasil diupdate
           */

          // Arrange
          const { connectionLabel } = await setupDevicePrerequisites(iotPage);
          await iotPage.gotoDevices();

          const deviceCode = `STATUS-DEVICE-${Date.now()}`;

          await iotPage.addDeviceBtn.click();
          await expect(iotPage.deviceCodeInput.first()).toBeVisible({ timeout: 10000 });
          await iotPage.deviceCodeInput.first().fill(deviceCode);
          await iotPage.deviceNameInput.first().fill('Device Status Test');
          await iotPage.unitBudidayaSelect.first().selectOption({ index: 1 });
          await iotPage.connectionConfigSelect.first().selectOption({ label: connectionLabel });
          await iotPage.statusSelect.first().selectOption('inactive');
          await iotPage.deviceSubmitBtn.click();
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 });

          // Act
          await iotPage.clickEditButtonInRow(deviceCode);
          await page.waitForTimeout(500);
          await iotPage.statusSelect.first().selectOption('active');
          await iotPage.deviceSubmitBtn.click();

          // Assert
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 });
          const row = page.locator('tr').filter({ hasText: deviceCode });
          await expect(row.locator('text=/active/i')).toBeVisible({ timeout: 5000 });

          // Cleanup
          await iotPage.clickDeleteButtonInRow(deviceCode);
     });

     // ═══════════════════════════════════════════════════════════════
     // PARAMETER MAPPING CRUD TESTS
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Menambah Parameter Mapping untuk device', async ({ page }) => {
          /**
           * Given: Sudah ada device dan parameter terdaftar
           * When: Admin membuat mapping antara device dan parameter
           * Then: Mapping berhasil disimpan
           */

          // Arrange
          const { connectionLabel } = await setupDevicePrerequisites(iotPage);

          // Buat parameter
          await iotPage.gotoConfig();
          const paramCode = `MAP_PARAM_${Date.now()}`;
          await iotPage.createParameter(paramCode, 'Mappable Parameter', '°C');

          // Buat device
          await iotPage.gotoDevices();
          const deviceCode = `MAP-DEVICE-${Date.now()}`;

          await iotPage.addDeviceBtn.click();
          await expect(iotPage.deviceCodeInput.first()).toBeVisible({ timeout: 10000 });
          await iotPage.deviceCodeInput.first().fill(deviceCode);
          await iotPage.deviceNameInput.first().fill('Device for Mapping');
          await iotPage.unitBudidayaSelect.first().selectOption({ index: 1 });
          await iotPage.connectionConfigSelect.first().selectOption({ label: connectionLabel });
          await iotPage.statusSelect.first().selectOption('active');
          await iotPage.deviceSubmitBtn.click();
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 });

          // Act - Buat mapping
          await iotPage.addMappingBtn.click();
          await page.waitForTimeout(500);

          const deviceSelect = page.locator('select[name="deviceId"]').first();
          const parameterSelect = page.locator('select[name="parameterId"]').first();
          const payloadKeyInput = page.locator('input[name="payloadKey"]').first();
          const mappingSubmitBtn = page.getByRole('button', { name: /Simpan Mapping/i }).first();

          await deviceSelect.selectOption({ label: deviceCode });
          await parameterSelect.selectOption({ label: 'Mappable Parameter' });
          await payloadKeyInput.fill('temperature');
          await mappingSubmitBtn.click();

          // Assert
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 });

          // Verify mapping muncul di tabel
          const mappingRow = page.locator('tr').filter({ hasText: deviceCode }).filter({ hasText: 'Mappable Parameter' });
          await expect(mappingRow).toBeVisible({ timeout: 5000 });

          // Cleanup
          await iotPage.clickDeleteButtonInRow(deviceCode);
     });

     test('Negatif - Menambah Mapping duplikat device-parameter', async ({ page }) => {
          /**
           * Given: Sudah ada mapping device-parameter tertentu
           * When: Coba buat mapping lagi dengan kombinasi yang sama
           * Then: Error duplikat muncul
           */

          // Arrange
          const { connectionLabel } = await setupDevicePrerequisites(iotPage);

          await iotPage.gotoConfig();
          const paramCode = `DUP_MAP_${Date.now()}`;
          await iotPage.createParameter(paramCode, 'Duplicate Mapping Test', 'unit');

          await iotPage.gotoDevices();
          const deviceCode = `DUP-MAP-DEVICE-${Date.now()}`;

          await iotPage.addDeviceBtn.click();
          await expect(iotPage.deviceCodeInput.first()).toBeVisible({ timeout: 10000 });
          await iotPage.deviceCodeInput.first().fill(deviceCode);
          await iotPage.deviceNameInput.first().fill('Duplicate Mapping Device');
          await iotPage.unitBudidayaSelect.first().selectOption({ index: 1 });
          await iotPage.connectionConfigSelect.first().selectOption({ label: connectionLabel });
          await iotPage.statusSelect.first().selectOption('active');
          await iotPage.deviceSubmitBtn.click();
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 });

          // Buat mapping pertama
          await iotPage.addMappingBtn.click();
          await page.waitForTimeout(500);

          const deviceSelect = page.locator('select[name="deviceId"]').first();
          const parameterSelect = page.locator('select[name="parameterId"]').first();
          const payloadKeyInput = page.locator('input[name="payloadKey"]').first();
          const mappingSubmitBtn = page.getByRole('button', { name: /Simpan Mapping/i }).first();

          await deviceSelect.selectOption({ label: deviceCode });
          await parameterSelect.selectOption({ label: 'Duplicate Mapping Test' });
          await payloadKeyInput.fill('key1');
          await mappingSubmitBtn.click();
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 });

          // Act - Coba buat mapping kedua dengan kombinasi sama
          await iotPage.addMappingBtn.click();
          await page.waitForTimeout(500);

          await deviceSelect.selectOption({ label: deviceCode });
          await parameterSelect.selectOption({ label: 'Duplicate Mapping Test' });
          await payloadKeyInput.fill('key2');
          await mappingSubmitBtn.click();

          // Assert
          await expect(page.locator('text=/sudah di-mapping|already mapped|duplicate/i')).toBeVisible({ timeout: 5000 });

          // Cleanup
          await page.reload();
          await iotPage.clickDeleteButtonInRow(deviceCode);
     });

     test('Positif - EDIT Mapping mengubah payload key', async ({ page }) => {
          /**
           * Given: Sudah ada mapping
           * When: Edit mapping dan ubah payload key
           * Then: Perubahan tersimpan
           */

          // Arrange
          const { connectionLabel } = await setupDevicePrerequisites(iotPage);

          await iotPage.gotoConfig();
          const paramCode = `EDIT_MAP_${Date.now()}`;
          await iotPage.createParameter(paramCode, 'Edit Mapping Test', 'unit');

          await iotPage.gotoDevices();
          const deviceCode = `EDIT-MAP-DEVICE-${Date.now()}`;

          await iotPage.addDeviceBtn.click();
          await expect(iotPage.deviceCodeInput.first()).toBeVisible({ timeout: 10000 });
          await iotPage.deviceCodeInput.first().fill(deviceCode);
          await iotPage.deviceNameInput.first().fill('Edit Mapping Device');
          await iotPage.unitBudidayaSelect.first().selectOption({ index: 1 });
          await iotPage.connectionConfigSelect.first().selectOption({ label: connectionLabel });
          await iotPage.statusSelect.first().selectOption('active');
          await iotPage.deviceSubmitBtn.click();
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 });

          // Buat mapping
          await iotPage.addMappingBtn.click();
          await page.waitForTimeout(500);

          const deviceSelect = page.locator('select[name="deviceId"]').first();
          const parameterSelect = page.locator('select[name="parameterId"]').first();
          const payloadKeyInput = page.locator('input[name="payloadKey"]').first();
          const mappingSubmitBtn = page.getByRole('button', { name: /Simpan Mapping/i }).first();

          await deviceSelect.selectOption({ label: deviceCode });
          await parameterSelect.selectOption({ label: 'Edit Mapping Test' });
          await payloadKeyInput.fill('originalKey');
          await mappingSubmitBtn.click();
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 });

          // Act - Edit mapping
          const mappingRow = page.locator('tr').filter({ hasText: deviceCode }).filter({ hasText: 'Edit Mapping Test' });
          const editBtn = mappingRow.locator('button, a').filter({ hasText: /edit|ubah/i }).first();
          await editBtn.click();
          await page.waitForTimeout(500);

          await payloadKeyInput.clear();
          await payloadKeyInput.fill('updatedKey');
          await mappingSubmitBtn.click();

          // Assert
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 });

          // Cleanup
          await iotPage.clickDeleteButtonInRow(deviceCode);
     });

     test('Positif - Menghapus Mapping', async ({ page }) => {
          /**
           * Given: Ada mapping terdaftar
           * When: Admin hapus mapping
           * Then: Mapping berhasil dihapus
           */

          // Arrange
          const { connectionLabel } = await setupDevicePrerequisites(iotPage);

          await iotPage.gotoConfig();
          const paramCode = `DEL_MAP_${Date.now()}`;
          await iotPage.createParameter(paramCode, 'Delete Mapping Test', 'unit');

          await iotPage.gotoDevices();
          const deviceCode = `DEL-MAP-DEVICE-${Date.now()}`;

          await iotPage.addDeviceBtn.click();
          await expect(iotPage.deviceCodeInput.first()).toBeVisible({ timeout: 10000 });
          await iotPage.deviceCodeInput.first().fill(deviceCode);
          await iotPage.deviceNameInput.first().fill('Delete Mapping Device');
          await iotPage.unitBudidayaSelect.first().selectOption({ index: 1 });
          await iotPage.connectionConfigSelect.first().selectOption({ label: connectionLabel });
          await iotPage.statusSelect.first().selectOption('active');
          await iotPage.deviceSubmitBtn.click();
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 });

          // Buat mapping
          await iotPage.addMappingBtn.click();
          await page.waitForTimeout(500);

          const deviceSelect = page.locator('select[name="deviceId"]').first();
          const parameterSelect = page.locator('select[name="parameterId"]').first();
          const payloadKeyInput = page.locator('input[name="payloadKey"]').first();
          const mappingSubmitBtn = page.getByRole('button', { name: /Simpan Mapping/i }).first();

          await deviceSelect.selectOption({ label: deviceCode });
          await parameterSelect.selectOption({ label: 'Delete Mapping Test' });
          await payloadKeyInput.fill('willBeDeleted');
          await mappingSubmitBtn.click();
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 });

          // Act - Hapus mapping
          const mappingRow = page.locator('tr').filter({ hasText: deviceCode }).filter({ hasText: 'Delete Mapping Test' });
          const deleteBtn = mappingRow.locator('form').filter({ hasText: /hapus|delete/i }).locator('button').first();

          page.once('dialog', dialog => dialog.accept());
          await deleteBtn.click();

          // Assert
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 });
          await expect(mappingRow).toBeHidden({ timeout: 5000 });

          // Cleanup device
          await iotPage.clickDeleteButtonInRow(deviceCode);
     });
});
