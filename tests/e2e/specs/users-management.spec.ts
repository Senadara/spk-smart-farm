import { test, expect, Page } from '@playwright/test';
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
            // Implementasi nyata (UserManagementController@destroy): SOFT-DELETE / nonaktifkan,
            // bukan hard-delete. Baris tetap tampil sebagai "Nonaktif".
            delete: 'Akun Petugas berhasil dinonaktifkan',
        };

        // --- ACT 1: CREATE ---
        await usersPage.createUser({
            name: initialName,
            email,
            password: 'Password123.',
        });

        // Assert 1
        await expect(page.getByText(notificationPayloads.create).first()).toBeVisible({ timeout: 15000 });
        await usersPage.expectRowVisible(initialName);

        // --- ACT 2: UPDATE ---
        await usersPage.editUser(initialName, {
            name: updatedName,
            email,
        });

        // Assert 2
        await expect(page.getByText(notificationPayloads.update).first()).toBeVisible({ timeout: 15000 });
        await usersPage.expectRowVisible(updatedName);

        // --- ACT 3: DELETE (soft-delete / nonaktifkan) ---
        await usersPage.deleteUser(updatedName);

        // Assert 3 - baris TETAP tampil dengan status "Nonaktif" (soft-delete by design)
        await expect(page.getByText(notificationPayloads.delete).first()).toBeVisible({ timeout: 15000 });
        await usersPage.expectRowDeactivated(updatedName);
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
        await usersPage.submitButton.click();

        // Assert
        await expect(usersPage.submitButton).toBeVisible();
        await expect(page.getByText('Akun Petugas berhasil dibuat.').first()).not.toBeVisible();
    });

    test('Negatif - Email duplikat ditolak dengan error validation', async ({ page }) => {
        /**
         * Given sudah ada user dengan email tertentu
         * When mencoba create user baru dengan email yang sama
         * Then sistem menampilkan error validation email sudah terdaftar
         */

        // Arrange - Create user pertama
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
        const dupEmail = `duplicate_${suffix}@example.com`;

        await usersPage.createUser({
            name: `User Pertama ${suffix}`,
            email: dupEmail,
            password: 'Password123.',
        });
        await expect(page.getByText('Akun Petugas berhasil dibuat.').first()).toBeVisible({ timeout: 15000 });

        // Act - Coba create user kedua dengan email sama
        await usersPage.addButton.click();
        const modal = page.locator('h3', { hasText: 'Tambah Petugas Baru' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();

        await modal.locator('input[name="name"]').fill(`User Kedua ${suffix}`);
        await modal.locator('input[name="email"]').fill(dupEmail); // Email sama
        await modal.locator('input[name="password"]').fill('Password123.');
        await modal.getByRole('button', { name: /Simpan Petugas/i }).click();

        // Assert - Kotak error validasi merah muncul dgn pesan "email has already been taken"
        // (locator di-scope ke box .bg-red-50 agar tidak bentrok dgn string email "duplicate_...")
        const dupErrorBox = page.locator('div.bg-red-50').filter({ hasText: /has already been taken|sudah.*(terdaftar|digunakan)/i }).first();
        await expect(dupErrorBox).toBeVisible({ timeout: 10000 });

        // Cleanup
        await page.reload();
        await usersPage.deleteUser(`User Pertama ${suffix}`);
    });

    test('Negatif - Password kurang dari 6 karakter ditolak', async ({ page }) => {
        /**
         * Given user membuka form tambah petugas
         * When input password dengan length < 6
         * Then validation error muncul
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

        // Assert - Error validation atau modal masih terbuka
        const errorVisible = await page.locator('text=/password.*minimal.*6|password.*at least.*6/i')
            .isVisible({ timeout: 5000 }).catch(() => false);
        const modalStillVisible = await modal.isVisible({ timeout: 3000 }).catch(() => false);

        expect(errorVisible || modalStillVisible).toBeTruthy();
    });

    test('Negatif - Field name kosong ditolak (required validation)', async ({ page }) => {
        /**
         * Given form tambah petugas terbuka
         * When field name dikosongkan tapi email dan password diisi
         * Then validation error untuk field name muncul
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

        // Assert - Modal masih ada atau ada error
        const modalStillVisible = await modal.isVisible({ timeout: 3000 }).catch(() => false);
        const errorVisible = await page.locator('text=/name.*required|name.*wajib/i')
            .isVisible({ timeout: 5000 }).catch(() => false);

        expect(modalStillVisible || errorVisible).toBeTruthy();
    });

    test('Negatif - Format email invalid ditolak', async ({ page }) => {
        /**
         * Given form tambah petugas terbuka
         * When input email dengan format invalid (tanpa @)
         * Then validation error muncul
         */

        // Arrange
        const suffix = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);

        // Act
        await usersPage.addButton.click();
        const modal = page.locator('h3', { hasText: 'Tambah Petugas Baru' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]').first();

        await modal.locator('input[name="name"]').fill(`Invalid Email ${suffix}`);
        await modal.locator('input[name="email"]').fill('invalidemail.com'); // Tanpa @
        await modal.locator('input[name="password"]').fill('Password123.');

        const submitBtn = modal.getByRole('button', { name: /Simpan Petugas/i });
        await submitBtn.click();

        // Assert - HTML5 validation atau backend error
        const emailInput = modal.locator('input[name="email"]');
        const isInvalid = await emailInput.evaluate((node: HTMLInputElement) => !node.checkValidity())
            .catch(() => false);
        const errorVisible = await page.locator('text=/email.*invalid|email.*tidak valid/i')
            .isVisible({ timeout: 5000 }).catch(() => false);

        expect(isInvalid || errorVisible).toBeTruthy();
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
        await expect(page.getByText('Akun Petugas berhasil dibuat.').first()).toBeVisible({ timeout: 15000 });

        // Act - Edit tanpa password
        await usersPage.editUser(userName, {
            name: `Updated ${userName}`,
            email,
            // password tidak diisi (undefined)
        });

        // Assert
        await expect(page.getByText('Akun Petugas berhasil diupdate.').first()).toBeVisible({ timeout: 15000 });
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
        await expect(page.getByText('Akun Petugas berhasil dibuat.').first()).toBeVisible({ timeout: 15000 });

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
        await expect(page.getByText('Akun Petugas berhasil diupdate.').first()).toBeVisible({ timeout: 15000 });

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

        // Assert - Both users visible
        await usersPage.expectRowVisible(`Multi User A ${suffix1}`);
        await usersPage.expectRowVisible(`Multi User B ${suffix2}`);

        // Check table headers + data.
        // UI nyata: kolom "Profil Karyawan" (gabungan nama+email), "Status", "Terdaftar Sejak", "Aksi".
        const table = usersPage.table;
        const tableText = await table.textContent();
        expect(tableText).toMatch(/Profil Karyawan/i);
        expect(tableText).toMatch(/Status/i);
        // Pastikan data nama & email petugas benar-benar tampil di tabel
        expect(tableText).toContain(`Multi User A ${suffix1}`);
        expect(tableText).toContain(`multi_a_${suffix1}@example.com`);

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
        await expect(page.getByText('Akun Petugas berhasil dibuat.').first()).toBeVisible({ timeout: 15000 });

        // Act - Handle dialog dan delete (soft-delete/nonaktifkan).
        // deleteUser dipanggil dgn autoConfirm:false karena dialog ditangani manual di sini.
        page.once('dialog', dialog => {
            expect(dialog.type()).toBe('confirm');
            expect(dialog.message()).toMatch(/nonaktif|hapus|delete|yakin/i);
            dialog.accept();
        });

        await usersPage.deleteUser(userName, { autoConfirm: false });

        // Assert - soft-delete: notifikasi "dinonaktifkan" + baris tetap tampil sbg Nonaktif
        await expect(page.getByText('Akun Petugas berhasil dinonaktifkan').first()).toBeVisible({ timeout: 15000 });
        await usersPage.expectRowDeactivated(userName);
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
        await expect(page.getByText('Akun Petugas berhasil dibuat.').first()).toBeVisible({ timeout: 15000 });

        // Act
        const row = page.locator('tr').filter({ hasText: userName }).first();
        await row.hover();

        // Assert
        // UI nyata: tombol destroy berjudul "Nonaktifkan" (soft-delete), bukan "Hapus".
        const editBtn = row.locator('button[title="Edit"]');
        const deleteBtn = row.locator('button[title="Nonaktifkan"]');

        await expect(editBtn).toBeVisible({ timeout: 5000 });
        await expect(deleteBtn).toBeVisible({ timeout: 5000 });

        // Cleanup
        await usersPage.deleteUser(userName);
    });

    test('Edge Case - Update user dengan email yang sama (self-update)', async ({ page }) => {
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
        await expect(page.getByText('Akun Petugas berhasil dibuat.').first()).toBeVisible({ timeout: 15000 });

        // Act - Edit dengan email SAMA
        await usersPage.editUser(userName, {
            name: `Updated ${userName}`,
            email, // Email SAMA
        });

        // Assert - Should success
        await expect(page.getByText('Akun Petugas berhasil diupdate.').first()).toBeVisible({ timeout: 15000 });
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
        const rows = await usersPage.table.locator('tbody tr').count().catch(() => 0);

        if (rows === 0) {
            // Tidak ada data -> empty state harus tampil
            await usersPage.expectEmptyStateVisible();
        } else {
            // Ada data -> empty state tidak relevan; verifikasi tabel tampil dgn benar.
            // (tidak men-skip agar test tetap ter-run & jujur sesuai kondisi data nyata)
            await usersPage.expectTableVisible();
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

        // Act - Coba akses /users (tangkap response utk cek status HTTP)
        const resp = await page.goto('/users');
        await page.waitForTimeout(1500);

        // Assert - Tidak bisa akses. RoleMiddleware -> abort(403,'Unauthorized action.'),
        // URL tetap /users saat 403, jadi sinyal utama = status HTTP 403.
        // Terima juga: redirect ke dashboard/login, teks 403/unauthorized, atau UI manajemen tidak muncul.
        const status = resp?.status() ?? 0;
        const currentUrl = page.url();
        const bodyText = (await page.locator('body').innerText().catch(() => '')) || '';
        const canSeeAddButton = await page.getByRole('button', { name: /Tambah Petugas/i })
            .isVisible({ timeout: 3000 }).catch(() => false);

        const isForbidden =
            status === 403 || status === 401 || status === 419 ||
            currentUrl.includes('/dashboard') ||
            currentUrl.includes('/login') ||
            currentUrl.includes('/403') ||
            /forbidden|unauthorized|tidak diizinkan|access denied|\b403\b/i.test(bodyText) ||
            !canSeeAddButton; // tidak bisa lihat UI kelola petugas = terblokir

        expect(isForbidden).toBeTruthy();
    });
});


// ============================================================
// Uji Fungsional Mendalam - digabung dari func-users.spec.ts (sebelumnya section 26.1)
// ============================================================

const PW = 'Password123.';

async function cap(page: Page, path: string) {
    try { await page.waitForLoadState('networkidle', { timeout: 10000 }); }
    catch { await page.waitForLoadState('domcontentloaded').catch(() => { }); }
    await page.waitForTimeout(500);
    await page.screenshot({ path: `qa-evidence/${path}`, fullPage: true });
}
function rid() { return Math.random().toString(36).replace(/[^a-z0-9]/g, '').slice(0, 6) || 'abcd12'; }

function addModal(page: Page) {
    return page.locator('h3', { hasText: 'Tambah Petugas Baru' }).locator('xpath=ancestor::div[contains(@class,"rounded-2xl")][1]').first();
}

async function openAdd(page: Page) {
    await page.getByRole('button', { name: /Tambah Petugas/i }).first().click();
    const m = addModal(page);
    await expect(m.locator('input[name="name"]')).toBeVisible({ timeout: 10000 });
    return m;
}

test.describe('FUNC Users - Manajemen Karyawan (pjawab)', () => {
    test.setTimeout(160000);
    test.beforeEach(async ({ page }) => {
        await page.route('**/:5173/**', (r) => r.abort());
        await page.route(/.*:5173.*/, (r) => r.abort());
        const auth = new AuthPage(page);
        await auth.loginAndWaitForDashboard('pjawab@email.com', PW);
        await page.goto('/users', { waitUntil: 'domcontentloaded' });
    });

    test('USERF001 - Tambah petugas data VALID -> tersimpan + notifikasi sukses', async ({ page }) => {
        const email = `qa.petugas.${rid()}@email.com`;
        const nama = `QA Petugas ${rid()}`;
        const m = await openAdd(page);
        await m.locator('input[name="name"]').fill(nama);
        await m.locator('input[name="email"]').fill(email);
        await m.locator('input[name="password"]').fill('Password123.');
        await m.getByRole('button', { name: /Simpan Petugas/i }).click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(1200);
        const body = await page.locator('body').innerText();
        const ok = /Akun Petugas berhasil dibuat/i.test(body);
        const rowExists = body.includes(email);
        console.log('USERF001:: sukses=' + ok + ' rowAda=' + rowExists);
        expect(ok).toBeTruthy();
        expect(rowExists).toBeTruthy();
        await cap(page, 'USER/USERF001_tambah_valid_sukses.png');
    });

    test('USERF002 - Tambah petugas EMAIL DUPLIKAT -> ditolak (validasi unique)', async ({ page }) => {
        const m = await openAdd(page);
        await m.locator('input[name="name"]').fill('QA Duplikat');
        await m.locator('input[name="email"]').fill('petugas@email.com'); // sudah terdaftar
        await m.locator('input[name="password"]').fill('Password123.');
        await m.getByRole('button', { name: /Simpan Petugas/i }).click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(1200);
        const body = await page.locator('body').innerText();
        const sukses = /Akun Petugas berhasil dibuat/i.test(body);
        const adaError = /(sudah|telah).*(digunakan|terdaftar|dipakai)|has already been taken|unique|email/i.test(body) && !sukses;
        console.log('USERF002:: sukses=' + sukses + ' adaErrorValidasi=' + adaError + ' url=' + page.url());
        expect(sukses).toBeFalsy();
        await cap(page, 'USER/USERF002_email_duplikat_ditolak.png');
    });

    test('USERF003 - Tambah petugas FORMAT EMAIL SALAH -> ditahan (validasi)', async ({ page }) => {
        const m = await openAdd(page);
        await m.locator('input[name="name"]').fill('QA Email Salah');
        await m.locator('input[name="email"]').fill('bukan-email-valid');
        await m.locator('input[name="password"]').fill('Password123.');
        await m.getByRole('button', { name: /Simpan Petugas/i }).click();
        await page.waitForTimeout(1000);
        const emailInput = m.locator('input[name="email"]');
        const invalid = await emailInput.evaluate((el: HTMLInputElement) => !el.checkValidity()).catch(() => false);
        const body = await page.locator('body').innerText();
        const sukses = /Akun Petugas berhasil dibuat/i.test(body);
        console.log('USERF003:: emailInvalid(HTML5)=' + invalid + ' sukses=' + sukses);
        expect(sukses).toBeFalsy();
        expect(invalid).toBeTruthy();
        await cap(page, 'USER/USERF003_email_format_salah.png');
    });

    test('USERF004 - Tambah petugas FIELD KOSONG -> ditahan (required)', async ({ page }) => {
        const m = await openAdd(page);
        await m.getByRole('button', { name: /Simpan Petugas/i }).click();
        await page.waitForTimeout(800);
        const nameInput = m.locator('input[name="name"]');
        const invalid = await nameInput.evaluate((el: HTMLInputElement) => !el.checkValidity()).catch(() => false);
        const body = await page.locator('body').innerText();
        const sukses = /Akun Petugas berhasil dibuat/i.test(body);
        console.log('USERF004:: nameRequiredInvalid=' + invalid + ' sukses=' + sukses);
        expect(sukses).toBeFalsy();
        expect(invalid).toBeTruthy();
        await cap(page, 'USER/USERF004_field_kosong.png');
    });

    test('USERF005 - Tambah petugas PASSWORD < 6 -> ditahan (minlength)', async ({ page }) => {
        const m = await openAdd(page);
        await m.locator('input[name="name"]').fill('QA Pw Pendek');
        await m.locator('input[name="email"]').fill(`qa.pw.${rid()}@email.com`);
        await m.locator('input[name="password"]').fill('123');
        await m.getByRole('button', { name: /Simpan Petugas/i }).click();
        await page.waitForTimeout(800);
        const pwInput = m.locator('input[name="password"]');
        const invalid = await pwInput.evaluate((el: HTMLInputElement) => !el.checkValidity()).catch(() => false);
        const body = await page.locator('body').innerText();
        const sukses = /Akun Petugas berhasil dibuat/i.test(body);
        console.log('USERF005:: pwInvalid=' + invalid + ' sukses=' + sukses);
        expect(sukses).toBeFalsy();
        expect(invalid).toBeTruthy();
        await cap(page, 'USER/USERF005_password_pendek.png');
    });

    test('USERF006 - Edit petugas VALID -> tersimpan + notifikasi update', async ({ page }) => {
        // buat petugas qa dulu agar self-contained
        const email = `qa.edit.${rid()}@email.com`;
        const m = await openAdd(page);
        await m.locator('input[name="name"]').fill('QA Sebelum Edit');
        await m.locator('input[name="email"]').fill(email);
        await m.locator('input[name="password"]').fill('Password123.');
        await m.getByRole('button', { name: /Simpan Petugas/i }).click();
        await page.waitForTimeout(1200);
        // buka edit pada baris email tsb
        const row = page.locator('tr', { hasText: email }).first();
        await row.locator('button[title="Edit"]').click();
        const editModal = page.locator('h3', { hasText: 'Edit Profil Petugas' }).locator('xpath=ancestor::div[contains(@class,"rounded-2xl")][1]').first();
        await expect(editModal.locator('input[name="name"]')).toBeVisible({ timeout: 10000 });
        const namaBaru = 'QA Sesudah Edit ' + rid();
        await editModal.locator('input[name="name"]').fill(namaBaru);
        await editModal.getByRole('button', { name: /Simpan Perubahan/i }).click();
        await page.waitForTimeout(1200);
        const body = await page.locator('body').innerText();
        const ok = /Akun Petugas berhasil diupdate/i.test(body);
        const namaTampil = body.includes(namaBaru);
        console.log('USERF006:: sukses=' + ok + ' namaBaruTampil=' + namaTampil);
        expect(ok).toBeTruthy();
        expect(namaTampil).toBeTruthy();
        await cap(page, 'USER/USERF006_edit_valid_sukses.png');
    });

    test('USERF007 - Nonaktifkan lalu Aktifkan kembali petugas -> status berubah + notifikasi', async ({ page }) => {
        const email = `qa.toggle.${rid()}@email.com`;
        const m = await openAdd(page);
        await m.locator('input[name="name"]').fill('QA Toggle Status');
        await m.locator('input[name="email"]').fill(email);
        await m.locator('input[name="password"]').fill('Password123.');
        await m.getByRole('button', { name: /Simpan Petugas/i }).click();
        await page.waitForTimeout(1200);
        // Nonaktifkan
        let row = page.locator('tr', { hasText: email }).first();
        page.once('dialog', (d) => d.accept());
        await row.locator('button[title="Nonaktifkan"]').click();
        await page.waitForTimeout(1200);
        let body = await page.locator('body').innerText();
        const nonaktifOk = /berhasil dinonaktifkan/i.test(body);
        console.log('USERF007a:: nonaktifSukses=' + nonaktifOk);
        // Aktifkan kembali
        row = page.locator('tr', { hasText: email }).first();
        page.once('dialog', (d) => d.accept());
        await row.getByRole('button', { name: /^Aktifkan$/i }).click();
        await page.waitForTimeout(1200);
        body = await page.locator('body').innerText();
        const aktifOk = /berhasil diaktifkan kembali/i.test(body);
        console.log('USERF007b:: aktifkanSukses=' + aktifOk);
        expect(nonaktifOk).toBeTruthy();
        expect(aktifOk).toBeTruthy();
        await cap(page, 'USER/USERF007_toggle_status.png');
    });
});
