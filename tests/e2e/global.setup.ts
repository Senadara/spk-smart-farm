import { test as setup, expect } from '@playwright/test';
import { LoginPage } from './pages/LoginPage.js';

const authFile = 'tests/e2e/.auth/user.json';

setup('Global Authentication Setup (Login to cache session)', async ({ page }) => {
    setup.setTimeout(120000);
    
    await page.route('**/:5173/**', route => route.abort());
    await page.route(/.*:5173.*/, route => route.abort());

    const loginPage = new LoginPage(page);
    
    await loginPage.goto();
    await loginPage.login('petugas@email.com', 'Password123.');
    
    await expect(page).toHaveURL(/.*dashboard/, { timeout: 80000 });
    
    await page.context().storageState({ path: authFile });
});
