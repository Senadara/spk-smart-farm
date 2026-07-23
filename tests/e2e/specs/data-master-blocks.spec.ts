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

    test('Positif - Field parameter (Kode/Nama/Unit) dapat diisi', async ({ page }) => {
        // Pastikan minimal ada satu baris
        if (await dm.envCodeInputs.count() === 0) {
            await dm.addParamButton.click();
        }
        const codeInput = dm.envCodeInputs.first();
        const nameInput = page.locator('input[name$="[parameter_name]"]').first();
        const unitInput = page.locator('input[name$="[unit]"]').first();
        await codeInput.fill('TEMP');
        await nameInput.fill('Suhu kandang');
        await unitInput.fill('C');
        await expect(codeInput).toHaveValue('TEMP');
        await expect(nameInput).toHaveValue('Suhu kandang');
        await expect(unitInput).toHaveValue('C');
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
        // pastikan ada minimal 1 baris parameter + 1 fungsi tercentang
        const codeCount = await page.locator('input[name$="[parameter_code]"]').count();
        if (codeCount === 0) {
            await page.getByRole('button', { name: /Tambah Parameter/i }).click();
            await page.waitForTimeout(400);
            await page.locator('input[name$="[parameter_code]"]').first().fill('QATEMP');
            await page.locator('input[name$="[parameter_name]"]').first().fill('QA Suhu Uji');
        }
        // pastikan minimal 1 fungsi produktivitas tercentang
        const funcs = page.locator('input[name="productivity_function_ids[]"]');
        const fCount = await funcs.count();
        let anyChecked = false;
        for (let i = 0; i < fCount; i++) { if (await funcs.nth(i).isChecked()) { anyChecked = true; break; } }
        if (!anyChecked && fCount > 0) await funcs.first().check().catch(() => { });
        await page.getByRole('button', { name: /Simpan Konfigurasi/i }).click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(1500);
        const body = await bodyText(page);
        const ok = /Data Master ternak berhasil disimpan|berhasil disimpan/i.test(body);
        console.log('DMF001:: simpanSukses=' + ok);
        expect(ok).toBeTruthy();
        await cap(page, 'MASTER/DMF001_simpan_valid.png');
    });

    test('DMF002 - Simpan dengan MIN >= MAX -> ditolak (validasi)', async ({ page }) => {
        const codeCount = await page.locator('input[name$="[parameter_code]"]').count();
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

    test('DMF003 - Simpan TANPA fungsi produktivitas -> ditolak (validasi)', async ({ page }) => {
        const funcs = page.locator('input[name="productivity_function_ids[]"]');
        const fCount = await funcs.count();
        for (let i = 0; i < fCount; i++) { await funcs.nth(i).uncheck().catch(() => { }); }
        await page.getByRole('button', { name: /Simpan Konfigurasi/i }).click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(1500);
        const body = await bodyText(page);
        const sukses = /Data Master ternak berhasil disimpan/i.test(body);
        const adaError = /(minimal satu fungsi|fungsi produktivitas|Pilih minimal)/i.test(body);
        console.log('DMF003:: sukses=' + sukses + ' adaErrorFungsi=' + adaError);
        expect(sukses).toBeFalsy();
        await cap(page, 'MASTER/DMF003_tanpa_fungsi.png');
    });
});
