import { Page, Locator, expect } from '@playwright/test';

/**
 * IotPage — page object untuk modul IoT (redesign Nanda).
 *
 * Realita implementasi (resources/views/iot/*):
 *  - /iot          => Dashboard "Monitoring Sensor" (heading), tombol/link "Setup IoT".
 *  - /iot/devices  => halaman "Setup IoT Kandang" (SATU halaman berbasis modal, bukan tab).
 *  - /iot/config   => redirect ke /iot/devices#advanced-iot-config.
 *  - /iot/monitoring => "Monitoring Sensor" dengan tab Data Sensor & Device Logs.
 *
 * Protokol bersifat FIXED (API/Antares + MQTT, di-seed). Tidak ada CRUD protokol.
 * Semua aksi (koneksi/device/mapping/parameter/threshold) lewat modal <x-iot.modal-form>
 * dengan tombol submit berteks "Simpan". Modal aktif ditandai atribut
 * x-show="modal === '<id>'". Sukses ditandai flash 'success' (toast-success)
 * dan/atau baris/kartu baru pada halaman setelah reload.
 */
export class IotPage {
    readonly page: Page;

    // Dashboard (/iot)
    readonly dashboardHeading: Locator;
    readonly setupIotLink: Locator;
    readonly registerDeviceBtn: Locator;

    // Setup IoT (/iot/devices)
    readonly setupHeading: Locator;
    readonly mulaiSetupBtn: Locator;
    readonly addConnectionBtn: Locator;
    readonly addDeviceBtn: Locator;
    readonly addMappingBtn: Locator;
    readonly addParameterBtn: Locator;
    readonly addThresholdBtn: Locator;

    // Monitoring (/iot/monitoring)
    readonly monitoringHeading: Locator;
    readonly sensorDataTab: Locator;
    readonly deviceLogsTab: Locator;

    readonly toastSuccess: Locator;

    constructor(page: Page) {
        this.page = page;

        this.dashboardHeading = page.locator('h1').filter({ hasText: /Monitoring Sensor/i });
        this.setupIotLink = page.locator('a').filter({ hasText: /Setup IoT/i });
        this.registerDeviceBtn = page.locator('a, button').filter({ hasText: /Setup IoT|Register Device|Tambah Device/i });

        this.setupHeading = page.locator('h1').filter({ hasText: /Setup IoT Kandang/i });
        this.mulaiSetupBtn = page.getByRole('button', { name: /Mulai Setup/i });
        this.addConnectionBtn = page.getByRole('button', { name: /^\s*Tambah Koneksi\s*$/i });
        this.addDeviceBtn = page.getByRole('button', { name: /^\s*Tambah Device\s*$/i });
        this.addMappingBtn = page.getByRole('button', { name: /^\s*Tambah Mapping\s*$/i });
        this.addParameterBtn = page.getByRole('button', { name: /^\s*Tambah Parameter\s*$/i });
        this.addThresholdBtn = page.getByRole('button', { name: /^\s*Tambah Threshold\s*$/i });

        this.monitoringHeading = page.locator('h1').filter({ hasText: /Monitoring Sensor|Monitoring IoT/i });
        this.sensorDataTab = page.locator('button').filter({ hasText: /Data Sensor/i });
        this.deviceLogsTab = page.locator('button').filter({ hasText: /Device Logs/i });

        this.toastSuccess = page.locator('#toastContainer .toast-success');
    }

    /** Backdrop modal aktif (x-show="modal === '<id>'"). first() = backdrop luar yang memuat form + tombol Simpan. */
    modal(id: string): Locator {
        return this.page.locator(`[x-show="modal === '${id}'"]`).first();
    }

    modalSubmit(id: string): Locator {
        return this.modal(id).locator('button[type="submit"]');
    }

