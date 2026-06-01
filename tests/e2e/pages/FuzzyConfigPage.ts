import { Locator, Page, expect } from '@playwright/test';

export class FuzzyConfigPage {
    readonly page: Page;
    readonly pageTitle: Locator;
    readonly variablesTab: Locator;
    readonly rulesTab: Locator;
    readonly sourcesTab: Locator;
    readonly addVariableButton: Locator;
    readonly addRuleButton: Locator;

    constructor(page: Page) {
        this.page = page;
        this.pageTitle = page.getByRole('heading', { name: /Konfigurasi Fuzzy Mamdani/i });
        this.variablesTab = page.getByRole('button', { name: /Variabel & MF/i });
        this.rulesTab = page.getByRole('button', { name: /Aturan \(Rules\)/i });
        this.sourcesTab = page.getByRole('button', { name: /Sumber Data/i });
        this.addVariableButton = page.getByRole('button', { name: /Tambah Variabel/i });
        this.addRuleButton = page.getByRole('button', { name: /Tambah Rule/i });
    }

    private modalByHeading(title: string): Locator {
        return this.page
            .locator('h3', { hasText: title })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]')
            .first();
    }

    private variableSection(variableName: string): Locator {
        return this.page.locator('[data-var-id]').filter({ hasText: variableName }).first();
    }

    async goto() {
        await this.page.goto('/settings/fuzzy');
        await expect(this.page).toHaveURL(/.*\/settings\/fuzzy/);
    }

    async expectPageReady() {
        await expect(this.pageTitle).toBeVisible();
    }

    async clickVariablesTab() {
        await this.variablesTab.click();
    }

    async clickRulesTab() {
        await this.rulesTab.click();
    }

    async clickSourcesTab() {
        await this.sourcesTab.click();
    }

    async createVariable(params: { name: string; group: string; type: string; unit: string; description: string; }) {
        await this.addVariableButton.click();
        const modal = this.modalByHeading('Tambah Variabel');

        await modal.locator('input[name="name"]').fill(params.name);
        await modal.locator('select[name="group"]').selectOption(params.group);
        await modal.locator('select[name="type"]').selectOption(params.type);
        await modal.locator('input[name="unit"]').fill(params.unit);
        await modal.locator('input[name="description"]').fill(params.description);
        await modal.getByRole('button', { name: /Simpan/i }).click();
    }

    async expandVariable(variableName: string) {
        await this.variableSection(variableName).locator('span').filter({ hasText: variableName }).first().click();
    }

    async createSetForVariable(variableName: string, params: { name: string; shape: 'triangle' | 'trapezoid'; a: string; b: string; c: string; d?: string; }) {
        const section = this.variableSection(variableName);
        await section.getByRole('button', { name: /\+ Tambah Set/i }).click();
        const modal = this.modalByHeading('Tambah Membership Function');

        await modal.locator('input[name="name"]').fill(params.name);
        await modal.locator('select[name="shape"]').selectOption(params.shape);
        await modal.locator('input[name="a"]').fill(params.a);
        await modal.locator('input[name="b"]').fill(params.b);
        await modal.locator('input[name="c"]').fill(params.c);
        if (params.d) {
            await modal.locator('input[name="d"]').fill(params.d);
        }
        await modal.getByRole('button', { name: /Simpan/i }).click();
    }

    async deleteVariable(variableName: string) {
        const section = this.variableSection(variableName);
        await section.getByRole('button', { name: /Hapus variabel/i }).click();
    }

    async expectVariableVisible(variableName: string) {
        await expect(this.variableSection(variableName)).toBeVisible();
    }

    async expectVariableHidden(variableName: string) {
        await expect(this.variableSection(variableName)).toHaveCount(0);
    }
}