import { Page, Locator, expect } from '@playwright/test';

export class DataMasterPage {
    readonly page: Page;
    readonly pageTitle: Locator;
    readonly infoBanner: Locator;
    readonly usersTab: Locator;
    readonly kebunTab: Locator;
    readonly usersSearchInput: Locator;
    readonly usersRoleSelect: Locator;
    readonly usersTable: Locator;
    readonly usersRows: Locator;
    readonly kebunSearchInput: Locator;
    readonly kebunJenisSelect: Locator;
    readonly kebunTable: Locator;
    readonly kebunRows: Locator;

    constructor(page: Page) {
        this.page = page;
        this.pageTitle = page.getByRole('heading', { name: 'Data Master Operasional' });
        this.infoBanner = page.locator('div.bg-amber-50 p.text-sm.text-amber-800').first();
        this.usersTab = page.getByRole('tab', { name: 'Daftar Pengguna' });
        this.kebunTab = page.getByRole('tab', { name: 'Blok Kebun' });
        this.usersSearchInput = page.getByPlaceholder('Cari nama atau email...');
        this.usersRoleSelect = page.locator('select').first();
        this.usersTable = page.locator('div[x-show="activeTab === \'users\'"] table');
        this.usersRows = page.locator('div[x-show="activeTab === \'users\'"] tbody tr:visible');
        this.kebunSearchInput = page.getByPlaceholder('Cari nama atau lokasi...');
        this.kebunJenisSelect = page.locator('div[x-show="activeTab === \'kebun\'"] select');
        this.kebunTable = page.locator('div[x-show="activeTab === \'kebun\'"] table');
        this.kebunRows = page.locator('div[x-show="activeTab === \'kebun\'"] tbody tr:visible');
    }

    async goto() {
        await this.page.goto('/data-master', { waitUntil: 'domcontentloaded', timeout: 120000 });
        await expect(this.page).toHaveURL(/.*\/data-master/);
    }

    async expectPageReady() {
        await expect(this.pageTitle).toBeVisible();
        await expect(this.infoBanner).toBeVisible();
        await expect(this.usersTab).toBeVisible();
        await expect(this.kebunTab).toBeVisible();
    }

    async clickUsersTab() {
        await this.usersTab.click();
    }

    async clickKebunTab() {
        await this.kebunTab.click();
    }

    async getVisibleUserRowCount() {
        return await this.usersRows.count();
    }

    async getVisibleKebunRowCount() {
        return await this.kebunRows.count();
    }

    async searchUsers(query: string) {
        await this.usersSearchInput.fill(query);
    }

    async filterUsersByRole(role: string) {
        await this.usersRoleSelect.selectOption(role);
    }

    async searchKebun(query: string) {
        await this.kebunSearchInput.fill(query);
    }

    async filterKebunByJenis(jenis: string) {
        await this.kebunJenisSelect.selectOption({ label: jenis });
    }
}