    /**
     * Submit form modal secara deterministik lewat form.requestSubmit() (menghindari flake klik
     * tombol saat transisi modal / overlay). actionSubstr = potongan URL action form target.
     * requestSubmit tetap menjalankan validasi HTML5 & handler onsubmit (mis. validateMinMax).
     */
    async submitForm(actionSubstr: string) {
        const form = this.page.locator(`form[action*="${actionSubstr}"]`).first();
        await form.evaluate((f: HTMLFormElement) => f.requestSubmit());
        await this.page.waitForLoadState('networkidle').catch(() => { });
    }

    /**
     * Submit form yang MEMBUNGKUS modal tertentu (form:has(backdrop modal)). Presisi — menghindari
     * salah target ke form hapus/edit lain yang action-nya mirip. Menjalankan validasi & onsubmit.
     */
    async submitModal(id: string) {
        const form = this.page.locator(`form:has([x-show="modal === '${id}'"])`).first();
        await form.evaluate((f: HTMLFormElement) => f.requestSubmit());
        await this.page.waitForLoadState('networkidle').catch(() => { });
    }

    // ─── Navigasi ──────────────────────────────────────────────────
    async gotoDashboard() {
        await this.page.goto('/iot', { timeout: 120000 }).catch(() => { });
        await this.expectToBeOnDashboard();
    }

    async gotoSetup() {
        await this.page.goto('/iot/devices', { timeout: 120000 }).catch(() => { });
        await this.expectToBeOnSetupPage();
    }

    /** Alias historis — config & devices kini halaman Setup IoT yang sama. */
    async gotoDevices() { await this.gotoSetup(); }
    async gotoConfig() { await this.gotoSetup(); }

    async gotoMonitoring() {
        await this.page.goto('/iot/monitoring', { timeout: 120000 }).catch(() => { });
        await this.expectToBeOnMonitoringPage();
    }

    async expectToBeOnDashboard() {
        await expect(this.dashboardHeading.first()).toBeVisible({ timeout: 15000 });
    }

    async expectToBeOnSetupPage() {
        await expect(this.setupHeading.first()).toBeVisible({ timeout: 15000 });
    }

    async expectToBeOnMonitoringPage() {
        await expect(this.monitoringHeading.first()).toBeVisible({ timeout: 15000 });
    }

    // ─── Koneksi ───────────────────────────────────────────────────
    async openConnectionModal() {
        await this.addConnectionBtn.first().click({ force: true });
        await expect(this.modal('addConnection')).toBeVisible({ timeout: 8000 });
    }

    /**
     * Buat koneksi via modal. Default MQTT. Mengembalikan nilai identitas yang tampil di kartu.
     */
    async createConnection(options: {
        mode?: 'MQTT' | 'API';
        mqttBrokerUrl?: string;
        mqttPort?: number;
        baseUrl?: string;
        endpointPath?: string;
        authType?: 'none' | 'api_key' | 'bearer' | 'basic';
        authKey?: string;
        expectSuccess?: boolean;
    } = {}) {
        const {
            mode = 'MQTT',
            mqttBrokerUrl = `mqtt://e2e-${Date.now()}.local`,
            mqttPort,
            baseUrl,
            endpointPath,
            authType,
            authKey,
            expectSuccess = true,
        } = options;

        await this.gotoSetup();
        await this.openConnectionModal();
        const m = this.modal('addConnection');

        if (mode === 'API') {
            await m.locator('button').filter({ hasText: /API \/ Antares/i }).first().click();
            if (baseUrl) await m.locator('input[name="baseUrl"]').fill(baseUrl);
            if (endpointPath) await m.locator('input[name="endpointPath"]').fill(endpointPath);
            if (authType) await m.locator('select[name="authType"]').selectOption(authType);
            if (authKey) await m.locator('input[name="authKey"]').fill(authKey);
        } else {
            // MQTT adalah mode default modal.
            await m.locator('button').filter({ hasText: /Laravel subscribe broker MQTT/i }).first().click();
            if (mqttBrokerUrl) await m.locator('input[name="mqttBrokerUrl"]').fill(mqttBrokerUrl);
            if (mqttPort) await m.locator('input[name="mqttPort"]').fill(String(mqttPort));
        }

        await this.modalSubmit('addConnection').click({ force: true });

        if (expectSuccess) {
            const identity = mode === 'API' ? (baseUrl ?? '') : mqttBrokerUrl;
            await expect(this.page.getByText(identity, { exact: false }).first()).toBeVisible({ timeout: 12000 });
        }

        return { mqttBrokerUrl, baseUrl };
    }

