import { test, expect } from '@playwright/test';
import { IotPage } from '../pages/IotPage.js';

/**
 * EVIDENCE - IOTD001 (Section 13): kirim data sensor via webhook API.
 * Buat koneksi + device, POST /iot/webhook/{deviceCode}, verifikasi 200 "Data diterima",
 * lalu tangkap bukti di halaman Monitoring. Sesi pjawab (storageState).
 */
test.describe.serial('Evidence IOTD001 - Webhook', () => {
     test.setTimeout(240000);

     test('IOTD001 - Mengirim data sensor via webhook dengan format benar', async ({ page }) => {
          await page.route('**/:5173/**', (route) => route.abort());
          await page.route(/.*:5173.*/, (route) => route.abort());
          const iot = new IotPage(page);
          const code = `WEBHOOK-EV-${Date.now()}`;

          await iot.createConnection({ mode: 'MQTT' });
          await iot.createDevice(code, 'E2E Webhook Evidence', 'active');

          const baseUrl = process.env.BASE_URL || 'http://127.0.0.1:8000';
          const resp = await page.context().request.post(`${baseUrl}/iot/webhook/${code}`, {
               data: { temperature: 28.5, humidity: 65.2, pm25: 15.3 },
               headers: { 'Content-Type': 'application/json' },
          });
          const status = resp.status();
          const body = await resp.json().catch(() => ({}));
          console.log('IOTD001_STATUS::' + status + ' BODY::' + JSON.stringify(body));
          expect(status).toBe(200);
          expect(JSON.stringify(body)).toMatch(/Data diterima|inserted/i);

          await iot.gotoMonitoring();
          try { await page.waitForLoadState('networkidle', { timeout: 15000 }); } catch { /* ignore */ }
          await page.waitForTimeout(700);
          await page.screenshot({ path: 'qa-evidence/IOTD/IOTD001_webhook_data_sensor.png', fullPage: true });

          // Bersihkan device uji
          await iot.gotoSetup();
          const row = page.locator('tr').filter({ hasText: code });
          if (await row.count() > 0) await iot.deleteRow(code);
     });
});
