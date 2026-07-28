import { test, expect, Page } from '@playwright/test';
import { DataMasterPage } from '../pages/DataMasterPage.js';
import { AuthPage } from '../pages/AuthPage.js';

/**
 * Modul Data Master Ternak — Form Konfigurasi (REWRITE)
 * Menguji form "Konfigurasi Data Master Ternak" baru: parameter lingkungan/IoT
 * (tambah/hapus baris), pilihan fungsi produktivitas, catatan, dan tombol Simpan.
 * Menggantikan spec lama "Blok Kebun" yang sudah tidak ada di implementasi.
 */
test.describe('Data Master Ternak - Form Konfigurasi', () => {
    let dm: DataMasterPage;

    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }, testInfo) => {
        testInfo.setTimeout(90000);
        page.setDefaultTimeout(30000);
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());
        dm = new DataMasterPage(page);
        await dm.goto();
        await dm.selectFirstType();
    });

    test('Positif - Section "Parameter Lingkungan / IoT" & tombol "Tambah Parameter" tampil', async () => {
        await expect(dm.envSectionHeading).toBeVisible();
        await expect(dm.addParamButton).toBeVisible();
    });

    test('Positif - Klik "Tambah Parameter" menambah satu baris parameter', async () => {
        const before = await dm.envCodeInputs.count();
        await dm.addParamButton.click();
        await expect(dm.envCodeInputs).toHaveCount(before + 1);
    });

    test('Positif - Pilih kode sensor pada baris parameter mengisi Nama & Unit otomatis', async ({ page }) => {
        // Desain terbaru: Kode sensor = <select> dari katalog Parameter Sensor.
        // Memilih kode akan mengisi Nama parameter & Unit secara otomatis (hidden + tampilan).
        if (await dm.envCodeInputs.count() === 0) {
            await dm.addParamButton.click();
        }
        const codeSelect = dm.envCodeInputs.first();
        // opsi valid (selain placeholder "" / "Pilih sensor")
        const optionValues = await codeSelect.locator('option').evaluateAll(
            (opts) => opts.map((o) => (o as HTMLOptionElement).value).filter((v) => v)
        );
        if (optionValues.length === 0) {
            test.skip(true, 'Katalog Parameter Sensor kosong; tidak ada kode untuk dipilih');
        }
        await codeSelect.selectOption(optionValues[0]);
        await page.waitForTimeout(300);
        // Nilai kode terpilih & hidden parameter_name terisi otomatis
        await expect(codeSelect).toHaveValue(optionValues[0]);
        const hiddenName = page.locator('input[type="hidden"][name$="[parameter_name]"]').first();
        await expect(hiddenName).not.toHaveValue('');
    });

    test('Positif - Section "Fungsi Produktivitas Tetap" tampil dengan daftar fungsi', async ({ page }) => {
        await expect(dm.funcSectionHeading).toBeVisible();
        const count = await dm.funcCheckboxes.count();
        expect(count).toBeGreaterThanOrEqual(1);
    });

    test('Positif - Checkbox fungsi produktivitas dapat di-toggle', async () => {
        const cb = dm.funcCheckboxes.first();
        const wasChecked = await cb.isChecked();
        await cb.setChecked(!wasChecked);
        await expect(cb).toBeChecked({ checked: !wasChecked });
    });

    test('Positif - Textarea "Catatan konfigurasi" dapat diisi', async () => {
        await expect(dm.notesTextarea).toBeVisible();
        await dm.notesTextarea.fill('Catatan QA otomatis');
        await expect(dm.notesTextarea).toHaveValue('Catatan QA otomatis');
    });

    test('Positif - Tombol "Simpan Konfigurasi" tersedia dan aktif (schema siap)', async () => {
        await expect(dm.saveButton).toBeVisible();
        await expect(dm.saveButton).toBeEnabled();
    });

    test('Negatif - Hapus baris parameter saat hanya satu baris tetap menyisakan satu baris', async () => {
        // Reset ke satu baris: tambah lalu hapus hingga sisa 1, lalu hapus lagi
        while (await dm.envCodeInputs.count() < 1) {
            await dm.addParamButton.click();
        }
        // Kurangi hingga tepat 1 baris
        let count = await dm.envCodeInputs.count();
        while (count > 1) {
            await dm.page.locator('button[title="Hapus parameter"]').first().click();
            count = await dm.envCodeInputs.count();
        }
        // Hapus baris terakhir → guard mempertahankan minimal 1 baris kosong
        await dm.page.locator('button[title="Hapus parameter"]').first().click();
        await expect(dm.envCodeInputs).toHaveCount(1);
    });
});

// ============================================================
// Uji Fungsional Mendalam - digabung dari func-datamaster.spec.ts (sebelumnya section 26.3)
// ============================================================

const PW = 'Password123.';

