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

        // Arrange & Act - Sudah dipastikan sah dari hook beforeEach()

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
        const diperbaruiName = `Petugas Update ${suffix}`;
        const email = `e2e_${suffix}@example.com`;

        const notificationPayloads = {
            create: 'Akun Petugas berhasil dibuat.',
            update: 'Akun Petugas berhasil diupdate.',
            delete: 'Akun Petugas berhasil dihapus.',
        };

        // --- ACT 1: Menambah ---
        await usersPage.createUser({
            name: initialName,
            email,
            password: 'Password123.',
        });

        // Assert 1
        await expect(page.getByText(notificationPayloads.create)).toBeVisible({ timeout: 15000 });
        await usersPage.expectRowVisible(initialName);

        // --- ACT 2: Mengubah ---
        await usersPage.editUser(initialName, {
            name: diperbaruiName,
            email,
        });

        // Assert 2
        await expect(page.getByText(notificationPayloads.update)).toBeVisible({ timeout: 15000 });
        await usersPage.expectRowVisible(updatedName);

        // --- ACT 3: Menghapus ---
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

    test('Negatif - Email duplikat ditolak dengan kesalahan sahation', async ({ page }) => {
        /**
         * Given sudah ada user dengan email tertentu
         * When mencoba create user baru dengan email yang sama
         * Then sistem menampilkan kesalahan sahation email sudah terdaftar
         */

        // Arrange - Create user pertama
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const dupEmail = `duplicate_${suffix}@example.com`;

        await usersPage.createUser({
            name: `User Pertama ${suffix}`,
            email: dupEmail,
            password: 'Password123.',
        });
        await expect(page.getByText('Akun Petugas berhasil dibuat.')).toBeVisible({ timeout: 15000 });

        // Act - Coba create user kedua dengan email sama
        await usersPage.addButton.click();
        const modal = page.locator('h3', { hasText: 'Tambah Petugas Baru' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();

        await modal.locator('input[name="name"]').fill(`User Kedua ${suffix}`);
        await modal.locator('input[name="email"]').fill(dupEmail); // Email sama
        await modal.locator('input[name="password"]').fill('Password123.');
        await modal.getByRole('button', { name: /Simpan Petugas/i }).click();

        // Assert - Harus ada kesalahan message
        await expect(page.locator('text=/email.*sudah.*terdaftar|email.*already|duplicate/i')).toBeVisible({ timeout: 10000 });

        // Cleanup
        await page.reload();
        await usersPage.deleteUser(`User Pertama ${suffix}`);
    });

    test('Negatif - Password kurang dari 6 karakter ditolak', async ({ page }) => {
        /**
         * Given user membuka form tambah petugas
         * When input password dengan length < 6
         * Then sahation kesalahan muncul
         */

        // Arrange
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);

        // Act
        await usersPage.addButton.click();
        const modal = page.locator('h3', { hasText: 'Tambah Petugas Baru' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();

        await modal.locator('input[name="name"]').fill(`Short Pass ${suffix}`);
        await modal.locator('input[name="email"]').fill(`short_${suffix}@example.com`);
        await modal.locator('input[name="password"]').fill('12345'); // 5 karakter
        await modal.getByRole('button', { name: /Simpan Petugas/i }).click();

        // Assert - Error sahation atau modal masih terbuka
        const kesalahanVisible = await page.locator('text=/password.*minimal.*6|password.*at least.*6/i')
            .isVisible({ timeout: 5000 }).catch(() => false);
        const modalStillVisible = await modal.isVisible({ timeout: 3000 }).catch(() => false);

        expect(kesalahanVisible || modalStillVisible).toBeTruthy();
    });

    test('Negatif - Field name kosong ditolak (required sahation)', async ({ page }) => {
        /**
         * Given form tambah petugas terbuka
         * When field name dikosongkan tapi email dan password diisi
         * Then sahation kesalahan untuk field name muncul
         */

        // Arrange
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);

        // Act
        await usersPage.addButton.click();
        const modal = page.locator('h3', { hasText: 'Tambah Petugas Baru' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();

        // Kosongkan name, isi yang lain
        await modal.locator('input[name="name"]').fill(''); // KOSONG
        await modal.locator('input[name="email"]').fill(`noname_${suffix}@example.com`);
        await modal.locator('input[name="password"]').fill('Password123.');
        await modal.getByRole('button', { name: /Simpan Petugas/i }).click();

        // Assert - Modal masih ada atau ada kesalahan
        const modalStillVisible = await modal.isVisible({ timeout: 3000 }).catch(() => false);
        const kesalahanVisible = await page.locator('text=/name.*required|name.*wajib/i')
            .isVisible({ timeout: 5000 }).catch(() => false);

        expect(modalStillVisible || kesalahanVisible).toBeTruthy();
    });

    test('Negatif - Format email tidak sah ditolak', async ({ page }) => {
        /**
         * Given form tambah petugas terbuka
         * When input email dengan format tidak sah (tanpa @)
         * Then sahation kesalahan muncul
         */

        // Arrange
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);

        // Act
        await usersPage.addButton.click();
        const modal = page.locator('h3', { hasText: 'Tambah Petugas Baru' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();

        await modal.locator('input[name="name"]').fill(`Insah Email ${suffix}`);
        await modal.locator('input[name="email"]').fill('tidak sahemail.com'); // Tanpa @
        await modal.locator('input[name="password"]').fill('Password123.');

        const submitBtn = modal.getByRole('button', { name: /Simpan Petugas/i });
        await submitBtn.click();

        // Assert - HTML5 sahation atau backend kesalahan
        const emailInput = modal.locator('input[name="email"]');
        const isInsah = await emailInput.evaluate((node: HTMLInputElement) => !node.checkValidity())
            .catch(() => false);
        const kesalahanVisible = await page.locator('text=/email.*tidak sah|email.*tidak sah/i')
            .isVisible({ timeout: 5000 }).catch(() => false);

        expect(isInsah || kesalahanVisible).toBeTruthy();
    });

    test('Positif - Update user tanpa mengubah password (password optional)', async ({ page }) => {
        /**
         * Given sudah ada user terdaftar
         * When edit user dan TIDAK mengisi field password
         * Then update berhasil tanpa mengubah password
         */

        // Arrange - Create user
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const userName = `Optional Pass ${suffix}`;
        const email = `optional_${suffix}@example.com`;

        await usersPage.createUser({
            name: userName,
            email,
            password: 'Password123.',
        });
        await expect(page.getByText('Akun Petugas berhasil dibuat.')).toBeVisible({ timeout: 15000 });

        // Act - Edit tanpa password
        await usersPage.editUser(userName, {
            name: `Updated ${userName}`,
            email,
            // password tidak diisi (undefined)
        });

        // Assert
        await expect(page.getByText('Akun Petugas berhasil diupdate.')).toBeVisible({ timeout: 15000 });
        await usersPage.expectRowVisible(`Updated ${userName}`);

        // Cleanup
        await usersPage.deleteUser(`Updated ${userName}`);
    });

    test('Positif - Update user dengan password baru', async ({ page }) => {
        /**
         * Given sudah ada user terdaftar
         * When edit user dan ISI field password dengan password baru
         * Then update berhasil dan password ter-update
         */

        // Arrange - Create user
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const userName = `Change Pass ${suffix}`;
        const email = `changepass_${suffix}@example.com`;

        await usersPage.createUser({
            name: userName,
            email,
            password: 'OldPassword123.',
        });
        await expect(page.getByText('Akun Petugas berhasil dibuat.')).toBeVisible({ timeout: 15000 });

        // Act - Edit dengan password baru
        const row = page.locator('tr').filter({ hasText: userName }).first();
        await row.hover();
        await row.locator('button[title="Edit"]').click();

        const modal = page.locator('h3', { hasText: 'Edit Profil Petugas' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();

        await modal.locator('input[name="name"]').fill(userName);
        await modal.locator('input[name="email"]').fill(email);
        await modal.locator('input[name="password"]').fill('NewPassword123.'); // Password BARU
        await modal.getByRole('button', { name: /Simpan Perubahan/i }).click();

        // Assert
        await expect(page.getByText('Akun Petugas berhasil diupdate.')).toBeVisible({ timeout: 15000 });

        // Cleanup
        await usersPage.deleteUser(userName);
    });

    test('Positif - Tabel menampilkan multiple users', async ({ page }) => {
        /**
         * Given ada beberapa petugas terdaftar
         * When halaman users dimuat
         * Then tabel menampilkan semua users dengan kolom yang benar
         */

        // Arrange - Create 2 users
        const suffix1 = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const suffix2 = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);

        await usersPage.createUser({
            name: `Multi User A ${suffix1}`,
            email: `multi_a_${suffix1}@example.com`,
            password: 'Password123.',
        });
        await page.waitForTimeout(1000);

        await usersPage.createUser({
            name: `Multi User B ${suffix2}`,
            email: `multi_b_${suffix2}@example.com`,
            password: 'Password123.',
        });

        // Act - Reload page
        await page.reload();
        await usersPage.expectPageReady();

        // Assert - Both users terlihat
        await usersPage.expectRowVisible(`Multi User A ${suffix1}`);
        await usersPage.expectRowVisible(`Multi User B ${suffix2}`);

        // Check table headers
        const table = usersPage.table;
        const tableText = await table.textContent();
        expect(tableText).toMatch(/Nama|Name/i);
        expect(tableText).toMatch(/Email/i);

        // Cleanup
        await usersPage.deleteUser(`Multi User A ${suffix1}`);
        await usersPage.deleteUser(`Multi User B ${suffix2}`);
    });

    test('Positif - Delete user dengan confirmation dialog', async ({ page }) => {
        /**
         * Given ada user yang akan dihapus
         * When klik tombol delete
         * Then confirmation dialog muncul (handled via page.once)
         */

        // Arrange
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const userName = `To Delete ${suffix}`;

        await usersPage.createUser({
            name: userName,
            email: `todelete_${suffix}@example.com`,
            password: 'Password123.',
        });
        await expect(page.getByText('Akun Petugas berhasil dibuat.')).toBeVisible({ timeout: 15000 });

        // Act - Handle dialog dan delete
        page.once('dialog', dialog => {
            expect(dialog.type()).toBe('confirm');
            expect(dialog.message()).toMatch(/hapus|delete|yakin/i);
            dialog.accept();
        });

        await usersPage.deleteUser(userName);

        // Assert
        await expect(page.getByText('Akun Petugas berhasil dihapus.')).toBeVisible({ timeout: 15000 });
        await usersPage.expectRowHidden(userName);
    });

    test('Positif - Verifikasi action buttons (Edit & Delete) tersedia per row', async ({ page }) => {
        /**
         * Given ada user di tabel
         * When hover pada row
         * Then button Edit dan Delete terlihat
         */

        // Arrange
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const userName = `Action Test ${suffix}`;

        await usersPage.createUser({
            name: userName,
            email: `actions_${suffix}@example.com`,
            password: 'Password123.',
        });
        await expect(page.getByText('Akun Petugas berhasil dibuat.')).toBeVisible({ timeout: 15000 });

        // Act
        const row = page.locator('tr').filter({ hasText: userName }).first();
        await row.hover();

        // Assert
        const editBtn = row.locator('button[title="Edit"]');
        const deleteBtn = row.locator('button[title="Hapus"]');

        await expect(editBtn).toBeVisible({ timeout: 5000 });
        await expect(deleteBtn).toBeVisible({ timeout: 5000 });

        // Cleanup
        await usersPage.deleteUser(userName);
    });

    test('Skenario Batas - Update user dengan email yang sama (self-update)', async ({ page }) => {
        /**
         * Given user ingin update nama tanpa mengubah email
         * When submit form dengan email yang sama persis
         * Then update berhasil (self-email allowed)
         */

        // Arrange
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const userName = `Self Update ${suffix}`;
        const email = `self_${suffix}@example.com`;

        await usersPage.createUser({
            name: userName,
            email,
            password: 'Password123.',
        });
        await expect(page.getByText('Akun Petugas berhasil dibuat.')).toBeVisible({ timeout: 15000 });

        // Act - Edit dengan email SAMA
        await usersPage.editUser(userName, {
            name: `Updated ${userName}`,
            email, // Email SAMA
        });

        // Assert - Should success
        await expect(page.getByText('Akun Petugas berhasil diupdate.')).toBeVisible({ timeout: 15000 });
        await usersPage.expectRowVisible(`Updated ${userName}`);

        // Cleanup
        await usersPage.deleteUser(`Updated ${userName}`);
    });

    test('Positif - Empty state ditampilkan ketika tidak ada petugas', async ({ page }) => {
        /**
         * Given belum ada petugas terdaftar (atau semua sudah dihapus)
         * When halaman users dimuat
         * Then empty state message ditampilkan
         */

        // Note: Test ini akan skip jika sudah ada data dari test sebelumnya
        // Untuk production test, bisa gunakan database transaction rollback

        // Check if table is empty
        const rows = await usersPage.table.locator('tbody tr').count();

        if (rows === 0) {
            // Assert empty state
            await usersPage.expectEmptyStateVisible();
        } else {
            // Skip karena ada data
            test.skip(rows > 0, 'Skipped: Table has existing data');
        }
    });

    test('Negatif - Non-pjawab role tidak bisa akses halaman users', async ({ page }) => {
        /**
         * Given user dengan role selain pjawab (misal: petugas)
         * When mencoba akses /users
         * Then redirect atau 403 Forbidden
         */

        // Arrange - Logout dulu
        await page.goto('/logout');
        await page.waitForTimeout(1000);

        // Login sebagai petugas
        await authPage.loginAndWaitForDashboard('petugas@email.com', 'Password123.');

        // Act - Coba akses /users
        await page.goto('/users');
        await page.waitForTimeout(2000);

        // Assert - Tidak bisa akses (redirect atau 403)
        const currentUrl = page.url();
        const isForbidden = currentUrl.includes('/dashboard') ||
            currentUrl.includes('/403') ||
            await page.locator('text=/forbidden|tidak diizinkan|access denied/i').isVisible({ timeout: 5000 }).catch(() => false);

        expect(isForbidden).toBeTruthy();
    });
});
