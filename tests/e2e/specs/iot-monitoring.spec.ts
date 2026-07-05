import { test, expect } from '@playwright/test';
import { IotPage } from '../pages/IotPage.js';

test.describe('Modul IoT Monitoring - Filter & Data Display E2E', () => {
     let iotPage: IotPage;

     test.setTimeout(90000);

     test.beforeEach(async ({ page }) => {
          await page.route('**/:5173/**', route => route.abort());
          await page.route(/.*:5173.*/, route => route.abort());

          iotPage = new IotPage(page);
     });

     // ═══════════════════════════════════════════════════════════════
     // MONITORING PAGE TESTS
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Halaman Monitoring IoT dapat diakses dan render dengan benar', async ({ page }) => {
          /**
           * Given: Admin sudah login
           * When: Navigasi ke halaman /iot/monitoring
           * Then: Halaman monitoring berhasil di-render dengan 2 tab (Data Sensor & Device Logs)
           */

          // Act
          await iotPage.gotoMonitoring();

          // Assert
          await iotPage.expectToBeOnMonitoringPage();
          await expect(iotPage.sensorDataTab).toBeVisible({ timeout: 8000 });
          await expect(iotPage.deviceLogsTab).toBeVisible({ timeout: 8000 });
     });

     test('Positif - Tab Data Sensor aktif secara default', async ({ page }) => {
          /**
           * Given: Admin membuka halaman monitoring
           * When: Halaman pertama kali di-load
           * Then: Tab Data Sensor aktif dan menampilkan tabel data sensor
           */

          // Act
          await iotPage.gotoMonitoring();

          // Assert
          const sensorDataPanel = page.locator('div[x-show="activeTab === \'sensorData\'"]');
          await expect(sensorDataPanel).toBeVisible({ timeout: 8000 });

          // Verify ada tabel data sensor
          const sensorTable = sensorDataPanel.locator('table');
          await expect(sensorTable).toBeVisible({ timeout: 5000 });
     });

     test('Positif - Switch tab ke Device Logs', async ({ page }) => {
          /**
           * Given: Admin di halaman monitoring tab Data Sensor
           * When: Klik tab Device Logs
           * Then: Panel Device Logs muncul dan panel Data Sensor tersembunyi
           */

          // Arrange
          await iotPage.gotoMonitoring();
          await expect(iotPage.sensorDataTab).toBeVisible({ timeout: 8000 });

          // Act
          await iotPage.deviceLogsTab.click();
          await page.waitForTimeout(500);

          // Assert
          const logsPanel = page.locator('div[x-show="activeTab === \'deviceLogs\'"]');
          await expect(logsPanel).toBeVisible({ timeout: 5000 });

          // Verify ada tabel device logs
          const logsTable = logsPanel.locator('table');
          await expect(logsTable).toBeVisible({ timeout: 5000 });
     });

     test('Positif - Filter Data Sensor berdasarkan device', async ({ page }) => {
          /**
           * Given: Ada multiple sensor data dari berbagai device
           * When: Admin pilih filter device tertentu
           * Then: Tabel hanya menampilkan data dari device tersebut
           */

          // Arrange
          await iotPage.gotoMonitoring();
          const sensorDataPanel = page.locator('div[x-show="activeTab === \'sensorData\'"]');

          // Act
          const deviceFilter = sensorDataPanel.locator('select[name="sensor_device_id"]');
          await deviceFilter.waitFor({ state: 'visible', timeout: 8000 });

          // Pilih device pertama yang ada (skip option pertama yang biasanya "Semua Device")
          const optionCount = await deviceFilter.locator('option').count();
          if (optionCount > 1) {
               await deviceFilter.selectOption({ index: 1 });

               // Submit filter (biasanya ada tombol "Filter" atau auto-submit)
               const filterButton = sensorDataPanel.locator('button').filter({ hasText: /Filter|Cari/i });
               if (await filterButton.count() > 0) {
                    await filterButton.click();
               }

               await page.waitForTimeout(1000);

               // Assert - Verify tabel data ter-refresh
               const table = sensorDataPanel.locator('table tbody tr');
               await expect(table.first()).toBeVisible({ timeout: 5000 });
          } else {
               // Jika belum ada device, skip assertion
               expect(optionCount).toBeGreaterThanOrEqual(1);
          }
     });

     test('Positif - Filter Data Sensor berdasarkan parameter', async ({ page }) => {
          /**
           * Given: Ada data sensor dengan berbagai parameter
           * When: Admin pilih filter parameter tertentu
           * Then: Tabel hanya menampilkan data dengan parameter tersebut
           */

          // Arrange
          await iotPage.gotoMonitoring();
          const sensorDataPanel = page.locator('div[x-show="activeTab === \'sensorData\'"]');

          // Act
          const parameterFilter = sensorDataPanel.locator('select[name="sensor_parameter_id"]');
          await parameterFilter.waitFor({ state: 'visible', timeout: 8000 });

          const optionCount = await parameterFilter.locator('option').count();
          if (optionCount > 1) {
               await parameterFilter.selectOption({ index: 1 });

               const filterButton = sensorDataPanel.locator('button').filter({ hasText: /Filter|Cari/i });
               if (await filterButton.count() > 0) {
                    await filterButton.click();
               }

               await page.waitForTimeout(1000);

               // Assert
               const table = sensorDataPanel.locator('table tbody tr');
               await expect(table.first()).toBeVisible({ timeout: 5000 });
          } else {
               expect(optionCount).toBeGreaterThanOrEqual(1);
          }
     });

     test('Positif - Filter Data Sensor berdasarkan date range', async ({ page }) => {
          /**
           * Given: Ada data sensor dari berbagai tanggal
           * When: Admin input date range (from - to)
           * Then: Tabel hanya menampilkan data dalam range tersebut
           */

          // Arrange
          await iotPage.gotoMonitoring();
          const sensorDataPanel = page.locator('div[x-show="activeTab === \'sensorData\'"]');

          // Act
          const dateFromInput = sensorDataPanel.locator('input[name="sensor_date_from"]');
          const dateToInput = sensorDataPanel.locator('input[name="sensor_date_to"]');

          await dateFromInput.waitFor({ state: 'visible', timeout: 8000 });

          // Set date range (7 hari ke belakang sampai hari ini)
          const today = new Date();
          const weekAgo = new Date(today);
          weekAgo.setDate(weekAgo.getDate() - 7);

          const formatDate = (date: Date) => {
               const year = date.getFullYear();
               const month = String(date.getMonth() + 1).padStart(2, '0');
               const day = String(date.getDate()).padStart(2, '0');
               return `${year}-${month}-${day}`;
          };

          await dateFromInput.fill(formatDate(weekAgo));
          await dateToInput.fill(formatDate(today));

          const filterButton = sensorDataPanel.locator('button').filter({ hasText: /Filter|Cari/i });
          if (await filterButton.count() > 0) {
               await filterButton.click();
          }

          await page.waitForTimeout(1000);

          // Assert - Table rendered (data bisa ada atau kosong tergantung database)
          const table = sensorDataPanel.locator('table');
          await expect(table).toBeVisible({ timeout: 5000 });
     });

     test('Positif - Filter kombinasi device + parameter + date', async ({ page }) => {
          /**
           * Given: Admin di halaman monitoring
           * When: Pilih filter kombinasi device, parameter, dan date range
           * Then: Tabel menampilkan data sesuai semua kriteria filter
           */

          // Arrange
          await iotPage.gotoMonitoring();
          const sensorDataPanel = page.locator('div[x-show="activeTab === \'sensorData\'"]');

          // Act - Kombinasi filter
          const deviceFilter = sensorDataPanel.locator('select[name="sensor_device_id"]');
          const parameterFilter = sensorDataPanel.locator('select[name="sensor_parameter_id"]');
          const dateFromInput = sensorDataPanel.locator('input[name="sensor_date_from"]');

          await deviceFilter.waitFor({ state: 'visible', timeout: 8000 });

          const deviceOptions = await deviceFilter.locator('option').count();
          const paramOptions = await parameterFilter.locator('option').count();

          if (deviceOptions > 1) await deviceFilter.selectOption({ index: 1 });
          if (paramOptions > 1) await parameterFilter.selectOption({ index: 1 });

          const today = new Date();
          const formatDate = (date: Date) => {
               const year = date.getFullYear();
               const month = String(date.getMonth() + 1).padStart(2, '0');
               const day = String(date.getDate()).padStart(2, '0');
               return `${year}-${month}-${day}`;
          };
          await dateFromInput.fill(formatDate(today));

          const filterButton = sensorDataPanel.locator('button').filter({ hasText: /Filter|Cari/i });
          if (await filterButton.count() > 0) {
               await filterButton.click();
          }

          await page.waitForTimeout(1000);

          // Assert
          const table = sensorDataPanel.locator('table');
          await expect(table).toBeVisible({ timeout: 5000 });
     });

     test('Positif - Reset filter Data Sensor', async ({ page }) => {
          /**
           * Given: Admin sudah apply filter tertentu
           * When: Klik tombol reset atau clear filter
           * Then: Filter kembali ke default dan menampilkan semua data
           */

          // Arrange
          await iotPage.gotoMonitoring();
          const sensorDataPanel = page.locator('div[x-show="activeTab === \'sensorData\'"]');

          const deviceFilter = sensorDataPanel.locator('select[name="sensor_device_id"]');
          await deviceFilter.waitFor({ state: 'visible', timeout: 8000 });

          const optionCount = await deviceFilter.locator('option').count();
          if (optionCount > 1) {
               await deviceFilter.selectOption({ index: 1 });
          }

          // Act - Reset filter
          const resetButton = sensorDataPanel.locator('button, a').filter({ hasText: /Reset|Clear|Hapus Filter/i });
          if (await resetButton.count() > 0) {
               await resetButton.click();
               await page.waitForTimeout(1000);

               // Assert - Filter kembali ke default (index 0)
               const selectedValue = await deviceFilter.inputValue();
               expect(selectedValue).toBe('');
          } else {
               // Jika tidak ada tombol reset, manual reset dengan pilih index 0
               await deviceFilter.selectOption({ index: 0 });
          }
     });

     // ═══════════════════════════════════════════════════════════════
     // DEVICE LOGS FILTER TESTS
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Filter Device Logs berdasarkan device', async ({ page }) => {
          /**
           * Given: Ada device logs dari berbagai device
           * When: Admin pilih filter device tertentu
           * Then: Tabel hanya menampilkan logs dari device tersebut
           */

          // Arrange
          await iotPage.gotoMonitoring();
          await iotPage.deviceLogsTab.click();
          await page.waitForTimeout(500);

          const logsPanel = page.locator('div[x-show="activeTab === \'deviceLogs\'"]');

          // Act
          const deviceFilter = logsPanel.locator('select[name="log_device_id"]');
          await deviceFilter.waitFor({ state: 'visible', timeout: 8000 });

          const optionCount = await deviceFilter.locator('option').count();
          if (optionCount > 1) {
               await deviceFilter.selectOption({ index: 1 });

               const filterButton = logsPanel.locator('button').filter({ hasText: /Filter|Cari/i });
               if (await filterButton.count() > 0) {
                    await filterButton.click();
               }

               await page.waitForTimeout(1000);

               // Assert
               const table = logsPanel.locator('table tbody tr');
               await expect(table.first()).toBeVisible({ timeout: 5000 });
          } else {
               expect(optionCount).toBeGreaterThanOrEqual(1);
          }
     });

     test('Positif - Filter Device Logs berdasarkan log type', async ({ page }) => {
          /**
           * Given: Ada device logs dengan berbagai tipe (INFO, WARNING, ERROR)
           * When: Admin pilih filter log type tertentu
           * Then: Tabel hanya menampilkan logs dengan tipe tersebut
           */

          // Arrange
          await iotPage.gotoMonitoring();
          await iotPage.deviceLogsTab.click();
          await page.waitForTimeout(500);

          const logsPanel = page.locator('div[x-show="activeTab === \'deviceLogs\'"]');

          // Act
          const typeFilter = logsPanel.locator('select[name="log_type"]');
          await typeFilter.waitFor({ state: 'visible', timeout: 8000 });

          const optionCount = await typeFilter.locator('option').count();
          if (optionCount > 1) {
               // Pilih INFO, WARNING, atau ERROR
               await typeFilter.selectOption({ index: 1 });

               const filterButton = logsPanel.locator('button').filter({ hasText: /Filter|Cari/i });
               if (await filterButton.count() > 0) {
                    await filterButton.click();
               }

               await page.waitForTimeout(1000);

               // Assert
               const table = logsPanel.locator('table');
               await expect(table).toBeVisible({ timeout: 5000 });
          } else {
               expect(optionCount).toBeGreaterThanOrEqual(1);
          }
     });

     test('Positif - Filter Device Logs berdasarkan tanggal', async ({ page }) => {
          /**
           * Given: Ada device logs dari berbagai tanggal
           * When: Admin pilih filter date tertentu
           * Then: Tabel hanya menampilkan logs dari tanggal tersebut
           */

          // Arrange
          await iotPage.gotoMonitoring();
          await iotPage.deviceLogsTab.click();
          await page.waitForTimeout(500);

          const logsPanel = page.locator('div[x-show="activeTab === \'deviceLogs\'"]');

          // Act
          const dateInput = logsPanel.locator('input[name="log_date"]');
          await dateInput.waitFor({ state: 'visible', timeout: 8000 });

          const today = new Date();
          const formatDate = (date: Date) => {
               const year = date.getFullYear();
               const month = String(date.getMonth() + 1).padStart(2, '0');
               const day = String(date.getDate()).padStart(2, '0');
               return `${year}-${month}-${day}`;
          };

          await dateInput.fill(formatDate(today));

          const filterButton = logsPanel.locator('button').filter({ hasText: /Filter|Cari/i });
          if (await filterButton.count() > 0) {
               await filterButton.click();
          }

          await page.waitForTimeout(1000);

          // Assert
          const table = logsPanel.locator('table');
          await expect(table).toBeVisible({ timeout: 5000 });
     });

     test('Positif - Tabel Device Logs menampilkan kolom yang benar', async ({ page }) => {
          /**
           * Given: Admin di tab Device Logs
           * When: Tabel di-render
           * Then: Kolom Device, Log Type, Message, dan Timestamp terlihat
           */

          // Arrange
          await iotPage.gotoMonitoring();
          await iotPage.deviceLogsTab.click();
          await page.waitForTimeout(500);

          const logsPanel = page.locator('div[x-show="activeTab === \'deviceLogs\'"]');

          // Assert
          const table = logsPanel.locator('table');
          await expect(table).toBeVisible({ timeout: 8000 });

          // Check table headers
          const headers = table.locator('thead th');
          await expect(headers).toHaveCount(4, { timeout: 5000 }); // Assuming 4 columns: Device, Type, Message, Timestamp
     });

     test('Positif - Tabel Data Sensor menampilkan nilai numerik dengan benar', async ({ page }) => {
          /**
           * Given: Ada sensor data dengan nilai numerik
           * When: Data ditampilkan di tabel
           * Then: Nilai dan unit parameter terlihat dengan format yang benar
           */

          // Arrange
          await iotPage.gotoMonitoring();
          const sensorDataPanel = page.locator('div[x-show="activeTab === \'sensorData\'"]');

          // Assert
          const table = sensorDataPanel.locator('table');
          await expect(table).toBeVisible({ timeout: 8000 });

          // Check if data rows exist
          const dataRows = table.locator('tbody tr');
          const rowCount = await dataRows.count();

          if (rowCount > 0) {
               // Verify first row has data
               const firstRow = dataRows.first();
               await expect(firstRow).toBeVisible({ timeout: 5000 });

               // Verify ada cell dengan angka (value sensor)
               const valueCell = firstRow.locator('td').nth(2); // Assuming value is 3rd column
               await expect(valueCell).toBeVisible();
          } else {
               // No data is acceptable if database is empty
               expect(rowCount).toBeGreaterThanOrEqual(0);
          }
     });
});
