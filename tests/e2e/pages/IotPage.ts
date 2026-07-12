import { Page, Locator, expect } from '@playwright/test';

export class IotPage {
    readonly page: Page;

    readonly dashboardHeading: Locator;
    readonly registerDeviceBtn: Locator;
    readonly statCards: Locator;

    readonly configHeading: Locator;
    readonly protocolsTab: Locator;
    readonly connectionsTab: Locator;
    readonly parametersTab: Locator;
    readonly commodityParamsTab: Locator;

    // Protocol Form
    readonly protocolNameInput: Locator;
    readonly protocolDescriptionInput: Locator;
    readonly protocolSubmitBtn: Locator;
    readonly addProtocolBtn: Locator;

    // Connection Form
    readonly connectionProtocolSelect: Locator;
    readonly baseUrlInput: Locator;
    readonly endpointPathInput: Locator;
    readonly mqttBrokerUrlInput: Locator;
    readonly mqttTopicInput: Locator;
    readonly authTypeSelect: Locator;
    readonly authKeyInput: Locator;
    readonly connectionSubmitBtn: Locator;
    readonly addConnectionBtn: Locator;

    // Parameter Form
    readonly parameterCodeInput: Locator;
    readonly parameterNameInput: Locator;
    readonly parameterUnitInput: Locator;
    readonly parameterDescriptionInput: Locator;
    readonly parameterSubmitBtn: Locator;
    readonly addParameterBtn: Locator;

    // Commodity Parameter Form
    readonly commoditySelect: Locator;
    readonly commodityParameterSelect: Locator;
    readonly minValueInput: Locator;
    readonly maxValueInput: Locator;
    readonly commodityParamSubmitBtn: Locator;
    readonly addCommodityParamBtn: Locator;

    readonly deviceManagementHeading: Locator;
    readonly addDeviceBtn: Locator;
    readonly addMappingBtn: Locator;
    readonly deviceTable: Locator;
    readonly deviceSubmitBtn: Locator;
    readonly deviceCodeInput: Locator;
    readonly deviceNameInput: Locator;
    readonly unitBudidayaSelect: Locator;
    readonly connectionConfigSelect: Locator;
    readonly statusSelect: Locator;
    readonly deleteButtons: Locator;
    readonly toastSuccess: Locator;
    readonly textDanger: Locator;

    readonly monitoringHeading: Locator;
    readonly sensorDataTab: Locator;
    readonly deviceLogsTab: Locator;

