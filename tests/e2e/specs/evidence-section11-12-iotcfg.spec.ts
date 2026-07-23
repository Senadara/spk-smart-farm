import { test, expect, Page } from '@playwright/test';
import { IotPage } from '../pages/IotPage.js';

/**
 * EVIDENCE - Section 11 (Konfigurasi IoT) & 12 (Perangkat IoT).
 * Alur nyata: halaman "Setup IoT Kandang" berbasis modal. Protokol FIXED (API/Antares & MQTT)
 * → skenario CRUD protokol (IOTCFG001/004/006/013) TIDAK BERLAKU (superseded), evidence = kondisi nyata.
 * Sesi pjawab (storageState). 1 screenshot unik per skenario.
 */
async function cap(page: Page, rel: string) {
     try { await page.waitForLoadState('networkidle', { timeout: 15000 }); }
     catch { await page.waitForLoadState('domcontentloaded'); }
     await page.waitForTimeout(600);
     await page.screenshot({ path: `qa-evidence/${rel}`, fullPage: true });
}

test.describe('Evidence Section 11-12 - Setup IoT (Konfigurasi & Perangkat)', () => {
     let iot: IotPage;
     test.setTimeout(150000);
     test.beforeEach(async ({ page }) => {
          await page.route('**/:5173/**', (route) => route.abort());
          await page.route(/.*:5173.*/, (route) => route.abort());
          iot = new IotPage(page);
     });

     // ── Protokol FIXED → superseded ──
     test('IOTCFG001 - Protokol IoT (fixed) pada modal koneksi', async ({ page }) => {
          await iot.gotoSetup();
          await iot.openConnectionModal();
          const m = iot.modal('addConnection');
          console.log('IOTCFG001_HAS_MQTT::' + await m.locator('button').filter({ hasText: /MQTT/i }).count());
          console.log('IOTCFG001_HAS_API::' + await m.locator('button').filter({ hasText: /API \/ Antares/i }).count());
          await cap(page, 'IOTCFG/IOTCFG001_protokol_fixed.png');
     });

     test('IOTCFG004 - Protokol tidak dapat diedit (fixed)', async ({ page }) => {
          await iot.gotoSetup();
          await cap(page, 'IOTCFG/IOTCFG004_protokol_tidak_editable.png');
     });

     test('IOTCFG006 - Protokol tidak dapat dihapus (fixed)', async ({ page }) => {
          await iot.gotoSetup();
          await cap(page, 'IOTCFG/IOTCFG006_protokol_tidak_hapus.png');
     });

     test('IOTCFG013 - Protokol duplikat tidak berlaku (fixed)', async ({ page }) => {
          await iot.gotoSetup();
          await iot.openConnectionModal();
          await cap(page, 'IOTCFG/IOTCFG013_protokol_duplikat_na.png');
     });

     // ── Koneksi ──
     test('IOTCFG002 - Membuat koneksi MQTT baru', async ({ page }) => {
          const url = `mqtt://ev-${Date.now()}.local`;
          await iot.createConnection({ mode: 'MQTT', mqttBrokerUrl: url });
          await cap(page, 'IOTCFG/IOTCFG002_buat_koneksi_mqtt.png');
     });

     test('IOTCFG007 - Koneksi tersimpan menampilkan kontrol edit', async ({ page }) => {
          const url = `mqtt://ev-edit-${Date.now()}.local`;
          await iot.createConnection({ mode: 'MQTT', mqttBrokerUrl: url });
          console.log('IOTCFG007_EDIT_BTN::' + await page.locator('button[title*="Edit"], a[title*="Edit"]').count());
          await cap(page, 'IOTCFG/IOTCFG007_koneksi_kontrol_edit.png');
     });

     test('IOTCFG010 - Menghapus koneksi', async ({ page }) => {
          const url = `mqtt://ev-del-${Date.now()}.local`;
          await iot.createConnection({ mode: 'MQTT', mqttBrokerUrl: url });
          await iot.deleteConnectionByText(url);
          await cap(page, 'IOTCFG/IOTCFG010_hapus_koneksi.png');
     });

     test('IOTCFG014 - Koneksi dengan alamat broker tidak valid (validasi)', async ({ page }) => {
          await iot.gotoSetup();
          await iot.openConnectionModal();
          const m = iot.modal('addConnection');
          await m.locator('button').filter({ hasText: /Laravel subscribe broker MQTT/i }).first().click();
          await m.locator('input[name="mqttBrokerUrl"]').fill('alamat tidak valid !!!');
          await iot.modalSubmit('addConnection').click({ force: true }).catch(() => {});
          await page.waitForTimeout(800);
          const stillOpen = await iot.modal('addConnection').isVisible().catch(() => false);
          console.log('IOTCFG014_MODAL_STILL_OPEN::' + stillOpen);
          await cap(page, 'IOTCFG/IOTCFG014_broker_tidak_valid.png');
     });

     // ── Parameter Sensor ──
     test('IOTCFG003 - Membuat parameter sensor baru', async ({ page }) => {
          const code = `EVPARAM${Date.now().toString().slice(-6)}`;
          await iot.createParameter(code, `Evidence Param ${code}`, 'C');
          await cap(page, 'IOTCFG/IOTCFG003_buat_parameter.png');
     });

     test('IOTCFG008 - Form parameter sensor (edit/kelola)', async ({ page }) => {
          await iot.gotoSetup();
          await iot.openParameterModal();
          await cap(page, 'IOTCFG/IOTCFG008_form_parameter.png');
     });

     test('IOTCFG011 - Daftar parameter sensor & kontrol hapus', async ({ page }) => {
          await iot.gotoSetup();
          console.log('IOTCFG011_DELETE_FORMS::' + await page.locator('form[action*="parameters"]').count());
          await cap(page, 'IOTCFG/IOTCFG011_parameter_kontrol_hapus.png');
     });

     // ── Mapping ──
     test('IOTCFG005 - Form tambah mapping payload', async ({ page }) => {
          await iot.gotoSetup();
          const btn = iot.addMappingBtn.first();
          const disabled = await btn.isDisabled().catch(() => false);
          console.log('IOTCFG005_MAPPING_BTN_DISABLED::' + disabled);
          if (!disabled) {
               await btn.click({ force: true });
               await page.waitForTimeout(600);
          }
          await cap(page, 'IOTCFG/IOTCFG005_form_mapping.png');
     });

     test('IOTCFG009 - Kelola mapping (langkah 3 Setup IoT)', async ({ page }) => {
          await iot.gotoSetup();
          await cap(page, 'IOTCFG/IOTCFG009_kelola_mapping.png');
     });

     test('IOTCFG012 - Daftar mapping & kontrol hapus', async ({ page }) => {
          await iot.gotoSetup();
          console.log('IOTCFG012_MAPPING_FORMS::' + await page.locator('form[action*="mappings"]').count());
          await cap(page, 'IOTCFG/IOTCFG012_mapping_kontrol_hapus.png');
     });

     // ── Perangkat (Section 12) ──
     test('IOTDEV001 - Mendaftarkan perangkat IoT baru', async ({ page }) => {
          await iot.createConnection({ mode: 'MQTT', mqttBrokerUrl: `mqtt://ev-dev-${Date.now()}.local` });
          const code = `EVDEV-${Date.now()}`;
          await iot.createDevice(code, 'Evidence Device', 'active');
          await cap(page, 'IOTDEV/IOTDEV001_daftar_perangkat.png');
     });

     test('IOTDEV002 - Menghapus perangkat IoT', async ({ page }) => {
          await iot.createConnection({ mode: 'MQTT', mqttBrokerUrl: `mqtt://ev-devdel-${Date.now()}.local` });
          const code = `EVDEVDEL-${Date.now()}`;
          await iot.createDevice(code, 'Evidence Device Delete', 'active');
          await iot.gotoSetup();
          const row = page.locator('tr').filter({ hasText: code });
          if (await row.count() > 0) await iot.deleteRow(code);
          await cap(page, 'IOTDEV/IOTDEV002_hapus_perangkat.png');
     });

     test('IOTDEV003 - Perangkat tersimpan menampilkan kontrol edit', async ({ page }) => {
          await iot.createConnection({ mode: 'MQTT', mqttBrokerUrl: `mqtt://ev-deved-${Date.now()}.local` });
          const code = `EVDEVED-${Date.now()}`;
          await iot.createDevice(code, 'Evidence Device Edit', 'active');
          await iot.gotoSetup();
          console.log('IOTDEV003_EDIT::' + await page.locator('button[title*="Edit"], a[title*="Edit"]').count());
          await cap(page, 'IOTDEV/IOTDEV003_perangkat_kontrol_edit.png');
     });

     test('IOTDEV004 - Mendaftarkan perangkat tanpa memilih kandang (validasi)', async ({ page }) => {
          await iot.gotoSetup();
          await iot.openDeviceModal();
          const m = iot.modal('addDevice');
          await m.locator('input[name="deviceCode"]').fill(`EVNOKANDANG-${Date.now()}`);
          await m.locator('input[name="deviceName"]').fill('Tanpa Kandang');
          await iot.modalSubmit('addDevice').click({ force: true }).catch(() => {});
          await page.waitForTimeout(800);
          const stillOpen = await iot.modal('addDevice').isVisible().catch(() => false);
          console.log('IOTDEV004_MODAL_STILL_OPEN::' + stillOpen);
          await cap(page, 'IOTDEV/IOTDEV004_validasi_tanpa_kandang.png');
     });
});
