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

     // ─── Parameter Sensor (REDESIGN: read-only di Setup IoT, CRUD pindah ke Data Master) ──────
     // Realita implementasi terbaru: section "Parameter Sensor" pada /iot/devices kini hanya KATALOG
     // read-only "dari Data Master yang dipakai pada mapping payload device", dengan tautan
     // "Kelola di Data Master" (-> /data-master?tab=sensor-parameters). Tidak ada lagi tombol/modal
     // Tambah Parameter di halaman IoT. CRUD parameter sensor (create/edit/hapus + boundary)
     // diuji pada modul Data Master (Section 23), bukan di sini.
     test('Positif - Parameter Sensor di Setup IoT bersifat read-only (katalog) + tautan "Kelola di Data Master"', async ({ page }) => {
          await iotPage.gotoSetup();
          await expect(page.getByRole('heading', { name: 'Parameter Sensor' })).toBeVisible({ timeout: 10000 });
          const kelola = page.getByRole('link', { name: /Kelola di Data Master/i }).first();
          await expect(kelola).toBeVisible();
          const href = await kelola.getAttribute('href');
          expect(href).toMatch(/data-master/);
          expect(href).toMatch(/sensor-parameters/);
          // Tidak ada lagi tombol Tambah Parameter di halaman IoT (dipindah ke Data Master)
          await expect(page.getByRole('button', { name: /^\s*Tambah Parameter\s*$/i })).toHaveCount(0);
     });

     test('Positif - Tautan "Kelola di Data Master" membuka pengelolaan parameter sensor', async ({ page }) => {
          await iotPage.gotoSetup();
          await page.getByRole('link', { name: /Kelola di Data Master/i }).first().click();
          await expect(page).toHaveURL(/\/data-master/, { timeout: 15000 });
          await expect(page).toHaveURL(/sensor-parameters/);
          await expect(page.getByRole('heading', { name: /Konfigurasi Data Master/i })).toBeVisible({ timeout: 10000 });
     });

     // ─── Threshold Sensor Ternak (REDESIGN: read-only di Setup IoT; diatur dari Data Master Ternak) ──
     // Realita implementasi terbaru: section "Threshold Sensor Ternak" (dahulu "Threshold Komoditas")
     // kini READ-ONLY — "Batas ideal diatur dari Data Master Ternak agar konsisten dengan SPK",
     // dengan tautan "Atur di Data Master" (-> /data-master?tab=livestock). Tidak ada lagi tombol/modal
     // "Tambah Threshold" di halaman IoT. Pengaturan threshold diuji di modul Data Master (Section 23/24).
     test('Positif - Threshold Sensor Ternak read-only + tautan "Atur di Data Master"', async ({ page }) => {
          await iotPage.gotoSetup();
          await expect(page.getByRole('heading', { name: 'Threshold Sensor Ternak' })).toBeVisible({ timeout: 10000 });
          const atur = page.getByRole('link', { name: /Atur di Data Master|Atur Threshold/i }).first();
          await expect(atur).toBeVisible();
          const href = await atur.getAttribute('href');
          expect(href).toMatch(/data-master/);
          // Tidak ada lagi tombol Tambah Threshold di halaman IoT (dipindah ke Data Master Ternak)
          await expect(page.getByRole('button', { name: /^\s*Tambah Threshold\s*$/i })).toHaveCount(0);
     });
});
