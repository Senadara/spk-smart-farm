import { Page, Locator, expect } from '@playwright/test';

export class SpkPage {
    readonly page: Page;

    // Default Routes untuk modul SPK
    readonly spkDashboardUrl = '/spk-analysis';
    readonly spkSuppliersUrl = '/spk-suppliers';

    // --- SPK Dashboard Elements ---
    readonly dashboardHeading: Locator;
    readonly spkFilterForm: Locator;
    readonly komoditasSelect: Locator;
    readonly lokasiSelect: Locator;
    readonly spkResultTable: Locator;
    readonly textDanger: Locator;

    // --- SPK Suppliers Elements ---
    readonly suppliersHeading: Locator;
    readonly supplierCardsOrRow: Locator;
    readonly viewDetailBtn: Locator;
    readonly filterSelect: Locator;
    readonly searchInput: Locator;
    readonly emptyStateMessage: Locator;

    constructor(page: Page) {
        this.page = page;

        this.dashboardHeading = page.locator('h1').filter({ hasText: /Pusat Analisis|Analisa SPK|Dashboard SPK/i });
        this.spkFilterForm = page.locator('form#spkFilterForm');
        this.komoditasSelect = page.locator('select[name="komoditas"]');
        this.lokasiSelect = page.locator('select[name="coop_id"]');
        this.spkResultTable = page.locator('table').first();
        this.textDanger = page.locator('.text-red-500, .text-danger, span.error').filter({ hasText: /wajib|required|pilih/i });

        this.suppliersHeading = page.locator('h1').filter({ hasText: /Supplier|Rekomendasi Supplier/i });
        this.supplierCardsOrRow = page.locator('.grid > div, tbody > tr'); 
        this.viewDetailBtn = page.getByRole('link', { name: /Detail|Lihat/i });
        this.filterSelect = page.locator('select'); 
        this.searchInput = page.locator('input[name="search"]');
        this.emptyStateMessage = page.getByText(/Tidak ada produk yang cocok/i);
    }

    async gotoSpkDashboard() {
        await this.page.goto(this.spkDashboardUrl);
    }

    async gotoSpkSuppliers() {
        await this.page.goto(this.spkSuppliersUrl);
    }
}
