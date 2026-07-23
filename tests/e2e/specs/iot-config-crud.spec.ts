import { test, expect } from '@playwright/test';
import { IotPage } from '../pages/IotPage.js';

/**
 * IoT Setup — Koneksi, Parameter Sensor, Threshold Komoditas (redesign Nanda).
 *
 * Catatan realita implementasi:
 *  - Protokol bersifat FIXED (API/Antares + MQTT, di-seed). Tidak ada CRUD protokol via UI;
 *    endpoint storeProtocol/updateProtocol/destroyProtocol sengaja menolak. Test protokol lama dihapus.
 *  - Koneksi/Parameter/Threshold dibuat lewat MODAL, submit "Simpan".
 *  - Validasi server untuk koneksi/parameter/threshold TIDAK ditampilkan sebagai pesan di halaman
 *    (hanya device yang membuka ulang modal). Karena itu kasus negatif diverifikasi dari
 *    "record tidak bertambah/tersimpan", bukan dari pesan error. (Temuan UX: perlu feedback validasi.)
 */
test.describe('Modul IoT Setup - Koneksi/Parameter/Threshold CRUD E2E', () => {
     let iotPage: IotPage;

     test.setTimeout(90000);

     test.beforeEach(async ({ page }) => {
          iotPage = new IotPage(page);
     });

     // ─── Protokol (FIXED) ──────────────────────────────────────────
     test('Positif - Protokol pakem (MQTT & API/Antares) tersedia sebagai pilihan koneksi', async ({ page }) => {
          await iotPage.gotoSetup();
          await iotPage.mulaiSetupBtn.first().click({ force: true });
          const m = iotPage.modal('addConnection');
          await expect(m).toBeVisible({ timeout: 8000 });
          await expect(m.locator('button').filter({ hasText: /^\s*MQTT/ }).first()).toBeVisible();
          await expect(m.locator('button').filter({ hasText: /API \/ Antares/i }).first()).toBeVisible();
          // Tidak ada UI tambah protokol manual.
          await expect(page.getByRole('button', { name: /Tambah Protokol/i })).toHaveCount(0);
     });

     // ─── Koneksi ───────────────────────────────────────────────────
     test('Positif - CREATE Koneksi MQTT baru', async ({ page }) => {
          const broker = `mqtt://e2e-mqtt-${Date.now()}.local`;
          await iotPage.createConnection({ mode: 'MQTT', mqttBrokerUrl: broker });
          await expect(page.getByText(broker, { exact: false }).first()).toBeVisible();
          // Cleanup
          await iotPage.deleteConnectionByText(broker);
     });

     test('Positif - CREATE Koneksi API/Antares baru', async ({ page }) => {
          const base = `https://e2e-api-${Date.now()}.example.test`;
          await iotPage.createConnection({ mode: 'API', baseUrl: base, endpointPath: '/api/v2/sensors', authType: 'bearer', authKey: 'tok-123' });
          await expect(page.getByText(base, { exact: false }).first()).toBeVisible();
          // Cleanup
          await iotPage.deleteConnectionByText(base);
     });

     test('Negatif - CREATE Koneksi MQTT tanpa Broker URL tidak tersimpan', async ({ page }) => {
          await iotPage.gotoSetup();
          const before = await iotPage.connectionCount();

          await iotPage.openConnectionModal();
          const m = iotPage.modal('addConnection');
          await m.locator('button').filter({ hasText: /Laravel subscribe broker MQTT/i }).first().click();
          // Broker URL sengaja dikosongkan
          await iotPage.modalSubmit('addConnection').click({ force: true });
          await page.waitForLoadState('networkidle').catch(() => { });

          // Server menolak (MQTT wajib Broker URL) → jumlah koneksi tidak bertambah.
          const after = await iotPage.connectionCount();
          expect(after).toBe(before);
     });

     test('Positif - DELETE Koneksi yang belum dipakai device', async ({ page }) => {
          const broker = `mqtt://e2e-del-${Date.now()}.local`;
          await iotPage.createConnection({ mode: 'MQTT', mqttBrokerUrl: broker });
          await expect(page.getByText(broker, { exact: false }).first()).toBeVisible();

          await iotPage.deleteConnectionByText(broker);
          await expect(iotPage.toastSuccess.first()).toBeVisible({ timeout: 8000 });
          await expect(page.getByText(broker, { exact: false })).toHaveCount(0);
     });

     // ─── Parameter Sensor ──────────────────────────────────────────
     // Catatan: tabel "Parameter Sensor" pada halaman ini adalah daftar TERKURASI
     // (configuredIotParametersForCommodity) — parameter baru tersimpan tetapi belum tentu tampil
     // di tabel. Karena itu keberhasilan diverifikasi via toast sukses, bukan baris tabel.
     test('Positif - CREATE Parameter Sensor baru (toast sukses)', async () => {
          const code = `E2E_TEMP_${Date.now()}`;
          await iotPage.createParameter(code, 'Suhu E2E', 'C', 'Parameter suhu untuk E2E');
          await expect(iotPage.toastSuccess.first()).toBeVisible();
     });

     test('Negatif - CREATE Parameter dengan kode duplikat ditolak (tidak ada toast sukses)', async ({ page }) => {
          const code = `E2E_DUP_${Date.now()}`;
          await iotPage.createParameter(code, 'Param Pertama', 'x'); // sukses (toast)

          // Coba buat lagi dengan kode sama → ditolak (unique). Setelah reload, tidak ada flash sukses.
          await iotPage.openParameterModal();
          const m = iotPage.modal('addParameter');
          await m.locator('input[name="parameterCode"]').fill(code);
          await m.locator('input[name="parameterName"]').fill('Param Duplikat');
          await iotPage.submitModal('addParameter');

          await expect(iotPage.toastSuccess).toHaveCount(0);
     });

     // ─── Threshold Komoditas ───────────────────────────────────────
     // Struktur modal Threshold tersedia (tombol "Tambah Threshold" membuka modal berisi pilihan
     // Komoditas, Parameter, Min, Max). Namun dropdown Parameter memakai daftar TERKURASI
     // (configuredIotParametersForCommodity) yang pada state seed saat ini kosong, sehingga
     // pembuatan threshold end-to-end bergantung pada data konfigurasi komoditas yang belum tersedia.
     // Di sini hanya diverifikasi ketersediaan & struktur modalnya (tanpa data seed, create tak dapat diuji andal).
     test('Positif - Modal Tambah Threshold Komoditas tersedia dengan field lengkap', async ({ page }) => {
          await iotPage.gotoSetup();
          await iotPage.addThresholdBtn.first().click({ force: true });
          const m = iotPage.modal('addCommodityParam');
          await expect(m).toBeVisible({ timeout: 8000 });
          await expect(m.locator('select[name="commodityId"]')).toBeVisible();
          await expect(m.locator('select[name="parameterId"]')).toBeVisible();
          await expect(m.locator('input[name="minValue"]')).toBeVisible();
          await expect(m.locator('input[name="maxValue"]')).toBeVisible();
     });
});
