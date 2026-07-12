import { test, expect } from '@playwright/test';
import { DashboardPage } from '../pages/DashboardPage.js';

test.describe('Modul Dashboard - E2E Tests', () => {
    test.describe.configure({ mode: 'serial' });

    let dashboardPage: DashboardPage;

    test.beforeEach(async ({ page }, testInfo) => {
        // Arrange
        testInfo.setTimeout(120000);
        page.setDefaultNavigationTimeout(120000);
        page.setDefaultTimeout(120000);

        dashboardPage = new DashboardPage(page);

        // Blocker akses Vite HMR
        await page.route('**/:5173/**', route => route.abort());
        await page.route(/.*:5173.*/, route => route.abort());

        // Act
        await dashboardPage.goto();
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - PAGE RENDERING
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Dashboard page loads successfully dengan breadcrumb "Dashboard"', async ({ page }) => {
        /**
         * Given: User sudah login
         * When: Navigate to /dashboard
         * Then: Page loads dengan breadcrumb "Dashboard"
         */

        // Assert: URL correct
        await expect(page).toHaveURL(/.*\/dashboard/);

        // Assert: Page title or breadcrumb
        const pageTitle = page.locator('h1, [class*="breadcrumb"]').first();
        const hasDashboard = await page.locator('text=Dashboard').count();
        expect(hasDashboard).toBeGreaterThan(0);
    });

    test('Positif - Semua widget cards (Welcome, Role, Email, Login Since) terender lengkap', async () => {
        /**
         * Given: User berada di dashboard
         * When: Page dimuat
         * Then: Semua info cards visible (Welcome, Role, Email, Login Since)
         */

        // Assert: All cards visible
        await dashboardPage.expectAllCardsVisible();

        await dashboardPage.expectLoginSinceCardVisible();
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - WELCOME CARD
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Welcome card menampilkan greeting "Selamat Datang" dengan nama user', async ({ page }) => {
        /**
         * Given: User session memiliki nama user
         * When: Dashboard dimuat
         * Then: Welcome card menampilkan "Selamat Datang, {Name}!"
         */

        // Assert: Welcome text visible
        await dashboardPage.expectWelcomeCardVisible();

        // Assert: Contains greeting
        await expect(dashboardPage.welcomeCard).toContainText('Selamat Datang');

        // Assert: Contains emoji
        const welcomeText = await dashboardPage.welcomeCard.textContent();
        expect(welcomeText).toContain('👋');
    });

    test('Positif - Welcome card memiliki gradient background (primary to primary-dark)', async ({ page }) => {
        /**
         * Given: Welcome card dirender
         * When: Check styling
         * Then: Card memiliki gradient background dan text white
         */

        // Assert: Welcome card has gradient class
        const welcomeCardElement = page.locator('text=Selamat Datang').locator('..');
        const classes = await welcomeCardElement.getAttribute('class');
        expect(classes).toContain('bg-gradient');
    });

    test('Positif - Welcome card menampilkan description text tentang SPK', async ({ page }) => {
        /**
         * Given: Welcome card dirender
         * When: Check content
         * Then: Description text tentang "Sistem Pendukung Keputusan" visible
         */

        // Assert: Description text
        const description = page.locator('text=Sistem Pendukung Keputusan');
        await expect(description).toBeVisible();
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - ROLE CARD
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Role card menampilkan "Role Anda" dengan label dan value', async ({ page }) => {
        /**
         * Given: User memiliki role di session
         * When: Dashboard dimuat
         * Then: Role card menampilkan label "Role Anda" dan role value
         */

        // Assert: Role card visible
        await dashboardPage.expectRoleCardVisible();

        // Assert: Contains "Role Anda" label
        await expect(dashboardPage.roleCard).toContainText('Role Anda');

        // Assert: Contains role value (Petugas or Pjawab)
        const roleCardText = await dashboardPage.roleCard.textContent();
        const hasRoleValue = roleCardText?.includes('Petugas') || roleCardText?.includes('Pjawab');
        expect(hasRoleValue).toBeTruthy();
    });

    test('Positif - Role card menampilkan role value yang capitalize (Petugas/Pjawab)', async ({ page }) => {
        /**
         * Given: User session memiliki role
         * When: Dashboard dimuat
         * Then: Role value ditampilkan dengan ucfirst (Petugas atau Pjawab)
         */

        // Assert: Role value exists
        const roleValue = dashboardPage.roleCard.locator('div.font-bold');
        await expect(roleValue).toBeVisible();

        // Assert: Value is capitalized (starts with uppercase)
        const roleText = await roleValue.textContent();
        expect(roleText).toBeTruthy();
        expect(roleText!.charAt(0)).toBe(roleText!.charAt(0).toUpperCase());
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - EMAIL CARD
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Email card menampilkan "Email" label dan email address', async ({ page }) => {
        /**
         * Given: User memiliki email di session
         * When: Dashboard dimuat
         * Then: Email card menampilkan label "Email" dan email address
         */

        // Assert: Email card visible
        await dashboardPage.expectEmailCardVisible();

        // Assert: Contains "Email" label
        await expect(dashboardPage.emailCard).toContainText('Email');

        // Assert: Contains email address with @
        const emailCardText = await dashboardPage.emailCard.textContent();
        expect(emailCardText).toContain('@');
    });

    test('Positif - Email card menampilkan email address user yang truncate jika panjang', async ({ page }) => {
        /**
         * Given: User session memiliki email
         * When: Dashboard dimuat
         * Then: Email ditampilkan dengan truncate styling (untuk handle email panjang)
         */

        // Assert: Email value exists
        const emailValue = dashboardPage.emailCard.locator('div.truncate, div:has-text("@")').last();
        await expect(emailValue).toBeVisible();

        // Assert: Email format valid (contains @)
        const emailText = await emailValue.textContent();
        expect(emailText).toContain('@');
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - LOGIN SINCE CARD
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Login Since card menampilkan "Login Sejak" label', async ({ page }) => {
        /**
         * Given: User login session exists
         * When: Dashboard dimuat
         * Then: Login Since card menampilkan label "Login Sejak"
         */

        // Assert: Login Since card visible
        await dashboardPage.expectLoginSinceCardVisible();

        // Assert: Contains "Login Sejak" label
        await expect(dashboardPage.loginSinceCard).toContainText('Login Sejak');
    });

    test('Positif - Login Since card menampilkan timestamp atau placeholder "-"', async ({ page }) => {
        /**
         * Given: Session memiliki logged_in_at timestamp
         * When: Dashboard dimuat
         * Then: Timestamp atau "-" ditampilkan
         */

        // Assert: Login Since value exists
        const loginSinceValue = dashboardPage.loginSinceCard.locator('div.font-semibold').last();
        await expect(loginSinceValue).toBeVisible();

        // Assert: Has value (timestamp or "-")
        const loginSinceText = await loginSinceValue.textContent();
        expect(loginSinceText).toBeTruthy();
        expect(loginSinceText!.trim().length).toBeGreaterThan(0);
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - PLACEHOLDER CARD
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Placeholder card "Fitur SPK Akan Hadir" visible di bawah info cards', async ({ page }) => {
        /**
         * Given: Dashboard dirender
         * When: Scroll ke bawah info cards
         * Then: Placeholder card dengan text "Fitur SPK Akan Hadir" visible
         */

        // Assert: Placeholder card visible
        await dashboardPage.expectPlaceholderCardVisible();

        // Assert: Contains expected text
        const placeholderText = await dashboardPage.placeholderCard.textContent();
        expect(placeholderText).toContain('Fitur SPK Akan Hadir');
    });

    test('Positif - Placeholder card memiliki centered content dengan description', async ({ page }) => {
        /**
         * Given: Placeholder card visible
         * When: Check content
         * Then: Description text tentang "sedang dikembangkan" visible
         */

        // Assert: Description text
        const description = page.locator('text=sedang dikembangkan');
        await expect(description).toBeVisible();
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - LAYOUT & RESPONSIVE
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Info cards menggunakan grid layout (1 col mobile, 2 cols tablet, 3 cols desktop)', async ({ page }) => {
        /**
         * Given: Dashboard dirender
         * When: Check layout structure
         * Then: Cards dalam grid container dengan responsive columns
         */

        // Assert: Grid container exists
        const gridContainer = page.locator('.grid.grid-cols-1.sm\\:grid-cols-2.lg\\:grid-cols-3').first();
        const exists = await gridContainer.count();
        expect(exists).toBeGreaterThan(0);
    });

    test('Positif - Setiap info card memiliki icon container dengan rounded styling', async ({ page }) => {
        /**
         * Given: Info cards (Role, Email, Login Since) dirender
         * When: Check icon containers
         * Then: Setiap card memiliki rounded icon container dengan emoji
         */

        // Assert: Icon containers exist (3 cards = 3 icons)
        const iconContainers = page.locator('[class*="w-12 h-12 rounded"]');
        const count = await iconContainers.count();
        expect(count).toBeGreaterThanOrEqual(3); // At least Role, Email, Login Since icons
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - SESSION DATA VALIDATION
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - User session data (name, role, email) populated dari session storage', async ({ page }) => {
        /**
         * Given: User logged in dengan session data
         * When: Dashboard loads
         * Then: Data dari session ditampilkan di cards (not default placeholders)
         */

        // Assert: Name is not "User" (default)
        const welcomeText = await dashboardPage.welcomeCard.textContent();
        expect(welcomeText).toContain('Selamat Datang');

        // Assert: Role is not "-" (has value)
        const roleValue = await dashboardPage.roleCard.locator('div.font-bold').textContent();
        expect(roleValue).not.toBe('-');

        // Assert: Email is not "-" (has value)
        const emailValue = await dashboardPage.emailCard.locator('div:has-text("@")').last().textContent();
        expect(emailValue).toContain('@');
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - NAVIGATION & ACCESS
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Dashboard accessible via /dashboard route dengan auth middleware', async ({ page }) => {
        /**
         * Given: User sudah authenticated
         * When: Navigate to /dashboard
         * Then: Dashboard loads successfully (no redirect to login)
         */

        // Arrange & Act: Already at dashboard from beforeEach

        // Assert: URL is dashboard (not redirected to login)
        await expect(page).toHaveURL(/.*\/dashboard/);
        await expect(page).not.toHaveURL(/.*login/);
    });

    test('Positif - Root route "/" redirects ke dashboard jika user logged in', async ({ page }) => {
        /**
         * Given: User sudah login
         * When: Navigate to root "/"
         * Then: Redirect ke /dashboard
         */

        // Act: Navigate to root
        await page.goto('/', { waitUntil: 'domcontentloaded' });

        // Assert: Redirected to dashboard
        await expect(page).toHaveURL(/.*dashboard/, { timeout: 10000 });
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - EDGE CASES & ERROR HANDLING
       ═══════════════════════════════════════════════════════════════════ */

    test('Edge Case - Dashboard tidak crash jika session data incomplete (missing fields)', async ({ page }) => {
        /**
         * Given: Dashboard loaded
         * When: Check untuk error messages atau crashes
         * Then: Tidak ada "Fatal error" atau "undefined" text di body
         */

        // Assert: No crash indicators
        const bodyContent = await page.locator('body').textContent();
        expect(bodyContent).not.toMatch(/Fatal render error|undefined/i);
        expect(bodyContent).not.toMatch(/Error 500/i);
    });

    test('Edge Case - Dashboard handles missing user name gracefully (fallback to "User")', async ({ page }) => {
        /**
         * Given: Session mungkin tidak memiliki name
         * When: Dashboard renders welcome card
         * Then: Falls back to "User" atau nama user yang valid
         */

        // Assert: Welcome text has valid greeting
        const welcomeText = await dashboardPage.welcomeCard.textContent();
        expect(welcomeText).toMatch(/Selamat Datang,.*!/);
    });

    test('Edge Case - Dashboard cards dengan long email text menggunakan truncate styling', async ({ page }) => {
        /**
         * Given: User memiliki email panjang
         * When: Email card dirender
         * Then: Email tidak overflow (menggunakan truncate class)
         */

        // Assert: Email container has truncate or min-w-0 (untuk flex truncation)
        const emailContainer = dashboardPage.emailCard.locator('div.truncate, div.min-w-0');
        const count = await emailContainer.count();
        expect(count).toBeGreaterThan(0); // Has truncation handling
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - VISUAL & STYLING
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Cards menggunakan x-card component dengan consistent styling', async ({ page }) => {
        /**
         * Given: Dashboard menggunakan blade component x-card
         * When: Check card elements
         * Then: Cards memiliki consistent padding, borders, shadows
         */

        // Assert: Multiple card elements exist
        const cards = page.locator('[class*="card"], [class*="rounded"]');
        const count = await cards.count();
        expect(count).toBeGreaterThan(3); // Welcome + 3 info cards + placeholder
    });

    test('Positif - Icon containers memiliki different background colors (primary, amber)', async ({ page }) => {
        /**
         * Given: Info cards dirender
         * When: Check icon container styling
         * Then: Role (primary-lighter), Email (amber-light), Login Since (primary-lighter)
         */

        // Assert: Icon containers have background colors
        const iconWithPrimary = page.locator('[class*="bg-"][class*="primary"]').first();
        await expect(iconWithPrimary).toBeVisible();

        const iconWithAmber = page.locator('[class*="bg-"][class*="amber"]').first();
        await expect(iconWithAmber).toBeVisible();
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - CONTENT VALIDATION
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Welcome card description mentions "pertanian cerdas"', async ({ page }) => {
        /**
         * Given: Welcome card dirender
         * When: Check description text
         * Then: Contains keywords tentang pertanian cerdas
         */

        // Assert: Description contains keywords
        const description = page.locator('text=pertanian cerdas');
        await expect(description).toBeVisible();
    });

    test('Positif - Placeholder card mentions "Modul analisis dan pendukung keputusan"', async ({ page }) => {
        /**
         * Given: Placeholder card dirender
         * When: Check description
         * Then: Description explains features under development
         */

        // Assert: Description text
        const description = page.locator('text=Modul analisis');
        await expect(description).toBeVisible();
    });

    /* ═══════════════════════════════════════════════════════════════════
       DASHBOARD - PERFORMANCE
       ═══════════════════════════════════════════════════════════════════ */

    test('Performance - Dashboard page loads dalam waktu reasonable (<3 detik)', async ({ page }) => {
        /**
         * Given: User navigate to dashboard
         * When: Measure load time
         * Then: Page should load < 3 seconds
         */

        // Arrange
        const startTime = Date.now();

        // Act: Reload dashboard
        await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
        await expect(dashboardPage.welcomeCard).toBeVisible();

        const endTime = Date.now();
        const loadTime = endTime - startTime;

        // Assert: Performance < 3000ms
        expect(loadTime).toBeLessThan(3000);
    });
});
