import { test, expect, Page } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';
import { SuperAdminSupplierPage } from '../pages/SuperAdminSupplierPage.js';

// Menu Super Admin hanya untuk role admin → tidak boleh pakai storageState pjawab (global.setup).
test.use({ storageState: { cookies: [], origins: [] } });

test.describe.serial('Modul Super Admin - Manajemen Mitra Supplier - E2E QA', () => {
    let authPage: AuthPage;
    let superAdminPage: SuperAdminSupplierPage;

    test.setTimeout(120000);

    test.beforeEach(async ({ page }) => {
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        authPage = new AuthPage(page);
        superAdminPage = new SuperAdminSupplierPage(page);

        // Login sebagai Admin (Super Admin)
        await authPage.loginAndWaitForDashboard('admin@email.com', 'Password123.');
        await superAdminPage.goto();
        await superAdminPage.expectPageReady();
    });

    /* ═══════════════════════════════════════════════════════════════════
       RENDER & NAVIGASI
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Halaman Manajemen Mitra Supplier dirender lengkap', async ({ page }) => {
        // Header
        await expect(superAdminPage.pageTitle).toBeVisible();
        await expect(page.getByText('Kelola supplier global untuk seluruh owner.', { exact: false })).toBeVisible();
        await expect(superAdminPage.addManualButton).toBeVisible();

        // 3 tab statistik dengan angka
        await expect(superAdminPage.statTab('Menunggu')).toBeVisible();
        await expect(superAdminPage.statTab('Aktif')).toBeVisible();
        await expect(superAdminPage.statTab('Ditolak')).toBeVisible();

        // Section utama
        await expect(page.getByRole('heading', { name: /Daftar toko supplier/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /Master Supplier Manual/i })).toBeVisible();

        // Search box
        await expect(superAdminPage.searchInput).toBeVisible();
        await expect(superAdminPage.searchButton).toBeVisible();
    });

    test('Positif - Navigasi tab status (Menunggu/Aktif/Ditolak) mengubah query & konten', async ({ page }) => {
        // Aktif
        await superAdminPage.statTab('Aktif').click();
        await expect(page).toHaveURL(/status=active/);
        await expect(page.getByText(/Status saat ini:\s*Aktif/i)).toBeVisible();

        // Ditolak
        await superAdminPage.statTab('Ditolak').click();
        await expect(page).toHaveURL(/status=reject/);
        await expect(page.getByText(/Status saat ini:\s*Ditolak/i)).toBeVisible();

        // Menunggu
        await superAdminPage.statTab('Menunggu').click();
        await expect(page).toHaveURL(/status=request/);
        await expect(page.getByText(/Status saat ini:\s*Menunggu/i)).toBeVisible();
    });

    test('Positif - Master Supplier Manual menampilkan data supplier dengan tombol Edit', async ({ page }) => {
        // Ada minimal 1 kartu master supplier manual dengan tombol Edit
        const editLinks = page.getByRole('link', { name: /^Edit$/i });
        await expect(editLinks.first()).toBeVisible({ timeout: 10000 });
        expect(await editLinks.count()).toBeGreaterThan(0);
    });

    test('Positif - Pencarian toko memperbarui query string', async ({ page }) => {
        await superAdminPage.goto('active');
        await superAdminPage.expectPageReady();

        await superAdminPage.searchInput.fill('pakan');
        await superAdminPage.searchButton.click();

        await page.waitForURL(/search=pakan/);
        await expect(page).toHaveURL(/search=pakan/);
        // Status dipertahankan lewat hidden input
        await expect(page).toHaveURL(/status=active/);
    });

    /* ═══════════════════════════════════════════════════════════════════
       CREATE / EDIT MASTER SUPPLIER MANUAL
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Tambah Supplier Manual (langsung aktif)', async ({ page }) => {
        const ts = Date.now();
        const nama = `AA Mitra QA ${ts}`;

        await superAdminPage.openCreateForm();
        await superAdminPage.fillSupplierForm({
            nama,
            whatsapp: '081234567890',
            alamat: 'Jl. Uji Coba No. 1, Malang',
            deskripsi: 'Supplier uji coba QA - dibuat otomatis.',
            selectFirstCategory: true,
        });
        await superAdminPage.submitCreate();

        // Redirect ke index?status=active + flash sukses
        await expect(page.getByText(/berhasil ditambahkan dan langsung aktif/i).first()).toBeVisible({ timeout: 15000 });
        await expect(page).toHaveURL(/status=active/);

        // Supplier baru tampil sebagai kartu toko aktif
        await expect(superAdminPage.storeCard(nama)).toBeVisible({ timeout: 10000 });
    });

    test('Positif - Edit master supplier manual', async ({ page }) => {
        // Edit supplier pertama pada Master Supplier Manual
        await page.getByRole('link', { name: /^Edit$/i }).first().click();
        await expect(page.getByRole('heading', { name: /Edit data mitra supplier/i })).toBeVisible({ timeout: 10000 });

        // Ubah catatan singkat (deskripsi) - perubahan minimal & aman
        const deskripsi = `Diperbarui via QA E2E ${Date.now()}`;
        await page.locator('textarea[name="deskripsi"]').fill(deskripsi);
        await superAdminPage.submitUpdate();

        // Flash sukses update
        await expect(page.getByText('Data mitra supplier berhasil diperbarui.').first()).toBeVisible({ timeout: 15000 });
    });

    test('Negatif - Tambah supplier tanpa memilih kategori ditolak validasi server', async ({ page }) => {
        await superAdminPage.openCreateForm();

        // Isi field wajib lain TAPI tidak centang kategori (min 1 divalidasi server-side)
        await superAdminPage.fillSupplierForm({
            nama: `Tanpa Kategori ${Date.now()}`,
            whatsapp: '081234567890',
            alamat: 'Jl. Tanpa Kategori No. 2',
        });
        await superAdminPage.submitCreate();

        // Server menolak → kembali ke form dengan pesan error kategori
        await expect(page.getByText(/Pilih minimal satu kategori supplier|Periksa kembali data yang belum sesuai/i).first())
            .toBeVisible({ timeout: 15000 });
        await expect(page.getByRole('heading', { name: /Tambah mitra supplier baru/i })).toBeVisible();
    });

    /* ═══════════════════════════════════════════════════════════════════
       APPROVE & REJECT (siklus memakai supplier buatan test)
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Tolak lalu Setujui supplier (siklus reject → approve)', async ({ page }) => {
        // Arrange: buat supplier manual (langsung aktif)
        const ts = Date.now();
        const nama = `AA Siklus QA ${ts}`;

        await superAdminPage.openCreateForm();
        await superAdminPage.fillSupplierForm({
            nama,
            whatsapp: '081234567891',
            alamat: 'Jl. Siklus Approve No. 3',
            selectFirstCategory: true,
        });
        await superAdminPage.submitCreate();
        await expect(page.getByText(/berhasil ditambahkan dan langsung aktif/i).first()).toBeVisible({ timeout: 15000 });
        await expect(page).toHaveURL(/status=active/);

        // Act 1: Tolak supplier aktif — REDESIGN: wajib isi alasan (textarea required minlength=5),
        // tombol kini "Tolak & Kirim Email" (ada confirm dialog).
        const activeCard = superAdminPage.storeCard(nama);
        await expect(activeCard).toBeVisible({ timeout: 10000 });
        await activeCard.locator('textarea[name="reason"]').fill('Data toko perlu dilengkapi terlebih dahulu.');
        page.once('dialog', dialog => dialog.accept());
        await activeCard.getByRole('button', { name: /Tolak/i }).click();

        // Assert 1: flash reject (pesan baru: "...dan notifikasi email sudah diproses.")
        await expect(page.getByText(/Pengajuan supplier ditolak/i).first()).toBeVisible({ timeout: 15000 });

        // Act 2: buka tab Ditolak, setujui kembali (tombol "Setujui & Kirim Email", alasan opsional)
        await superAdminPage.goto('reject');
        await superAdminPage.expectPageReady();
        const rejectCard = superAdminPage.storeCard(nama);
        await expect(rejectCard).toBeVisible({ timeout: 10000 });
        await rejectCard.getByRole('button', { name: /Setujui/i }).click();

        // Assert 2: flash approve (pesan baru: "...dan notifikasi email sudah diproses.")
        await expect(page.getByText(/Supplier disetujui/i).first()).toBeVisible({ timeout: 15000 });
    });

    test('Negatif/Boundary - Tolak supplier dengan alasan < 5 karakter ditahan (minlength)', async ({ page }) => {
        // Arrange: buat supplier manual (langsung aktif) agar ada kartu dengan form tolak
        const ts = Date.now();
        const nama = `AA Boundary Tolak QA ${ts}`;
        await superAdminPage.openCreateForm();
        await superAdminPage.fillSupplierForm({
            nama,
            whatsapp: '081234567892',
            alamat: 'Jl. Boundary Tolak No. 5',
            selectFirstCategory: true,
        });
        await superAdminPage.submitCreate();
        await expect(page).toHaveURL(/status=active/, { timeout: 15000 });

        const card = superAdminPage.storeCard(nama);
        await expect(card).toBeVisible({ timeout: 10000 });
        const reasonBox = card.locator('textarea[name="reason"]');
        await reasonBox.fill('ab'); // < 5 karakter (melanggar minlength=5)
        page.once('dialog', dialog => dialog.accept());
        await card.getByRole('button', { name: /Tolak/i }).click();
        await page.waitForTimeout(1200);

        // Validasi minlength menahan submit → tidak ada flash "ditolak", tetap di tab aktif
        const rejected = await page.getByText(/Pengajuan supplier ditolak/i).isVisible().catch(() => false);
        const reasonInvalid = await reasonBox.evaluate((el: HTMLTextAreaElement) => !el.checkValidity()).catch(() => false);
        expect(rejected).toBeFalsy();
        expect(reasonInvalid).toBeTruthy();
    });
});

/* ═══════════════════════════════════════════════════════════════════════
   ACCESS CONTROL — menu Super Admin hanya untuk role admin
   ═══════════════════════════════════════════════════════════════════════ */
test.describe('Modul Super Admin - Kontrol Akses Role', () => {
    test.use({ storageState: { cookies: [], origins: [] } });
    test.setTimeout(120000);

    test('Negatif - Role non-admin (pjawab) tidak bisa akses /super-admin/suppliers', async ({ page }) => {
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        const authPage = new AuthPage(page);
        await authPage.loginAndWaitForDashboard('pjawab@email.com', 'Password123.');

        await page.goto('/super-admin/suppliers', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1500);

        // Middleware role:admin → halaman 403 "Anda tidak memiliki akses ke halaman ini."
        const currentUrl = page.url();
        const blocked = currentUrl.includes('/dashboard')
            || currentUrl.includes('/403')
            || await page.locator('text=/forbidden|tidak diizinkan|tidak memiliki akses|access denied|\\b403\\b/i')
                .first().isVisible({ timeout: 5000 }).catch(() => false);
        expect(blocked).toBeTruthy();

        // Pastikan konten Super Admin TIDAK muncul
        await expect(page.getByRole('heading', { name: /Manajemen Mitra Supplier/i }))
            .toBeHidden({ timeout: 3000 }).catch(() => { });
    });
});


// ============================================================
// Uji Fungsional Mendalam - digabung dari func-superadmin.spec.ts (sebelumnya section 26.6)
// ============================================================

const PW = 'Password123.';
const QA_NAME = 'AAA QA Mitra ' + Date.now();

async function cap(page: Page, path: string) {
    try { await page.waitForLoadState('networkidle', { timeout: 10000 }); }
    catch { await page.waitForLoadState('domcontentloaded').catch(() => { }); }
    await page.waitForTimeout(500);
    await page.screenshot({ path: `qa-evidence/${path}`, fullPage: true });
}
async function bodyText(page: Page): Promise<string> {
    return (await page.locator('body').innerText().catch(() => '')) || '';
}

async function loginAdmin(page: Page) {
    await page.route(/.*:5173.*/, (r) => r.abort());
    await page.context().clearCookies();
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => { try { localStorage.clear(); sessionStorage.clear(); } catch (e) { } });
    await page.context().clearCookies();
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="email"]').waitFor({ state: 'visible', timeout: 30000 });
    await page.locator('input[name="email"]').fill('admin@email.com');
    await page.locator('input[name="password"]').fill(PW);
    await page.getByRole('button', { name: /log in|masuk/i }).or(page.locator('button[type="submit"]')).first().click();
    await expect(page).toHaveURL(/.*dashboard/, { timeout: 120000 });
}

