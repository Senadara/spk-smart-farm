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

test.describe('Modul IoT dan Monitoring - E2E Smoke Tests', () => {
    let iotPage: IotPage;

    test.setTimeout(90000);

    test.beforeEach(async ({ page }) => {
        // Arrnage
        iotPage = new IotPage(page);
    });

    test('Positif - UI Dashboard IoT merender elemen pendaftaran Node IoT', async ({ page }) => {
        /**
         * Given pengguna menavigasi ke dasbor IoT /iot
         * When halaman dasbor berhasil diload
         * Then tombol dan form registrasi perangkat akan terlihat di layar
         */

        // Arrange & Act
        await iotPage.gotoDashboard();

        // Assert
        await iotPage.expectToBeOnDashboard();
        await expect(iotPage.registerDeviceBtn.first()).toBeVisible();
    });

    test('Positif - Tab halaman Konfigurasi IoT (4 tabs) aktif', async ({ page }) => {
        /**
         * Given user merupakan network admin
         * When navigasi ke parameter konfigurasi /iot/config
         * Then sistem render 4 panel config: Protokol, Koneksi, Parameter Sensor, Komoditas Parameter
         */

        // Arrange & Act
        await iotPage.gotoConfig();

        // Assert
        await iotPage.expectToBeOnConfigPage();
        await expect(iotPage.protocolsTab).toBeVisible();
        await expect(iotPage.connectionsTab).toBeVisible();
        await expect(iotPage.parametersTab).toBeVisible();
        await expect(iotPage.commodityParamsTab).toBeVisible();
    });

    test('Negatif - Simpan Pendaftaran IoT tanpa Code memicu alert validasi mandatory', async ({ page }) => {
        /**
         * Given pengguna admin pada halaman perangkat IoT
         * When pengguna memaksa submit form kosong
         * Then validity API mencegah payload request HTTP terkirim ke backend
         */

        // Arrange
        await iotPage.gotoDevices();
        await page.waitForLoadState('networkidle');
        await iotPage.addDeviceBtn.scrollIntoViewIfNeeded();
        await page.waitForTimeout(1000);
        await iotPage.addDeviceBtn.click();
        await page.waitForTimeout(2000);
        await expect(iotPage.deviceCodeInput.first()).toBeVisible({ timeout: 15000 });

        // Act
        await iotPage.deviceSubmitBtn.click();

        // Assert
        const codeInput = iotPage.deviceCodeInput.first();
        const isInvalidInput = await codeInput.evaluate((node: HTMLInputElement) => !node.checkValidity());

        expect(isInvalidInput).toBeTruthy();
    });

    test('Positif - Validasi E2E end-to-end penambahan perangkat IoT baru success', async ({ page }) => {
        /**
         * Given admin memiliki parameter protocal broker
         * When admin meng-assign Node Mac/Code, penamaan dan payload protocol lengkap
         * Then record MQTT IoT device baru harus disimpan dan ditampilkan pada Datatable Device
         */

        // Arrange
        await seedIoTConnectionConfig(iotPage);
        await page.goto('/iot/devices');
        await page.waitForLoadState('networkidle');

        await iotPage.addDeviceBtn.scrollIntoViewIfNeeded();
        await page.waitForTimeout(1000);
        await iotPage.addDeviceBtn.click();
        await page.waitForTimeout(2000);
        await expect(iotPage.deviceCodeInput.first()).toBeVisible({ timeout: 15000 });

        const mockCode = `TEST-DEV-${Date.now()}`;
        const mockDeviceName = `Sensor E2E ${Date.now()}`;

        // Act
        await iotPage.deviceCodeInput.first().fill(mockCode);
        await iotPage.deviceNameInput.first().fill(mockDeviceName);
        await iotPage.unitBudidayaSelect.first().selectOption({ index: 1 });

        const connOptions = await iotPage.connectionConfigSelect.first().locator('option').count();
        if (connOptions > 1) {
            await iotPage.connectionConfigSelect.first().selectOption({ index: 1 });
        }

        await iotPage.statusSelect.first().selectOption('active');

        await iotPage.deviceSubmitBtn.click();

        // Assert
        await expect(iotPage.page.getByRole('cell', { name: mockCode })).toBeVisible({ timeout: 15000 });
        await expect(iotPage.toastSuccess).toBeVisible({ timeout: 15000 });

        // Cleanup
        const row = iotPage.page.locator('tr').filter({ hasText: mockCode });
        const deleteBtn = row.locator('form').filter({ hasText: /hapus|delete/i }).locator('button');

        if (await deleteBtn.count() > 0) {
            page.once('dialog', dialog => dialog.accept());
            await deleteBtn.first().click();
            try {
                await expect(iotPage.page.getByRole('cell', { name: mockCode })).toBeHidden({ timeout: 10000 });
            } catch { }
        }
    });
});
