import { test, expect } from '@playwright/test';
import { IotPage } from '../pages/IotPage.js';

test.describe('Modul IoT Config - CRUD Operations E2E', () => {
     let iotPage: IotPage;

     test.setTimeout(90000);

     test.beforeEach(async ({ page }) => {
          await page.route('**/:5173/**', route => route.abort());
          await page.route(/.*:5173.*/, route => route.abort());

          iotPage = new IotPage(page);
     });

     // ═══════════════════════════════════════════════════════════════
     // PROTOCOL CRUD TESTS
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Menambah Protocol baru dengan data sah', async ({ page }) => {
          /**
           * Given: Admin berada di halaman Config IoT tab Protokol
           * When: Admin mengisi form Tambah Protokol dengan data sah dan submit
           * Then: Protocol baru berhasil disimpan dan muncul di tabel
           */

          // Arrange
          await iotPage.gotoConfig();
          await iotPage.protocolsTab.click();
          await page.waitForTimeout(500);

          const protocolName = `E2E-PROTO-${Date.now()}`;
          const protocolDesc = 'Protocol untuk testing E2E CRUD';

          // Act
          await iotPage.createProtocol(protocolName, protocolDesc);

          // Assert
          await expect(page.getByRole('cell', { name: protocolName })).toBeVisible({ timeout: 8000 });
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 5000 });

          // Cleanup
          await iotPage.clickDeleteButtonInRow(protocolName);
          await expect(page.getByRole('cell', { name: protocolName })).toBeHidden({ timeout: 8000 });
     });

     test('Negatif - Menambah Protocol dengan nama duplikat', async ({ page }) => {
          /**
           * Given: Sudah ada protocol dengan nama tertentu
           * When: Admin mencoba membuat protocol baru dengan nama yang sama
           * Then: Sistem menampilkan kesalahan sahasi duplikat
           */

          // Arrange
          await iotPage.gotoConfig();
          const protocolName = `DUPLIKAT-PROTO-${Date.now()}`;
          await iotPage.createProtocol(protocolName, 'Protocol pertama');

          // Act - Coba buat lagi dengan nama sama
          await iotPage.protocolsTab.click();
          await page.waitForTimeout(500);

          const protocolsPanel = page.locator('div[x-show="activeTab === \'protocols\'"]');
          const addButton = protocolsPanel.getByRole('button', { name: /Tambah/i });
          await addButton.click({ force: true });
          await page.waitForTimeout(500);

          await iotPage.protocolNameInput.fill(protocolName);
          await iotPage.protocolDescriptionInput.fill('Protocol duplikat');
          await iotPage.protocolSubmitBtn.click();

          // Assert
          await expect(page.locator('text=/sudah ada|already ada|duplicate/i')).toBeVisible({ timeout: 5000 });

          // Cleanup
          await page.reload();
          await iotPage.clickDeleteButtonInRow(protocolName);
     });

     test('Positif - EDIT Protocol mengubah deskripsi', async ({ page }) => {
          /**
           * Given: Sudah ada protocol terdaftar
           * When: Admin mengklik tombol edit dan mengubah deskripsi
           * Then: Perubahan berhasil disimpan
           */

          // Arrange
          await iotPage.gotoConfig();
          const protocolName = `EDIT-PROTO-${Date.now()}`;
          await iotPage.createProtocol(protocolName, 'Deskripsi awal');

          // Act
          await iotPage.clickEditButtonInRow(protocolName);
          await page.waitForTimeout(500);

          const newDescription = 'Deskripsi telah diubah via E2E';
          await iotPage.protocolDescriptionInput.clear();
          await iotPage.protocolDescriptionInput.fill(newDescription);
          await iotPage.protocolSubmitBtn.click();

          // Assert
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 5000 });

          // Verify perubahan tersimpan
          const row = page.locator('tr').filter({ hasText: protocolName });
          await expect(row.locator('text=/Deskripsi telah diubah/i')).toBeVisible({ timeout: 5000 });

          // Cleanup
          await iotPage.clickDeleteButtonInRow(protocolName);
     });

     test('Positif - Menghapus Protocol yang tidak digunakan', async ({ page }) => {
          /**
           * Given: Ada protocol yang belum digunakan oleh koneksi
           * When: Admin mengklik tombol hapus dan konfirmasi
           * Then: Protocol berhasil dihapus dari database
           */

          // Arrange
          await iotPage.gotoConfig();
          const protocolName = `DELETE-PROTO-${Date.now()}`;
          await iotPage.createProtocol(protocolName, 'Protocol yang akan dihapus');

          // Act
          await iotPage.clickDeleteButtonInRow(protocolName);

          // Assert
          await expect(page.getByRole('cell', { name: protocolName })).toBeHidden({ timeout: 8000 });
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 5000 });
     });

     test('Negatif - Menghapus Protocol yang masih digunakan', async ({ page }) => {
          /**
           * Given: Ada protocol yang sudah digunakan oleh koneksi
           * When: Admin mencoba menghapus protocol tersebut
           * Then: Sistem menampilkan kesalahan karena masih ada relasi
           */

          // Arrange
          await iotPage.gotoConfig();
          const protocolName = `USED-PROTO-${Date.now()}`;
          await iotPage.createProtocol(protocolName, 'Protocol dengan koneksi');

          // Buat koneksi yang menggunakan protocol ini
          await iotPage.createConnectionConfig({
               protocolName,
               mqttBrokerUrl: `mqtt://broker-${Date.now()}.test:1883`,
               authType: 'none',
          });

          // Act - Coba hapus protocol
          await iotPage.protocolsTab.click();
          await page.waitForTimeout(500);
          await iotPage.clickDeleteButtonInRow(protocolName);

          // Assert
          await expect(page.locator('text=/masih digunakan|still used|in use/i')).toBeVisible({ timeout: 5000 });
     });

     // ═══════════════════════════════════════════════════════════════
     // CONNECTION CONFIG CRUD TESTS
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Menambah Connection dengan MQTT Broker', async ({ page }) => {
          /**
           * Given: Sudah ada protocol terdaftar
           * When: Admin membuat koneksi baru dengan MQTT Broker URL
           * Then: Koneksi berhasil disimpan
           */

          // Arrange
          await iotPage.gotoConfig();
          const protocolName = `MQTT-PROTO-${Date.now()}`;
          await iotPage.createProtocol(protocolName, 'MQTT Protocol');

          // Act
          const mqttBroker = `mqtts://broker-${Date.now()}.example.com:8883`;
          await iotPage.createConnectionConfig({
               protocolName,
               mqttBrokerUrl: mqttBroker,
               mqttTopic: 'sensors/farm/+',
               authType: 'api_key',
               authKey: 'test-api-key-12345',
          });

          // Assert
          await iotPage.connectionsTab.click();
          await page.waitForTimeout(500);
          await expect(page.locator(`text=${protocolName}`).first()).toBeVisible({ timeout: 8000 });
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 5000 });
     });

     test('Positif - Menambah Connection dengan HTTP Webhook', async ({ page }) => {
          /**
           * Given: Sudah ada protocol terdaftar
           * When: Admin membuat koneksi dengan Base URL dan Endpoint
           * Then: Koneksi berhasil disimpan
           */

          // Arrange
          await iotPage.gotoConfig();
          const protocolName = `HTTP-PROTO-${Date.now()}`;
          await iotPage.createProtocol(protocolName, 'HTTP Webhook');

          // Act
          await iotPage.createConnectionConfig({
               protocolName,
               baseUrl: 'https://api.smartfarm.test',
               endpointPath: '/webhook/sensor-data',
               authType: 'bearer',
               authKey: 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9',
          });

          // Assert
          await iotPage.connectionsTab.click();
          await page.waitForTimeout(500);
          await expect(page.locator(`text=${protocolName}`).first()).toBeVisible({ timeout: 8000 });
     });

     test('Negatif - Menambah Connection tanpa endpoint (kosong semua)', async ({ page }) => {
          /**
           * Given: Admin di form tambah koneksi
           * When: Submit tanpa mengisi Base URL maupun MQTT Broker
           * Then: Sistem menampilkan kesalahan sahasi
           */

          // Arrange
          await iotPage.gotoConfig();
          const protocolName = `EMPTY-PROTO-${Date.now()}`;
          await iotPage.createProtocol(protocolName, 'Protocol kosong');

          await iotPage.connectionsTab.click();
          await page.waitForTimeout(500);

          const connectionsPanel = page.locator('div[x-show="activeTab === \'connections\'"]');
          const addButton = connectionsPanel.getByRole('button', { name: /Tambah/i });
          await addButton.click({ force: true });
          await page.waitForTimeout(500);

          // Act - Submit tanpa isi endpoint
          await iotPage.connectionProtocolSelect.selectOption({ label: protocolName });
          await iotPage.authTypeSelect.selectOption('none');
          await iotPage.connectionSubmitBtn.click();

          // Assert
          await expect(page.locator('text=/minimal|harus/i')).toBeVisible({ timeout: 5000 });
     });

     test('Positif - EDIT Connection mengubah auth type', async ({ page }) => {
          /**
           * Given: Sudah ada koneksi dengan auth none
           * When: Admin edit dan ubah menjadi API Key
           * Then: Perubahan berhasil disimpan
           */

          // Arrange
          await iotPage.gotoConfig();
          const protocolName = `EDIT-CONN-PROTO-${Date.now()}`;
          const mqttBroker = `mqtt://edit-test-${Date.now()}.local:1883`;

          await iotPage.createProtocol(protocolName, 'Edit test');
          await iotPage.createConnectionConfig({
               protocolName,
               mqttBrokerUrl: mqttBroker,
               authType: 'none',
          });

          // Act
          await iotPage.connectionsTab.click();
          await page.waitForTimeout(500);
          await iotPage.clickEditButtonInRow(protocolName);
          await page.waitForTimeout(500);

          await iotPage.authTypeSelect.selectOption('api_key');
          await iotPage.authKeyInput.fill('new-api-key-9876');
          await iotPage.connectionSubmitBtn.click();

          // Assert
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 5000 });
     });

     test('Positif - Menghapus Connection yang tidak digunakan', async ({ page }) => {
          /**
           * Given: Ada koneksi yang belum digunakan device
           * When: Admin hapus koneksi
           * Then: Koneksi berhasil dihapus
           */

          // Arrange
          await iotPage.gotoConfig();
          const protocolName = `DELETE-CONN-${Date.now()}`;
          await iotPage.createProtocol(protocolName, 'Will be deleted');
          await iotPage.createConnectionConfig({
               protocolName,
               mqttBrokerUrl: `mqtt://temp-${Date.now()}.test`,
          });

          // Act
          await iotPage.connectionsTab.click();
          await page.waitForTimeout(500);
          await iotPage.clickDeleteButtonInRow(protocolName);

          // Assert
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 5000 });
     });

     // ═══════════════════════════════════════════════════════════════
     // PARAMETER CRUD TESTS
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Menambah Parameter Sensor baru', async ({ page }) => {
          /**
           * Given: Admin di tab Parameter Sensor
           * When: Tambah parameter dengan kode, nama, dan unit
           * Then: Parameter berhasil didaftarkan
           */

          // Arrange
          await iotPage.gotoConfig();
          const paramCode = `E2E_TEMP_${Date.now()}`;
          const paramName = 'Temperature E2E Test';

          // Act
          await iotPage.createParameter(paramCode, paramName, '°C', 'Temperature sensor parameter');

          // Assert
          await expect(page.getByRole('cell', { name: paramCode })).toBeVisible({ timeout: 8000 });
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 5000 });

          // Cleanup
          await iotPage.clickDeleteButtonInRow(paramCode);
     });

     test('Negatif - Menambah Parameter dengan kode duplikat', async ({ page }) => {
          /**
           * Given: Sudah ada parameter dengan kode tertentu
           * When: Coba buat parameter baru dengan kode sama
           * Then: Validasi kesalahan duplikat muncul
           */

          // Arrange
          await iotPage.gotoConfig();
          const paramCode = `DUPLIKAT_PARAM_${Date.now()}`;
          await iotPage.createParameter(paramCode, 'Parameter Pertama', 'unit1');

          // Act
          await iotPage.parametersTab.click();
          await page.waitForTimeout(500);

          const parametersPanel = page.locator('div[x-show="activeTab === \'parameters\'"]');
          const addButton = parametersPanel.getByRole('button', { name: /Tambah/i });
          await addButton.click({ force: true });
          await page.waitForTimeout(500);

          await iotPage.parameterCodeInput.fill(paramCode);
          await iotPage.parameterNameInput.fill('Parameter Duplikat');
          await iotPage.parameterSubmitBtn.click();

          // Assert
          await expect(page.locator('text=/sudah terdaftar|already ada|duplicate/i')).toBeVisible({ timeout: 5000 });

          // Cleanup
          await page.reload();
          await iotPage.parametersTab.click();
          await page.waitForTimeout(500);
          await iotPage.clickDeleteButtonInRow(paramCode);
     });

     test('Positif - EDIT Parameter mengubah unit dan nama', async ({ page }) => {
          /**
           * Given: Sudah ada parameter terdaftar
           * When: Edit parameter dan ubah nama serta unit
           * Then: Perubahan tersimpan
           */

          // Arrange
          await iotPage.gotoConfig();
          const paramCode = `EDIT_PARAM_${Date.now()}`;
          await iotPage.createParameter(paramCode, 'Nama Awal', 'unit1');

          // Act
          await iotPage.clickEditButtonInRow(paramCode);
          await page.waitForTimeout(500);

          await iotPage.parameterNameInput.clear();
          await iotPage.parameterNameInput.fill('Nama Sudah Diubah');
          await iotPage.parameterUnitInput.clear();
          await iotPage.parameterUnitInput.fill('%RH');
          await iotPage.parameterSubmitBtn.click();

          // Assert
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 5000 });
          const row = page.locator('tr').filter({ hasText: paramCode });
          await expect(row.locator('text=/Nama Sudah Diubah/i')).toBeVisible({ timeout: 5000 });

          // Cleanup
          await iotPage.clickDeleteButtonInRow(paramCode);
     });

     test('Positif - Menghapus Parameter yang tidak digunakan', async ({ page }) => {
          /**
           * Given: Ada parameter yang belum di-mapping
           * When: Admin hapus parameter
           * Then: Parameter berhasil dihapus
           */

          // Arrange
          await iotPage.gotoConfig();
          const paramCode = `DELETE_PARAM_${Date.now()}`;
          await iotPage.createParameter(paramCode, 'Will be deleted', 'unit');

          // Act
          await iotPage.clickDeleteButtonInRow(paramCode);

          // Assert
          await expect(page.getByRole('cell', { name: paramCode })).toBeHidden({ timeout: 8000 });
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 5000 });
     });

     // ═══════════════════════════════════════════════════════════════
     // COMMODITY PARAMETER CRUD TESTS
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Menambah Commodity Parameter dengan min max value', async ({ page }) => {
          /**
           * Given: Sudah ada parameter dan komoditas
           * When: Admin assign parameter ke komoditas dengan range nilai
           * Then: Relasi berhasil disimpan
           */

          // Arrange
          await iotPage.gotoConfig();
          const paramCode = `COMMOD_PARAM_${Date.now()}`;
          await iotPage.createParameter(paramCode, 'Temperature for Commodity', '°C');

          // Act - Asumsikan ada komoditas "Ayam Broiler" di database
          await iotPage.createCommodityParameter('Ayam Broiler', 'Temperature for Commodity', 20, 35);

          // Assert
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 8000 });

          // Note: Cleanup akan dilakukan manual atau via database seeder reset
     });

     test('Negatif - Menambah Commodity Parameter dengan min >= max', async ({ page }) => {
          /**
           * Given: Admin di form tambah commodity parameter
           * When: Input min value lebih besar atau sama dengan max
           * Then: Validasi kesalahan muncul
           */

          // Arrange
          await iotPage.gotoConfig();
          const paramCode = `INVALID_RANGE_${Date.now()}`;
          await iotPage.createParameter(paramCode, 'Insah Range Test', 'unit');

          await iotPage.commodityParamsTab.click();
          await page.waitForTimeout(500);

          const commodityPanel = page.locator('div[x-show="activeTab === \'commodityParams\'"]');
          const addButton = commodityPanel.getByRole('button', { name: /Tambah/i });
          await addButton.click({ force: true });
          await page.waitForTimeout(500);

          // Act
          await iotPage.commoditySelect.selectOption({ index: 1 });
          await iotPage.commodityParameterSelect.selectOption({ label: 'Insah Range Test' });
          await iotPage.minValueInput.fill('50');
          await iotPage.maxValueInput.fill('30'); // Max < Min
          await iotPage.commodityParamSubmitBtn.click();

          // Assert
          await expect(page.locator('text=/minimum harus lebih kecil|min.*max/i')).toBeVisible({ timeout: 5000 });
     });

     test('Negatif - Menambah Commodity Parameter duplikat kombinasi', async ({ page }) => {
          /**
           * Given: Sudah ada relasi commodity-parameter tertentu
           * When: Coba assign parameter yang sama ke komoditas yang sama
           * Then: Error duplikat muncul
           */

          // Arrange
          await iotPage.gotoConfig();
          const paramCode = `DUP_COMMOD_${Date.now()}`;
          await iotPage.createParameter(paramCode, 'Duplicate Test', 'ppm');

          await iotPage.createCommodityParameter('Ayam Broiler', 'Duplicate Test', 0, 100);

          // Act - Coba assign lagi
          await iotPage.commodityParamsTab.click();
          await page.waitForTimeout(500);

          const commodityPanel = page.locator('div[x-show="activeTab === \'commodityParams\'"]');
          const addButton = commodityPanel.getByRole('button', { name: /Tambah/i });
          await addButton.click({ force: true });
          await page.waitForTimeout(500);

          await iotPage.commoditySelect.selectOption({ label: 'Ayam Broiler' });
          await iotPage.commodityParameterSelect.selectOption({ label: 'Duplicate Test' });
          await iotPage.minValueInput.fill('10');
          await iotPage.maxValueInput.fill('90');
          await iotPage.commodityParamSubmitBtn.click();

          // Assert
          await expect(page.locator('text=/sudah ditambahkan|already added|duplicate/i')).toBeVisible({ timeout: 5000 });
     });

     test('Positif - EDIT Commodity Parameter mengubah range nilai', async ({ page }) => {
          /**
           * Given: Sudah ada relasi commodity-parameter
           * When: Edit dan ubah min/max value
           * Then: Perubahan tersimpan
           */

          // Arrange
          await iotPage.gotoConfig();
          const paramCode = `EDIT_COMMOD_${Date.now()}`;
          await iotPage.createParameter(paramCode, 'Editable Commodity Param', '%');
          await iotPage.createCommodityParameter('Ayam Broiler', 'Editable Commodity Param', 10, 50);

          // Act
          await iotPage.commodityParamsTab.click();
          await page.waitForTimeout(500);
          await iotPage.clickEditButtonInRow('Editable Commodity Param');
          await page.waitForTimeout(500);

          await iotPage.minValueInput.clear();
          await iotPage.minValueInput.fill('15');
          await iotPage.maxValueInput.clear();
          await iotPage.maxValueInput.fill('45');
          await iotPage.commodityParamSubmitBtn.click();

          // Assert
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 5000 });
     });

     test('Positif - Menghapus Commodity Parameter', async ({ page }) => {
          /**
           * Given: Ada relasi commodity-parameter
           * When: Admin hapus relasi
           * Then: Relasi berhasil dihapus
           */

          // Arrange
          await iotPage.gotoConfig();
          const paramCode = `DELETE_COMMOD_${Date.now()}`;
          await iotPage.createParameter(paramCode, 'To Be Deleted Commodity', 'unit');
          await iotPage.createCommodityParameter('Ayam Broiler', 'To Be Deleted Commodity', 0, 100);

          // Act
          await iotPage.commodityParamsTab.click();
          await page.waitForTimeout(500);
          await iotPage.clickDeleteButtonInRow('To Be Deleted Commodity');

          // Assert
          await expect(iotPage.toastSuccess).toBeVisible({ timeout: 5000 });
     });
});