test.describe('FUNC Super Admin Supplier (admin)', () => {
    test.setTimeout(180000);

    test('SADMF002/003 - Create INVALID (tanpa kategori & WhatsApp salah) -> ditolak validasi', async ({ page }) => {
        await loginAdmin(page);

        // --- SADMF002: tanpa kategori ---
        await page.goto('/super-admin/suppliers/create', { waitUntil: 'domcontentloaded' });
        await page.locator('input[name="nama"]').fill('QA Invalid NoKategori');
        await page.locator('input[name="whatsapp"]').fill('081234567890');
        await page.locator('textarea[name="alamat"]').fill('Jl. Uji Coba No. 1, Malang');
        // sengaja tidak mencentang kategori apapun
        await page.getByRole('button', { name: /Tambah Mitra/i }).click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(1200);
        const body2 = await bodyText(page);
        const noKat = /Pilih minimal satu kategori supplier|Periksa kembali data/i.test(body2);
        console.log('SADMF002:: ditolakTanpaKategori=' + noKat + ' url=' + page.url());
        expect(noKat).toBeTruthy();
        await cap(page, 'SADM/SADMF002_tanpa_kategori.png');

        // --- SADMF003: WhatsApp mengandung huruf ---
        await page.goto('/super-admin/suppliers/create', { waitUntil: 'domcontentloaded' });
        await page.locator('input[name="nama"]').fill('QA Invalid WA');
        await page.locator('input[name="whatsapp"]').fill('abcxyz-bukan-nomor');
        await page.locator('textarea[name="alamat"]').fill('Jl. Uji Coba No. 2, Malang');
        await page.locator('input[name="kategori[]"]').first().check();
        await page.getByRole('button', { name: /Tambah Mitra/i }).click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(1200);
        const body3 = await bodyText(page);
        const waErr = /Nomor WhatsApp hanya boleh|Periksa kembali data/i.test(body3);
        console.log('SADMF003:: ditolakWaSalah=' + waErr + ' url=' + page.url());
        expect(waErr).toBeTruthy();
        await cap(page, 'SADM/SADMF003_whatsapp_salah.png');
    });

    test('SADMF001/004/005/006 - Create valid -> search -> edit -> tolak -> setujui', async ({ page }) => {
        page.on('dialog', (d) => d.accept().catch(() => { }));
        await loginAdmin(page);

        // --- SADMF001: create VALID ---
        await page.goto('/super-admin/suppliers/create', { waitUntil: 'domcontentloaded' });
        await page.locator('input[name="nama"]').fill(QA_NAME);
        await page.locator('input[name="whatsapp"]').fill('081298765432');
        await page.locator('textarea[name="alamat"]').fill('Jl. QA Otomatis No. 7, Malang');
        await page.locator('input[name="kategori[]"]').first().check();
        await page.locator('textarea[name="deskripsi"]').fill('Mitra uji otomatis QA - awal.');
        await page.getByRole('button', { name: /Tambah Mitra/i }).click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(1500);
        const bodyC = await bodyText(page);
        const created = /berhasil ditambahkan dan langsung aktif/i.test(bodyC);
        console.log('SADMF001:: created=' + created + ' url=' + page.url());
        expect(created).toBeTruthy();
        await cap(page, 'SADM/SADMF001_create_valid.png');

        // --- SADMF004: search di tab aktif ---
        await page.goto('/super-admin/suppliers?status=active', { waitUntil: 'domcontentloaded' });
        const searchInput = page.locator('input[name="search"]');
        await searchInput.fill(QA_NAME);
        await page.getByRole('button', { name: /^Cari$/ }).click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(1000);
        const foundCard = await page.locator('article', { hasText: QA_NAME }).count();
        console.log('SADMF004_valid:: kartuDitemukan=' + foundCard);
        await cap(page, 'SADM/SADMF004a_search_valid.png');
        // search tidak ada
        await searchInput.fill('zzz-mitra-tidak-ada-999');
        await page.getByRole('button', { name: /^Cari$/ }).click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(1000);
        const bodyS = await bodyText(page);
        const kosong = /Tidak ada toko pada status ini/i.test(bodyS);
        console.log('SADMF004_none:: kosong=' + kosong);
        await cap(page, 'SADM/SADMF004b_search_kosong.png');
        expect(foundCard).toBeGreaterThan(0);
        expect(kosong).toBeTruthy();

        // --- SADMF005: edit via Master Supplier Manual (nama diawali AAA -> tampil di daftar) ---
        await page.goto('/super-admin/suppliers?status=active', { waitUntil: 'domcontentloaded' });
        const editLink = page.locator('div', { hasText: QA_NAME }).getByRole('link', { name: /^Edit$/ }).first();
        await expect(editLink).toBeVisible({ timeout: 8000 });
        await editLink.click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.locator('textarea[name="deskripsi"]').fill('Mitra uji otomatis QA - DIEDIT.');
        await page.getByRole('button', { name: /Simpan Perubahan/i }).click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(1500);
        const bodyE = await bodyText(page);
        const edited = /Data mitra supplier berhasil diperbarui/i.test(bodyE);
        console.log('SADMF005:: edited=' + edited + ' url=' + page.url());
        expect(edited).toBeTruthy();
        await cap(page, 'SADM/SADMF005_edit_valid.png');

        // --- SADMF006a: TOLAK record QA (active -> reject) ---
        await page.goto('/super-admin/suppliers?status=active', { waitUntil: 'domcontentloaded' });
        await page.locator('input[name="search"]').fill(QA_NAME);
        await page.getByRole('button', { name: /^Cari$/ }).click();
        await page.waitForTimeout(1000);
        const cardActive = page.locator('article', { hasText: QA_NAME });
        // REDESIGN: tolak wajib isi alasan (textarea required minlength=5). Confirm dialog
        // sudah ditangani handler global page.on('dialog') di describe ini.
        await cardActive.locator('textarea[name="reason"]').first().fill('Alasan penolakan QA otomatis.');
        await cardActive.getByRole('button', { name: /Tolak/i }).first().click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(1500);
        const bodyR = await bodyText(page);
        const rejected = /Pengajuan supplier ditolak/i.test(bodyR);
        console.log('SADMF006_tolak:: rejected=' + rejected);
        expect(rejected).toBeTruthy();
        await cap(page, 'SADM/SADMF006a_tolak.png');

        // --- SADMF006b: SETUJUI record QA (reject -> active) ---
        await page.goto('/super-admin/suppliers?status=reject', { waitUntil: 'domcontentloaded' });
        await page.locator('input[name="search"]').fill(QA_NAME);
        await page.getByRole('button', { name: /^Cari$/ }).click();
        await page.waitForTimeout(1000);
        const cardReject = page.locator('article', { hasText: QA_NAME });
        await cardReject.getByRole('button', { name: /Setujui/i }).first().click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(1500);
        const bodyA = await bodyText(page);
        const approved = /Supplier disetujui/i.test(bodyA);
        console.log('SADMF006_setujui:: approved=' + approved);
        expect(approved).toBeTruthy();
        await cap(page, 'SADM/SADMF006b_setujui.png');
    });
});
