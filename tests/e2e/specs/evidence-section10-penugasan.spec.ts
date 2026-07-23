import { test, expect, Page } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';
import { PenugasanPage } from '../pages/PenugasanPage.js';

/**
 * EVIDENCE CAPTURE - Section 10: Penugasan / Tindakan (TINDAK001-015)
 * 1 screenshot unik per skenario → qa-evidence/TINDAK/<TEST-ID>_<judul>.png
 * Login: Penanggung Jawab (TINDAK010 pakai Petugas). Hasil jujur.
 */
const PW = 'Password123.';
const G = 'TINDAK';

async function shot(page: Page, file: string) {
     await page.waitForTimeout(300);
     await page.screenshot({ path: `qa-evidence/${G}/${file}`, fullPage: true });
}
async function stable(page: Page, file: string) {
     try { await page.waitForLoadState('networkidle', { timeout: 15000 }); }
     catch { await page.waitForLoadState('domcontentloaded'); }
     await page.waitForTimeout(600);
     await page.screenshot({ path: `qa-evidence/${G}/${file}`, fullPage: true });
}

test.describe.serial('Evidence Section 10 - Penugasan (Penanggung Jawab)', () => {
     let auth: AuthPage;
     let pen: PenugasanPage;
     test.setTimeout(150000);

     test.beforeEach(async ({ page }) => {
          await page.route('**/:5173/**', (route) => route.abort());
          await page.route(/.*:5173.*/, (route) => route.abort());
          auth = new AuthPage(page);
          pen = new PenugasanPage(page);
          await auth.loginAndWaitForDashboard('pjawab@email.com', PW);
          await pen.goto();
     });

     test('TINDAK001 - Halaman Penugasan menampilkan papan tugas (Kanban)', async ({ page }) => {
          await pen.expectPageReady();
          await expect(page.getByText('Board Penugasan')).toBeVisible();
          await stable(page, 'TINDAK001_kanban_board.png');
     });

     test('TINDAK011 - Lima kartu statistik penugasan tampil', async ({ page }) => {
          await pen.expectPageReady();
          await expect(page.getByText('Total Tugas')).toBeVisible();
          await stable(page, 'TINDAK011_statistik.png');
     });

     test('TINDAK003 - Validasi membuat tugas tanpa judul', async ({ page }) => {
          await pen.createButton.click();
          await expect(page.getByRole('heading', { name: /Buat Tugas Baru/i })).toBeVisible();
          await page.locator('button[type="submit"]').filter({ hasText: /Simpan|Buat/i }).first().click();
          await expect(page.getByRole('heading', { name: /Buat Tugas Baru/i })).toBeVisible();
          const msg = await page.locator('input[name="title"]').first().evaluate((el: HTMLInputElement) => el.validationMessage);
          expect(msg).toBeTruthy();
          await shot(page, 'TINDAK003_validasi_judul_kosong.png');
     });

     test('TINDAK004 - Membuat tugas baru dengan data lengkap', async ({ page }) => {
          const title = `QA Task ${Date.now()}`;
          await pen.createTask({ title, description: 'QA membuat tugas lengkap', priority: 'high' });
          await expect(page.getByText('Tugas berhasil dibuat.').first()).toBeVisible({ timeout: 15000 });
          await shot(page, 'TINDAK004_buat_tugas.png');
     });

     test('TINDAK005 - Membuka halaman detail tugas', async ({ page }) => {
          const title = `QA Detail ${Date.now()}`;
          await pen.createTask({ title, description: 'Detail tugas', priority: 'medium' });
          await expect(page.getByText('Tugas berhasil dibuat.').first()).toBeVisible({ timeout: 15000 });
          await page.getByText(title).first().click();
          await expect(page.getByRole('heading', { name: title })).toBeVisible();
          await expect(page.getByText('Informasi Tugas')).toBeVisible();
          await stable(page, 'TINDAK005_detail_tugas.png');
     });

     test('TINDAK006 - Mengubah status tugas To Do menjadi Dikerjakan', async ({ page }) => {
          const title = `QA Start ${Date.now()}`;
          await pen.createTask({ title, description: 'Mulai kerjakan', priority: 'high' });
          await expect(page.getByText('Tugas berhasil dibuat.').first()).toBeVisible({ timeout: 15000 });
          await page.getByText(title).first().click();
          await page.getByRole('button', { name: /Mulai Kerjakan/i }).click();
          await expect(page.getByText('Status tugas diperbarui.')).toBeVisible({ timeout: 15000 });
          await expect(page.getByText('Dikerjakan', { exact: true }).first()).toBeVisible();
          await shot(page, 'TINDAK006_status_dikerjakan.png');
     });

     test('TINDAK007 - Menyaring tugas berdasarkan prioritas', async ({ page }) => {
          const pr = page.locator('select[name="priority"]').first();
          await pr.selectOption('urgent');
          await page.waitForURL(/priority=urgent/);
          await stable(page, 'TINDAK007_filter_prioritas.png');
     });

     test('TINDAK008 - Mencari tugas berdasarkan kata kunci', async ({ page }) => {
          const s = page.locator('input[name="search"]').first();
          await s.fill('QA Task');
          await s.press('Enter');
          await page.waitForURL(/search=QA/);
          await stable(page, 'TINDAK008_pencarian.png');
     });

     test('TINDAK009 - Tab Histori menampilkan tugas selesai', async ({ page }) => {
          await pen.goToHistoryTab();
          await expect(page.getByText('Histori & Arsip Tugas')).toBeVisible();
          await stable(page, 'TINDAK009_tab_histori.png');
     });

     test('TINDAK012 - Mengubah data tugas dari halaman detail', async ({ page }) => {
          const title = `QA Edit ${Date.now()}`;
          const updated = `QA Edit Updated ${Date.now()}`;
          await pen.createTask({ title, description: 'asli', priority: 'low' });
          await expect(page.getByText('Tugas berhasil dibuat.').first()).toBeVisible({ timeout: 15000 });
          await page.getByText(title).first().click();
          const editForm = page.locator('form').filter({ has: page.getByRole('button', { name: /Simpan Perubahan/i }) }).first();
          await editForm.locator('input[name="title"]').fill(updated);
          await editForm.locator('select[name="priority"]').selectOption('urgent');
          await editForm.getByRole('button', { name: /Simpan Perubahan/i }).click();
          await expect(page.getByText('Tugas berhasil diperbarui.').first()).toBeVisible({ timeout: 15000 });
          await shot(page, 'TINDAK012_edit_tugas.png');
     });

     test('TINDAK013 - Menghapus tugas permanen dari halaman detail', async ({ page }) => {
          const title = `QA Delete ${Date.now()}`;
          await pen.createTask({ title, description: 'hapus', priority: 'medium' });
          await expect(page.getByText('Tugas berhasil dibuat.').first()).toBeVisible({ timeout: 15000 });
          await page.getByText(title).first().click();
          await expect(page.getByRole('heading', { name: title })).toBeVisible();
          page.once('dialog', (d) => d.accept());
          await page.locator('button[type="submit"]').filter({ hasText: /Hapus Tugas Permanen/i }).click();
          await expect(page.getByText('Tugas berhasil dihapus.').first()).toBeVisible({ timeout: 15000 });
          await shot(page, 'TINDAK013_hapus_tugas.png');
     });

     test('TINDAK014 - Membatalkan tugas', async ({ page }) => {
          const title = `QA Cancel ${Date.now()}`;
          await pen.createTask({ title, description: 'batal', priority: 'low' });
          await expect(page.getByText('Tugas berhasil dibuat.').first()).toBeVisible({ timeout: 15000 });
          await page.getByText(title).first().click();
          await expect(page.getByRole('heading', { name: title })).toBeVisible();
          page.once('dialog', (d) => d.accept());
          await page.getByRole('button', { name: /Batalkan/i }).click();
          await expect(page.getByText('Status tugas diperbarui.')).toBeVisible({ timeout: 15000 });
          await expect(page.getByText('Dibatalkan', { exact: true }).first()).toBeVisible();
          await shot(page, 'TINDAK014_batalkan_tugas.png');
     });

     test('TINDAK002 - Mencatat laporan penerapan tindakan pada tugas', async ({ page }) => {
          const title = `QA Report ${Date.now()}`;
          await pen.createTask({ title, description: 'laporan', priority: 'high' });
          await expect(page.getByText('Tugas berhasil dibuat.').first()).toBeVisible({ timeout: 15000 });
          await page.getByText(title).first().click();
          await page.getByRole('button', { name: /Mulai Kerjakan/i }).click();
          await expect(page.getByText('Status tugas diperbarui.')).toBeVisible({ timeout: 15000 });
          await pen.submitReport({ description: 'Laporan penerapan tindakan: progres 50%', statusUpdate: 'in_progress' });
          await expect(page.getByText('Laporan pengerjaan berhasil disubmit.').first()).toBeVisible({ timeout: 15000 });
          await shot(page, 'TINDAK002_catat_laporan.png');
     });

     test('TINDAK015 - Mengirim laporan penyelesaian dengan status Selesai', async ({ page }) => {
          const title = `QA Report Done ${Date.now()}`;
          await pen.createTask({ title, description: 'laporan selesai', priority: 'high' });
          await expect(page.getByText('Tugas berhasil dibuat.').first()).toBeVisible({ timeout: 15000 });
          await page.getByText(title).first().click();
          await page.getByRole('button', { name: /Mulai Kerjakan/i }).click();
          await expect(page.getByText('Status tugas diperbarui.')).toBeVisible({ timeout: 15000 });
          await pen.submitReport({ description: 'Pekerjaan selesai 100%', statusUpdate: 'done' });
          await expect(page.getByText('Laporan pengerjaan berhasil disubmit.').first()).toBeVisible({ timeout: 15000 });
          await page.goto('/penugasan?tab=history', { waitUntil: 'domcontentloaded' });
          await expect(page.locator('tbody tr').filter({ hasText: title }).first()).toContainText('Selesai');
          await stable(page, 'TINDAK015_laporan_selesai.png');
     });
});

test.describe('Evidence Section 10 - Penugasan (Petugas)', () => {
     test.setTimeout(120000);
     test.beforeEach(async ({ page }) => {
          await page.route('**/:5173/**', (route) => route.abort());
          await page.route(/.*:5173.*/, (route) => route.abort());
     });

     test('TINDAK010 - Petugas dapat melihat tugas yang ditugaskan', async ({ page }) => {
          const auth = new AuthPage(page);
          await auth.loginAndWaitForDashboard('petugas@email.com', PW);
          await page.goto('/penugasan', { waitUntil: 'domcontentloaded' });
          await expect(page.getByText('Penugasan & Laporan Tindakan')).toBeVisible({ timeout: 15000 });
          try { await page.waitForLoadState('networkidle', { timeout: 15000 }); } catch { /* ignore */ }
          await page.waitForTimeout(600);
          await page.screenshot({ path: `qa-evidence/${G}/TINDAK010_petugas_lihat_tugas.png`, fullPage: true });
     });
});
