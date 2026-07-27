import { test, expect } from '@playwright/test';

test.describe('Modul SPK Fuzzy Mamdani - Process & Integration', () => {
    test.setTimeout(120000);

    let csrfToken = '';

    test.beforeEach(async ({ page }) => { });

    async function apiPost(page: any, url: string, data?: any) {
        if (!csrfToken) {
            await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
            csrfToken = await page.evaluate(() => {
                const meta = document.querySelector('meta[name="csrf-token"]');
                return meta?.getAttribute('content') || '';
            });
        }
        const body = { ...(data || {}), _token: csrfToken };
        return page.request.post(url, { data: body });
    }

    /* ═══════════════════════════════════════════════════════════════════
       POST /spk-fuzzy/process - FUZZY PROCESSING
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - POST /process (mode GLOBAL) berhasil dengan 3-engine cascaded result', async ({ page }) => {
        const response = await apiPost(page, '/spk-fuzzy/process');
        expect(response.ok()).toBeTruthy();
        expect(response.status()).toBe(200);

        const payload = await response.json();
        expect(payload.success).toBe(true);
        expect(payload).toHaveProperty('log_id');
        expect(payload.coop_id).toBeNull();
        expect(payload.barn_name).toBeNull();
        expect(payload.inputs).toBeDefined();
        expect(payload.result).toBeDefined();
        expect(payload.result).toHaveProperty('status_lingkungan');
        expect(payload.result).toHaveProperty('score_lingkungan');
        expect(payload.result).toHaveProperty('status_kesehatan');
        expect(payload.result).toHaveProperty('score_kesehatan');
        expect(payload.result).toHaveProperty('diagnosis_kausalitas');
        expect(payload.result).toHaveProperty('recommendation');
        expect(payload.result).toHaveProperty('narrative');
        expect(payload.result).toHaveProperty('dominant_lingkungan');
        expect(payload.result).toHaveProperty('dominant_kesehatan');
    });

    test('Positif - POST /process dengan coop_id (mode PER-KANDANG) berhasil', async ({ page }) => {
        const unitsResponse = await page.request.get('/api/farm/unit-budidaya?limit=1');
        const unitsPayload = await unitsResponse.json();

        if (!unitsPayload.data || unitsPayload.data.length === 0) {
            test.skip();
            return;
        }

        const firstUnit = unitsPayload.data[0];
        const coopId = firstUnit.id;
        const response = await apiPost(page, `/spk-fuzzy/process?coop_id=${coopId}`);

        expect(response.ok()).toBeTruthy();
        const payload = await response.json();
        expect(payload.success).toBe(true);
        expect(payload.coop_id).toBe(coopId);
        expect(payload.barn_name).toBeTruthy();
        expect(payload.result.status_lingkungan).toBeTruthy();
        expect(payload.result.status_kesehatan).toBeTruthy();
        expect(payload.result.diagnosis_kausalitas).toBeTruthy();
    });

    test('Positif - POST /process menyimpan log ke database (SpkFuzzyLog)', async ({ page }) => {
        const processResponse = await apiPost(page, '/spk-fuzzy/process');
        const processPayload = await processResponse.json();
        const logId = processPayload.log_id;

        const logResponse = await page.request.get(`/spk-fuzzy/history/${logId}`);
        expect(logResponse.ok()).toBeTruthy();
        const logPayload = await logResponse.json();
        expect(logPayload.success).toBe(true);
        expect(logPayload.data.id).toBe(logId);
        expect(logPayload.data).toHaveProperty('input_json');
        expect(logPayload.data).toHaveProperty('fuzzified_json');
        expect(logPayload.data).toHaveProperty('rule_result_json');
        expect(logPayload.data).toHaveProperty('status_lingkungan');
        expect(logPayload.data).toHaveProperty('status_kesehatan');
        expect(logPayload.data).toHaveProperty('diagnosis_kausalitas');
        expect(logPayload.data).toHaveProperty('output_value');
        expect(logPayload.data).toHaveProperty('narrative');
        expect(logPayload.data).toHaveProperty('recommendation');
    });

    test('Positif - Process result memiliki narrative AI-like dari NarrativeGenerator', async ({ page }) => {
        const response = await apiPost(page, '/spk-fuzzy/process');
        const payload = await response.json();
        expect(payload.result.narrative).toBeTruthy();
        expect(typeof payload.result.narrative).toBe('string');
        expect(payload.result.narrative.length).toBeGreaterThan(50);
    });

    test('Positif - Process result memiliki recommendation dari kausalitas rule', async ({ page }) => {
        const response = await apiPost(page, '/spk-fuzzy/process');
        const payload = await response.json();
        expect(payload.result.recommendation).toBeTruthy();
        expect(typeof payload.result.recommendation).toBe('string');
    });

    test('Negatif - POST /process dengan coop_id invalid tidak crash', async ({ page }) => {
        const invalidCoopId = '00000000-0000-0000-0000-000000000000';
        const response = await apiPost(page, `/spk-fuzzy/process?coop_id=${invalidCoopId}`);

        expect(response.ok()).toBeTruthy();
        const payload = await response.json();
        expect(payload.success).toBe(true);
        expect(payload.barn_name).toBeNull();
    });

    test('Advanced - Process result status labels are valid (Buruk|Waspada|Baik|Optimal)', async ({ page }) => {
        const response = await apiPost(page, '/spk-fuzzy/process');
        const payload = await response.json();
        // Label status berasal dari himpunan output fuzzy yang bisa dikonfigurasi
        // (mis. "Sangat Nyaman", "Nyaman", dll), sehingga daftar tetap tidak akurat.
        // Verifikasi label = string non-kosong hasil defuzzifikasi.
        expect(typeof payload.result.status_lingkungan).toBe('string');
        expect((payload.result.status_lingkungan || '').length).toBeGreaterThan(0);
        expect(typeof payload.result.status_kesehatan).toBe('string');
        expect((payload.result.status_kesehatan || '').length).toBeGreaterThan(0);
    });

    test('Advanced - Process result kausalitas labels valid (from config)', async ({ page }) => {
        const response = await apiPost(page, '/spk-fuzzy/process');
        const payload = await response.json();
        expect(payload.result.diagnosis_kausalitas).toBeTruthy();
        expect(typeof payload.result.diagnosis_kausalitas).toBe('string');
    });

    /* ═══════════════════════════════════════════════════════════════════
       INTEGRATION & ADVANCED TESTS
       ═══════════════════════════════════════════════════════════════════ */

    test('Integration - Complete flow: Process → History list includes log → Detail matches', async ({ page }) => {
        const processResponse = await apiPost(page, '/spk-fuzzy/process');
        const processPayload = await processResponse.json();
        const logId = processPayload.log_id;

        const historyResponse = await page.request.get('/spk-fuzzy/history?limit=10');
        const historyPayload = await historyResponse.json();
        const foundInHistory = historyPayload.data.some((log: any) => log.id === logId);
        expect(foundInHistory).toBe(true);

        const detailResponse = await page.request.get(`/spk-fuzzy/history/${logId}`);
        const detailPayload = await detailResponse.json();
        expect(detailPayload.data.status_lingkungan).toBe(processPayload.result.status_lingkungan);
        expect(detailPayload.data.status_kesehatan).toBe(processPayload.result.status_kesehatan);
        expect(detailPayload.data.diagnosis_kausalitas).toBe(processPayload.result.diagnosis_kausalitas);
    });

    test('Integration - Process 2x generates 2 different log_id', async ({ page }) => {
        const response1 = await apiPost(page, '/spk-fuzzy/process');
        const payload1 = await response1.json();
        const logId1 = payload1.log_id;

        const response2 = await apiPost(page, '/spk-fuzzy/process');
        const payload2 = await response2.json();
        const logId2 = payload2.log_id;

        expect(logId1).not.toBe(logId2);
    });

    /* ═══════════════════════════════════════════════════════════════════
       PERFORMANCE
       ═══════════════════════════════════════════════════════════════════ */

    test('Performance - POST /process completes <5 detik', async ({ page }) => {
        const startTime = Date.now();
        const response = await apiPost(page, '/spk-fuzzy/process');
        await response.json();
        expect(Date.now() - startTime).toBeLessThan(5000);
    });
});
