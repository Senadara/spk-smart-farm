import { test as setup, expect } from '@playwright/test';
import { AuthPage } from './pages/AuthPage.js';

const authFile = 'tests/e2e/.auth/user.json';

setup('Global Authentication Setup (Login to cache session)', async ({ page }) => {
    setup.setTimeout(300000);

    await page.route('**/:5173/**', route => route.abort());
    await page.route(/.*:5173.*/, route => route.abort());

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
        }
        await new Promise(r => setTimeout(r, 500));
    }

    console.log('Vite ready:', viteReady);

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
        }
        await new Promise(r => setTimeout(r, 500));
    }
    console.log('Login page ready:', loginReady, '(', loginUrl, ')');

    const authPage = new AuthPage(page);

    await authPage.gotoLogin();
    try {
        await authPage.emailInput.waitFor({ state: 'visible', timeout: 120000 });
    } catch (e) {
        console.error('Email input not visible after wait, capturing diagnostics');
        const fs = await import('fs');
        try {
            const dumpPath = 'test-results/global-setup-diagnostic.html';
            const html = await page.content();
            fs.writeFileSync(dumpPath, html, 'utf8');
            console.log('Wrote login page snapshot to', dumpPath);
            await page.screenshot({ path: 'test-results/global-setup-screenshot.png', fullPage: true }).catch(() => { });
        } catch (writeErr) {
            console.error('Failed to write diagnostic snapshot', writeErr);
        }
    }
    await page.waitForLoadState('domcontentloaded');

    try {
        await authPage.loginAndWaitForDashboard('pjawab@email.com', 'Password123.');
        await expect(page).toHaveURL(/.*dashboard/, { timeout: 150000 });
        await page.context().storageState({ path: authFile });
    } catch (err) {
        console.error('Global setup login failed:', err);
        try {
            await page.screenshot({ path: 'test-results/global-setup-failure.png', fullPage: true });
            const fs = await import('fs');
            fs.writeFileSync('test-results/global-setup-failure.html', await page.content(), 'utf8');
        } catch (diagErr) {
            console.error('Failed to capture diagnostics after login failure', diagErr);
        }
        throw err;
    }
});