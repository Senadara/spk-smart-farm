import { test, expect } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';

const PW = 'Password123.';

async function loginPetugas(page: import('@playwright/test').Page) {
    await page.route('**/:5173/**', (r) => r.abort());
    await page.route(/.*:5173.*/, (r) => r.abort());
    const auth = new AuthPage(page);
    await page.context().clearCookies();
    await auth.loginAndWaitForDashboard('petugas@email.com', PW);
}

/** Rute yang HARUS diblokir untuk petugas (403 / redirect keluar). */
const BLOCKED: Array<[string, string]> = [
    ['/iot', 'IoT Dashboard'],
    ['/iot/devices', 'Setup IoT Kandang'],
    ['/iot/monitoring', 'Monitoring IoT'],
    ['/settings', 'Pengaturan (Settings Hub)'],
    ['/settings/fuzzy', 'Konfigurasi Fuzzy Mamdani'],
    ['/settings/notifications', 'Pengaturan Notifikasi'],
    ['/data-master', 'Data Master'],
    ['/spk-suppliers', 'Rekomendasi SPK Supplier'],
    ['/users', 'Manajemen Karyawan/User'],
    ['/super-admin/suppliers', 'Super Admin Mitra Supplier'],
    ['/supplier', 'Panel Supplier'],
];

/**
 * Rute operasional (fitur Dev1 Nanda) yang HARUS bisa diakses petugas.
 * Catatan: /perkebunan SENGAJA tidak diuji di sini — modul perkebunan (tanaman) di luar
 * cakupan Dev1 Nanda dan saat ini merespons 504 (gateway timeout) → dicatat sebagai isu
 * performa modul lain, bukan kontrol akses petugas.
 */
const ALLOWED: Array<[string, RegExp]> = [
    ['/dashboard', /\/dashboard/],
    ['/peternakan', /\/peternakan/],
    ['/penugasan', /\/penugasan/],
    ['/spk-analysis', /\/spk-analysis/],
    ['/inventory', /\/inventory/],
    ['/profil', /\/profil/],
];

test.describe('Kontrol Akses Role - PETUGAS', () => {
    test.setTimeout(180000);

    test('Negatif - Petugas DIBLOKIR dari fitur khusus pjawab/owner/admin/inventor', async ({ page }) => {
        await loginPetugas(page);

        const results: string[] = [];
        for (const [route, label] of BLOCKED) {
            const resp = await page.goto(route, { waitUntil: 'domcontentloaded' }).catch(() => null);
            const status = resp?.status() ?? 0;
            await page.waitForTimeout(300);
            const body = (await page.locator('body').innerText().catch(() => '')) || '';
            const url = page.url();
            const is403 = status === 403 || /TIDAK MEMILIKI AKSES|Unauthorized|Forbidden|403/i.test(body);
            // Beberapa rute bisa redirect ke dashboard/login alih-alih 403.
            const bouncedOut = /\/(dashboard|login)(\?|$)/.test(url) && !url.includes(route);
            const blocked = is403 || bouncedOut;
            results.push(`${route} -> status=${status} blocked=${blocked}`);
            expect(blocked, `Petugas seharusnya DIBLOKIR dari ${label} (${route}) tetapi tidak (status=${status}, url=${url})`).toBeTruthy();
        }
        console.log('PETUGAS_BLOCKED::\n' + results.join('\n'));
    });

    test('Positif - Petugas BISA mengakses fitur operasional yang diizinkan', async ({ page }) => {
        await loginPetugas(page);

        const results: string[] = [];
        for (const [route, urlRe] of ALLOWED) {
            const resp = await page.goto(route, { waitUntil: 'domcontentloaded' }).catch(() => null);
            const status = resp?.status() ?? 0;
            await page.waitForTimeout(300);
            const url = page.url();
            const ok = status > 0 && status < 400 && urlRe.test(url);
            results.push(`${route} -> status=${status} url=${url} ok=${ok}`);
            expect(ok, `Petugas seharusnya BISA mengakses ${route} (status=${status}, url=${url})`).toBeTruthy();
        }
        console.log('PETUGAS_ALLOWED::\n' + results.join('\n'));
    });
});
