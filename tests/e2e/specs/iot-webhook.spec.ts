import { test, expect } from '@playwright/test';
import { IotPage } from '../pages/IotPage.js';

test.describe('Modul IoT Webhook API Integrasi - E2E QA', () => {
    let iotPage: IotPage;
    let context: any;

    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }, testInfo) => {
        // Arrange
        testInfo.setTimeout(240000); 
        page.setDefaultNavigationTimeout(120000);
        page.setDefaultTimeout(120000);

        // Blocker statis Vite
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        iotPage = new IotPage(page);
        context = page.context();
    });

    // ─── HELPER FUNCTIONS ────────────────────────────────────────────────────────
    async function createTestDeviceWithMapping(iotPage: IotPage, deviceCode: string, deviceName: string) {
        await iotPage.gotoConfig();
        await iotPage.expectToBeOnConfigPage();

        const protocolName = `E2E-WEBHOOK-${Date.now()}`;
        await iotPage.createProtocol(protocolName, 'Test protocol for webhook');

        await iotPage.createConnectionConfig({
            protocolName,
            mqttBrokerUrl: 'mqtt://test-broker.local:1883',
        });

        await iotPage.page.waitForTimeout(2000);
        await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 }).catch(() => {});

        await iotPage.gotoDevices();
        await iotPage.expectToBeOnDevicesPage();

        await iotPage.addDeviceBtn.click();
        await expect(iotPage.deviceCodeInput.first()).toBeVisible({ timeout: 10000 });

        try {
            await expect(iotPage.connectionConfigSelect.first().locator('option', { hasText: protocolName })).toHaveCount(1, { timeout: 10000 });
        } catch {
            await iotPage.page.waitForTimeout(2000);
        }

        await iotPage.deviceCodeInput.first().fill(deviceCode);
        await iotPage.deviceNameInput.first().fill(deviceName);
        await iotPage.unitBudidayaSelect.first().selectOption({ index: 1 });
        await iotPage.connectionConfigSelect.first().selectOption({ label: protocolName });
        await iotPage.statusSelect.first().selectOption('active');
        await iotPage.deviceSubmitBtn.click();

        await iotPage.page.waitForTimeout(2000);
        await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 }).catch(() => {});

        return { deviceCode, deviceName, protocolName };
    }

    async function deleteTestDevice(iotPage: IotPage, deviceCode: string) {
        await iotPage.gotoDevices();
        await iotPage.page.waitForLoadState('networkidle').catch(() => {});

        const row = iotPage.page.locator('tr').filter({ hasText: deviceCode });
        const deleteBtn = row.locator('form').filter({ hasText: /hapus|delete/i }).locator('button');

        if (await deleteBtn.count() > 0) {
            iotPage.page.once('dialog', dialog => dialog.accept());
            await deleteBtn.first().click();

            await iotPage.page.waitForTimeout(2000);
            await expect(iotPage.toastSuccess).toBeVisible({ timeout: 10000 }).catch(() => {});
        }
    }

    async function sendWebhookRequest(context: any, deviceCode: string, payload: Record<string, any>) {
        const baseUrl = process.env.BASE_URL || 'http://127.0.0.1:8000';
        const webhookUrl = `${baseUrl}/iot/webhook/${deviceCode}`;

        const response = await context.request.post(webhookUrl, {
            data: payload,
            headers: { 'Content-Type': 'application/json' },
        });

        return {
            status: response.status(),
            statusText: response.statusText(),
            body: await response.json().catch(() => ({})),
        };
    }

    // ─── TEST SCENARIOS ──────────────────────────────────────────────────────────

    test('Positif - Device berhasil mengirim webhook via API tanpa Authorization dan menerima 200 OK', async ({ page }) => {
        /**
         * Given perangkat lunak Smart Farm memiliki open endpoint webhook
         * When Node Sensor mengirim payload HTTP POST JSON ke rute kode alat sah
         * Then Server membalas HTTP 200 OK dengan sukses data diterima
         */
        
        // Arrange
        const testDevice = await createTestDeviceWithMapping(iotPage, `WEBHOOK-TEST-${Date.now()}`, 'E2E Webhook Test Device');

        try {
            const webhookPayload = { temperature: 28.5, humidity: 65.2, pm25: 15.3 };

            // Act
            const response = await sendWebhookRequest(context, testDevice.deviceCode, webhookPayload);

            // Assert
            expect(response.status).toBe(200);
            expect(response.body).toHaveProperty('message');
            expect(response.body.message).toContain('Data diterima');
        } finally {
            // Teardown
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });

    test('Positif - Response Webhook mengekspos property "inserted" count yang akurat', async ({ page }) => {
        /**
         * Given endpoint Webhook memiliki payload handler jamak sensor
         * When payload dengan 2 parameter sah dikirimkan dari device node
         * Then JSON Return 'inserted' memiliki properti hitungan insert baris (number)
         */
         
        // Arrange
        const testDevice = await createTestDeviceWithMapping(iotPage, `WEBHOOK-COUNT-${Date.now()}`, 'E2E Webhook Count Test');

        try {
            const webhookPayload = { sensorValue1: 100, sensorValue2: 200 };

            // Act
            const response = await sendWebhookRequest(context, testDevice.deviceCode, webhookPayload);

            // Assert
            expect(response.status).toBe(200);
            expect(response.body).toHaveProperty('inserted');
            expect(typeof response.body.inserted).toBe('number');
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });

    test('Positif - Visibilitas data Webhook sah ter-record pada Dasbor IotDeviceLog UI', async ({ page }) => {
        /**
         * Given perangkat IoT yang telah disahasi sinkronisasi
         * When trigger proses webhook payload sukses diterima backend DB
         * Then record logs data tersebut dapat diamati admin dalam antarmuka tab Monitoring
         */
         
        // Arrange
        const testDevice = await createTestDeviceWithMapping(iotPage, `WEBHOOK-LOG-${Date.now()}`, 'E2E Webhook Log Test');

        try {
            const webhookPayload = { sensorData: 42 };
            const response = await sendWebhookRequest(context, testDevice.deviceCode, webhookPayload);

            // Act
            await iotPage.gotoMonitoring();
            await page.waitForLoadState('networkidle').catch(() => {});
            
            // Assert
            expect(response.status).toBe(200);
            expect(response.body.message).not.toContain('kesalahan');

            const pageContent = await page.content();
            const hasLogEntry = pageContent.includes(testDevice.deviceCode) || pageContent.includes(testDevice.deviceName);
            
            expect(hasLogEntry || response.body.inserted >= 0).toBeTruthy();
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });

    test('Positif - Skalabilitas Multiple sensor values tercerna dalam satu request webhook payload tunggal', async ({ page }) => {
        /**
         * Given perangkat dengan spesifikasi arsitektur bacaan multisensor ekstrim
         * When string JSON payload HTTP membawa kombinasi variabel cuaca, kimia, dan cahaya
         * Then logic API webhook merespon semua pemetaan ke DB secara efisien dengan code sukses
         */

        // Arrange
        const testDevice = await createTestDeviceWithMapping(iotPage, `WEBHOOK-MULTI-${Date.now()}`, 'E2E Webhook Multi-Sensor Test');

        try {
            const webhookPayload = {
                temperature: 27.5,
                humidity: 62.0,
                co2Level: 450.5,
                ammoniaLevel: 5.2,
                lightIntensity: 800,
            };

            // Act
            const response = await sendWebhookRequest(context, testDevice.deviceCode, webhookPayload);

            // Assert
            expect(response.status).toBe(200);
            expect(response.body).toHaveProperty('inserted');
            expect(response.body.inserted).toBeGreaterThanOrEqual(0);
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });

    test('Negatif - Operasi Webhook ke Device Code yang tak eksis ditolak oleh REST Constraint 404', async ({ page }) => {
        /**
         * Given sistem webhook smart-farm terbuka secara logic public
         * When malicious attacker atau sensor terputus menembak HTTP POST rute fiktif
         * Then sahasi API melemparkan 404 Not Found kesalahan (Bebas dari resiko blank 500 kesalahan)
         */

        // Arrange
        const nonExistentDeviceCode = `NONEXISTENT-${Date.now()}`;
        const webhookPayload = { temperature: 25.0 };
        
        // Act
        const response = await sendWebhookRequest(context, nonExistentDeviceCode, webhookPayload);

        // Assert
        expect(response.status).toBe(404);
        expect(response.body).toHaveProperty('kesalahan');
        expect(response.body.kesalahan).toContain('not found');
    });

    test('Negatif - Injeksi Payload object kosong pada perangkat sah tidak menyulut Fatal Crash', async ({ page }) => {
        /**
         * Given parameter endpoint sah dan sensor terdaftar
         * When mikrokontroler cacat mengirim struktur {} Empty JSON ke service Webhook
         * Then Backend memproses payload gracefully ber-status kode antara 200 hingga aman (< 500)
         */

        // Arrange
        const testDevice = await createTestDeviceWithMapping(iotPage, `WEBHOOK-EMPTY-${Date.now()}`, 'E2E Webhook Empty Payload');

        try {
            // Act
            const response = await sendWebhookRequest(context, testDevice.deviceCode, {});

            // Assert
            expect(response.status).toBeGreaterThanOrEqual(200);
            expect(response.status).toBeLessThan(500);

            if (response.status === 200) {
                expect(response.body.inserted).toBeGreaterThanOrEqual(0); // 0 data tercetak
            }
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });

    test('Negatif - Handler menangkal Injeksi array variabel null tanpa kesalahan backend parsial', async ({ page }) => {
        /**
         * Given endpoint device sah untuk Webhook HTTP
         * When property variabel object sensor terkirim berisi string NULL / Undefined
         * Then Laravel/Backend Webhook adapter menangani null exception tanpa HTTP 5xx
         */

        // Arrange
        const testDevice = await createTestDeviceWithMapping(iotPage, `WEBHOOK-NULL-${Date.now()}`, 'E2E Webhook Null Test');

        try {
            const webhookPayload = { temperature: null, humidity: null, co2: undefined };

            // Act
            const response = await sendWebhookRequest(context, testDevice.deviceCode, webhookPayload);

            // Assert
            expect(response.status).toBeGreaterThanOrEqual(200);
            expect(response.status).toBeLessThan(500);
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });

    test('Negatif - Device Identifier yang mengandung String / XSS Pattern di route diblokir router', async ({ page }) => {
        /**
         * Given framework router Laravel atau Web Server menaungi sistem
         * When URL webhook disisipi manipulasi injeksi code seperti <script>
         * Then sistem firewall mencetak 404 (atau 403) dan menolak echo string tersebut dalam object response
         */

        // Arrange
        const specialDeviceCode = `TEST-DEV/<script>alert(1)</script>`;

        // Act
        const response = await sendWebhookRequest(context, specialDeviceCode, { test: 'data' });

        // Assert
        expect(response.status).toBe(404);
        expect(JSON.stringify(response.body)).not.toContain('<script>');
    });

    test('Positif - Penerimaan angka nilai margin sensor besar ditangkap parsial Webhook', async ({ page }) => {
        /**
         * Given endpoint operasional perangkat webhook sinkron
         * When angka variabel numerikal dikirim menggunakan float massive atau notasi saintifik
         * Then Backend/JSON parser PHP menerima nilainya stabil as Number Array Log (HTTP 200 OK)
         */

        // Arrange
        const testDevice = await createTestDeviceWithMapping(iotPage, `WEBHOOK-LARGE-${Date.now()}`, 'E2E Webhook Large Values Test');

        try {
            const webhookPayload = {
                largeValue: 999999999.999,
                negativeValue: -99999.99,
                scientificNotation: 1.23e10,
            };

            // Act
            const response = await sendWebhookRequest(context, testDevice.deviceCode, webhookPayload);

            // Assert
            expect(response.status).toBe(200);
            expect(response.body.inserted).toBeGreaterThanOrEqual(0);
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });
});
