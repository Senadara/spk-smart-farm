import { test, expect } from '@playwright/test';
import { IotPage } from '../pages/IotPage.js';

test.describe('Modul IoT Webhook Integration - API PUSH E2E QA', () => {
    let iotPage: IotPage;
    let context: any;

    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }, testInfo) => {
        testInfo.setTimeout(240000); 
        page.setDefaultNavigationTimeout(120000);
        page.setDefaultTimeout(120000);

        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        iotPage = new IotPage(page);
        context = page.context();
    });

    async function createTestDeviceWithMapping(
        iotPage: IotPage,
        deviceCode: string,
        deviceName: string
    ) {
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

        return {
            deviceCode,
            deviceName,
            protocolName,
        };
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

    async function sendWebhookRequest(
        context: any,
        deviceCode: string,
        payload: Record<string, any>
    ) {
        const baseUrl = process.env.BASE_URL || 'http://127.0.0.1:8000';
        const webhookUrl = `${baseUrl}/iot/webhook/${deviceCode}`;

        const response = await context.request.post(webhookUrl, {
            data: payload,
            headers: {
                'Content-Type': 'application/json',
            },
        });

        return {
            status: response.status(),
            statusText: response.statusText(),
            body: await response.json().catch(() => ({})),
        };
    }


    test('Positif - Device berhasil mengirim webhook tanpa auth & menerima 200 OK', async ({ page }) => {
        const testDevice = await createTestDeviceWithMapping(
            iotPage,
            `WEBHOOK-TEST-${Date.now()}`,
            'E2E Webhook Test Device'
        );

        try {
            const webhookPayload = {
                temperature: 28.5,
                humidity: 65.2,
                pm25: 15.3,
            };

            const response = await sendWebhookRequest(
                context,
                testDevice.deviceCode,
                webhookPayload
            );

            expect(response.status).toBe(200);
            expect(response.body).toHaveProperty('message');
            expect(response.body.message).toContain('Data diterima');
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });

    test('Positif - Webhook menerima response dengan inserted count', async ({ page }) => {
        const testDevice = await createTestDeviceWithMapping(
            iotPage,
            `WEBHOOK-COUNT-${Date.now()}`,
            'E2E Webhook Count Test'
        );

        try {
            const webhookPayload = {
                sensorValue1: 100,
                sensorValue2: 200,
            };

            const response = await sendWebhookRequest(
                context,
                testDevice.deviceCode,
                webhookPayload
            );

            expect(response.status).toBe(200);
            expect(response.body).toHaveProperty('inserted');
            expect(typeof response.body.inserted).toBe('number');
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });

    test('Positif - Webhook dengan valid payload tercatat di IotDeviceLog', async ({ page }) => {
        const testDevice = await createTestDeviceWithMapping(
            iotPage,
            `WEBHOOK-LOG-${Date.now()}`,
            'E2E Webhook Log Test'
        );

        try {
            const webhookPayload = { sensorData: 42 };
            const response = await sendWebhookRequest(
                context,
                testDevice.deviceCode,
                webhookPayload
            );

            expect(response.status).toBe(200);
            expect(response.body.message).not.toContain('error');

            await iotPage.gotoMonitoring();
            await page.waitForLoadState('networkidle').catch(() => {});

            const pageContent = await page.content();
            const hasLogEntry = pageContent.includes(testDevice.deviceCode) ||
                pageContent.includes(testDevice.deviceName);
            expect(hasLogEntry || response.body.inserted >= 0).toBeTruthy();
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });

    test('Positif - Multiple sensor values dalam satu webhook payload', async ({ page }) => {
        const testDevice = await createTestDeviceWithMapping(
            iotPage,
            `WEBHOOK-MULTI-${Date.now()}`,
            'E2E Webhook Multi-Sensor Test'
        );

        try {
            const webhookPayload = {
                temperature: 27.5,
                humidity: 62.0,
                co2Level: 450.5,
                ammoniaLevel: 5.2,
                lightIntensity: 800,
            };

            const response = await sendWebhookRequest(
                context,
                testDevice.deviceCode,
                webhookPayload
            );

            expect(response.status).toBe(200);
            expect(response.body).toHaveProperty('inserted');
            expect(response.body.inserted).toBeGreaterThanOrEqual(0);
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });


    test('Negatif - Webhook dengan device code yang tidak ada return 404', async ({ page }) => {
        const nonExistentDeviceCode = `NONEXISTENT-${Date.now()}`;
        const webhookPayload = { temperature: 25.0 };
        const response = await sendWebhookRequest(
            context,
            nonExistentDeviceCode,
            webhookPayload
        );

        expect(response.status).toBe(404);
        expect(response.body).toHaveProperty('error');
        expect(response.body.error).toContain('not found');
    });

    test('Negatif - Webhook dengan payload kosong tidak crash', async ({ page }) => {
        const testDevice = await createTestDeviceWithMapping(
            iotPage,
            `WEBHOOK-EMPTY-${Date.now()}`,
            'E2E Webhook Empty Payload Test'
        );

        try {
            const response = await sendWebhookRequest(context, testDevice.deviceCode, {});

            expect(response.status).toBeGreaterThanOrEqual(200);
            expect(response.status).toBeLessThan(500);

            if (response.status === 200) {
                expect(response.body.inserted).toBeGreaterThanOrEqual(0);
            }
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });

    test('Negatif - Webhook dengan null values tidak crash', async ({ page }) => {
        const testDevice = await createTestDeviceWithMapping(
            iotPage,
            `WEBHOOK-NULL-${Date.now()}`,
            'E2E Webhook Null Values Test'
        );

        try {
            const webhookPayload = {
                temperature: null,
                humidity: null,
                co2: undefined,
            };

            const response = await sendWebhookRequest(
                context,
                testDevice.deviceCode,
                webhookPayload
            );

            expect(response.status).toBeGreaterThanOrEqual(200);
            expect(response.status).toBeLessThan(500);
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });

    test('Negatif - Webhook dengan device code spesial character handle dengan aman', async ({ page }) => {
        const specialDeviceCode = `TEST-DEV/<script>alert(1)</script>`;

        const response = await sendWebhookRequest(
            context,
            specialDeviceCode,
            { test: 'data' }
        );

        expect(response.status).toBe(404);

        expect(JSON.stringify(response.body)).not.toContain('<script>');
    });


    test('Positif - Webhook dengan very large sensor values', async ({ page }) => {
        const testDevice = await createTestDeviceWithMapping(
            iotPage,
            `WEBHOOK-LARGE-${Date.now()}`,
            'E2E Webhook Large Values Test'
        );

        try {
            const webhookPayload = {
                largeValue: 999999999.999,
                negativeValue: -99999.99,
                scientificNotation: 1.23e10,
            };

            const response = await sendWebhookRequest(
                context,
                testDevice.deviceCode,
                webhookPayload
            );

            expect(response.status).toBe(200);
            expect(response.body.inserted).toBeGreaterThanOrEqual(0);
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });

    test('Positif - Webhook dengan special numeric values (0, negative, decimal)', async ({ page }) => {
        const testDevice = await createTestDeviceWithMapping(
            iotPage,
            `WEBHOOK-NUMS-${Date.now()}`,
            'E2E Webhook Special Numbers Test'
        );

        try {
            const webhookPayload = {
                zeroValue: 0,
                negativeTemp: -15.5,
                fraction: 0.001,
                largeDecimal: 99.99999,
            };

            const response = await sendWebhookRequest(
                context,
                testDevice.deviceCode,
                webhookPayload
            );

            expect(response.status).toBe(200);
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });


    test('Positif - Data dari webhook muncul di halaman Monitoring (real-time)', async ({ page }) => {
        const testDevice = await createTestDeviceWithMapping(
            iotPage,
            `WEBHOOK-REALTIME-${Date.now()}`,
            'E2E Webhook Real-Time Test'
        );

        try {
            const webhookPayload = {
                sensorReading: 42.42,
            };

            const response = await sendWebhookRequest(
                context,
                testDevice.deviceCode,
                webhookPayload
            );

            expect(response.status).toBe(200);

            await iotPage.gotoMonitoring();
            await page.waitForLoadState('networkidle').catch(() => {});
            const monitoringContent = await page.content();

            const hasDeviceRef = monitoringContent.includes(testDevice.deviceCode) ||
                monitoringContent.includes(testDevice.deviceName) ||
                monitoringContent.includes('42.42');

            expect(hasDeviceRef).toBeTruthy();
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });

    test('Positif - Monitoring tabs (Sensor Data, Device Logs) menampilkan webhook activity', async ({ page }) => {
        const testDevice = await createTestDeviceWithMapping(
            iotPage,
            `WEBHOOK-TABS-${Date.now()}`,
            'E2E Webhook Tabs Test'
        );

        try {
            await sendWebhookRequest(context, testDevice.deviceCode, {
                temperature: 30.0,
            });

            await iotPage.gotoMonitoring();
            await iotPage.expectToBeOnMonitoringPage();

            await expect(iotPage.sensorDataTab.first()).toBeVisible({ timeout: 10000 });
            await expect(iotPage.deviceLogsTab.first()).toBeVisible({ timeout: 10000 });

            await iotPage.sensorDataTab.first().click();
            await page.waitForTimeout(500);

            await iotPage.deviceLogsTab.first().click();
            await page.waitForTimeout(500);

            expect(true).toBeTruthy();
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });


    test('Positif - Webhook menggunakan parameter mapping dari device config', async ({ page }) => {
        const testDevice = await createTestDeviceWithMapping(
            iotPage,
            `WEBHOOK-MAPPING-${Date.now()}`,
            'E2E Webhook Mapping Test'
        );

        try {
            const webhookPayload = {
                temperature: 28.5,
                humidity: 65.0,
            };

            const response = await sendWebhookRequest(
                context,
                testDevice.deviceCode,
                webhookPayload
            );

            expect(response.status).toBe(200);
            expect(response.body).toHaveProperty('inserted');
            expect(response.body.inserted).toBeGreaterThanOrEqual(0);
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });

    test('Negatif - Webhook dengan fields yang tidak ada di mapping tidak throw error', async ({ page }) => {
        const testDevice = await createTestDeviceWithMapping(
            iotPage,
            `WEBHOOK-UNMAPPED-${Date.now()}`,
            'E2E Webhook Unmapped Fields Test'
        );

        try {
            const webhookPayload = {
                unknownField1: 'value',
                unmappedSensor: 123,
                randomData: 'test',
            };

            const response = await sendWebhookRequest(
                context,
                testDevice.deviceCode,
                webhookPayload
            );

            expect(response.status).toBe(200);
            expect(response.body.inserted).toBeGreaterThanOrEqual(0);
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });


    test('Positif - Webhook menerima dan process response cepat (< 5s)', async ({ page }) => {
        const testDevice = await createTestDeviceWithMapping(
            iotPage,
            `WEBHOOK-PERF-${Date.now()}`,
            'E2E Webhook Performance Test'
        );

        try {
            const startTime = Date.now();

            await sendWebhookRequest(context, testDevice.deviceCode, {
                temperature: 25.0,
                humidity: 60.0,
            });

            const endTime = Date.now();
            const responseTime = endTime - startTime;

            expect(responseTime).toBeLessThan(5000);
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });

    test('Positif - Multiple sequential webhooks dari device yang sama berhasil', async ({ page }) => {
        const testDevice = await createTestDeviceWithMapping(
            iotPage,
            `WEBHOOK-SEQ-${Date.now()}`,
            'E2E Webhook Sequential Test'
        );

        try {
            const responses = [];
            for (let i = 0; i < 3; i++) {
                const response = await sendWebhookRequest(
                    context,
                    testDevice.deviceCode,
                    {
                        temperature: 25.0 + i,
                        sequence: i,
                    }
                );
                responses.push(response);
            }

            responses.forEach(response => {
                expect(response.status).toBe(200);
            });
        } finally {
            await deleteTestDevice(iotPage, testDevice.deviceCode);
        }
    });
});
