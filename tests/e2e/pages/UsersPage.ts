import { Locator, Page, expect } from '@playwright/test';

export class UsersPage {
    readonly page: Page;
    readonly pageTitle: Locator;
    readonly addButton: Locator;
    readonly emptyStateTitle: Locator;
    readonly table: Locator;

    // Generic modal submit button locator (searches visible 'Simpan' buttons inside modals)
    // Use a getter to always resolve the currently-visible submit button to avoid stale locators
    get submitButton(): Locator {
        return this.page.getByRole('button', { name: /Simpan/i }).first();
    }

    constructor(page: Page) {
        this.page = page;
        this.pageTitle = page.getByRole('heading', { name: /Manajemen Petugas/i });
        this.addButton = page.getByRole('button', { name: /Tambah Petugas/i });
        this.emptyStateTitle = page.getByRole('heading', { name: /Belum ada Petugas/i });
        this.table = page.getByRole('table');
    }

    private modalByHeading(title: string): Locator {
        return this.page
            .locator('h3', { hasText: title })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]')
            .first();
    }

    private rowByText(name: string): Locator {
        return this.page.locator('tr').filter({ hasText: name }).first();
    }

    async goto() {
        await this.page.goto('/users');
        await expect(this.page).toHaveURL(/.*\/users/);
    }

    async expectPageReady() {
        await expect(this.pageTitle).toBeVisible();
    }

    async expectEmptyStateVisible() {
        await expect(this.emptyStateTitle).toBeVisible();
    }

    async expectTableVisible() {
        await expect(this.table).toBeVisible();
    }

    async createUser(params: { name: string; email: string; password: string; }) {
        await this.addButton.click();
        const modal = this.modalByHeading('Tambah Petugas Baru');

        await modal.locator('input[name="name"]').fill(params.name);
        await modal.locator('input[name="email"]').fill(params.email);
        await modal.locator('input[name="password"]').fill(params.password);
        await modal.getByRole('button', { name: /Simpan Petugas/i }).click();
    }

    async editUser(currentName: string, params: { name: string; email: string; password?: string; }) {
        const row = this.rowByText(currentName);
        await row.hover();
        await row.locator('button[title="Edit"]').click();

        const modal = this.modalByHeading('Edit Profil Petugas');
        await modal.locator('input[name="name"]').fill(params.name);
        await modal.locator('input[name="email"]').fill(params.email);
        const passwordInput = modal.locator('input[name="password"]');
        if (params.password) {
            await passwordInput.fill(params.password);
        } else {
            await passwordInput.fill('');
        }
        await modal.getByRole('button', { name: /Simpan Perubahan/i }).click();
    }

    async deleteUser(name: string) {
        const row = this.rowByText(name);
        await row.hover();
        await row.locator('button[title="Hapus"]').click();
    }

    async expectRowVisible(name: string) {
        await expect(this.rowByText(name)).toBeVisible();
    }

    async expectRowHidden(name: string) {
        await expect(this.rowByText(name)).toHaveCount(0);
    }
}