import { test as setup, expect } from '@playwright/test';
import { AuthPage } from './pages/AuthPage.js';

const authFile = 'tests/e2e/.auth/user.json';

setup('Global Authentication Setup (Login to cache session)', async ({ page }) => {
    setup.setTimeout(300000);
    
    // abort vite dev server requests during setup (redundant-safe)
    await page.route('**/:5173/**', route => route.abort());
    await page.route(/.*:5173.*/, route => route.abort());

    // Poll Vite dev server readiness (if present) to reduce flakiness
    const viteUrl = 'http://localhost:5173/';
    let viteReady = false;
    const viteDeadline = Date.now() + 30000; // 30s
    while (Date.now() < viteDeadline) {
        try {
            const resp = await fetch(viteUrl, { method: 'GET' });
            if (resp && (resp.status === 200 || resp.status === 304 || resp.status === 404)) {
                viteReady = true;
                break;
            }
        } catch (e) {
            // ignore and retry
        }
        await new Promise(r => setTimeout(r, 500));
    }
    // Log for diagnostics
    // eslint-disable-next-line no-console
    console.log('Vite ready:', viteReady);

    // Additionally poll the app login page to ensure backend is up and responding
    const baseUrl = process.env.BASE_URL || 'http://127.0.0.1:8000';
    const loginUrl = `${baseUrl.replace(/\/$/, '')}/login`;
    let loginReady = false;
    const loginDeadline = Date.now() + 120000; // 120s
    while (Date.now() < loginDeadline) {
        try {
            const resp = await fetch(loginUrl, { method: 'GET' });
            if (resp && (resp.status === 200 || resp.status === 302 || resp.status === 404)) {
                loginReady = true;
                break;
            }
        } catch (e) {
            // ignore and retry
        }
        await new Promise(r => setTimeout(r, 500));
    }
    // eslint-disable-next-line no-console
    console.log('Login page ready:', loginReady, '(', loginUrl, ')');

    const authPage = new AuthPage(page);
    
    await authPage.gotoLogin();
    // wait for the email input explicitly (longer timeout to handle slow startups)
    try {
        await authPage.emailInput.waitFor({ state: 'visible', timeout: 120000 });
    } catch (e) {
        // If email input never appears, capture diagnostic snapshot
        // eslint-disable-next-line no-console
        console.error('Email input not visible after wait, capturing diagnostics');
        const fs = await import('fs');
        try {
            const dumpPath = 'test-results/global-setup-diagnostic.html';
            const html = await page.content();
            fs.writeFileSync(dumpPath, html, 'utf8');
            // eslint-disable-next-line no-console
            console.log('Wrote login page snapshot to', dumpPath);
            await page.screenshot({ path: 'test-results/global-setup-screenshot.png', fullPage: true }).catch(() => {});
        } catch (writeErr) {
            // eslint-disable-next-line no-console
            console.error('Failed to write diagnostic snapshot', writeErr);
        }
        // continue to attempt login to surface the original error
    }
    await page.waitForLoadState('domcontentloaded');

    // Attempt login; if it fails, capture diagnostic info and rethrow
    try {
        await authPage.loginAndWaitForDashboard('petugas@email.com', 'Password123.');
        await expect(page).toHaveURL(/.*dashboard/, { timeout: 150000 });
        await page.context().storageState({ path: authFile });
    } catch (err) {
        // eslint-disable-next-line no-console
        console.error('Global setup login failed:', err);
        try {
            await page.screenshot({ path: 'test-results/global-setup-failure.png', fullPage: true });
            const fs = await import('fs');
            fs.writeFileSync('test-results/global-setup-failure.html', await page.content(), 'utf8');
        } catch (diagErr) {
            // eslint-disable-next-line no-console
            console.error('Failed to capture diagnostics after login failure', diagErr);
        }
        throw err;
    }
});