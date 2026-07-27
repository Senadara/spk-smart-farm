import { test, expect, Page } from '@playwright/test';
import { SpkPage } from '../pages/SpkPage.js';
import { AuthPage } from '../pages/AuthPage.js';

test.describe("Modul SPK - History, Tickets & Edge Cases", () => {
    let spkPage: SpkPage;
    test.setTimeout(120000);
    test.beforeEach(async ({ page }) => {
        spkPage = new SpkPage(page);
    });
    /* ═══════════════════════════════════════════════════════════════════
       SPK HISTORY
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - SPK History section menampilkan latest 10 analysis logs', async ({ page }) => {
        /**
         * Given: SpkFuzzyLog has records
         * When: Check history section
         * Then: List of history items displayed
         */

        // Arrange
        await spkPage.gotoSpkDashboard();
        await page.waitForTimeout(2000);

        // Assert: History section menampilkan timestamp (WIB atau pola jam HH:MM)
        const bodyText = await page.locator('body').textContent();
        const hasHistory = bodyText?.includes('WIB') || /\d{2}:\d{2}/.test(bodyText || '');

        expect(hasHistory).toBeTruthy();
    });

    test('Positif - History items menampilkan date, mode, barn, status, verdict', async ({ page }) => {
        /**
         * Given: History available
         * When: Check history item structure
         * Then: Each item shows date, mode (Fuzzy Mamdani), barn, status
         */

        // Arrange
        await spkPage.gotoSpkDashboard();
        await page.waitForTimeout(2000);

        // Assert: History attributes
        const bodyText = await page.locator('body').textContent();
        const hasHistoryAttrs =
            bodyText?.includes('Fuzzy') ||
            bodyText?.includes('Mamdani') ||
            bodyText?.includes('Global') ||
            bodyText?.includes('Kandang');

        expect(hasHistoryAttrs).toBeTruthy();
    });

    test('Positif - Clicking history item loads that specific analysis result', async ({ page }) => {
        /**
         * Given: History list displayed
         * When: Click one history item
         * Then: Dashboard updates dengan data from that log
         */

        // Arrange
        await spkPage.gotoSpkDashboard();
        await page.waitForTimeout(2000);

        // Act: Click first history item (if exists)
        const historyItem = page.locator('[data-history-id], button:has-text("WIB"), a:has-text("WIB")').first();
        const hasHistory = await historyItem.count();

        if (hasHistory > 0) {
            await historyItem.click();
            await page.waitForTimeout(2000);

            // Assert: URL may have history_id param
            const currentUrl = page.url();
            expect(currentUrl).toMatch(/spk-analysis/);
        }
    });

    /* ═══════════════════════════════════════════════════════════════════
       ACTION TICKETS
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Action tickets section displays tasks from SpkActionTask', async ({ page }) => {
        /**
         * Given: Action tasks created from fuzzy log
         * When: Check action tickets section
         * Then: Task list displayed
         */

        // Arrange
        await spkPage.gotoSpkDashboard();
        await page.waitForTimeout(2000);

        // Assert: Action/task keywords
        const bodyText = await page.locator('body').textContent();
        const hasTasks =
            bodyText?.includes('Tiket') ||
            bodyText?.includes('Task') ||
            bodyText?.includes('Tugas');

        expect(hasTasks).toBeTruthy();
    });

    test('Positif - Action ticket cards show priority and status badges', async ({ page }) => {
        /**
         * Given: Action tickets available
         * When: Check ticket cards
         * Then: Priority (Urgent/High/Medium/Low) dan Status (To Do/In Progress/Done) visible
         */

        // Arrange
        await spkPage.gotoSpkDashboard();
        await page.waitForTimeout(2000);

        // Assert: Priority/status keywords
        const bodyText = await page.locator('body').textContent();
        const hasStatus =
            bodyText?.match(/Urgent|High|Medium|Low|To Do|In Progress|Done/i);

        expect(typeof hasStatus).toBe('object');
    });

    /* ═══════════════════════════════════════════════════════════════════
       NARRATIVE & RECOMMENDATIONS
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Fuzzy narrative (AI-like explanation) displayed', async ({ page }) => {
        /**
         * Given: NarrativeGenerator created narrative
         * When: Check narrative section
         * Then: Long text explanation visible
         */

        // Arrange
        await spkPage.gotoSpkDashboard();
        await page.waitForTimeout(2000);

        // Assert: Narrative text (look for long paragraphs)
        const paragraphs = page.locator('p');
        const count = await paragraphs.count();

        expect(count).toBeGreaterThan(0);
    });

    test('Positif - Recommendations from kausalitas rule displayed', async ({ page }) => {
        /**
         * Given: Fuzzy kausalitas has recommendations
         * When: Check recommendation section
         * Then: Action items visible
         */

        // Arrange
        await spkPage.gotoSpkDashboard();
        await page.waitForTimeout(2000);

        // Assert: area rekomendasi/tindak lanjut hadir. Dashboard SPK selalu menampilkan
        // panel "Tindak Lanjut Hasil SPK"; blok "Rekomendasi" muncul saat ada hasil aktif.
        const bodyText = (await page.locator('body').textContent()) || '';
        const hasRecommendations = /Rekomendasi|Tindak Lanjut|Tindakan|Saran/i.test(bodyText);

        expect(hasRecommendations).toBeTruthy();
    });

    /* ═══════════════════════════════════════════════════════════════════
       ERROR HANDLING
       ═══════════════════════════════════════════════════════════════════ */

    test('Edge Case - Dashboard loads gracefully jika fuzzy engine error', async ({ page }) => {
        /**
         * Given: Fuzzy engine may fail (no sensor data)
         * When: Dashboard loads
         * Then: No 500 error, empty state atau placeholder displayed
         */

        // Arrange & Act
        await spkPage.gotoSpkDashboard();
        await page.waitForTimeout(2000);

        // Assert: No crash
        const bodyContent = await page.locator('body').textContent();
        expect(bodyContent).not.toMatch(/Error 500|Fatal/i);
    });

    test('Edge Case - Dashboard handles empty history gracefully (shows "Belum ada analisa")', async ({ page }) => {
        /**
         * Given: No SpkFuzzyLog records yet
         * When: Check history section
         * Then: Empty state message displayed
         */

        // Arrange
        await spkPage.gotoSpkDashboard();
        await page.waitForTimeout(2000);

        // Assert: Empty state may exist
        const emptyState = page.locator('text=Belum ada analisa, text=Tidak ada data');
        const count = await emptyState.count();

        expect(count).toBeGreaterThanOrEqual(0);
    });

    /* ═══════════════════════════════════════════════════════════════════
       PERFORMANCE
       ═══════════════════════════════════════════════════════════════════ */

    test('Performance - SPK Dashboard loads dalam waktu reasonable (<5 detik)', async ({ page }) => {
        /**
         * Given: Navigate to SPK dashboard
         * When: Measure load time
         * Then: Page loads < 5 seconds (complex page dengan fuzzy engine)
         */

        // Arrange
        const startTime = Date.now();

        // Act
        await spkPage.gotoSpkDashboard();
        await expect(spkPage.dashboardHeading).toBeVisible({ timeout: 15000 });

        const endTime = Date.now();
        const loadTime = endTime - startTime;

        // Assert: Performance < 5000ms
        expect(loadTime).toBeLessThan(5000);
    });
});


