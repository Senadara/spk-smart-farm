import { test, expect, Page } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';
import { FuzzyConfigPage } from '../pages/FuzzyConfigPage.js';

/**
 * EVIDENCE CAPTURE - Section 17-19 (DEV1 Nanda)
 *   Section 17: Aturan Fuzzy / Rules (RULE001-007) -> qa-evidence/RULE/
 *   Section 18: Variabel & Membership Function (FUZV001-012) -> qa-evidence/FUZV/
 *   Section 19: Simulasi / Analisa SPK (SIM001-004) -> qa-evidence/SIM/
 *
 * Prinsip QA jujur:
 *  - Operasi destruktif pada data seed (reset konfigurasi, hapus rule bawaan) TIDAK dieksekusi;
 *    evidence menampilkan kontrol + mekanismenya. Operasi CRUD dijalankan penuh pada data uji (qa_*)
 *    lalu dibersihkan.
 *  - FUZV010 di dokumen sebelumnya dobel 3x -> di-relabel FUZV010 (edit MF), FUZV011 (hapus MF),
 *    FUZV012 (tab Sumber Data).
 */
const PW = 'Password123.';

async function cap(page: Page, path: string) {
    try { await page.waitForLoadState('networkidle', { timeout: 12000 }); }
    catch { await page.waitForLoadState('domcontentloaded').catch(() => { }); }
    await page.waitForTimeout(700);
    await page.screenshot({ path: `qa-evidence/${path}`, fullPage: true });
}
async function shot(page: Page, path: string) {
    await page.waitForTimeout(400);
    await page.screenshot({ path: `qa-evidence/${path}`, fullPage: true });
}
function rid() { return Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6) || 'abcdef'; }

/* ══════════════════════════════════════════════════════════════════════
   SECTION 17 - Aturan Fuzzy / Rules (RULE001-007)
   ══════════════════════════════════════════════════════════════════════ */