    // ─── Device ────────────────────────────────────────────────────
    async openDeviceModal() {
        await this.addDeviceBtn.first().click({ force: true });
        await expect(this.modal('addDevice')).toBeVisible({ timeout: 8000 });
    }

    /** Isi form Add Device di modal aktif (tidak submit). Deterministik: pilih opsi non-kosong & verifikasi nilai. */
    async fillDeviceForm(deviceCode: string, deviceName: string, status: 'active' | 'inactive' | 'maintenance' = 'active') {
        const m = this.modal('addDevice');
        const codeInput = m.locator('input[name="deviceCode"]');
        const unitSelect = m.locator('select[name="unitBudidayaId"]');
        const connSelect = m.locator('select[name="connectionConfigId"]');

        await codeInput.fill(deviceCode);
        await m.locator('input[name="deviceName"]').fill(deviceName);

        // Pilih opsi valid (bukan placeholder value="") berdasarkan value option ke-2.
        const unitValue = await unitSelect.locator('option').nth(1).getAttribute('value');
        const connValue = await connSelect.locator('option').nth(1).getAttribute('value');
        await unitSelect.selectOption(unitValue!);
        await connSelect.selectOption(connValue!);
        await m.locator('select[name="status"]').selectOption(status);

        // Pastikan field wajib benar-benar terisi sebelum submit (hindari race x-model Alpine).
        await expect(codeInput).toHaveValue(deviceCode);
        await expect(unitSelect).not.toHaveValue('');
        await expect(connSelect).not.toHaveValue('');
    }

    async createDevice(deviceCode: string, deviceName: string, status: 'active' | 'inactive' | 'maintenance' = 'active') {
        // Muat halaman setup fresh agar Alpine & daftar koneksi benar-benar siap (hindari race pasca-reload).
        await this.gotoSetup();
        await this.openDeviceModal();
        await this.fillDeviceForm(deviceCode, deviceName, status);
        await this.modalSubmit('addDevice').click({ force: true });
        // Submit form => full page reload. Tunggu modal hilang & halaman settle lalu verifikasi baris device.
        await this.modal('addDevice').waitFor({ state: 'hidden', timeout: 15000 }).catch(() => { });
        await this.page.waitForLoadState('networkidle').catch(() => { });
        await expect(this.page.getByRole('cell', { name: deviceCode }).first()).toBeVisible({ timeout: 15000 });
    }

    // ─── Parameter Sensor ──────────────────────────────────────────
    async openParameterModal() {
        await this.addParameterBtn.first().click({ force: true });
        await expect(this.modal('addParameter')).toBeVisible({ timeout: 8000 });
    }

    async createParameter(parameterCode: string, parameterName: string, unit = '', description = 'E2E parameter') {
        await this.gotoSetup();
        await this.openParameterModal();
        const m = this.modal('addParameter');
        await m.locator('input[name="parameterCode"]').fill(parameterCode);
        await m.locator('input[name="parameterName"]').fill(parameterName);
        if (unit) await m.locator('input[name="unit"]').fill(unit);
        if (description) await m.locator('textarea[name="description"]').fill(description);
        await expect(m.locator('input[name="parameterCode"]')).toHaveValue(parameterCode);
        await this.submitModal('addParameter');
        // Catatan: tabel "Parameter Sensor" adalah daftar TERKURASI (configuredIotParametersForCommodity),
        // sehingga parameter baru belum tentu tampil di tabel. Sukses diverifikasi via toast.
        await expect(this.toastSuccess.first()).toBeVisible({ timeout: 10000 });
    }

