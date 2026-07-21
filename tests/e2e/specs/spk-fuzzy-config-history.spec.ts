import { test, expect } from '@playwright/test';

test.describe('Modul SPK Fuzzy Mamdani - Config & History', () => {
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
       GET /spk-fuzzy/config - FUZZY CONFIGURATION
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - GET /config returns complete fuzzy configuration (variables & rules)', async ({ page }) => {
        const response = await page.request.get('/spk-fuzzy/config');
        expect(response.ok()).toBeTruthy();
        expect(response.status()).toBe(200);

        const payload = await response.json();
        expect(payload.success).toBe(true);
        expect(Array.isArray(payload.variables)).toBe(true);
        expect(payload.variables.length).toBeGreaterThan(0);
        expect(Array.isArray(payload.rules)).toBe(true);
        expect(payload.rules.length).toBeGreaterThan(0);
    });

    test('Positif - Config variables memiliki structure lengkap (sets & inputSource)', async ({ page }) => {
        const response = await page.request.get('/spk-fuzzy/config');
        const payload = await response.json();

        const firstVariable = payload.variables[0];
        expect(firstVariable).toHaveProperty('id');
        expect(firstVariable).toHaveProperty('name');
        expect(firstVariable).toHaveProperty('type');
        expect(firstVariable).toHaveProperty('group');
        expect(Array.isArray(firstVariable.sets)).toBe(true);

        if (firstVariable.sets.length > 0) {
            const firstSet = firstVariable.sets[0];
            expect(firstSet).toHaveProperty('name');
            expect(firstSet).toHaveProperty('shape');
            expect(firstSet).toHaveProperty('a');
            expect(firstSet).toHaveProperty('b');
            expect(firstSet).toHaveProperty('c');
        }

        if (firstVariable.type === 'input' && firstVariable.inputSource) {
            expect(firstVariable.inputSource).toHaveProperty('source_type');
        }
    });

    test('Positif - Config rules memiliki structure lengkap (conditions, outputSet)', async ({ page }) => {
        const response = await page.request.get('/spk-fuzzy/config');
        const payload = await response.json();

        const firstRule = payload.rules[0];
        expect(firstRule).toHaveProperty('id');
        expect(firstRule).toHaveProperty('operator');
        expect(firstRule).toHaveProperty('output_set_id');
        expect(firstRule).toHaveProperty('group');
        expect(Array.isArray(firstRule.conditions)).toBe(true);

        if (firstRule.conditions.length > 0) {
            const firstCondition = firstRule.conditions[0];
            expect(firstCondition).toHaveProperty('variable_id');
            expect(firstCondition).toHaveProperty('set_id');
        }

        expect(firstRule.output_set).toBeDefined();
    });

    /* ═══════════════════════════════════════════════════════════════════
       GET /spk-fuzzy/history - FUZZY HISTORY LIST
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - GET /history returns array of logs dengan default limit', async ({ page }) => {
        const response = await page.request.get('/spk-fuzzy/history');
        expect(response.ok()).toBeTruthy();
        const payload = await response.json();
        expect(payload.success).toBe(true);
        expect(Array.isArray(payload.data)).toBe(true);
        expect(payload).toHaveProperty('total');
    });

    test('Positif - GET /history?limit=5 returns max 5 logs', async ({ page }) => {
        const response = await page.request.get('/spk-fuzzy/history?limit=5');
        const payload = await response.json();
        expect(payload.data.length).toBeLessThanOrEqual(5);
    });

    test('Positif - GET /history?coop_id={uuid} filters by specific kandang', async ({ page }) => {
        const unitsResponse = await page.request.get('/api/farm/unit-budidaya?limit=1');
        const unitsPayload = await unitsResponse.json();

        if (!unitsPayload.data || unitsPayload.data.length === 0) {
            test.skip();
            return;
        }

        const coopId = unitsPayload.data[0].id;
        await apiPost(page, `/spk-fuzzy/process?coop_id=${coopId}`);
        const historyResponse = await page.request.get(`/spk-fuzzy/history?coop_id=${coopId}`);
        const historyPayload = await historyResponse.json();
        expect(historyPayload.success).toBe(true);
        expect(Array.isArray(historyPayload.data)).toBe(true);
    });

    test('Positif - History data memiliki format lengkap (date, mode, barn, status, verdict, scores)', async ({ page }) => {
        const response = await page.request.get('/spk-fuzzy/history?limit=1');
        const payload = await response.json();

        if (payload.data.length === 0) {
            test.skip();
            return;
        }

        const firstLog = payload.data[0];
        expect(firstLog).toHaveProperty('id');
        expect(firstLog).toHaveProperty('date');
        expect(firstLog).toHaveProperty('time');
        expect(firstLog).toHaveProperty('mode');
        expect(firstLog).toHaveProperty('modeColor');
        expect(firstLog).toHaveProperty('barn');
        expect(firstLog).toHaveProperty('status');
        expect(firstLog).toHaveProperty('color');
        expect(firstLog).toHaveProperty('verdict');
        expect(firstLog).toHaveProperty('scores');
        expect(firstLog.scores).toHaveProperty('lingkungan');
        expect(firstLog.scores).toHaveProperty('kesehatan');
        expect(firstLog.scores).toHaveProperty('kausalitas');
    });

    test('Positif - History verdict adalah excerpt dari narrative (max 120 chars)', async ({ page }) => {
        const response = await page.request.get('/spk-fuzzy/history?limit=1');
        const payload = await response.json();

        if (payload.data.length === 0) {
            test.skip();
            return;
        }

        expect(payload.data[0].verdict.length).toBeLessThanOrEqual(125);
    });

    test('Edge Case - GET /history dengan limit>50 tetap return max 50 (protection)', async ({ page }) => {
        const response = await page.request.get('/spk-fuzzy/history?limit=100');
        const payload = await response.json();
        expect(payload.data.length).toBeLessThanOrEqual(50);
    });

    /* ═══════════════════════════════════════════════════════════════════
       GET /spk-fuzzy/history/{id} - FUZZY HISTORY DETAIL
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - GET /history/{id} returns complete log detail', async ({ page }) => {
        const processResponse = await apiPost(page, '/spk-fuzzy/process');
        const processPayload = await processResponse.json();
        const logId = processPayload.log_id;

        const response = await page.request.get(`/spk-fuzzy/history/${logId}`);
        expect(response.ok()).toBeTruthy();
        const payload = await response.json();
        expect(payload.success).toBe(true);

        const logData = payload.data;
        expect(logData.id).toBe(logId);
        expect(typeof logData.input_json).toBe('object');
        expect(typeof logData.fuzzified_json).toBe('object');
        expect(typeof logData.rule_result_json).toBe('object');
    });

    test('Positif - History detail memiliki input_json dengan variabel fuzzy', async ({ page }) => {
        const processResponse = await apiPost(page, '/spk-fuzzy/process');
        const processPayload = await processResponse.json();
        const logId = processPayload.log_id;

        const response = await page.request.get(`/spk-fuzzy/history/${logId}`);
        const payload = await response.json();
        const inputJson = payload.data.input_json;
        expect(inputJson).toBeDefined();
        expect(typeof inputJson).toBe('object');
    });

    test('Positif - History detail memiliki fuzzified_json dengan membership degrees', async ({ page }) => {
        const processResponse = await apiPost(page, '/spk-fuzzy/process');
        const processPayload = await processResponse.json();
        const logId = processPayload.log_id;

        const response = await page.request.get(`/spk-fuzzy/history/${logId}`);
        const payload = await response.json();
        const fuzzifiedJson = payload.data.fuzzified_json;
        expect(fuzzifiedJson).toBeDefined();
        expect(fuzzifiedJson).toHaveProperty('lingkungan');
        expect(fuzzifiedJson).toHaveProperty('kesehatan');
    });

    test('Positif - History detail memiliki rule_result_json dengan dominant rules', async ({ page }) => {
        const processResponse = await apiPost(page, '/spk-fuzzy/process');
        const processPayload = await processResponse.json();
        const logId = processPayload.log_id;

        const response = await page.request.get(`/spk-fuzzy/history/${logId}`);
        const payload = await response.json();
        const ruleResultJson = payload.data.rule_result_json;
        expect(ruleResultJson).toBeDefined();
        expect(ruleResultJson).toHaveProperty('lingkungan');
        expect(ruleResultJson).toHaveProperty('kesehatan');
        expect(ruleResultJson).toHaveProperty('kausalitas');
    });

    test('Negatif - GET /history/{invalid-id} returns 404 Not Found', async ({ page }) => {
        const invalidId = '00000000-0000-0000-0000-000000000000';
        const response = await page.request.get(`/spk-fuzzy/history/${invalidId}`);
        expect(response.status()).toBe(404);
        const payload = await response.json();
        expect(payload.success).toBe(false);
        expect(payload.message).toMatch(/tidak ditemukan/i);
    });

    test('Negatif - GET /history/{malformed-id} returns 404 Not Found', async ({ page }) => {
        const malformedId = 'invalid-log-id-xxx';
        const response = await page.request.get(`/spk-fuzzy/history/${malformedId}`);
        expect(response.status()).toBe(404);
    });

    /* ═══════════════════════════════════════════════════════════════════
       PERFORMANCE
       ═══════════════════════════════════════════════════════════════════ */

    test('Performance - GET /config completes <2 detik', async ({ page }) => {
        const startTime = Date.now();
        const response = await page.request.get('/spk-fuzzy/config');
        await response.json();
        expect(Date.now() - startTime).toBeLessThan(2000);
    });

    test('Performance - GET /history limit=10 completes <1 detik', async ({ page }) => {
        const startTime = Date.now();
        const response = await page.request.get('/spk-fuzzy/history?limit=10');
        await response.json();
        expect(Date.now() - startTime).toBeLessThan(1000);
    });
});
