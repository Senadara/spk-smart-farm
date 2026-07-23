import { test, expect, Page } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';
import { PeternakanPage } from '../pages/PeternakanPage.js';

/**
 * EVIDENCE - DKAND009-021 (detail kandang) yang belum punya screenshot.
 * Login petugas, buka detail kandang pertama. Screenshot -> qa-evidence/DKAND/
 * DKAND015 (Export) = GAGAL jujur (tombol ada tapi belum berfungsi). DKAND020 = 404 (negatif).
 */
const PW = 'Password123.';

async function cap(page: Page, path: string) {
    try { await page.waitForLoadState('networkidle', { timeout: 12000 }); }
    catch { await page.waitForLoadState('domcontentloaded').catch(() => { }); }
    await page.waitForTimeout(600);
    await page.screenshot({ path: `qa-evidence/${path}`, fullPage: true });
}

async function openDetail(page: Page) {
    const auth = new AuthPage(page);
    const pt = new PeternakanPage(page);
    await auth.loginAndWaitForDashboard('petugas@email.com', PW);
    await pt.goto();
    await pt.expectToBeOnPeternakanPage();
    await pt.kandangLinks.first().click();
    await expect(page).toHaveURL(/\/peternakan\/.+/, { timeout: 30000 });
    try { await page.waitForLoadState('networkidle', { timeout: 15000 }); }
    catch { await page.waitForLoadState('domcontentloaded'); }
    await page.waitForTimeout(900);
}

async function scrollToText(page: Page, re: RegExp) {
    const el = page.getByText(re).first();
    if (await el.count() > 0) { await el.scrollIntoViewIfNeeded().catch(() => { }); await page.waitForTimeout(500); }
}

test.describe('Evidence Detail Kandang DKAND009-021', () => {
    test.setTimeout(150000);
    test.beforeEach(async ({ page }) => {
        await page.route('**/:5173/**', (r) => r.abort());
        await page.route(/.*:5173.*/, (r) => r.abort());
    });

    test('DKAND009 - Filter metrik produktivitas (dropdown)', async ({ page }) => {
        await openDetail(page);
        const sel = page.locator('select[x-model="prodFilter"]');
        if (await sel.count() > 0) { await sel.selectOption('HDP').catch(() => { }); await page.waitForTimeout(500); }
        await scrollToText(page, /Tren Produktivitas/i);
        await cap(page, 'DKAND/DKAND009_filter_metrik.png');
    });

    test('DKAND010 - Egg quality size distribution bar', async ({ page }) => {
        await openDetail(page);
        await scrollToText(page, /Egg Production|Produksi Telur|Distribusi/i);
        await cap(page, 'DKAND/DKAND010_size_distribution.png');
    });

    test('DKAND011 - Broken & dirty egg rate', async ({ page }) => {
        await openDetail(page);
        await scrollToText(page, /Egg Production|Produksi Telur/i);
        await cap(page, 'DKAND/DKAND011_broken_dirty_egg.png');
    });

    test('DKAND012 - Tabel Log Produksi 7 hari', async ({ page }) => {
        await openDetail(page);
        await scrollToText(page, /Log Produksi/i);
        await cap(page, 'DKAND/DKAND012_log_produksi.png');
    });

    test('DKAND013 - 4 kartu pesan SPK', async ({ page }) => {
        await openDetail(page);
        await scrollToText(page, /Analisis SPK|Analisa SPK/i);
        await cap(page, 'DKAND/DKAND013_spk_messages.png');
    });

    test('DKAND014 - Timeline aktivitas petugas', async ({ page }) => {
        await openDetail(page);
        await scrollToText(page, /Aktivitas Petugas/i);
        await cap(page, 'DKAND/DKAND014_aktivitas_petugas.png');
    });

    test('DKAND015 - Tombol Export (GAGAL - belum berfungsi)', async ({ page }) => {
        await openDetail(page);
        const exportBtn = page.getByRole('button', { name: /Export/i }).first();
        const linkExport = page.getByRole('link', { name: /Export/i }).first();
        const cnt = (await exportBtn.count()) + (await linkExport.count());
        console.log('DKAND015_EXPORT::' + cnt);
        await cap(page, 'DKAND/DKAND015_tombol_export.png');
    });

    test('DKAND016 - Tombol rentang grafik 7H/14H/30H berfungsi', async ({ page }) => {
        await openDetail(page);
        await scrollToText(page, /Tren Produktivitas/i);
        const b14 = page.getByRole('button', { name: /^14H$|14 Hari/i }).first();
        if (await b14.count() > 0) { await b14.click().catch(() => { }); await page.waitForTimeout(600); }
        const b30 = page.getByRole('button', { name: /^30H$|30 Hari/i }).first();
        if (await b30.count() > 0) { await b30.click().catch(() => { }); await page.waitForTimeout(600); }
        await cap(page, 'DKAND/DKAND016_rentang_grafik.png');
    });

    test('DKAND017 - Dropdown filter sensor pada tren sensor', async ({ page }) => {
        await openDetail(page);
        await scrollToText(page, /Tren Sensor/i);
        const sel = page.locator('select[x-model="sensorFilter"], select[x-model="trendSensor"]').first();
        if (await sel.count() > 0) { await sel.selectOption({ index: 1 }).catch(() => { }); await page.waitForTimeout(500); }
        await cap(page, 'DKAND/DKAND017_filter_sensor_tren.png');
    });

    test('DKAND018 - Dropdown filter metrik pada tren produktivitas', async ({ page }) => {
        await openDetail(page);
        await scrollToText(page, /Tren Produktivitas/i);
        const sel = page.locator('select[x-model="prodFilter"]').first();
        if (await sel.count() > 0) {
            try { await sel.selectOption('FCR'); } catch { await sel.selectOption({ index: 2 }).catch(() => { }); }
            await page.waitForTimeout(500);
        }
        await cap(page, 'DKAND/DKAND018_filter_metrik_tren.png');
    });

    test('DKAND019 - Kartu perangkat IoT di detail kandang', async ({ page }) => {
        await openDetail(page);
        await scrollToText(page, /Sensor & Perangkat|Perangkat IoT/i);
        await cap(page, 'DKAND/DKAND019_kartu_perangkat_iot.png');
    });

    test('DKAND020 - UUID tidak valid -> 404', async ({ page }) => {
        const auth = new AuthPage(page);
        await auth.loginAndWaitForDashboard('petugas@email.com', PW);
        const resp = await page.goto('/peternakan/nonexistent-uuid-12345', { waitUntil: 'domcontentloaded' });
        console.log('DKAND020_STATUS::' + (resp?.status() ?? 'n/a'));
        await cap(page, 'DKAND/DKAND020_uuid_invalid_404.png');
    });

    test('DKAND021 - Activity log placeholder tanpa error', async ({ page }) => {
        await openDetail(page);
        await scrollToText(page, /Aktivitas Petugas/i);
        const body = await page.locator('body').innerText();
        expect(body).not.toMatch(/Error 500|Exception|Fatal error/i);
        await cap(page, 'DKAND/DKAND021_activity_placeholder.png');
    });
});