async function cap(page: Page, path: string) {
    try { await page.waitForLoadState('networkidle', { timeout: 10000 }); }
    catch { await page.waitForLoadState('domcontentloaded').catch(() => { }); }
    await page.waitForTimeout(500);
    await page.screenshot({ path: `qa-evidence/${path}`, fullPage: true });
}
async function bodyText(page: Page): Promise<string> {
    return (await page.locator('body').innerText().catch(() => '')) || '';
}

test.describe('FUNC Data Master (pjawab)', () => {
    test.setTimeout(160000);
    test.beforeEach(async ({ page }) => {
        await page.route('**/:5173/**', (r) => r.abort());
        await page.route(/.*:5173.*/, (r) => r.abort());
        const auth = new AuthPage(page);
        await auth.loginAndWaitForDashboard('pjawab@email.com', PW);
        await page.goto('/data-master', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(800);
    });

    test('DMF001 - Simpan Konfigurasi Data Master VALID -> tersimpan', async ({ page }) => {
        // Desain terbaru: Kode sensor = <select> dari katalog; Nama/Unit auto-fill.
        const codeSelects = page.locator('select[name^="environment_parameters"][name$="[parameter_code]"]');
        // pastikan minimal 1 baris parameter dengan kode terpilih
        if (await codeSelects.count() === 0) {
            await page.getByRole('button', { name: /Tambah Baris Sensor/i }).click();
            await page.waitForTimeout(400);
        }
        const firstSelect = codeSelects.first();
        const opts = await firstSelect.locator('option').evaluateAll(
            (o) => o.map((x) => (x as HTMLOptionElement).value).filter((v) => v)
        );
        // jika baris pertama belum ada kode, pilih kode valid pertama
        if (opts.length > 0 && !(await firstSelect.inputValue())) {
            await firstSelect.selectOption(opts[0]);
            await page.waitForTimeout(300);
        }
        // set min/max valid pada baris pertama
        await page.locator('input[name$="[min_value]"]').first().fill('20');
        await page.locator('input[name$="[max_value]"]').first().fill('30');
        // pastikan minimal 1 fungsi operasional tercentang
        const funcOps = page.locator('input[type="checkbox"][name$="[is_active]"]');
        if (await funcOps.count() > 0) {
            const f = funcOps.first();
            if (!(await f.isChecked())) await f.check().catch(() => { });
        }
        await page.getByRole('button', { name: /Simpan Konfigurasi/i }).click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(1500);
        const body = await bodyText(page);
        const ok = /berhasil disimpan/i.test(body);
        console.log('DMF001:: simpanSukses=' + ok + ' url=' + page.url());
        expect(ok).toBeTruthy();
        await cap(page, 'MASTER/DMF001_simpan_valid.png');
    });

    test('DMF002 - Simpan dengan MIN >= MAX -> ditolak (validasi)', async ({ page }) => {
        const codeCount = await page.locator('select[name^="environment_parameters"][name$="[parameter_code]"]').count();
        expect(codeCount).toBeGreaterThan(0);
        // set min > max pada baris pertama
        await page.locator('input[name$="[min_value]"]').first().fill('999');
        await page.locator('input[name$="[max_value]"]').first().fill('1');
        await page.getByRole('button', { name: /Simpan Konfigurasi/i }).click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(1500);
        const body = await bodyText(page);
        const sukses = /Data Master ternak berhasil disimpan/i.test(body);
        const adaError = /(minimum harus lebih kecil|min.*max|lebih kecil dari maksimum)/i.test(body);
        console.log('DMF002:: sukses=' + sukses + ' adaErrorMinMax=' + adaError);
        expect(sukses).toBeFalsy();
        await cap(page, 'MASTER/DMF002_min_lebih_besar_max.png');
    });

    test('DMF003 - Fungsi produktivitas OPSIONAL: simpan tanpa fungsi tetap berhasil', async ({ page }) => {
        // Fakta implementasi (DataMasterController@store): validasi hanya mewajibkan
        // environment_parameters (min 1); fungsi produktivitas TIDAK diwajibkan (opsional).
        // Maka menyimpan tanpa mencentang fungsi apa pun harus tetap berhasil.
        const funcs = page.locator('input[type="checkbox"][name^="productivity_functions"]');
        const fCount = await funcs.count();
        for (let i = 0; i < fCount; i++) { await funcs.nth(i).uncheck().catch(() => { }); }
        // pastikan baris parameter lingkungan tetap valid (min/max terisi)
        await page.locator('input[name$="[min_value]"]').first().fill('20');
        await page.locator('input[name$="[max_value]"]').first().fill('30');
        await page.getByRole('button', { name: /Simpan Konfigurasi/i }).click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(1500);
        const body = await bodyText(page);
        const sukses = /berhasil disimpan/i.test(body);
        console.log('DMF003:: sukses=' + sukses + ' (fungsi opsional) url=' + page.url());
        expect(sukses).toBeTruthy();
        await cap(page, 'MASTER/DMF003_tanpa_fungsi_opsional.png');
    });
});
