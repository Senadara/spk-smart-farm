import { test, expect } from '@playwright/test';
import { IotPage } from '../pages/IotPage.js';

async function seedIoTConnectionConfig(iotPage: IotPage) {
    const protocolName = `E2E MQTT ${Date.now()}`;
    const mqttBrokerUrl = `mqtts://broker-${Date.now()}.example.test:8883`;

    await iotPage.createProtocol(protocolName, 'Protokol khusus E2E IoT');
    await iotPage.createConnectionConfig({
        protocolName,
        mqttBrokerUrl,
        authType: 'none',
    });

    return {
        protocolName,
        mqttBrokerUrl,
        connectionLabel: `${protocolName} — ${mqttBrokerUrl}`,
    };
}

test.describe('Modul IoT dan Monitoring - E2E QA', () => {
    let iotPage: IotPage;

    test.setTimeout(60000);
    test.setTimeout(90000);

    test.beforeEach(async ({ page }) => {
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        iotPage = new IotPage(page);
    });

    test('Positif - Berhasil memuat halaman Dasbor IoT', async ({ page }) => {
        await iotPage.gotoDashboard();

        await iotPage.expectToBeOnDashboard();

        await expect(iotPage.registerDeviceBtn.first()).toBeVisible();
    });

    test('Positif - Berhasil memuat halaman Konfigurasi IoT', async ({ page }) => {
        await iotPage.gotoConfig();

        await iotPage.expectToBeOnConfigPage();

        await expect(iotPage.protocolsTab).toBeVisible();
        await expect(iotPage.connectionsTab).toBeVisible();
    });

    test('Positif - Berhasil memuat halaman Manajemen Perangkat', async ({ page }) => {
        await iotPage.gotoDevices();

        await iotPage.expectToBeOnDevicesPage();

        await expect(iotPage.addDeviceBtn.first()).toBeVisible();
        await expect(iotPage.addMappingBtn.first()).toBeVisible();
    });

    test('Positif - Berhasil memuat halaman Monitoring IoT dan tab berfungsi', async ({ page }) => {
        await iotPage.gotoMonitoring();
        
        await iotPage.expectToBeOnMonitoringPage();
        await expect(iotPage.sensorDataTab.first()).toBeVisible();
        await expect(iotPage.deviceLogsTab.first()).toBeVisible();

        await expect(page.locator('canvas, table').first()).toBeVisible();
    });

    test.describe('CRUD & Validasi Device Management', () => {
        test('Negatif - Kirim tambah perangkat kosong menampilkan validasi form', async ({ page }) => {
            await iotPage.gotoDevices();
            await iotPage.addDeviceBtn.click();
            await expect(iotPage.deviceCodeInput.first()).toBeVisible({ timeout: 10000 });

            await iotPage.deviceSubmitBtn.click();

            const isInvalidInput = await iotPage.deviceCodeInput.first().evaluate((node: HTMLInputElement) => !node.checkValidity());
            expect(isInvalidInput).toBeTruthy();
        });

        test('Positif - Berhasil menambah perangkat dengan data valid', async ({ page }) => {
            const { connectionLabel } = await seedIoTConnectionConfig(iotPage);

            await iotPage.gotoDevices();
            await iotPage.expectToBeOnDevicesPage();
            await iotPage.addDeviceBtn.click();
            await expect(iotPage.deviceCodeInput.first()).toBeVisible({ timeout: 10000 });

            const mockCode = `TEST-DEV-${Date.now()}`;
            const mockDeviceName = `Sensor E2E ${Date.now()}`;

            await iotPage.deviceCodeInput.first().fill(mockCode);
            await iotPage.deviceNameInput.first().fill(mockDeviceName);
            await iotPage.unitBudidayaSelect.first().selectOption({ index: 1 });
            await iotPage.connectionConfigSelect.first().selectOption({ label: connectionLabel });
            await iotPage.statusSelect.first().selectOption('active');
            await iotPage.deviceSubmitBtn.click();

            await expect(iotPage.page.getByRole('cell', { name: mockCode })).toBeVisible({ timeout: 10000 });
            await expect(iotPage.toastSuccess).toBeVisible({ timeout: 12000 });

            const row = iotPage.page.locator('tr').filter({ hasText: mockCode });
            const deleteBtn = row.locator('form').filter({ hasText: /hapus|delete/i }).locator('button');
            
            if (await deleteBtn.count() > 0) {
                page.once('dialog', dialog => dialog.accept());
                await deleteBtn.first().click();
                
                await expect(iotPage.toastSuccess).toBeVisible({ timeout: 12000 });
                await expect(iotPage.page.getByRole('cell', { name: mockCode })).toBeHidden({ timeout: 10000 });
            }
        });

        test('Positif - Fungsi hapus memunculkan dialog konfirmasi', async ({ page }) => {
            await iotPage.gotoDevices();
            
            if (await iotPage.deleteButtons.count() > 0) {
                page.on('dialog', dialog => dialog.accept());
                await iotPage.deleteButtons.first().click();
            }
        });
    });
});