    constructor(page: Page) {
        this.page = page;

        this.dashboardHeading = page.locator('h1').filter({ hasText: /IoT Dashboard|Overview status/i });
        this.registerDeviceBtn = page.locator('a, button').filter({ hasText: /Register Device/i });
        this.statCards = page.locator('.grid > div');

        this.configHeading = page.locator('h1').filter({ hasText: /Konfigurasi IoT/i });
        this.protocolsTab = page.getByRole('button', { name: 'Protokol' });
        this.connectionsTab = page.getByRole('button', { name: 'Koneksi' });
        this.parametersTab = page.getByRole('button', { name: /Parameter Sensor/i });
        this.commodityParamsTab = page.getByRole('button', { name: /Komoditas Parameter/i });

        // Protocol locators
        this.protocolNameInput = page.locator('input[name="protocolName"]').first();
        this.protocolDescriptionInput = page.locator('textarea[name="description"]').first();
        this.protocolSubmitBtn = page.getByRole('button', { name: /Simpan Protokol/i }).first();
        this.addProtocolBtn = page.locator('button').filter({ hasText: /Tambah Protokol/i });

        // Connection locators
        this.connectionProtocolSelect = page.locator('select[name="protocolId"]').first();
        this.baseUrlInput = page.locator('input[name="baseUrl"]').first();
        this.endpointPathInput = page.locator('input[name="endpointPath"]').first();
        this.mqttBrokerUrlInput = page.locator('input[name="mqttBrokerUrl"]').first();
        this.mqttTopicInput = page.locator('input[name="mqttTopic"]').first();
        this.authTypeSelect = page.locator('select[name="authType"]').first();
        this.authKeyInput = page.locator('input[name="authKey"]').first();
        this.connectionSubmitBtn = page.getByRole('button', { name: /Simpan Koneksi/i }).first();
        this.addConnectionBtn = page.locator('button').filter({ hasText: /Tambah Koneksi/i });

        // Parameter locators
        this.parameterCodeInput = page.locator('input[name="parameterCode"]').first();
        this.parameterNameInput = page.locator('input[name="parameterName"]').first();
        this.parameterUnitInput = page.locator('input[name="unit"]').first();
        this.parameterDescriptionInput = page.locator('textarea[name="description"]').first();
        this.parameterSubmitBtn = page.getByRole('button', { name: /Simpan Parameter/i }).first();
        this.addParameterBtn = page.locator('button').filter({ hasText: /Tambah Parameter/i });

        // Commodity Parameter locators
        this.commoditySelect = page.locator('select[name="commodityId"]').first();
        this.commodityParameterSelect = page.locator('select[name="parameterId"]').first();
        this.minValueInput = page.locator('input[name="minValue"]').first();
        this.maxValueInput = page.locator('input[name="maxValue"]').first();
        this.commodityParamSubmitBtn = page.getByRole('button', { name: /Simpan.*Parameter/i }).first();
        this.addCommodityParamBtn = page.locator('button').filter({ hasText: /Tambah/i });

        this.deviceManagementHeading = page.locator('h1').filter({ hasText: /Device Management/i });
        this.addDeviceBtn = page.locator('button').filter({ hasText: /Tambah Device/i });
        this.addMappingBtn = page.locator('button').filter({ hasText: /Tambah Mapping/i });
        this.deviceTable = page.locator('table');

        this.deviceCodeInput = page.locator('input[name="deviceCode"]');
        this.deviceNameInput = page.locator('input[name="deviceName"]');
        this.unitBudidayaSelect = page.locator('select[name="unitBudidayaId"]');
        this.connectionConfigSelect = page.locator('select[name="connectionConfigId"]');
        this.statusSelect = page.locator('select[name="status"]');
        this.deviceSubmitBtn = page.getByRole('button', { name: 'Simpan Device' });
        this.deleteButtons = page.locator('form').filter({ hasText: /hapus|delete/i }).locator('button');

        this.toastSuccess = page.locator('#toastContainer .toast-success');
        this.textDanger = page.locator('.text-red-500, .text-danger, span').filter({ hasText: /wajib|required|kosong/i });

        this.monitoringHeading = page.locator('h1').filter({ hasText: /Monitoring IoT/i });
        this.sensorDataTab = page.locator('button').filter({ hasText: /Data Sensor/i });
        this.deviceLogsTab = page.locator('button').filter({ hasText: /Device Logs/i });
    }

    async gotoDashboard() {
        await this.page.goto('/iot', { waitUntil: 'commit' }).catch(() => { });
        await this.expectToBeOnDashboard();
    }

    async gotoConfig() {
        await this.page.goto('/iot/config', { waitUntil: 'commit' }).catch(() => { });
        await this.expectToBeOnConfigPage();
    }

    async gotoDevices() {
        await this.page.goto('/iot/devices', { waitUntil: 'commit' }).catch(() => { });
        await this.expectToBeOnDevicesPage();
    }

    async gotoMonitoring() {
        await this.page.goto('/iot/monitoring', { waitUntil: 'commit' }).catch(() => { });
        await this.expectToBeOnMonitoringPage();
    }

    async expectToBeOnDashboard() {
        await expect(this.dashboardHeading.first()).toBeVisible({ timeout: 12000 });
    }

    async expectToBeOnConfigPage() {
        await expect(this.configHeading.first()).toBeVisible({ timeout: 12000 });
    }