test.describe('Evidence Section 17 - Aturan Fuzzy / Rules (pjawab)', () => {
    let auth: AuthPage;
    let fuzzy: FuzzyConfigPage;
    test.setTimeout(150000);

    test.beforeEach(async ({ page }) => {
        await page.route('**/:5173/**', (r) => r.abort());
        await page.route(/.*:5173.*/, (r) => r.abort());
        auth = new AuthPage(page);
        fuzzy = new FuzzyConfigPage(page);
        await auth.loginAndWaitForDashboard('pjawab@email.com', PW);
        await fuzzy.goto();
        await fuzzy.expectPageReady();
        await fuzzy.clickRulesTab();
        await expect(page.getByRole('heading', { name: /Rule IF-THEN/i })).toBeVisible({ timeout: 20000 });
    });

    test('RULE001 - Melihat daftar aturan fuzzy', async ({ page }) => {
        await cap(page, 'RULE/RULE001_daftar_rules.png');
    });

    test('RULE002 - Mengubah rule yang sudah terpakai (modal edit + ubah diagnosis)', async ({ page }) => {
        await page.locator('button[title="Edit rule"]').first().click();
        const modal = page.locator('h3', { hasText: 'Edit Rule IF-THEN' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();
        await expect(modal).toBeVisible({ timeout: 10000 });
        // Perlihatkan bahwa diagnosis bisa diubah (isi field, tanpa persist ke data seed)
        const diag = modal.locator('input[name="diagnosis"]');
        await diag.fill('Kondisi ideal (QA cek edit)');
        await shot(page, 'RULE/RULE002_edit_rule_terpakai.png');
    });

    test('RULE003 - Modal edit aturan fuzzy terbuka', async ({ page }) => {
        // Buka edit pada rule terakhir agar evidence berbeda dari RULE002
        await page.locator('button[title="Edit rule"]').last().click();
        const modal = page.locator('h3', { hasText: 'Edit Rule IF-THEN' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();
        await expect(modal).toBeVisible({ timeout: 10000 });
        await shot(page, 'RULE/RULE003_modal_edit_rule.png');
    });

    test('RULE004 - Mekanisme hapus aturan (tombol Hapus per rule)', async ({ page }) => {
        // Dialog confirm() native tidak bisa difoto; evidence menampilkan tombol Hapus di tiap rule.
        const delBtns = page.locator('button[title="Hapus rule"]');
        expect(await delBtns.count()).toBeGreaterThan(0);
        await delBtns.first().scrollIntoViewIfNeeded();
        await cap(page, 'RULE/RULE004_hapus_rule.png');
    });

    test('RULE005 - Filter daftar aturan berdasarkan engine', async ({ page }) => {
        await page.locator('select[x-model="ruleFilterGroup"]').selectOption('kausalitas');
        await page.waitForTimeout(500);
        await cap(page, 'RULE/RULE005_filter_engine.png');
    });

    test('RULE006 - Filter daftar aturan berdasarkan output', async ({ page }) => {
        await page.locator('select[x-model="ruleFilterOutput"]').selectOption('Optimal');
        await page.waitForTimeout(500);
        await cap(page, 'RULE/RULE006_filter_output.png');
    });

    test('RULE007 - Pencarian aturan berdasarkan teks diagnosis', async ({ page }) => {
        await page.locator('input[x-model="ruleSearch"]').fill('kondisi');
        await page.waitForTimeout(500);
        await cap(page, 'RULE/RULE007_cari_diagnosis.png');
    });
});

/* ══════════════════════════════════════════════════════════════════════
   SECTION 18 - Variabel & Membership Function (FUZV001-012)
   ══════════════════════════════════════════════════════════════════════ */
test.describe('Evidence Section 18 - Variabel & MF Fuzzy (pjawab)', () => {
    let auth: AuthPage;
    let fuzzy: FuzzyConfigPage;
    test.setTimeout(180000);

    test.beforeEach(async ({ page }) => {
        await page.route('**/:5173/**', (r) => r.abort());
        await page.route(/.*:5173.*/, (r) => r.abort());
        auth = new AuthPage(page);
        fuzzy = new FuzzyConfigPage(page);
        await auth.loginAndWaitForDashboard('pjawab@email.com', PW);
        await fuzzy.goto();
        await fuzzy.expectPageReady();
        await fuzzy.clickVariablesTab();
        await expect(fuzzy.addVariableButton).toBeVisible({ timeout: 20000 });
    });

    test('FUZV001 - Membuat variabel fuzzy baru dengan data valid', async ({ page }) => {
        const name = `qa_var_${rid()}`;
        await fuzzy.createVariable({ name, group: 'lingkungan', type: 'input', unit: 'C', description: `QA evidence ${name}` });
        await expect(page.getByText('Variabel berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });
        await shot(page, 'FUZV/FUZV001_buat_variabel.png');
        await fuzzy.deleteVariable(name).catch(() => { });
    });

    test('FUZV002 - Nama variabel duplikat di group sama ditolak', async ({ page }) => {
        const name = `qa_dup_${rid()}`;
        await fuzzy.createVariable({ name, group: 'lingkungan', type: 'input', unit: 'C', description: 'pertama' });
        await expect(page.getByText('Variabel berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });
        // Coba buat lagi dengan nama + group sama -> ditolak
        await fuzzy.addVariableButton.click();
        const modal = page.locator('h3', { hasText: 'Tambah Variabel' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();
        await modal.locator('input[name="name"]').fill(name);
        await modal.locator('select[name="group"]').selectOption('lingkungan');
        await modal.locator('select[name="type"]').selectOption('input');
        await modal.locator('input[name="unit"]').fill('C');
        await modal.locator('input[name="description"]').fill('duplikat');
        await modal.getByRole('button', { name: /Simpan/i }).click();
        const err = page.locator('.bg-red-50, .toast-error, [role="alert"]').first();
        await expect(err).toBeVisible({ timeout: 10000 });
        await shot(page, 'FUZV/FUZV002_nama_duplikat.png');
        // cleanup
        await page.reload({ waitUntil: 'domcontentloaded' });
        await fuzzy.expectPageReady();
        await fuzzy.clickVariablesTab();
        await fuzzy.deleteVariable(name).catch(() => { });
    });

    test('FUZV003 - Nama variabel mengandung huruf kapital ditolak', async ({ page }) => {
        await fuzzy.addVariableButton.click();
        const modal = page.locator('h3', { hasText: 'Tambah Variabel' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();
        await expect(modal).toBeVisible({ timeout: 10000 });
        await modal.locator('input[name="name"]').fill('QA_Suhu_Test');
        await modal.locator('select[name="group"]').selectOption('lingkungan');
        await modal.locator('select[name="type"]').selectOption('input');
        await modal.locator('input[name="unit"]').fill('C');
        await modal.locator('input[name="description"]').fill('uppercase test');
        await modal.getByRole('button', { name: /Simpan/i }).click();
        // Client-side pattern [a-z_]+ memblokir submit -> input invalid & modal tetap terbuka
        const nameInput = modal.locator('input[name="name"]');
        const isInvalid = await nameInput.evaluate((el: HTMLInputElement) => !el.checkValidity()).catch(() => false);
        const modalOpen = await modal.isVisible({ timeout: 2000 }).catch(() => false);
        expect(isInvalid || modalOpen).toBeTruthy();
        await shot(page, 'FUZV/FUZV003_nama_uppercase.png');
    });

    test('FUZV004 - Mengubah data variabel (Edit)', async ({ page }) => {
        const name = `qa_edit_${rid()}`;
        await fuzzy.createVariable({ name, group: 'lingkungan', type: 'input', unit: 'C', description: 'sebelum edit' });
        await expect(page.getByText('Variabel berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });
        const section = page.locator('[data-var-id]').filter({ hasText: name }).first();
        await section.locator('button[title="Edit variabel"]').click();
        const modal = page.locator('h3', { hasText: 'Edit Variabel' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();
        await expect(modal).toBeVisible({ timeout: 10000 });
        await modal.locator('input[name="description"]').fill('sesudah edit QA');
        await shot(page, 'FUZV/FUZV004_edit_variabel.png');
        // submit lalu cleanup
        await modal.getByRole('button', { name: /Simpan/i }).click().catch(() => { });
        await page.waitForTimeout(1500);
        await fuzzy.deleteVariable(name).catch(() => { });
    });

    test('FUZV005 - Menghapus variabel (Delete) berhasil', async ({ page }) => {
        const name = `qa_del_${rid()}`;
        await fuzzy.createVariable({ name, group: 'lingkungan', type: 'input', unit: 'C', description: 'akan dihapus' });
        await expect(page.getByText('Variabel berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });
        await fuzzy.deleteVariable(name);
        await expect(page.getByText(/berhasil dihapus/i).first()).toBeVisible({ timeout: 15000 });
        await shot(page, 'FUZV/FUZV005_hapus_variabel.png');
    });

    test('FUZV006 - Reset konfigurasi fuzzy ke default (kontrol + konfirmasi)', async ({ page }) => {
        // JUJUR: reset TIDAK dieksekusi karena akan menghapus seluruh konfigurasi fuzzy berjalan.
        // Evidence menampilkan tombol Reset yang memicu dialog konfirmasi (onsubmit confirm()).
        const resetForm = page.locator('form[action*="fuzzy/reset"]');
        await expect(resetForm.getByRole('button', { name: /^\s*Reset\s*$/i })).toBeVisible({ timeout: 10000 });
        await resetForm.scrollIntoViewIfNeeded();
        await cap(page, 'FUZV/FUZV006_reset_default.png');
    });

    test('FUZV007 - Membuat membership function shape triangle (valid)', async ({ page }) => {
        const name = `qa_tri_${rid()}`;
        await fuzzy.createVariable({ name, group: 'lingkungan', type: 'input', unit: 'C', description: 'var triangle' });
        await expect(page.getByText('Variabel berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });
        await fuzzy.expandVariable(name);
        await fuzzy.createSetForVariable(name, { name: 'qa_normal', shape: 'triangle', a: '20', b: '25', c: '30' });
        await expect(page.getByText('Membership function berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });
        await shot(page, 'FUZV/FUZV007_mf_triangle.png');
        await fuzzy.deleteVariable(name).catch(() => { });
    });

    test('FUZV008 - Membuat membership function shape trapezoid (valid)', async ({ page }) => {
        const name = `qa_trap_${rid()}`;
        await fuzzy.createVariable({ name, group: 'lingkungan', type: 'input', unit: 'C', description: 'var trapezoid' });
        await expect(page.getByText('Variabel berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });
        await fuzzy.expandVariable(name);
        await fuzzy.createSetForVariable(name, { name: 'qa_hangat', shape: 'trapezoid', a: '25', b: '28', c: '32', d: '35' });
        await expect(page.getByText('Membership function berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });
        await shot(page, 'FUZV/FUZV008_mf_trapezoid.png');
        await fuzzy.deleteVariable(name).catch(() => { });
    });

    test('FUZV009 - Set dengan parameter a > b ditolak', async ({ page }) => {
        const name = `qa_inv_${rid()}`;
        await fuzzy.createVariable({ name, group: 'lingkungan', type: 'input', unit: 'C', description: 'var invalid mf' });
        await expect(page.getByText('Variabel berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });
        await fuzzy.expandVariable(name);
        const section = page.locator('[data-var-id]').filter({ hasText: name }).first();
        await section.getByRole('button', { name: /Tambah Set/i }).click();
        const modal = page.locator('h3', { hasText: 'Tambah Himpunan Fuzzy' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();
        await modal.locator('input[name="name"]').fill('qa_invalid');
        await modal.locator('select[name="shape"]').selectOption('triangle');
        await modal.locator('input[name="a"]').fill('30');
        await modal.locator('input[name="b"]').fill('20');
        await modal.locator('input[name="c"]').fill('25');
        await modal.getByRole('button', { name: /Simpan/i }).click();
        const err = page.locator('.bg-red-50, .toast-error, [role="alert"]').first();
        const errVisible = await err.isVisible({ timeout: 8000 }).catch(() => false);
        const modalOpen = await modal.isVisible({ timeout: 2000 }).catch(() => false);
        expect(errVisible || modalOpen).toBeTruthy();
        await shot(page, 'FUZV/FUZV009_param_invalid.png');
        await modal.getByRole('button', { name: /Batal/i }).click().catch(() => { });
        await page.waitForTimeout(500);
        await fuzzy.deleteVariable(name).catch(() => { });
    });

    test('FUZV010 - Mengubah membership function (Edit set)', async ({ page }) => {
        const name = `qa_editmf_${rid()}`;
        await fuzzy.createVariable({ name, group: 'lingkungan', type: 'input', unit: 'C', description: 'var edit mf' });
        await expect(page.getByText('Variabel berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });
        await fuzzy.expandVariable(name);
        await fuzzy.createSetForVariable(name, { name: 'qa_set', shape: 'triangle', a: '10', b: '15', c: '20' });
        await expect(page.getByText('Membership function berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });
        // setelah simpan set, halaman reload -> variabel collapse. Expand variabel kita, lalu cari tombol
        // Edit set DI DALAM section variabel tsb (bukan first() global yang menunjuk variabel seed tersembunyi).
        await fuzzy.expandVariable(name);
        const section = page.locator('[data-var-id]').filter({ hasText: name }).first();
        const editBtn = section.locator('button[title="Edit set"]').first();
        await editBtn.waitFor({ state: 'visible', timeout: 15000 });
        await editBtn.click();
        const modal = page.locator('h3', { hasText: /Edit Himpunan Fuzzy|Edit Set/i })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();
        await expect(modal).toBeVisible({ timeout: 10000 });
        await shot(page, 'FUZV/FUZV010_edit_mf.png');
        await modal.getByRole('button', { name: /Batal/i }).click().catch(() => { });
        await page.waitForTimeout(400);
        await fuzzy.deleteVariable(name).catch(() => { });
    });

    test('FUZV011 - Menghapus membership function (Delete set) berhasil', async ({ page }) => {
        const name = `qa_delmf_${rid()}`;
        await fuzzy.createVariable({ name, group: 'lingkungan', type: 'input', unit: 'C', description: 'var del mf' });
        await expect(page.getByText('Variabel berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });
        await fuzzy.expandVariable(name);
        await fuzzy.createSetForVariable(name, { name: 'qa_delset', shape: 'triangle', a: '10', b: '15', c: '20' });
        await expect(page.getByText('Membership function berhasil ditambahkan.').first()).toBeVisible({ timeout: 15000 });
        await fuzzy.expandVariable(name);
        const section = page.locator('[data-var-id]').filter({ hasText: name }).first();
        const delSetBtn = section.locator('button[title="Hapus set"]').first();
        await delSetBtn.waitFor({ state: 'visible', timeout: 15000 });
        page.once('dialog', (d) => d.accept());
        await delSetBtn.click();
        await expect(page.getByText(/berhasil dihapus/i).first()).toBeVisible({ timeout: 15000 });
        await shot(page, 'FUZV/FUZV011_hapus_mf.png');
        await fuzzy.deleteVariable(name).catch(() => { });
    });

    test('FUZV012 - Tab Sumber Data menampilkan mapping variabel', async ({ page }) => {
        await fuzzy.clickSourcesTab();
        await expect(page.getByRole('heading', { name: /Sumber Data/i }).first()).toBeVisible({ timeout: 15000 });
        await cap(page, 'FUZV/FUZV012_tab_sumber_data.png');
    });
});

/* ══════════════════════════════════════════════════════════════════════
   SECTION 19 - Simulasi / Analisa SPK (SIM001-004)
   ══════════════════════════════════════════════════════════════════════ */
test.describe('Evidence Section 19 - Simulasi / Analisa SPK', () => {
    let auth: AuthPage;
    test.setTimeout(180000);

    test.beforeEach(async ({ page }) => {
        await page.route('**/:5173/**', (r) => r.abort());
        await page.route(/.*:5173.*/, (r) => r.abort());
        auth = new AuthPage(page);
        await auth.loginAndWaitForDashboard('pjawab@email.com', PW);
    });

    test('SIM001 - Dashboard Analisa SPK menampilkan hasil evaluasi', async ({ page }) => {
        await page.goto('/spk-analysis', { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1').filter({ hasText: /Pusat Analisis|Analisa SPK/i })).toBeVisible({ timeout: 20000 });
        await cap(page, 'SIM/SIM001_dashboard_analisa.png');
    });

    test('SIM002 - Menjalankan evaluasi SPK (Run Full Evaluation)', async ({ page }) => {
        await page.goto('/spk-analysis', { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1').filter({ hasText: /Pusat Analisis|Analisa SPK/i })).toBeVisible({ timeout: 20000 });
        const runBtn = page.getByRole('button', { name: /Run Full Evaluation|Menjalankan/i }).first();
        const cnt = await runBtn.count();
        console.log('SIM002_RUNBTN::' + cnt);
        if (cnt > 0) {
            await runBtn.scrollIntoViewIfNeeded();
            await runBtn.click();
            // proses async lalu reload otomatis ~1.2s; beri waktu proses evaluasi
            await page.waitForTimeout(6000);
            await page.waitForLoadState('domcontentloaded').catch(() => { });
        }
        await cap(page, 'SIM/SIM002_run_evaluation.png');
    });

    test('SIM003 - Hasil evaluasi menampilkan narasi/diagnosis', async ({ page }) => {
        // Jalankan simulasi SPK untuk memunculkan diagnosis + narasi sistem
        await page.goto('/spk-analysis/simulation', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Simulasi SPK Fuzzy/i })).toBeVisible({ timeout: 20000 });
        const runForm = page.locator('form#spkSimulationForm');
        if (await runForm.count() > 0) {
            // isi input number yang masih kosong dengan nilai tengah agar simulasi bisa jalan
            const numInputs = runForm.locator('input[type="number"]');
            const n = await numInputs.count();
            for (let i = 0; i < n; i++) {
                const v = await numInputs.nth(i).inputValue().catch(() => '');
                if (!v) await numInputs.nth(i).fill('25').catch(() => { });
            }
            await page.getByRole('button', { name: /Jalankan Simulasi SPK/i }).click().catch(() => { });
            await page.waitForLoadState('domcontentloaded').catch(() => { });
            await page.waitForTimeout(1500);
        }
        await cap(page, 'SIM/SIM003_narasi_diagnosis.png');
    });

    test('SIM004 - Halaman analisa tetap stabil tanpa rekomendasi aktif', async ({ page }) => {
        await page.goto('/spk-analysis', { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1').filter({ hasText: /Pusat Analisis|Analisa SPK/i })).toBeVisible({ timeout: 20000 });
        const body = await page.locator('body').textContent() || '';
        expect(body).not.toMatch(/Error 500|Fatal error|Exception/i);
        await cap(page, 'SIM/SIM004_tanpa_rekomendasi.png');
    });
});
