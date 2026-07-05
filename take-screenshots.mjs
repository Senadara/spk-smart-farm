import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8000';
const EVIDENCE_DIR = '../qa-evidence';

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  // REG-001: Login page + Dashboard setelah login
  await page.goto(`${BASE}/login`);
  await page.waitForTimeout(2000);
  await page.screenshot({ path: `${EVIDENCE_DIR}/REG-001_login_page.png`, fullPage: true });
  console.log('REG-001: Login page screenshot taken');

  await page.fill('input[name="email"]', 'petugas@email.com');
  await page.fill('input[name="password"]', 'Password123.');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 30000 });
  await page.waitForTimeout(2000);
  await page.screenshot({ path: `${EVIDENCE_DIR}/REG-001_dashboard.png`, fullPage: true });
  console.log('REG-001: Dashboard screenshot taken');

  // REG-002: Login dengan password salah
  await page.goto(`${BASE}/login`);
  await page.waitForTimeout(1000);
  await page.fill('input[name="email"]', 'petugas@email.com');
  await page.fill('input[name="password"]', 'SalahPassword123!');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(3000);
  await page.screenshot({ path: `${EVIDENCE_DIR}/REG-002_error_password_salah.png`, fullPage: true });
  console.log('REG-002: Error password screenshot taken');

  // REG-003: Login dengan email tidak terdaftar
  await page.goto(`${BASE}/login`);
  await page.waitForTimeout(1000);
  await page.fill('input[name="email"]', 'tidakada@email.com');
  await page.fill('input[name="password"]', 'Password123.');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(3000);
  await page.screenshot({ path: `${EVIDENCE_DIR}/REG-003_error_email_not_found.png`, fullPage: true });
  console.log('REG-003: Error email not found screenshot taken');

  // REG-004: Field kosong (email kosong)
  await page.goto(`${BASE}/login`);
  await page.waitForTimeout(1000);
  await page.fill('input[name="password"]', 'Password123.');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(2000);
  await page.screenshot({ path: `${EVIDENCE_DIR}/REG-004_email_kosong.png`, fullPage: true });
  console.log('REG-004: Empty email screenshot taken');

  // REG-005: Login dulu, lalu logout
  await page.goto(`${BASE}/login`);
  await page.waitForTimeout(1000);
  await page.fill('input[name="email"]', 'petugas@email.com');
  await page.fill('input[name="password"]', 'Password123.');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 30000 });
  await page.waitForTimeout(2000);
  await page.screenshot({ path: `${EVIDENCE_DIR}/REG-005_before_logout.png`, fullPage: true });
  // Cari tombol logout
  const logoutBtn = page.locator('button:has-text("Keluar"), a:has-text("Keluar")').first();
  if (await logoutBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
    await logoutBtn.click();
  }
  await page.waitForURL('**/login', { timeout: 15000 });
  await page.waitForTimeout(2000);
  await page.screenshot({ path: `${EVIDENCE_DIR}/REG-005_after_logout.png`, fullPage: true });
  console.log('REG-005: After logout screenshot taken');

  // REG-006: Akses halaman setelah session expired
  await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(3000);
  await page.screenshot({ path: `${EVIDENCE_DIR}/REG-006_session_expired.png`, fullPage: true });
  console.log('REG-006: Session expired redirect screenshot taken');

  await browser.close();
  console.log('All screenshots completed!');
}

main().catch(err => { console.error(err); process.exit(1); });
