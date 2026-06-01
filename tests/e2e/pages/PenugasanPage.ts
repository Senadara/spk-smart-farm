import { Locator, Page, expect } from '@playwright/test';

export class PenugasanPage {
    readonly page: Page;
    readonly pageTitle: Locator;
    readonly createButton: Locator;
    readonly activeTab: Locator;
    readonly historyTab: Locator;
    readonly boardHeading: Locator;
    readonly historyHeading: Locator;

    constructor(page: Page) {
        this.page = page;
        this.pageTitle = page.getByRole('heading', { level: 1, name: /Penugasan & Laporan Tindakan/i });
        this.createButton = page.getByRole('button', { name: /Buat Tugas/i });
        this.activeTab = page.getByRole('link', { name: /Papan Tugas Aktif/i });
        this.historyTab = page.getByRole('link', { name: /Arsip & Histori Selesai/i });
        this.boardHeading = page.getByRole('heading', { name: /Board Penugasan/i });
        this.historyHeading = page.getByRole('heading', { name: /Histori & Arsip Tugas/i });
    }

    private modalByHeading(title: string): Locator {
        return this.page
            .locator('h3', { hasText: title })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]')
            .first();
    }

    async goto() {
        await this.page.goto('/penugasan?tab=active');
        await expect(this.page).toHaveURL(/.*\/penugasan\?tab=active/);
    }

    async expectPageReady() {
        await expect(this.pageTitle).toBeVisible();
        await expect(this.boardHeading).toBeVisible();
    }

    async openCreateModal() {
        await this.createButton.click();
        await expect(this.modalByHeading('Buat Tugas Baru')).toBeVisible();
    }

    async createTask(params: { title: string; description: string; priority?: 'urgent' | 'high' | 'medium' | 'low'; }) {
        await this.openCreateModal();
        const modal = this.modalByHeading('Buat Tugas Baru');

        await modal.locator('input[name="title"]').fill(params.title);
        await modal.locator('textarea[name="description"]').fill(params.description);
        await modal.locator('select[name="priority"]').selectOption(params.priority ?? 'high');
        await modal.getByRole('button', { name: /Simpan|Buat Tugas/i }).click();
    }

    async taskLink(title: string): Promise<Locator> {
        return this.page.locator('a[href*="/penugasan/"]').filter({ hasText: title }).first();
    }

    async openTaskDetail(title: string) {
        const link = await this.taskLink(title);
        if (await link.count() > 0) {
            await link.click();
            return;
        }

        // Fallback: click any visible text containing the title
        const textLocator = this.page.getByText(title).first();
        await textLocator.click();
    }

    async expectTaskVisible(title: string) {
        const link = await this.taskLink(title);
        const count = await link.count();
        if (count > 0) {
            await expect(link).toBeVisible();
            return;
        }

        // Fallback: look for any element containing the title text
        const textLocator = this.page.getByText(title).first();
        await expect(textLocator).toBeVisible();
    }

    async goToHistoryTab() {
        await this.historyTab.click();
        await expect(this.page).toHaveURL(/.*tab=history/);
        await expect(this.historyHeading).toBeVisible();
    }

    async historyRow(title: string): Promise<Locator> {
        return this.page.locator('tbody tr').filter({ hasText: title }).first();
    }

    async startTask() {
        await this.page.getByRole('button', { name: /Mulai Kerjakan/i }).click();
    }

    async openReportModal() {
        await this.page.getByRole('button', { name: /Kirim Laporan/i }).click();
        await expect(this.modalByHeading('Kirim Laporan Pengerjaan')).toBeVisible();
    }

    async submitReport(params: { description: string; photo?: string; statusUpdate?: 'in_progress' | 'done'; }) {
        await this.openReportModal();
        const modal = this.modalByHeading('Kirim Laporan Pengerjaan');

        await modal.locator('textarea[name="description"]').fill(params.description);
        if (params.photo) {
            await modal.locator('input[name="photo"]').fill(params.photo);
        }
        await modal.locator('select[name="status_update"]').selectOption(params.statusUpdate ?? 'done');
        await modal.getByRole('button', { name: /Kirim Laporan/i }).click();
    }
}