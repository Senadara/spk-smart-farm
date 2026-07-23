import { test, expect } from '@playwright/test';
import { AuthPage } from '../pages/AuthPage.js';
import { PenugasanPage } from '../pages/PenugasanPage.js';

test.describe.serial('Modul Penugasan - Board & Workflow - E2E Tests', () => {
    let authPage: AuthPage;
    let penugasanPage: PenugasanPage;

    test.setTimeout(120000);

    test.beforeEach(async ({ page }) => {
        // Arrange: Setup dan Login
        await page.route('**/:5173/**', route => route.abort());

        authPage = new AuthPage(page);
        penugasanPage = new PenugasanPage(page);

        // Login sebagai Penanggung Jawab
        await authPage.loginAndWaitForDashboard('pjawab@email.com', 'Password123.');
        await penugasanPage.goto();
    });

    /* ═══════════════════════════════════════════════════════════════════
       INDEX PAGE - UI RENDERING & STATS
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Halaman Index Penugasan dirender lengkap dengan Stats, Tabs, dan Create Button', async ({ page }) => {
        /**
         * Given: User login sebagai Penanggung Jawab
         * When: Mengakses halaman Penugasan
         * Then: UI elements utama dirender (Header, Stats, Tabs, Create Button)
         */

        // Assert: Header & Page Title
        await penugasanPage.expectPageReady();
        await expect(page.getByText('Penugasan & Laporan Tindakan')).toBeVisible();
        await expect(page.getByText(/Kelola tindak lanjut|analisa SPK/i).first()).toBeVisible();

        // Assert: Create Button (Pjawab only)
        await expect(penugasanPage.createButton).toBeVisible();

        // Assert: Stats Dashboard (5 stats) — scope to stats grid
        const statsGrid = page.locator('.grid.grid-cols-2.md\\:grid-cols-3.xl\\:grid-cols-5');
        const statsText = await statsGrid.textContent() || '';
        expect(statsText).toContain('Total Tugas');
        expect(statsText).toContain('To Do');
        expect(statsText).toContain('Dikerjakan');
        expect(statsText).toContain('Selesai');
        expect(statsText).toContain('Terlambat');

        // Assert: Tabs
        await expect(penugasanPage.activeTab).toBeVisible();
        await expect(penugasanPage.historyTab).toBeVisible();
        await expect(penugasanPage.activeTab.getByText(/Papan Tugas Aktif/i)).toBeVisible();
    });

    test('Positif - Stats Dashboard menampilkan angka yang valid', async ({ page }) => {
        /**
         * Given: Halaman Penugasan dimuat
         * When: Stats dashboard dirender
         * Then: Setiap stat menampilkan angka numerik yang valid (>= 0)
         */

        // Assert: Stats memiliki nilai numerik — scope to stats grid
        const statsGrid = page.locator('.grid.grid-cols-2.md\\:grid-cols-3.xl\\:grid-cols-5');
        const statValues = statsGrid.locator('.text-2xl');
        const count = await statValues.count();
        expect(count).toBeGreaterThanOrEqual(5);
        for (let i = 0; i < count; i++) {
            const text = await statValues.nth(i).textContent() || '';
            const val = parseInt(text.trim(), 10);
            expect(val).toBeGreaterThanOrEqual(0);
        }
    });

    test('Positif - Kanban Board (To Do & In Progress) dirender dengan kolom yang benar', async ({ page }) => {
        /**
         * Given: Tab Active dipilih
         * When: Kanban board dirender
         * Then: Kolom To Do dan In Progress tampil dengan header dan counter badge
         */

        // Assert: Board Heading
        await expect(page.getByText('Board Penugasan')).toBeVisible();

        // Assert: To Do Column
        await expect(page.getByText('To Do').first()).toBeVisible();
        const todoCounter = page.locator('h4').filter({ hasText: /To Do/i }).locator('span.text-slate-700, span.bg-white').first();
        await expect(todoCounter).toBeVisible();
        const todoCount = await todoCounter.textContent();
        expect(todoCount).toMatch(/^\d+$/);

        // Assert: In Progress Column
        await expect(page.getByText('Dikerjakan').first()).toBeVisible();
        const inProgressCounter = page.locator('h4').filter({ hasText: /Dikerjakan/i }).locator('span.text-sky-700, span.bg-white').first();
        await expect(inProgressCounter).toBeVisible();
        const inProgressCount = await inProgressCounter.textContent();
        expect(inProgressCount).toMatch(/^\d+$/);

        // Assert: Empty state messages atau task cards (new UI uses partial text)
        const bodyText = await page.locator('body').textContent() || '';
        const hasTodoContent = bodyText.includes('Tidak ada tugas') || bodyText.includes('To Do');
        const hasProgressContent = bodyText.includes('Dikerjakan');

        expect(hasTodoContent).toBeTruthy();
        expect(hasProgressContent).toBeTruthy();
    });

    test('Positif - Tab switching antara Active dan History berfungsi', async ({ page }) => {
        /**
         * Given: User berada di tab Active
         * When: Click tab History
         * Then: URL berubah, History table dirender, dan tab visual berubah
         */

        // Arrange: Pastikan di tab Active
        await expect(page).toHaveURL(/tab=active/);

        // Act: Switch ke History tab
        await penugasanPage.goToHistoryTab();

        // Assert: URL dan UI
        await expect(page).toHaveURL(/tab=history/);
        await expect(penugasanPage.historyHeading).toBeVisible();
        await expect(page.getByText('Histori & Arsip Tugas')).toBeVisible();

        // Assert: History table elements
        await expect(page.getByRole('columnheader', { name: /Judul Tugas/i })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: /Petugas/i })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: /Waktu Selesai/i })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: /Status/i })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: /Aksi/i })).toBeVisible();

        // Act: Switch kembali ke Active
        await penugasanPage.activeTab.click();

        // Assert: Kembali ke Active
        await expect(page).toHaveURL(/tab=active/);
        await expect(penugasanPage.boardHeading).toBeVisible();
    });

    /* ═══════════════════════════════════════════════════════════════════
       FILTERS & SEARCH
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Filter Priority di Kanban Board berfungsi', async ({ page }) => {
        /**
         * Given: User di halaman Kanban Board
         * When: Memilih priority filter (Urgent, Tinggi, Sedang, Rendah)
         * Then: URL query berubah dan filter diterapkan
         */

        // Assert: Priority filter visible
        const priorityFilter = page.locator('select[name="priority"]').first();
        await expect(priorityFilter).toBeVisible();

        // Act: Pilih Urgent
        await priorityFilter.selectOption('urgent');

        // Assert: URL mengandung priority=urgent
        await page.waitForURL(/priority=urgent/);
        await expect(page).toHaveURL(/priority=urgent/);

        // Act: Reset ke all
        await priorityFilter.selectOption('all');
        await page.waitForURL(/priority=all/);
        await expect(page).toHaveURL(/priority=all/);
    });

    test('Positif - Search box di Kanban berfungsi', async ({ page }) => {
        /**
         * Given: User di Kanban Board
         * When: Mengisi search box dan submit
         * Then: URL query berubah dengan parameter search
         */

        // Arrange: Locate search input
        const searchInput = page.locator('input[name="search"]').first();
        await expect(searchInput).toBeVisible();

        // Act: Type search query
        await searchInput.fill('test');
        await searchInput.press('Enter');

        // Assert: URL berubah
        await page.waitForURL(/search=test/);
        await expect(page).toHaveURL(/search=test/);
    });

    test('Positif - User filter di Kanban berfungsi (Pjawab only)', async ({ page }) => {
        /**
         * Given: Login sebagai Pjawab (melihat semua petugas)
         * When: Memilih user dari dropdown filter
         * Then: URL query berubah dengan user_id
         */

        // Assert: User filter visible (Pjawab only)
        const userFilter = page.locator('select[name="user_id"]').first();
        await expect(userFilter).toBeVisible();

        // Act: Pilih user pertama (bukan "all")
        const firstUserValue = await userFilter.locator('option').nth(1).getAttribute('value');
        if (firstUserValue) {
            await userFilter.selectOption(firstUserValue);
            await page.waitForURL(new RegExp(`user_id=${firstUserValue}`));
            await expect(page).toHaveURL(new RegExp(`user_id=${firstUserValue}`));
        }
    });

    test('Positif - Date range filter di History tab berfungsi', async ({ page }) => {
        /**
         * Given: User di tab History
         * When: Mengisi start_date dan end_date
         * Then: URL query berubah dengan date filters
         */

        // Act: Go to History tab
        await penugasanPage.goToHistoryTab();

        // Assert: Date inputs visible
        const startDateInput = page.locator('input[name="start_date"]');
        const endDateInput = page.locator('input[name="end_date"]');
        await expect(startDateInput).toBeVisible();
        await expect(endDateInput).toBeVisible();

        // Act: Set date range
        await startDateInput.fill('2026-01-01');
        await startDateInput.blur();
        await page.waitForTimeout(500);

        await endDateInput.fill('2026-12-31');
        await endDateInput.blur();

        // Assert: URL mengandung date parameters
        await page.waitForURL(/start_date=2026-01-01/);
        await expect(page).toHaveURL(/start_date=2026-01-01/);
        await expect(page).toHaveURL(/end_date=2026-12-31/);
    });

    /* ═══════════════════════════════════════════════════════════════════
       CREATE TASK - CRUD OPERATIONS
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Create Task dengan data minimal (Title + Priority)', async ({ page }) => {
        /**
         * Given: User membuka modal Create Task
         * When: Mengisi hanya field wajib (title, priority)
         * Then: Task berhasil dibuat dan muncul di Kanban Board
         */

        // Arrange: Generate unique data
        const timestamp = Date.now();
        const taskTitle = `Task Minimal ${timestamp}`;

        // Act: Open modal dan isi form
        await penugasanPage.createButton.click();
        await page.waitForTimeout(1000);
        await expect(page.getByRole('heading', { name: /Buat Tugas Baru/i })).toBeVisible({ timeout: 10000 });

        // Scope semua interaksi ke MODAL aktif (hindari memilih form/tombol tersembunyi lain).
        const modal = page.locator('h3', { hasText: 'Buat Tugas Baru' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]')
            .first();

        await modal.locator('input[name="title"]').first().fill(taskTitle);
        await modal.locator('select[name="priority"]').first().selectOption('medium');

        // Submit button click + wait for navigation
        const submitBtn = modal.locator('button[type="submit"]').filter({ hasText: /Simpan/i }).first();
        await expect(submitBtn).toBeVisible({ timeout: 5000 });
        await Promise.all([
            page.waitForLoadState('domcontentloaded'),
            submitBtn.click(),
        ]);
        await page.waitForTimeout(1500);

        // Assert: Task muncul di board
        const bodyAfter = await page.locator('body').textContent() || '';
        console.log('Task created:', bodyAfter.includes(taskTitle));
        await expect(page.getByText(taskTitle, { exact: false })).toBeVisible({ timeout: 10000 });
    });

    test('Positif - Create Task dengan semua field lengkap (Title, Description, Priority, Assignee, Barn, Due Date)', async ({ page }) => {
        /**
         * Given: User membuka modal Create Task
         * When: Mengisi SEMUA field termasuk optional
         * Then: Task berhasil dibuat dengan semua data tersimpan
         */

        // Arrange: Generate unique data
        const timestamp = Date.now();
        const taskTitle = `Task Lengkap ${timestamp}`;
        const taskDescription = `Deskripsi lengkap untuk task ${timestamp}`;

        // Act: Open modal
        await penugasanPage.createButton.click();
        await page.waitForTimeout(1000);
        await expect(page.getByRole('heading', { name: /Buat Tugas Baru/i })).toBeVisible({ timeout: 10000 });

        // Scope semua interaksi ke MODAL aktif (hindari form/field tersembunyi lain di halaman).
        const modal = page.locator('h3', { hasText: 'Buat Tugas Baru' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]')
            .first();

        // Act: Isi field wajib
        await modal.locator('input[name="title"]').first().fill(taskTitle);
        await modal.locator('textarea[name="description"]').first().fill('QA Test: Task lengkap dengan semua field');
        await modal.locator('select[name="priority"]').first().selectOption('urgent');

        // Optional: Assignee (guard jika ada opsi selain default)
        const assigneeSelect = modal.locator('select[name="assigned_to"], select[name="assignee"]').first();
        if (await assigneeSelect.count() > 0 && await assigneeSelect.locator('option').count() > 1) {
            await assigneeSelect.selectOption({ index: 1 });
        }

        // Optional: Barn/kandang (guard jika ada opsi selain default — seed-dependent)
        const barnSelect = modal.locator('select[name="unit_budidaya_id"], select[name="barn"]').first();
        if (await barnSelect.count() > 0 && await barnSelect.locator('option').count() > 1) {
            const firstBarn = await barnSelect.locator('option').nth(1).getAttribute('value');
            if (firstBarn) {
                await barnSelect.selectOption(firstBarn);
            }
        }

        // Optional: Due date (besok) — hanya jika field ada
        const dueDateInput = modal.locator('input[name="due_date"]').first();
        if (await dueDateInput.count() > 0) {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const dueDateStr = tomorrow.toISOString().split('T')[0];
            await dueDateInput.fill(dueDateStr);
        }

        // Submit (scoped ke modal)
        const submitBtn = modal.locator('button[type="submit"]').filter({ hasText: /Simpan|Buat/i }).first();
        await expect(submitBtn).toBeVisible({ timeout: 5000 });
        await Promise.all([
            page.waitForLoadState('domcontentloaded'),
            submitBtn.click(),
        ]);
        await page.waitForTimeout(1500);

        // Assert: Task muncul di board
        await expect(page.getByText(taskTitle, { exact: false })).toBeVisible({ timeout: 10000 });
    });

    test('Positif - Create Task dengan SPK Reference selection', async ({ page }) => {
        /**
         * Given: Modal Create Task memiliki dropdown SPK Reference
         * When: Memilih salah satu SPK dari recent SPKs
         * Then: Task berhasil dibuat dengan SPK reference
         */

        // Arrange
        const timestamp = Date.now();
        const taskTitle = `Task dengan SPK ${timestamp}`;

        // Act: Open modal
        await penugasanPage.createButton.click();
        await page.waitForTimeout(1000);
        await expect(page.getByRole('heading', { name: /Buat Tugas Baru/i })).toBeVisible({ timeout: 10000 });

        // Scope ke MODAL (select[name=priority] juga dipakai filter board di halaman).
        const modal = page.locator('h3', { hasText: 'Buat Tugas Baru' })
            .locator('xpath=ancestor::div[contains(@class,"fixed")][1]')
            .first();

        // Fill form
        await modal.locator('input[name="title"]').first().fill(taskTitle);
        await modal.locator('select[name="priority"]').first().selectOption('medium');

        // Select SPK if available
        const spkSelect = modal.locator('select[name="spk_fuzzy_log_id"]').first();
        await expect(spkSelect).toBeVisible();
        const spkCount = await spkSelect.locator('option').count();

        if (spkCount > 1) { // Ada SPK selain "Tidak Berkaitan"
            const firstSpk = await spkSelect.locator('option').nth(1).getAttribute('value');
            if (firstSpk) {
                await spkSelect.selectOption(firstSpk);
            }
        }

        // Submit
        await modal.locator('button[type="submit"]').filter({ hasText: /Simpan|Buat/i }).first().click();
        await page.waitForTimeout(1500);

        // Assert
        await expect(page.getByText(taskTitle, { exact: false })).toBeVisible({ timeout: 10000 });
    });

    test('Negatif - Create Task tanpa Title (Required Validation)', async ({ page }) => {
        /**
         * Given: Modal Create Task terbuka
         * When: Submit form tanpa mengisi Title (required field)
         * Then: HTML5 validation mencegah submit, modal tetap terbuka
         */

        // Act: Open modal
        await penugasanPage.createButton.click();
        await expect(page.getByRole('heading', { name: /Buat Tugas Baru/i })).toBeVisible();

        // Jangan isi title, langsung submit
        const submitBtn = page.locator('button[type="submit"]').filter({ hasText: /Simpan|Buat/i });
        await submitBtn.click();

        // Assert: Modal masih visible (tidak tersubmit)
        await expect(page.getByRole('heading', { name: /Buat Tugas Baru/i })).toBeVisible({ timeout: 2000 });

        // Assert: Validation message (HTML5)
        const titleInput = page.locator('input[name="title"]');
        const validationMessage = await titleInput.evaluate((el: HTMLInputElement) => el.validationMessage);
        expect(validationMessage).toBeTruthy(); // Ada pesan validasi
    });

    /* ═══════════════════════════════════════════════════════════════════
       UPDATE & DELETE OPERATIONS
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Update Task dari Detail Page', async ({ page }) => {
        /**
         * Given: Task sudah dibuat
         * When: Edit task dari detail page (update title, description, priority)
         * Then: Perubahan tersimpan
         */

        // Arrange: Create task dulu
        const timestamp = Date.now();
        const originalTitle = `Task Original ${timestamp}`;
        const updatedTitle = `Task Updated ${timestamp}`;

        await penugasanPage.createTask({
            title: originalTitle,
            description: 'Deskripsi original',
            priority: 'low',
        });

        await expect(page.getByText('Tugas berhasil dibuat.')).toBeVisible({ timeout: 10000 });

        // Act: Go to detail page
        await page.getByText(originalTitle).first().click();
        await expect(page.getByRole('heading', { name: originalTitle })).toBeVisible();

        // Act: Edit form di sidebar kanan — scope ke form "Edit Tugas".
        // Halaman detail juga punya textarea[name=description] milik form "Kirim Laporan" di DOM.
        const editForm = page.locator('form').filter({
            has: page.getByRole('button', { name: /Simpan Perubahan/i }),
        }).first();

        await editForm.locator('input[name="title"]').fill(updatedTitle);
        await editForm.locator('textarea[name="description"]').fill('Deskripsi updated');
        await editForm.locator('select[name="priority"]').selectOption('urgent');

        // Submit
        await editForm.getByRole('button', { name: /Simpan Perubahan/i }).click();

        // Assert: Success redirect ke index penugasan (controller redirect ke route tanpa query string)
        await expect(page.getByText('Tugas berhasil diperbarui.')).toBeVisible({ timeout: 10000 });
        await expect(page).toHaveURL(/\/penugasan(\?|$)/);

        // Assert: Updated title visible
        await expect(page.getByText(updatedTitle)).toBeVisible();
    });

    test('Positif - Delete Task dari Detail Page', async ({ page }) => {
        /**
         * Given: Task sudah dibuat
         * When: Delete task dari detail page
         * Then: Task terhapus dan redirect ke index
         */

        // Arrange: Create task
        const timestamp = Date.now();
        const taskTitle = `Task To Delete ${timestamp}`;

        await penugasanPage.createTask({
            title: taskTitle,
            description: 'Will be deleted',
            priority: 'medium',
        });

        await expect(page.getByText('Tugas berhasil dibuat.')).toBeVisible({ timeout: 10000 });

        // Act: Go to detail
        await page.getByText(taskTitle).first().click();
        await expect(page.getByRole('heading', { name: taskTitle })).toBeVisible();

        // Act: Click delete button (with confirmation)
        page.once('dialog', dialog => dialog.accept());
        await page.locator('button[type="submit"]').filter({ hasText: /Hapus Tugas Permanen/i }).click();

        // Assert: Success redirect
        await expect(page.getByText('Tugas berhasil dihapus.')).toBeVisible({ timeout: 10000 });
        await expect(page).toHaveURL(/\/penugasan/);

        // Assert: Task tidak ada lagi
        await expect(page.getByText(taskTitle)).not.toBeVisible();
    });

    /* ═══════════════════════════════════════════════════════════════════
       STATUS MANAGEMENT
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Start Task (TODO → IN_PROGRESS) dari Detail Page', async ({ page }) => {
        /**
         * Given: Task dengan status TODO
         * When: Click "Mulai Kerjakan"
         * Then: Status berubah ke IN_PROGRESS, button "Kirim Laporan" muncul
         */

        // Arrange: Create task
        const timestamp = Date.now();
        const taskTitle = `Task To Start ${timestamp}`;

        await penugasanPage.createTask({
            title: taskTitle,
            description: 'Will be started',
            priority: 'high',
        });

        await expect(page.getByText('Tugas berhasil dibuat.')).toBeVisible({ timeout: 10000 });

        // Act: Go to detail
        await page.getByText(taskTitle).first().click();
        await expect(page.getByRole('heading', { name: taskTitle })).toBeVisible();

        // Assert: Task masih TODO, ada button "Mulai Kerjakan"
        await expect(page.getByText('To Do', { exact: false })).toBeVisible();
        await expect(page.getByRole('button', { name: /Mulai Kerjakan/i })).toBeVisible();

        // Act: Start task
        await page.getByRole('button', { name: /Mulai Kerjakan/i }).click();

        // Assert: Status updated. Badge status = "Dikerjakan" (exact) — bedakan dari
        // <option>Masih Dikerjakan</option> di form Kirim Laporan yang selalu ada di DOM.
        await expect(page.getByText('Status tugas diperbarui.')).toBeVisible({ timeout: 10000 });
        await expect(page.getByText('Dikerjakan', { exact: true }).first()).toBeVisible();

        // Assert: Tombol "Kirim Laporan" muncul
        await expect(page.getByRole('button', { name: /Kirim Laporan/i })).toBeVisible();
    });

    test('Positif - Cancel Task dari Detail Page', async ({ page }) => {
        /**
         * Given: Task dengan status TODO atau IN_PROGRESS
         * When: Click "Batalkan" dan konfirmasi
         * Then: Status berubah ke CANCELLED
         */

        // Arrange: Create task
        const timestamp = Date.now();
        const taskTitle = `Task To Cancel ${timestamp}`;

        await penugasanPage.createTask({
            title: taskTitle,
            description: 'Will be cancelled',
            priority: 'low',
        });

        await expect(page.getByText('Tugas berhasil dibuat.')).toBeVisible({ timeout: 10000 });

        // Act: Go to detail
        await page.getByText(taskTitle).first().click();
        await expect(page.getByRole('heading', { name: taskTitle })).toBeVisible();

        // Act: Cancel task (with confirmation)
        page.once('dialog', dialog => dialog.accept());
        await page.getByRole('button', { name: /Batalkan/i }).click();

        // Assert: Status updated
        await expect(page.getByText('Status tugas diperbarui.')).toBeVisible({ timeout: 10000 });

        // Assert: Status badge changed → "Dibatalkan" (exact, badge pertama di DOM)
        await expect(page.getByText('Dibatalkan', { exact: true }).first()).toBeVisible();
    });

});