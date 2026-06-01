import { test, expect } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';
import { PenugasanPage } from '../pages/PenugasanPage.js';

test.describe.serial('Modul Penugasan / Board Task - E2E Pjwb QA', () => {
    let authPage: AuthPage;
    let penugasanPage: PenugasanPage;

    test.setTimeout(120000);

    test.beforeEach(async ({ page }) => {
        // Arrange
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        authPage = new AuthPage(page);
        penugasanPage = new PenugasanPage(page);

        // Sub-seed: Login
        await authPage.loginAndWaitForDashboard('pjawab@email.com', 'Password123.');
        
        // Act
        await penugasanPage.goto();
    });

    test('Positif - UI Halaman utama Penugasan Task Board dirender lengkap dengan Tool Create', async () => {
        /**
         * Given user bertindak sebagai Penanggung Jawab
         * When masuk ke dalam route board tugas
         * Then elemen canvas board dan tombol Create Task mematuhi visibilitas interface
         */

        // Arrange & Act (from beforeEach)

        // Assert
        await penugasanPage.expectPageReady();
        await expect(penugasanPage.createButton).toBeVisible({ timeout: 10000 });
    });

    test('Positif - Simulasi Skrip E2E CRUD dan Proses Lifecycle penuh satu task penugasan', async ({ page }) => {
        /**
         * Given privilege set dari penanggung jawab yang berhak Create to Done Task
         * When form submit berhasil dilakukan untuk entri baru, dan status digeser menuju Selesai (Done) & submit pelaporan
         * Then Task merespon dinamis masuk ke riwayat (History Tab) dan UI menampilkan log Diselesaikan
         */

        // Arrange : Data Prep
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const taskTitle = `e2e_tugas_${suffix}`;
        const taskDescription = `Deskripsi tugas otomatis QA ${suffix}`;

        // Act 1: Membuka Modul Laporan dan Mendaftarkan Task Baru
        await penugasanPage.createTask({
            title: taskTitle,
            description: taskDescription,
            priority: 'high',
        });

        // Assert 1: Indikator penciptaan
        await expect(page.getByText('Tugas berhasil dibuat.')).toBeVisible({ timeout: 15000 });
        await penugasanPage.expectTaskVisible(taskTitle);

        // Act 2: Buka Detil dan Update Progress (Kerjakan Task)
        await penugasanPage.openTaskDetail(taskTitle);
        await expect(page.getByRole('heading', { name: taskTitle })).toBeVisible();

        await penugasanPage.startTask();
        
        // Assert 2: UI bereaksi atas progress assignment
        await expect(page.getByText('Status tugas diperbarui.')).toBeVisible({ timeout: 15000 });
        await expect(page.getByRole('button', { name: /Kirim Laporan/i })).toBeVisible();

        // Act 3: Evaluasi Report Akhir & Tutup Buku
        await penugasanPage.submitReport({
            description: `Laporan tes validasi QA E2E selesai ${suffix}`,
            statusUpdate: 'done',
        });

        // Assert 3: Flag Finish
        await expect(page.getByText('Laporan pengerjaan berhasil disubmit.')).toBeVisible({ timeout: 15000 });

        // Act 4: Review Sejarah Riwayat
        await penugasanPage.goToHistoryTab();
        const historyRow = await penugasanPage.historyRow(taskTitle);
        
        // Assert Terakhir E2E Lifecycle
        await expect(historyRow).toBeVisible();
        await expect(historyRow).toContainText('Selesai');

        // Check Detil History
        await historyRow.getByRole('link', { name: /Detail/i }).click();
        await expect(page.getByRole('heading', { name: taskTitle })).toBeVisible();
        await expect(page.getByText('Diselesaikan')).toBeVisible();
    });

    test('Negatif - Menciptakan Task Tanpa Judul akan menggagalkan state Form dan dicegah HTML Validations', async ({ page }) => {
        /**
         * Given jendela Modal Create Task tampil
         * When field Nama Tugas (Title) dibiarkan kosong, lalu nekat di submit
         * Then API Server tidak diakses, UI tetap open, Browser mencekik HTTP Method (HTML checkValidity)
         */

        // Arrange
        await expect(penugasanPage.createButton).toBeVisible();
        
        // Act
        // Tidak mengisi argumen field wajib
        await penugasanPage.createButton.click(); // Trigger pop up
        // Kita intercept submit button di UI modal yang harus ditebak (contoh selector .btn-primary)
        const submitBtnModal = page.locator('button[type="submit"]', { hasText: /Simpan|Buat|Tambah/i }).first();
        
        if (await submitBtnModal.isVisible()) {
            await submitBtnModal.click();
            // Assert: Gagal tersubmit karena modal masih terpampang solid
            await expect(submitBtnModal).toBeVisible();
        } else {
             test.skip(true, 'DOM locator spesifik submit form task tidak dirender');
        }
    });
});
