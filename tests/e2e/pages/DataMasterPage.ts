import { Page, Locator, expect } from '@playwright/test';

/**
 * Page Object — Data Master (REDESIGN Nanda): "Konfigurasi Data Master Ternak" (/data-master)
 * Halaman lama (tab "Daftar Pengguna"/"Blok Kebun") sudah dihapus & diganti total.
 * Struktur baru:
 *  - Heading "Konfigurasi Data Master Ternak" + breadcrumb "Data Master / Ternak"
 *  - 3 kartu statistik: Jenis Ternak / Siap / Perlu Setup
 *  - Aside "Jenis Ternak dari Mobile": daftar jenis (link ?jenis_budidaya_id=<id>)
 *  - Panel kanan (per jenis terpilih): tautan "IoT Device" & "Fuzzy SPK",
 *    form konfigurasi (Parameter Lingkungan/IoT rows + Fungsi Produktivitas + Catatan + Simpan)
 */
export class DataMasterPage {
    readonly page: Page;
    readonly pageTitle: Locator;
    readonly breadcrumb: Locator;
    readonly statJenisTernak: Locator;
    readonly jenisTernakHeading: Locator;
    readonly jenisTernakLinks: Locator;
    readonly emptyTypeState: Locator;
    readonly iotDeviceLink: Locator;
    readonly fuzzyLink: Locator;
    readonly envSectionHeading: Locator;
    readonly addParamButton: Locator;
    readonly envCodeInputs: Locator;
    readonly funcSectionHeading: Locator;
    readonly funcCheckboxes: Locator;
    readonly notesTextarea: Locator;
    readonly saveButton: Locator;

    constructor(page: Page) {
        this.page = page;
        this.pageTitle = page.getByRole('heading', { name: 'Konfigurasi Data Master Ternak' });
        this.breadcrumb = page.getByText('Data Master', { exact: false }).first();
        this.statJenisTernak = page.getByText('Jenis Ternak', { exact: true }).first();
        this.jenisTernakHeading = page.getByRole('heading', { name: /Jenis Ternak dari Mobile/i });
        this.jenisTernakLinks = page.locator('aside a[href*="jenis_budidaya_id="]');
        this.emptyTypeState = page.getByText('Belum ada jenis ternak');
        this.iotDeviceLink = page.getByRole('link', { name: 'IoT Device' });
        this.fuzzyLink = page.getByRole('link', { name: 'Fuzzy SPK' });
        this.envSectionHeading = page.getByRole('heading', { name: /Parameter Lingkungan \/ IoT/i });
        this.addParamButton = page.getByRole('button', { name: /Tambah Parameter/i });
        this.envCodeInputs = page.locator('input[name$="[parameter_code]"]');
        this.funcSectionHeading = page.getByRole('heading', { name: /Fungsi Produktivitas Tetap/i });
        this.funcCheckboxes = page.locator('input[name="productivity_function_ids[]"]');
        this.notesTextarea = page.locator('textarea[name="notes"]');
        this.saveButton = page.getByRole('button', { name: /Simpan Konfigurasi/i });
    }

    async goto() {
        await this.page.goto('/data-master', { waitUntil: 'domcontentloaded' });
        await expect(this.page).toHaveURL(/.*\/data-master/);
    }

    async gotoInvalidType() {
        await this.page.goto('/data-master?jenis_budidaya_id=nonexistent-type-123', { waitUntil: 'domcontentloaded' });
    }

    async expectPageReady() {
        await expect(this.pageTitle).toBeVisible();
    }

    async hasTypes(): Promise<boolean> {
        return (await this.jenisTernakLinks.count()) > 0;
    }

    /** Pilih jenis ternak pertama sehingga panel konfigurasi (form) tampil. */
    async selectFirstType() {
        if (await this.jenisTernakLinks.count() > 0) {
            await this.jenisTernakLinks.first().click();
            await this.page.waitForLoadState('domcontentloaded');
        }
    }
}
