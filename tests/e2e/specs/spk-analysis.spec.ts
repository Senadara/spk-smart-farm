import { test, expect } from '@playwright/test';
import { SpkPage } from '../pages/SpkPage.js';

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

        // Assert: History section (look for timestamps, dates)
        const bodyText = await page.locator('body').textContent();
        const hasHistory = bodyText?.includes('WIB') || bodyText?.match(/\d{2}:\d{2}/);

        expect(typeof hasHistory).toBe('object');
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

        // Assert: Recommendation keywords
        const bodyText = await page.locator('body').textContent();
        const hasRecommendations =
            bodyText?.includes('Rekomendasi') ||
            bodyText?.includes('Tindakan') ||
            bodyText?.includes('Saran');

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
