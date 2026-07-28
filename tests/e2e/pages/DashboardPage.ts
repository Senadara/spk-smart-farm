import { Page, Locator, expect } from '@playwright/test';

/**
 * Page Object — Dashboard (REDESIGN Nanda 464c630)
 * Struktur baru /dashboard:
 *  - Hero "Prioritas Hari Ini" + 4 overview card (Data Master, Laporan Unit, Jadwal Panen, Penugasan SPK)
 *  - Panel "Kegiatan Wajib Petugas" (Langkah 1) dengan badge "<n> pending"
 *  - Panel "Riwayat Aktivitas Petugas" (Langkah 2)
 *  - Panel "Penyelesaian Sistem" (catatan konfigurasi)
 *  - Panel "Ringkasan Unit": kartu Peternakan & Perkebunan (metrik Unit/Populasi/Laporan)
 *  - Panel "Tren 7 Hari": SVG chart per jenis budidaya + legenda
 */
export class DashboardPage {
    readonly page: Page;
    readonly heading: Locator;
    readonly overviewSection: Locator;
    readonly productivityCards: Locator;
    readonly trendChart: Locator;
    readonly kegiatanWajibPanel: Locator;
    readonly riwayatAktivitasPanel: Locator;
    readonly penyelesaianSistemPanel: Locator;
    readonly ringkasanUnitPanel: Locator;
    readonly peternakanCard: Locator;
    readonly perkebunanCard: Locator;
    readonly penugasanSpkCard: Locator;

    constructor(page: Page) {
        this.page = page;
        this.heading = page.getByText('Prioritas Hari Ini');
        // Section pertama = hero + grid overview cards
        this.overviewSection = page.locator('section').first();
        this.productivityCards = this.overviewSection.locator('article');
        this.trendChart = page.getByText('Tren 7 Hari');
        this.kegiatanWajibPanel = page.getByText('Kegiatan Wajib Petugas');
        this.riwayatAktivitasPanel = page.getByText('Riwayat Aktivitas Petugas');
        this.penyelesaianSistemPanel = page.getByText('Penyelesaian Sistem');
        this.ringkasanUnitPanel = page.getByText('Ringkasan Unit');
        // Kartu unit (link dengan grid metrik 3 kolom) di panel Ringkasan Unit
        this.peternakanCard = page.locator('a:has(.grid.grid-cols-3):has-text("Peternakan")').first();
        this.perkebunanCard = page.locator('a:has(.grid.grid-cols-3):has-text("Perkebunan")').first();
        // Kartu overview "Penugasan SPK" (article yang tampil, bukan tooltip hint tersembunyi)
        this.penugasanSpkCard = page.getByRole('article').filter({ hasText: 'Penugasan SPK' });
    }

    async goto() {
        await this.page.goto('/dashboard', { waitUntil: 'domcontentloaded', timeout: 120000 });
        await expect(this.page).toHaveURL(/.*\/dashboard/);
    }

    async expectPageLoaded() {
        await expect(this.heading.first()).toBeVisible({ timeout: 15000 });
    }

    async expectProductivityCardsVisible() {
        const count = await this.productivityCards.count();
        expect(count).toBeGreaterThanOrEqual(4);
    }

    async expectTrendChartVisible() {
        await expect(this.trendChart.first()).toBeVisible({ timeout: 10000 });
    }

    async expectPeternakanSectionVisible() {
        await expect(this.peternakanCard).toBeVisible({ timeout: 10000 });
    }

    async expectPerkebunanSectionVisible() {
        await expect(this.perkebunanCard).toBeVisible({ timeout: 10000 });
    }

    async expectSpkPanelVisible() {
        await expect(this.penugasanSpkCard.first()).toBeVisible({ timeout: 10000 });
    }

    async expectPenyelesaianSistemVisible() {
        await expect(this.penyelesaianSistemPanel.first()).toBeVisible({ timeout: 10000 });
    }

    async expectNoCrash() {
        const bodyContent = await this.page.locator('body').textContent();
        expect(bodyContent).not.toMatch(/Fatal render error|Error 500/i);
    }

    async expectAllSectionsVisible() {
        await this.expectPageLoaded();
        await this.expectProductivityCardsVisible();
        await this.expectTrendChartVisible();
        await expect(this.kegiatanWajibPanel.first()).toBeVisible();
        await this.expectPeternakanSectionVisible();
        await this.expectPerkebunanSectionVisible();
        await expect(this.page.getByText('Populasi').first()).toBeVisible();
    }
}