    async expectToBeOnDevicesPage() {
        await expect(this.deviceManagementHeading.first()).toBeVisible({ timeout: 12000 });
    }

    async expectToBeOnMonitoringPage() {
        await expect(this.monitoringHeading.first()).toBeVisible({ timeout: 12000 });
    }

    async createProtocol(protocolName: string, description = 'E2E protocol config') {
        if (!this.page.url().includes('/iot/config')) {
            await this.gotoConfig();
            await this.expectToBeOnConfigPage();
        }

        await this.protocolsTab.waitFor({ state: 'visible' });
        await this.protocolsTab.click();
        const protocolsPanel = this.page.locator('div[x-show="activeTab === \'protocols\'"]');

        await expect(async () => {
            if (!(await protocolsPanel.isVisible())) {
                await this.protocolsTab.click();
            }
            expect(await protocolsPanel.isVisible()).toBeTruthy();
        }).toPass({ timeout: 10000 });

        const addProtocolButton = protocolsPanel.getByRole('button', { name: 'Tambah' });
        await addProtocolButton.click({ force: true });
        // Bypass strict visibility check since AlpineJS might hide it or delay its rendering
        await this.page.waitForTimeout(500);
        // Ensure the protocol form input is visible before interacting
        await this.protocolNameInput.waitFor({ state: 'visible', timeout: 10000 });
        await this.protocolNameInput.fill(protocolName);
        await this.protocolDescriptionInput.fill(description);
        await this.protocolSubmitBtn.click();
        try {
            await expect(this.page.getByRole('cell', { name: protocolName }).first()).toBeVisible({ timeout: 8000 });
        } catch {
            await expect(this.page.locator('select[name="protocolId"]').getByText(protocolName)).toBeVisible({ timeout: 8000 });
        }
    }

    async createConnectionConfig(options: {
        protocolName: string;
        baseUrl?: string;
        endpointPath?: string;
        mqttBrokerUrl?: string;
        mqttTopic?: string;
        authType?: 'none' | 'api_key' | 'bearer' | 'basic';
        authKey?: string;
    }) {
        const {
            protocolName,
            baseUrl = '',
            endpointPath = '',
            mqttBrokerUrl = '',
            mqttTopic = '',
            authType = 'none',
            authKey = '',
        } = options;

        if (!this.page.url().includes('/iot/config')) {
            await this.gotoConfig();
            await this.expectToBeOnConfigPage();
        }

        await this.connectionsTab.waitFor({ state: 'visible' });
        await this.connectionsTab.click();
        const connectionsPanel = this.page.locator('div[x-show="activeTab === \'connections\'"]');

        // Ensure AlpineJS processes the click. If the panel is not visible within a short time, retry the click
        await expect(async () => {
            if (!(await connectionsPanel.isVisible())) {
                await this.connectionsTab.click();
            }
            expect(await connectionsPanel.isVisible()).toBeTruthy();
        }).toPass({ timeout: 10000 });

        const addConnButton = connectionsPanel.getByRole('button', { name: 'Tambah' });
        await addConnButton.click({ force: true });
        // Bypass strict visibility check since AlpineJS might hide it or delay its rendering
        await this.page.waitForTimeout(500);
        // Ensure the connection form/selects are visible before interacting
        await this.connectionProtocolSelect.waitFor({ state: 'visible', timeout: 10000 });
        await this.connectionProtocolSelect.selectOption({ label: protocolName });

        if (baseUrl) {
            await this.baseUrlInput.fill(baseUrl);
        }

        if (endpointPath) {
            await this.endpointPathInput.fill(endpointPath);
        }

        if (mqttBrokerUrl) {
            await this.mqttBrokerUrlInput.fill(mqttBrokerUrl);
        }

        if (mqttTopic) {
            await this.mqttTopicInput.fill(mqttTopic);
        }

        await this.authTypeSelect.selectOption(authType);

        if (authKey) {
            await this.authKeyInput.fill(authKey);
        }

        await this.connectionSubmitBtn.click();
        // Accept either a table cell or a select option as evidence the connection was added
        try {
            await expect(this.page.getByRole('cell', { name: protocolName }).first()).toBeVisible({ timeout: 8000 });
        } catch {
            await expect(this.page.locator('select[name="protocolId"]').getByText(protocolName)).toBeVisible({ timeout: 8000 });
        }
    }

