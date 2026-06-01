import { test, expect } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';
import { UsersPage } from '../pages/UsersPage.js';

test.describe.serial('Modul Manajemen User / Petugas - E2E QA', () => {
    let authPage: AuthPage;
    let usersPage: UsersPage;

    test.setTimeout(120000);

    test.beforeEach(async ({ page }) => {
        // Arrange
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        authPage = new AuthPage(page);
        usersPage = new UsersPage(page);

        // Pre-req: Pastikan sesi admin / pjawab telah aktif
        await authPage.loginAndWaitForDashboard('pjawab@email.com', 'Password123.');
        
        // Act
        await usersPage.goto();
    });

    test('Positif - UI dashboard User Management berhasil dimuat', async () => {
        /**
         * Given user Penanggung Jawab telah masuk ke dashboard internal aplikasi
         * When navigasi menuju akses menu Manajemen Petugas (/users)
         * Then komponen utama seperti Heading, Tombol Tambah, dan DOM table render sukses
         */

        // Arrange & Act - Sudah dipastikan valid dari hook beforeEach()

        // Assert
        await usersPage.expectPageReady();
        await usersPage.expectTableVisible();
        await expect(usersPage.addButton).toBeVisible({ timeout: 10000 });
    });

    test('Positif - Eksekusi sukses prosedur CRUD (Create, Update, Delete) karyawan/petugas', async ({ page }) => {
        /**
         * Given privilege set dari penanggung jawab yang bisa mengelola bawahan (petugas)
         * When operasi sekuensial nambah user, edit info, dan mendepak user itu dipanggil
         * Then setiap tahapan harus memancarkan feedback sukses ke DOM table yang akurat
         */

        // Arrange
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const initialName = `Petugas E2E ${suffix}`;
        const updatedName = `Petugas Update ${suffix}`;
        const email = `e2e_${suffix}@example.com`;
        
        const notificationPayloads = {
            create: 'Akun Petugas berhasil dibuat.',
            update: 'Akun Petugas berhasil diupdate.',
            delete: 'Akun Petugas berhasil dihapus.',
        };

        // --- ACT 1: CREATE ---
        await usersPage.createUser({
            name: initialName,
            email,
            password: 'Password123.',
        });

        // Assert 1
        await expect(page.getByText(notificationPayloads.create)).toBeVisible({ timeout: 15000 });
        await usersPage.expectRowVisible(initialName);

        // --- ACT 2: UPDATE ---
        await usersPage.editUser(initialName, {
            name: updatedName,
            email,
        });

        // Assert 2
        await expect(page.getByText(notificationPayloads.update)).toBeVisible({ timeout: 15000 });
        await usersPage.expectRowVisible(updatedName);

        // --- ACT 3: DELETE ---
        await usersPage.deleteUser(updatedName);
        
        // Assert 3
        await expect(page.getByText(notificationPayloads.delete)).toBeVisible({ timeout: 15000 });
        await usersPage.expectRowHidden(updatedName);
    });

    test('Negatif - Form Registrasi memblokir submit blank mandatory text input', async ({ page }) => {
        /**
         * Given interface modal tambah user baru muncul
         * When tidak memberikan isi data teks pada form (Blank parameter)
         * Then registrasi HTTP request tercegah, dan UI tidak merusak sesi/state
         */
         
        // Arrange
        await usersPage.addButton.click();
        await expect(usersPage.submitButton).toBeVisible();

        // Act
        // Isi form dummy yang fail constraint length/email
        // Langsung hajar hit confirm tanpa context isi
        await usersPage.submitButton.click();

        // Assert
        // Modal idealnya harus tetap berada disitu dan row user tidak di trigger reload
        await expect(usersPage.submitButton).toBeVisible();
        await expect(page.getByText('Akun Petugas berhasil dibuat.')).not.toBeVisible();
    });
});
