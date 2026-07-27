import { Page, Locator, expect } from '@playwright/test';

/**
 * Page Object — Data Master (REDESIGN Nanda 464c630): "Konfigurasi Data Master" (/data-master)
 * Halaman kini bertab: Parameter Sensor / Ternak / Kategori Stok / Satuan Produk.
 * Default tab = "livestock" (Ternak). Struktur tab Ternak:
 *  - Heading h1 "Konfigurasi Data Master" + breadcrumb "Data Master / <tab label>"
 *  - Header pill statistik: Jenis / Siap / Setup
 *  - Section "Jenis Ternak" (h2): pemilih jenis via <select id="jenis_budidaya_id">
 *    (onchange auto-submit -> URL ?tab=livestock&jenis_budidaya_id=<id>)
 *  - Panel per jenis terpilih (auto-pilih jenis pertama):
 *      * link "Kelola Katalog Sensor" (-> tab=sensor-parameters)
 *      * Section "1. Parameter Lingkungan / IoT"
 *      * Section "2. Konfigurasi Fuzzy Produktivitas"
 *      * Section "3. Konfigurasi Afkir / Akhir Siklus"
 *      * Section "4. Catatan dan Simpan" + tombol "Simpan Konfigurasi"
 *  - Empty state (tanpa jenis): "Belum ada jenis ternak"
 */
export class DataMasterPage {
    readonly page: Page;
    readonly pageTitle: Locator;
    readonly breadcrumb: Locator;
    readonly statPills: Locator;
    readonly jenisTernakHeading: Locator;
    readonly typeSelect: Locator;
    readonly typeOptions: Locator;
    readonly emptyTypeState: Locator;
    readonly katalogSensorLink: Locator;
    readonly envSectionHeading: Locator;
    readonly addParamButton: Locator;
    readonly envCodeInputs: Locator;
    readonly funcSectionHeading: Locator;
    readonly funcCheckboxes: Locator;
    readonly notesTextarea: Locator;
    readonly saveButton: Locator;

    constructor(page: Page) {
        this.page = page;
        this.pageTitle = page.getByRole('heading', { name: 'Konfigurasi Data Master', exact: true });
        this.breadcrumb = page.getByText('Data Master', { exact: false }).first();
        this.statPills = page.getByText(/^(Jenis|Siap|Setup)$/);
        this.jenisTernakHeading = page.getByRole('heading', { name: 'Jenis Ternak', exact: true });
        this.typeSelect = page.locator('select#jenis_budidaya_id');
        this.typeOptions = this.typeSelect.locator('option');
        this.emptyTypeState = page.getByText('Belum ada jenis ternak');
        this.katalogSensorLink = page.getByRole('link', { name: /Kelola Katalog Sensor/i });
        this.envSectionHeading = page.getByRole('heading', { name: /Parameter Lingkungan \/ IoT/i });
        this.addParamButton = page.getByRole('button', { name: /Tambah Baris Sensor/i });
        // Kode sensor per baris = <select name="environment_parameters[i][parameter_code]">
        this.envCodeInputs = page.locator('select[name^="environment_parameters"][name$="[parameter_code]"]');
        this.funcSectionHeading = page.getByRole('heading', { name: /Konfigurasi Fuzzy Produktivitas/i });
        // Checkbox fungsi produktivitas (Data Operasional + Input Fuzzy)
        this.funcCheckboxes = page.locator('input[type="checkbox"][name^="productivity_functions"]');
        this.notesTextarea = page.locator('textarea[name="notes"]');
        this.saveButton = page.getByRole('button', { name: /Simpan Konfigurasi/i });
    }

    async goto() {
        await this.page.goto('/data-master', { waitUntil: 'domcontentloaded' });
        await expect(this.page).toHaveURL(/.*\/data-master/);
    }

    async gotoInvalidType() {
        await this.page.goto('/data-master?tab=livestock&jenis_budidaya_id=nonexistent-type-123', { waitUntil: 'domcontentloaded' });
    }

    async expectPageReady() {
        await expect(this.pageTitle).toBeVisible();
    }

    async hasTypes(): Promise<boolean> {
        if (await this.typeSelect.count() === 0) {
            return false;
        }
        return (await this.typeOptions.count()) > 0;
    }

    /** Pilih jenis ternak pertama pada dropdown; onchange akan submit form (reload dengan query). */
    async selectFirstType() {
        if (await this.hasTypes()) {
            const firstValue = await this.typeOptions.first().getAttribute('value');
            if (firstValue) {
                await Promise.all([
                    this.page.waitForURL(/jenis_budidaya_id=/, { timeout: 30000 }).catch(() => { }),
                    this.typeSelect.selectOption(firstValue),
                ]);
                await this.page.waitForLoadState('domcontentloaded');
            }
        }
    }
}
