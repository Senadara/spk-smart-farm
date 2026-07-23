import { test, expect, Page } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';

/**
 * EVIDENCE - Section 23: Konfigurasi Data Master Ternak (DASH-02) - MASTER001-005
 * Tombol oranye "Konfigurasi Data Master" ada di dashboard Peternakan untuk SEMUA peran
 * (tidak ada guard peran di blade), tetapi rute /data-master dijaga role:pjawab,owner,admin.
 * -> Petugas melihat tombol tetapi klik mengarah ke halaman 403 (defect UX/otorisasi).
 * Diuji untuk kedua peran (petugas & pjawab). Screenshot -> qa-evidence/MASTER/
 */
const PW = 'Password123.';

async function cap(page: Page, path: string) {
    try { await page.waitForLoadState('networkidle', { timeout: 12000 }); }
    catch { await page.waitForLoadState('domcontentloaded').catch(() => { }); }
    await page.waitForTimeout(700);
    await page.screenshot({ path: `qa-evidence/${path}`, fullPage: true });
}

async function bodyText(page: Page): Promise<string> {
    return (await page.locator('body').innerText().catch(() => '')) || '';
}

/* ===== Peran Penanggung Jawab (punya akses) ===== */
test.describe('Evidence Section 23 - Data Master (Penanggung Jawab)', () => {
    let auth: AuthPage;
    test.setTimeout(150000);

    test.beforeEach(async ({ page }) => {
        await page.route('**/:5173/**', (r) => r.abort());
        await page.route(/.*:5173.*/, (r) => r.abort());
        auth = new AuthPage(page);
        await auth.loginAndWaitForDashboard('pjawab@email.com', PW);
    });

    test('MASTER001 - pjawab: tombol Konfigurasi Data Master di dashboard membuka halaman Data Master', async ({ page }) => {
        await page.goto('/peternakan', { waitUntil: 'domcontentloaded' });
        const btn = page.getByRole('link', { name: /Konfigurasi Data Master/i }).first();
        const visible = await btn.count();
        console.log('MASTER001_BTN_PJAWAB::' + visible);
        await btn.waitFor({ state: 'visible', timeout: 20000 });
        await btn.click();
        const body = await bodyText(page);
        const is403 = /TIDAK MEMILIKI AKSES|Forbidden|403/i.test(body);
        const hasHeading = /Konfigurasi Data Master Ternak/i.test(body);
        console.log('MASTER001_RESULT:: url=' + page.url() + ' is403=' + is403 + ' heading=' + hasHeading);
        expect(hasHeading).toBeTruthy();
        expect(is403).toBeFalsy();
        await cap(page, 'MASTER/MASTER001_buka_dari_dashboard.png');
    });

    test('MASTER002 - pjawab: halaman Data Master menampilkan jenis ternak & konfigurasi', async ({ page }) => {
        await page.goto('/data-master', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Konfigurasi Data Master Ternak/i })).toBeVisible({ timeout: 20000 });
        await cap(page, 'MASTER/MASTER002_halaman_data_master.png');
    });

    test('MASTER003 - pjawab: form parameter lingkungan & fungsi produktivitas tersedia', async ({ page }) => {
        await page.goto('/data-master', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Konfigurasi Data Master Ternak/i })).toBeVisible({ timeout: 20000 });
        const saveBtnCount = await page.getByRole('button', { name: /Simpan/i }).count();
        console.log('MASTER003_SAVE_BTN::' + saveBtnCount);
        await cap(page, 'MASTER/MASTER003_form_konfigurasi.png');
    });
});

/* ===== Peran Petugas (tanpa akses) ===== */
test.describe('Evidence Section 23 - Data Master (Petugas)', () => {
    let auth: AuthPage;
    test.setTimeout(150000);

    test.beforeEach(async ({ page }) => {
        await page.route('**/:5173/**', (r) => r.abort());
        await page.route(/.*:5173.*/, (r) => r.abort());
        auth = new AuthPage(page);
        await auth.loginAndWaitForDashboard('petugas@email.com', PW);
    });

    test('MASTER004 - petugas: tombol Konfigurasi Data Master TAMPIL di dashboard lalu klik mengarah ke 403 (defect)', async ({ page }) => {
        await page.goto('/peternakan', { waitUntil: 'domcontentloaded' });
        const btn = page.getByRole('link', { name: /Konfigurasi Data Master/i }).first();
        const btnVisible = await btn.count();
        console.log('MASTER004_BTN_PETUGAS::' + btnVisible);
        if (btnVisible > 0) {
            // tombol memang ditampilkan untuk petugas -> klik dan amati hasilnya
            await btn.click();
            await page.waitForLoadState('domcontentloaded').catch(() => { });
            await page.waitForTimeout(800);
        } else {
            // fallback: akses langsung URL
            await page.goto('/data-master', { waitUntil: 'domcontentloaded' });
        }
        const body = await bodyText(page);
        const is403 = /TIDAK MEMILIKI AKSES|Forbidden|403/i.test(body);
        console.log('MASTER004_RESULT:: btnVisible=' + btnVisible + ' url=' + page.url() + ' is403=' + is403);
        // Temuan jujur: tombol tampil untuk petugas (btnVisible>0) TAPI mengarah ke 403
        await cap(page, 'MASTER/MASTER004_petugas_tombol_403.png');
    });

    test('MASTER005 - petugas: akses langsung /data-master ditolak 403', async ({ page }) => {
        const resp = await page.goto('/data-master?jenis_budidaya_id=b2c3d4e5-f6a7-4b5c-9d0e-1f2a3b4c5d6e', { waitUntil: 'domcontentloaded' });
        const body = await bodyText(page);
        const is403 = /TIDAK MEMILIKI AKSES|Forbidden|403/i.test(body);
        console.log('MASTER005_RESULT:: status=' + (resp?.status() ?? 'n/a') + ' is403=' + is403);
        expect(is403).toBeTruthy();
        await cap(page, 'MASTER/MASTER005_akses_langsung_403.png');
    });
});