    // ─── Threshold Komoditas ───────────────────────────────────────
    async openThresholdModal() {
        await this.addThresholdBtn.first().click({ force: true });
        await expect(this.modal('addCommodityParam')).toBeVisible({ timeout: 8000 });
    }

    /**
     * Buat threshold komoditas (modal addCommodityParam). Komoditas & parameter dipilih dari opsi ter-seed.
     * expectSuccess=false untuk kasus negatif (mis. min>=max) — hanya submit tanpa assert sukses.
     */
    async createThreshold(min: number, max: number, expectSuccess = true) {
        await this.gotoSetup();
        await this.openThresholdModal();
        const m = this.modal('addCommodityParam');
        const commodityValue = await m.locator('select[name="commodityId"] option').nth(1).getAttribute('value');
        const parameterValue = await m.locator('select[name="parameterId"] option').nth(1).getAttribute('value');
        await m.locator('select[name="commodityId"]').selectOption(commodityValue!);
        await m.locator('select[name="parameterId"]').selectOption(parameterValue!);
        await m.locator('input[name="minValue"]').fill(String(min));
        await m.locator('input[name="maxValue"]').fill(String(max));
        // min>=max dicegah client-side (alert via onsubmit) → auto-accept dialog agar tidak menggantung.
        this.page.on('dialog', dialog => dialog.accept().catch(() => { }));
        await this.submitModal('addCommodityParam');
        if (expectSuccess) {
            await expect(this.toastSuccess.first()).toBeVisible({ timeout: 10000 });
        }
    }

    // ─── Mapping Payload ───────────────────────────────────────────
    async openMappingModal() {
        await this.addMappingBtn.first().click({ force: true });
        await expect(this.modal('addMapping')).toBeVisible({ timeout: 8000 });
    }

    /** Jumlah koneksi dari badge "N koneksi" pada kartu langkah 1. */
    async connectionCount(): Promise<number> {
        const badge = this.page.locator('span').filter({ hasText: /^\s*\d+\s*koneksi\s*$/i }).first();
        const txt = (await badge.textContent().catch(() => '')) || '';
        const match = txt.match(/(\d+)/);
        return match ? parseInt(match[1], 10) : 0;
    }

    /** Hapus kartu koneksi yang memuat teks tertentu (broker/base URL). Native confirm di-accept. */
    async deleteConnectionByText(text: string) {
        const card = this.page.locator('div').filter({ hasText: text }).filter({ has: this.page.locator('form[action*="connections"]') }).last();
        this.page.once('dialog', dialog => dialog.accept());
        await card.locator('button[title="Hapus koneksi"]').first().click({ force: true });
        await this.page.waitForLoadState('networkidle').catch(() => { });
    }

    // ─── Aksi baris (edit/hapus) ───────────────────────────────────
    /** Klik tombol hapus (form + native confirm) pada baris/kartu yang memuat teks tertentu. */
    async deleteByText(text: string, titleRegex: RegExp = /Hapus/i) {
        const container = this.page.locator('tr, div').filter({ hasText: text }).first();
        const deleteBtn = container.locator('button[title]').filter({ has: this.page.locator('svg') })
            .filter({ hasText: '' });
        // Utamakan tombol dengan title Hapus di dalam form
        const formBtn = container.locator('form button[type="submit"]').first();
        this.page.once('dialog', dialog => dialog.accept());
        if (await formBtn.count() > 0) {
            await formBtn.click({ force: true });
        } else {
            await container.locator('button', { hasText: '' }).filter({ hasText: '' }).first().click({ force: true });
        }
        await this.page.waitForTimeout(800);
    }

    /** Hapus baris tabel (device/parameter) berdasarkan teks, tombol delete berupa <form> submit. */
    async deleteRow(text: string) {
        const row = this.page.locator('tr').filter({ hasText: text }).first();
        const deleteForm = row.locator('form').last();
        this.page.once('dialog', dialog => dialog.accept());
        await deleteForm.locator('button[type="submit"]').first().click({ force: true });
        await this.page.waitForTimeout(1000);
    }
}
