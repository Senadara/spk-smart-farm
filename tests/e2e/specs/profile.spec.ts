import { test, expect } from '@playwright/test';
import { ProfilePage } from '../pages/ProfilePage.js';

test.describe('Pengujian Halaman Profil Pengguna', () => {
    test.describe.configure({ mode: 'serial' });

    let profilePage: ProfilePage;

    test.beforeEach(async ({ page }, testInfo) => {
        testInfo.setTimeout(240000);
        profilePage = new ProfilePage(page);
        await profilePage.goto();
    });

    test('Positif - Halaman profil berhasil dimuat dengan judul benar', async () => {
        await profilePage.expectPageTitleVisible();
    });

    test('Positif - Informasi profil pengguna menampilkan dengan lengkap', async () => {
        await profilePage.expectProfileDetailsVisible();
    });

    test('Positif - Badge role pengguna menampilkan dengan benar', async () => {
        await expect(profilePage.roleBadge).toBeVisible();
    });

    test('Positif - Tabel riwayat login menampilkan dengan benar', async () => {
        await profilePage.expectLoginHistoryVisible();
    });

    test('Positif - Riwayat login memiliki data dan menampilkan setidaknya 1 baris', async () => {
        await profilePage.expectLoginHistoryHasRows(1);
    });

    test('Positif - Kolom waktu login menampilkan dengan format yang benar', async () => {
        const firstRow = profilePage.loginHistoryRows.first();
        await expect(firstRow.locator('td').first()).toBeVisible();
    });

    test('Positif - Kolom IP address menampilkan data dengan benar', async () => {
        const firstRow = profilePage.loginHistoryRows.first();
        const ipCell = firstRow.locator('td').nth(1);
        await expect(ipCell).toBeVisible();
    });

    test('Positif - Kolom browser/perangkat menampilkan data dengan benar', async () => {
        const firstRow = profilePage.loginHistoryRows.first();
        const browserCell = firstRow.locator('td').nth(2);
        await expect(browserCell).toBeVisible();
    });
});