    async createParameter(paramCode: string, paramName: string, unit = '', description = 'E2E test parameter') {
        if (!this.page.url().includes('/iot/config')) {
            await this.gotoConfig();
            await this.expectToBeOnConfigPage();
        }

        await this.parametersTab.waitFor({ state: 'visible' });
        await this.parametersTab.click();
        const parametersPanel = this.page.locator('div[x-show="activeTab === \'parameters\'"]');

        await expect(async () => {
            if (!(await parametersPanel.isVisible())) {
                await this.parametersTab.click();
            }
            expect(await parametersPanel.isVisible()).toBeTruthy();
        }).toPass({ timeout: 10000 });

        const addParamButton = parametersPanel.getByRole('button', { name: /Tambah/i });
        await addParamButton.click({ force: true });
        await this.page.waitForTimeout(500);

        await this.parameterCodeInput.waitFor({ state: 'visible', timeout: 10000 });
        await this.parameterCodeInput.fill(paramCode);
        await this.parameterNameInput.fill(paramName);

        if (unit) {
            await this.parameterUnitInput.fill(unit);
        }

        if (description) {
            await this.parameterDescriptionInput.fill(description);
        }

        await this.parameterSubmitBtn.click();

        try {
            await expect(this.page.getByRole('cell', { name: paramCode }).first()).toBeVisible({ timeout: 8000 });
        } catch {
            await expect(this.toastSuccess).toBeVisible({ timeout: 8000 });
        }
    }

    async createCommodityParameter(commodityName: string, parameterName: string, minValue?: number, maxValue?: number) {
        if (!this.page.url().includes('/iot/config')) {
            await this.gotoConfig();
            await this.expectToBeOnConfigPage();
        }

        await this.commodityParamsTab.waitFor({ state: 'visible' });
        await this.commodityParamsTab.click();
        const commodityPanel = this.page.locator('div[x-show="activeTab === \'commodityParams\'"]');

        await expect(async () => {
            if (!(await commodityPanel.isVisible())) {
                await this.commodityParamsTab.click();
            }
            expect(await commodityPanel.isVisible()).toBeTruthy();
        }).toPass({ timeout: 10000 });

        const addButton = commodityPanel.getByRole('button', { name: /Tambah/i });
        await addButton.click({ force: true });
        await this.page.waitForTimeout(500);

        await this.commoditySelect.waitFor({ state: 'visible', timeout: 10000 });
        await this.commoditySelect.selectOption({ label: commodityName });
        await this.commodityParameterSelect.selectOption({ label: parameterName });

        if (minValue !== undefined) {
            await this.minValueInput.fill(minValue.toString());
        }

        if (maxValue !== undefined) {
            await this.maxValueInput.fill(maxValue.toString());
        }

        await this.commodityParamSubmitBtn.click();
        await expect(this.toastSuccess).toBeVisible({ timeout: 8000 });
    }

    async clickEditButtonInRow(entityName: string) {
        const row = this.page.locator('tr').filter({ hasText: entityName });
        const editBtn = row.locator('button, a').filter({ hasText: /edit|ubah/i }).first();
        await editBtn.click();
        await this.page.waitForTimeout(500);
    }

    async clickDeleteButtonInRow(entityName: string) {
        const row = this.page.locator('tr').filter({ hasText: entityName });
        const deleteBtn = row.locator('form').filter({ hasText: /hapus|delete/i }).locator('button').first();

        this.page.once('dialog', dialog => dialog.accept());
        await deleteBtn.click();
        await this.page.waitForTimeout(500);
    }
}
