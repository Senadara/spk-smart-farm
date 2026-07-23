import { test, expect, Page } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';
import { UsersPage } from '../pages/UsersPage.js';

/**
 * EVIDENCE CAPTURE - Section 2: Manajemen Karyawan / User (USER001-USER004)
 * 1 screenshot unik per skenario → qa-evidence/USER/<TEST-ID>_<judul>.png
 * Dijalankan sebagai Penanggung Jawab (pjawab). Hasil jujur sesuai perilaku nyata.
 * Catatan: aksi "hapus" pada implementasi nyata = NONAKTIFKAN (soft-delete).
 */

const GROUP = 'USER';
const PW = 'Password123.';

test.describe.configure({ mode: 'serial' });

// Screenshot segera (untuk notifikasi yang cepat hilang).
async function shot(page: Page, file: string) {
     await page.waitForTimeout(200);
     await page.screenshot({ path: `qa-evidence/${GROUP}/${file}`, fullPage: true });
}

test.describe('Evidence Section 2 - Manajemen Karyawan / User', () => {
     let authPage: AuthPage;
     let usersPage: UsersPage;
     test.setTimeout(120000);

     test.beforeEach(async ({ page }) => {
          await page.route('**/:5173/**', (route) => route.abort());
          await page.route(/.*:5173.*/, (route) => route.abort());
          authPage = new AuthPage(page);
          usersPage = new UsersPage(page);
          await authPage.loginAndWaitForDashboard('pjawab@email.com', PW);
          await usersPage.goto();
          await usersPage.expectPageReady();
     });

     test('USER001 - Menambah akun petugas baru dengan data valid', async ({ page }) => {
          const s = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
          const name = `Petugas Baru ${s}`;
          await usersPage.createUser({ name, email: `baru_${s}@example.com`, password: PW });
          await expect(page.getByText('Akun Petugas berhasil dibuat.').first()).toBeVisible({ timeout: 15000 });
          await usersPage.expectRowVisible(name);
          await shot(page, 'USER001_tambah_petugas_valid.png');
     });

     test('USER002 - Menambah petugas dengan kolom wajib kosong', async ({ page }) => {
          await usersPage.addButton.click();
          const modal = page.locator('h3', { hasText: 'Tambah Petugas Baru' })
               .locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();
          await expect(modal).toBeVisible();
          await modal.getByRole('button', { name: /Simpan Petugas/i }).click();
          // Form tidak terkirim: modal tetap terbuka & tidak ada notifikasi sukses.
          await expect(modal).toBeVisible();
          await expect(page.getByText('Akun Petugas berhasil dibuat.')).toHaveCount(0);
          await shot(page, 'USER002_tambah_field_kosong.png');
     });

     test('USER003 - Mengubah data petugas yang sudah ada', async ({ page }) => {
          const s = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
          const name = `Petugas Edit ${s}`;
          const email = `edit_${s}@example.com`;
          await usersPage.createUser({ name, email, password: PW });
          await expect(page.getByText('Akun Petugas berhasil dibuat.').first()).toBeVisible({ timeout: 15000 });

          await usersPage.editUser(name, { name: `Petugas Update ${s}`, email });
          await expect(page.getByText('Akun Petugas berhasil diupdate.').first()).toBeVisible({ timeout: 15000 });
          await usersPage.expectRowVisible(`Petugas Update ${s}`);
          await shot(page, 'USER003_edit_petugas.png');
     });

     test('USER004 - Menonaktifkan akun petugas', async ({ page }) => {
          const s = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
          const name = `Petugas Hapus ${s}`;
          await usersPage.createUser({ name, email: `hapus_${s}@example.com`, password: PW });
          await expect(page.getByText('Akun Petugas berhasil dibuat.').first()).toBeVisible({ timeout: 15000 });

          await usersPage.deleteUser(name);
          await expect(page.getByText('Akun Petugas berhasil dinonaktifkan').first()).toBeVisible({ timeout: 15000 });
          await usersPage.expectRowDeactivated(name);
          await shot(page, 'USER004_nonaktifkan_petugas.png');
     });
});
