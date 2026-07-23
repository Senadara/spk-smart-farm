import { Locator, Page, expect } from '@playwright/test';

export class PenugasanPage {
    readonly page: Page;
    readonly pageTitle: Locator;
    readonly createButton: Locator;
    readonly activeTab: Locator;
    readonly historyTab: Locator;
    readonly boardHeading: Locator;
    readonly historyHeading: Locator;

    // Stats locators
    readonly statTotal: Locator;
    readonly statTodo: Locator;
    readonly statInProgress: Locator;
    readonly statDone: Locator;
    readonly statOverdue: Locator;

    // Filter locators
    readonly userFilter: Locator;
    readonly priorityFilter: Locator;
    readonly statusFilter: Locator;
    readonly searchInput: Locator;

    constructor(page: Page) {
        this.page = page;
        this.pageTitle = page.locator('h1').filter({ hasText: /Penugasan|Tindakan/i }).first();
        this.createButton = page.locator('button').filter({ hasText: /Buat Tugas/i }).first();
        this.activeTab = page.locator('button, a').filter({ hasText: /Papan Tugas Aktif|Active/i }).first();
        this.historyTab = page.locator('button, a').filter({ hasText: /Arsip|Histori/i }).first();
        this.boardHeading = page.locator('h1, h2').filter({ hasText: /Penugasan|Board|Tugas/i }).first();
        this.historyHeading = page.locator('h1, h2, h3').filter({ hasText: /Histori|Arsip/i }).first();

        // Stats
        this.statTotal = page.locator('text=Total Tugas').locator('xpath=following::span[1]');
        this.statTodo = page.locator('text=To Do').locator('xpath=following::span[1]');
        this.statInProgress = page.locator('text=Dikerjakan').locator('xpath=following::span[1]');
        this.statDone = page.locator('text=Selesai').locator('xpath=following::span[1]');
        this.statOverdue = page.locator('text=Terlambat').locator('xpath=following::span[1]');

        // Filters
        this.userFilter = page.locator('select[name="user_id"]').first();
        this.priorityFilter = page.locator('select[name="priority"]').first();
        this.statusFilter = page.locator('select[name="status"]');
        this.searchInput = page.locator('input[name="search"]').first();
    }

    private modalByHeading(title: string): Locator {
        return this.page
            .locator('h3', { hasText: title })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]')
            .first();
    }

    async goto() {
        await this.page.goto('/penugasan?tab=active', { waitUntil: 'domcontentloaded' });
        await this.page.waitForTimeout(2000);
    }

    async expectPageReady() {
        await expect(this.pageTitle).toBeVisible({ timeout: 30000 });
    }

    async openCreateModal() {
        await this.createButton.click();
        await this.page.waitForTimeout(800);
        await this.page.waitForSelector('h3:has-text("Buat Tugas Baru")', { timeout: 10000 });
    }

    async createTask(params: { title: string; description: string; priority?: 'urgent' | 'high' | 'medium' | 'low'; }) {
        await this.openCreateModal();
        const modal = this.modalByHeading('Buat Tugas Baru');

        await modal.locator('input[name="title"]').first().fill(params.title);
        await modal.locator('textarea[name="description"]').first().fill(params.description);
        await modal.locator('select[name="priority"]').first().selectOption(params.priority ?? 'high');
        await modal.locator('button[type="submit"]').filter({ hasText: /Simpan/i }).first().click();
        await this.page.waitForTimeout(1500);
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
            // Bukti foto laporan adalah <input type="file"> (disimpan ke disk public), BUKAN URL.
            // Unggah PNG 1x1 in-memory agar tidak bergantung file fixture di disk.
            await modal.locator('input[name="photo"]').setInputFiles({
                name: 'bukti-laporan.png',
                mimeType: 'image/png',
                buffer: Buffer.from(
                    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMCAQGVFrfaAAAAAElFTkSuQmCC',
                    'base64',
                ),
            });
        }
        await modal.locator('select[name="status_update"]').selectOption(params.statusUpdate ?? 'done');
        await modal.getByRole('button', { name: /Kirim Laporan/i }).click();
    }
}