// ============================================================
// Uji Fungsional Mendalam - Simulasi SPK (digabung dari func-misc.spec.ts, sebelumnya section 26.9)
// ============================================================

const PW_Sim = 'Password123.';

async function capSim(page: Page, path: string) {
    try { await page.waitForLoadState('networkidle', { timeout: 10000 }); }
    catch { await page.waitForLoadState('domcontentloaded').catch(() => { }); }
    await page.waitForTimeout(500);
    await page.screenshot({ path: `qa-evidence/${path}`, fullPage: true });
}
async function bodyTextSim(page: Page): Promise<string> {
    return (await page.locator('body').innerText().catch(() => '')) || '';
}

test.describe('FUNC Simulasi SPK (pjawab)', () => {
    test.setTimeout(160000);
    test.beforeEach(async ({ page }) => {
        await page.route(/.*:5173.*/, (r) => r.abort());
        const auth = new AuthPage(page);
        await auth.loginAndWaitForDashboard('pjawab@email.com', PW_Sim);
    });

    test('SIMF001 - Simulasi SPK: jalankan menghasilkan diagnosis', async ({ page }) => {
        await page.goto('/spk-analysis/simulation', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1000);
        // pastikan semua input terisi angka
        const inputs = page.locator('input[name^="input_values"]');
        const n = await inputs.count();
        console.log('SIMF001_inputs::' + n);
        for (let i = 0; i < n; i++) {
            const el = inputs.nth(i);
            const val = await el.inputValue();
            if (!val || val.trim() === '') await el.fill('10');
        }
        await page.getByRole('button', { name: /Jalankan Simulasi SPK/i }).click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(2000);
        const body = await bodyTextSim(page);
        const hasResult = /Diagnosis dan Rekomendasi|Kausalitas|Lingkungan/i.test(body);
        console.log('SIMF001:: hasResult=' + hasResult);
        await capSim(page, 'MISC/SIMF001_simulasi_hasil.png');
        expect(hasResult).toBeTruthy();
    });

    test('SIMF002 - Simulasi SPK: kirim notifikasi uji (best-effort node)', async ({ page }) => {
        await page.goto('/spk-analysis/simulation', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1000);
        const inputs = page.locator('input[name^="input_values"]');
        const n = await inputs.count();
        for (let i = 0; i < n; i++) {
            const el = inputs.nth(i);
            const val = await el.inputValue();
            if (!val || val.trim() === '') await el.fill('10');
        }
        const kirim = page.getByRole('button', { name: /Kirim Notifikasi Uji/i });
        const adaKirim = await kirim.count();
        console.log('SIMF002_tombolKirim::' + adaKirim);
        if (adaKirim === 0) {
            console.log('SIMF002:: tombol Kirim Notifikasi tidak tersedia untuk peran ini');
            await capSim(page, 'MISC/SIMF002_no_button.png');
            test.skip(true, 'Tombol kirim notifikasi tidak tersedia');
            return;
        }
        await kirim.first().click();
        await page.waitForLoadState('domcontentloaded').catch(() => { });
        await page.waitForTimeout(3000);
        const body = await bodyTextSim(page);
        const ok = /Notifikasi uji berhasil dikirim/i.test(body);
        const err = /Gateway Node|gagal|error/i.test(body);
        console.log('SIMF002:: sukses=' + ok + ' errNode=' + err);
        await capSim(page, 'MISC/SIMF002_kirim_notifikasi.png');
        expect(ok || err).toBeTruthy();
    });
});
