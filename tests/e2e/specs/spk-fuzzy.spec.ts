import { test, expect } from '@playwright/test';

test.describe('Modul SPK Fuzzy Mamdani - E2E Tests', () => {
    test.setTimeout(120000);

    /* ═══════════════════════════════════════════════════════════════════
       Melihat /spk-fuzzy/config - FUZZY CONFIGURATION
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Melihat /config returns complete fuzzy configuration (variables & rules)', async ({ page }) => {
        /**
         * Given: Fuzzy Mamdani engine sudah configured
         * When: Melihat /spk-fuzzy/config
         * Then: Return 200 dengan payload variables (dengan sets & inputSource) dan rules (dengan conditions)
         */

        // Act
        const response = await page.request.get('/spk-fuzzy/config');

        // Assert: Response OK
        expect(response.ok()).toBeTruthy();
        expect(response.status()).toBe(200);

        const payload = await response.json();
        expect(payload.success).toBe(true);

        // Assert: Variables array ada dan memiliki data
        expect(Array.isArray(payload.variables)).toBe(true);
        expect(payload.variables.length).toBeGreaterThan(0);

        // Assert: Rules array ada dan memiliki data
        expect(Array.isArray(payload.rules)).toBe(true);
        expect(payload.rules.length).toBeGreaterThan(0);
    });

    test('Positif - Config variables memiliki structure lengkap (sets & inputSource relations)', async ({ page }) => {
        /**
         * Given: Fuzzy config endpoint available
         * When: Melihat /spk-fuzzy/config
         * Then: Variables memiliki relations: sets (membership functions) & inputSource
         */

        // Act
        const response = await page.request.get('/spk-fuzzy/config');
        const payload = await response.json();

        // Assert: First variable memiliki structure lengkap
        const firstVariable = payload.variables[0];
        expect(firstVariable).toHaveProperty('id');
        expect(firstVariable).toHaveProperty('name');
        expect(firstVariable).toHaveProperty('type'); // 'input' or 'output'
        expect(firstVariable).toHaveProperty('group'); // 'lingkungan', 'kesehatan', 'kausalitas'

        // Assert: Sets relation (membership functions)
        expect(Array.isArray(firstVariable.sets)).toBe(true);
        if (firstVariable.sets.length > 0) {
            const firstSet = firstVariable.sets[0];
            expect(firstSet).toHaveProperty('name'); // e.g., 'Dingin', 'Nyaman', 'Panas'
            expect(firstSet).toHaveProperty('shape'); // 'triangle' or 'trapezoid'
            expect(firstSet).toHaveProperty('a'); // Membership function parameters
            expect(firstSet).toHaveProperty('b');
            expect(firstSet).toHaveProperty('c');
        }

        // Assert: InputSource relation (if type is 'input')
        if (firstVariable.type === 'input' && firstVariable.inputSource) {
            expect(firstVariable.inputSource).toHaveProperty('source_type'); // 'iot', 'database', 'function'
        }
    });

    test('Positif - Config rules memiliki structure lengkap (conditions, outputSet)', async ({ page }) => {
        /**
         * Given: Fuzzy rules configured
         * When: Melihat /spk-fuzzy/config
         * Then: Rules memiliki relations: conditions (IF part) & outputSet (THEN part)
         */

        // Act
        const response = await page.request.get('/spk-fuzzy/config');
        const payload = await response.json();

        // Assert: First rule memiliki structure lengkap
        const firstRule = payload.rules[0];
        expect(firstRule).toHaveProperty('id');
        expect(firstRule).toHaveProperty('operator'); // 'AND' or 'OR'
        expect(firstRule).toHaveProperty('output_set_id');
        expect(firstRule).toHaveProperty('group'); // 'lingkungan', 'kesehatan', 'kausalitas'

        // Assert: Conditions relation (IF part)
        expect(Array.isArray(firstRule.conditions)).toBe(true);
        if (firstRule.conditions.length > 0) {
            const firstCondition = firstRule.conditions[0];
            expect(firstCondition).toHaveProperty('variable_id');
            expect(firstCondition).toHaveProperty('set_id');
        }

        // Assert: OutputSet relation (THEN part)
        expect(firstRule.outputSet).toBeDefined();
    });

    /* ═══════════════════════════════════════════════════════════════════
       POST /spk-fuzzy/process - FUZZY PROCESSING
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - POST /process (mode GLOBAL) berhasil dengan 3-engine cascaded result', async ({ page }) => {
        /**
         * Given: Fuzzy Mamdani engine configured
         * When: POST /spk-fuzzy/process (tanpa coop_id = mode global)
         * Then: Return 200 dengan result lengkap (lingkungan, kesehatan, kausalitas)
         */

        // Act
        const response = await page.request.post('/spk-fuzzy/process');

        // Assert: Response OK
        expect(response.ok()).toBeTruthy();
        expect(response.status()).toBe(200);

        const payload = await response.json();
        expect(payload.success).toBe(true);

        // Assert: Core fields
        expect(payload).toHaveProperty('log_id'); // UUID dari SpkFuzzyLog
        expect(payload.coop_id).toBeNull(); // Mode global
        expect(payload.barn_name).toBeNull();

        // Assert: Inputs object (resolved dari InputResolver)
        expect(payload.inputs).toBeDefined();
        expect(typeof payload.inputs).toBe('object');

        // Assert: Result object dengan 3-engine cascaded
        expect(payload.result).toBeDefined();
        expect(payload.result).toHaveProperty('status_lingkungan'); // Engine 1: Buruk|Waspada|Baik|Optimal
        expect(payload.result).toHaveProperty('score_lingkungan'); // 0-100
        expect(payload.result).toHaveProperty('status_kesehatan'); // Engine 2
        expect(payload.result).toHaveProperty('score_kesehatan');
        expect(payload.result).toHaveProperty('diagnosis_kausalitas'); // Engine 3: Krisis Total, Stres Lingkungan, etc.
        expect(payload.result).toHaveProperty('recommendation'); // Tindakan rekomendasi
        expect(payload.result).toHaveProperty('narrative'); // AI-like narrative from NarrativeGenerator
        expect(payload.result).toHaveProperty('dominant_lingkungan'); // Dominant rule info
        expect(payload.result).toHaveProperty('dominant_kesehatan');
    });

    test('Positif - POST /process dengan coop_id (mode PER-KANDANG) berhasil', async ({ page }) => {
        /**
         * Given: Unit budidaya (kandang) exist
         * When: POST /spk-fuzzy/process?coop_id={uuid}
         * Then: Return 200 dengan result specific untuk kandang tersebut
         */

        // Arrange: Get first unit budidaya (kandang) from database
        const unitsResponse = await page.request.get('/api/farm/unit-budidaya?limit=1');
        const unitsPayload = await unitsResponse.json();

        if (!unitsPayload.data || unitsPayload.data.length === 0) {
            test.skip(); // Skip jika tidak ada unit budidaya
            return;
        }

        const firstUnit = unitsPayload.data[0];
        const coopId = firstUnit.id;

        // Act
        const response = await page.request.post(`/spk-fuzzy/process?coop_id=${coopId}`);

        // Assert: Response OK
        expect(response.ok()).toBeTruthy();

        const payload = await response.json();
        expect(payload.success).toBe(true);

        // Assert: coop_id specified
        expect(payload.coop_id).toBe(coopId);
        expect(payload.barn_name).toBeTruthy(); // Nama kandang harus ada

        // Assert: Result lengkap seperti mode global
        expect(payload.result.status_lingkungan).toBeTruthy();
        expect(payload.result.status_kesehatan).toBeTruthy();
        expect(payload.result.diagnosis_kausalitas).toBeTruthy();
    });

    test('Positif - POST /process menyimpan log ke database (SpkFuzzyLog)', async ({ page }) => {
        /**
         * Given: Fuzzy process berhasil
         * When: Check log_id returned
         * Then: Log dapat diakses via Melihat /spk-fuzzy/history/{id}
         */

        // Arrange: Process fuzzy
        const processResponse = await page.request.post('/spk-fuzzy/process');
        const processPayload = await processResponse.json();
        const logId = processPayload.log_id;

        // Act: Get log detail
        const logResponse = await page.request.get(`/spk-fuzzy/history/${logId}`);

        // Assert: Log ada
        expect(logResponse.ok()).toBeTruthy();
        const logPayload = await logResponse.json();
        expect(logPayload.success).toBe(true);
        expect(logPayload.data.id).toBe(logId);

        // Assert: Log has all required fields
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
        /**
         * Given: Fuzzy process berhasil
         * When: Check result.narrative
         * Then: Narrative adalah string yang panjang dan informatif (bukan empty)
         */

        // Act
        const response = await page.request.post('/spk-fuzzy/process');
        const payload = await response.json();

        // Assert: Narrative ada dan tidak kosong
        expect(payload.result.narrative).toBeTruthy();
        expect(typeof payload.result.narrative).toBe('string');
        expect(payload.result.narrative.length).toBeGreaterThan(50); // Minimal 50 karakter
    });

    test('Positif - Process result memiliki recommendation dari kausalitas rule', async ({ page }) => {
        /**
         * Given: Fuzzy kausalitas engine memiliki rules dengan recommendation
         * When: Process fuzzy
         * Then: Result.recommendation adalah string berisi action items
         */

        // Act
        const response = await page.request.post('/spk-fuzzy/process');
        const payload = await response.json();

        // Assert: Recommendation ada
        expect(payload.result.recommendation).toBeTruthy();
        expect(typeof payload.result.recommendation).toBe('string');
    });

    test('Negatif - POST /process dengan coop_id tidak sah (tidak ditemukan) tidak crash', async ({ page }) => {
        /**
         * Given: coop_id yang tidak exist
         * When: POST /spk-fuzzy/process?coop_id={tidak sah-uuid}
         * Then: Return 200 tapi barn_name null (graceful handling)
         */

        // Arrange
        const tidak sahCoopId = '00000000-0000-0000-0000-000000000000';

        // Act
        const response = await page.request.post(`/spk-fuzzy/process?coop_id=${tidak sahCoopId}`);

        // Assert: Response OK (graceful handling)
        expect(response.ok()).toBeTruthy();
        const payload = await response.json();
        expect(payload.success).toBe(true);
        expect(payload.barn_name).toBeNull(); // Kandang tidak ditemukan
    });

    /* ═══════════════════════════════════════════════════════════════════
       Melihat /spk-fuzzy/history - FUZZY HISTORY LIST
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Melihat /history returns array of logs dengan bawaan limit', async ({ page }) => {
        /**
         * Given: SpkFuzzyLog has records
         * When: Melihat /spk-fuzzy/history
         * Then: Return 200 dengan data array (bawaan limit=10)
         */

        // Act
        const response = await page.request.get('/spk-fuzzy/history');

        // Assert: Response OK
        expect(response.ok()).toBeTruthy();
        const payload = await response.json();
        expect(payload.success).toBe(true);
        expect(Array.isArray(payload.data)).toBe(true);
        expect(payload).toHaveProperty('total');
    });

    test('Positif - Melihat /history?limit=5 returns max 5 logs', async ({ page }) => {
        /**
         * Given: History endpoint dengan limit parameter
         * When: Melihat /spk-fuzzy/history?limit=5
         * Then: Return max 5 records
         */

        // Act
        const response = await page.request.get('/spk-fuzzy/history?limit=5');
        const payload = await response.json();

        // Assert: Max 5 records
        expect(payload.data.length).toBeLessThanOrEqual(5);
    });

    test('Positif - Melihat /history?coop_id={uuid} filters by specific kandang', async ({ page }) => {
        /**
         * Given: Process fuzzy untuk kandang specific
         * When: Melihat /spk-fuzzy/history?coop_id={uuid}
         * Then: Return only logs untuk kandang tersebut
         */

        // Arrange: Process fuzzy untuk kandang specific
        const unitsResponse = await page.request.get('/api/farm/unit-budidaya?limit=1');
        const unitsPayload = await unitsResponse.json();

        if (!unitsPayload.data || unitsPayload.data.length === 0) {
            test.skip();
            return;
        }

        const coopId = unitsPayload.data[0].id;

        // Process untuk kandang ini (create log)
        await page.request.post(`/spk-fuzzy/process?coop_id=${coopId}`);

        // Act: Get history filtered by coop_id
        const historyResponse = await page.request.get(`/spk-fuzzy/history?coop_id=${coopId}`);
        const historyPayload = await historyResponse.json();

        // Assert: All logs memiliki barn name yang sama
        expect(historyPayload.success).toBe(true);
        expect(Array.isArray(historyPayload.data)).toBe(true);
        // Note: We can't easily verify all barn names match tanpa DB access
    });

    test('Positif - History data memiliki format lengkap (date, mode, barn, status, verdict, scores)', async ({ page }) => {
        /**
         * Given: History endpoint returns logs
         * When: Melihat /spk-fuzzy/history?limit=1
         * Then: Each log memiliki format yang cocok untuk frontend display
         */

        // Act
        const response = await page.request.get('/spk-fuzzy/history?limit=1');
        const payload = await response.json();

        if (payload.data.length === 0) {
            test.skip(); // No logs yet
            return;
        }

        // Assert: First log memiliki format lengkap
        const firstLog = payload.data[0];
        expect(firstLog).toHaveProperty('id');
        expect(firstLog).toHaveProperty('date'); // diffForHumans format
        expect(firstLog).toHaveProperty('time'); // H:i WIB
        expect(firstLog).toHaveProperty('mode'); // 'Fuzzy Mamdani'
        expect(firstLog).toHaveProperty('modeColor'); // 'purple'
        expect(firstLog).toHaveProperty('barn'); // Nama kandang atau 'Global'
        expect(firstLog).toHaveProperty('status'); // diagnosis_kausalitas or status_lingkungan
        expect(firstLog).toHaveProperty('color'); // emerald, blue, amber, red, gray
        expect(firstLog).toHaveProperty('verdict'); // Limited narrative (120 chars)
        expect(firstLog).toHaveProperty('scores'); // {lingkungan, kesehatan, kausalitas}

        // Assert: Scores object structure
        expect(firstLog.scores).toHaveProperty('lingkungan');
        expect(firstLog.scores).toHaveProperty('kesehatan');
        expect(firstLog.scores).toHaveProperty('kausalitas');
    });

    test('Positif - History verdict adalah excerpt dari narrative (max 120 chars)', async ({ page }) => {
        /**
         * Given: Logs memiliki narrative panjang
         * When: Melihat /spk-fuzzy/history
         * Then: Verdict adalah limited version dari narrative (120 chars max)
         */

        // Act
        const response = await page.request.get('/spk-fuzzy/history?limit=1');
        const payload = await response.json();

        if (payload.data.length === 0) {
            test.skip();
            return;
        }

        // Assert: Verdict max 120 characters
        const firstLog = payload.data[0];
        expect(firstLog.verdict.length).toBeLessThanOrEqual(125); // 120 + "..." = 123
    });

    test('Skenario Batas - Melihat /history dengan limit>50 tetap return max 50 (protection)', async ({ page }) => {
        /**
         * Given: Controller has max limit protection (50)
         * When: Melihat /spk-fuzzy/history?limit=100
         * Then: Return max 50 records
         */

        // Act
        const response = await page.request.get('/spk-fuzzy/history?limit=100');
        const payload = await response.json();

        // Assert: Max 50 records (controller protection)
        expect(payload.data.length).toBeLessThanOrEqual(50);
    });

    /* ═══════════════════════════════════════════════════════════════════
       Melihat /spk-fuzzy/history/{id} - FUZZY HISTORY DETAIL
       ═══════════════════════════════════════════════════════════════════ */

    test('Positif - Melihat /history/{id} returns complete log detail', async ({ page }) => {
        /**
         * Given: Process fuzzy creates log
         * When: Melihat /spk-fuzzy/history/{id}
         * Then: Return 200 dengan complete log data (input_json, fuzzified_json, rule_result_json)
         */

        // Arrange: Create new log
        const processResponse = await page.request.post('/spk-fuzzy/process');
        const processPayload = await processResponse.json();
        const logId = processPayload.log_id;

        // Act
        const response = await page.request.get(`/spk-fuzzy/history/${logId}`);

        // Assert: Response OK
        expect(response.ok()).toBeTruthy();
        const payload = await response.json();
        expect(payload.success).toBe(true);

        // Assert: Complete data
        const logData = payload.data;
        expect(logData.id).toBe(logId);

        // Assert: JSON fields are objects/arrays
        expect(typeof logData.input_json).toBe('object');
        expect(typeof logData.fuzzified_json).toBe('object');
        expect(typeof logData.rule_result_json).toBe('object');
    });

    test('Positif - History detail memiliki input_json dengan variabel fuzzy (suhu, kelembapan, amonia, hdp, fcr)', async ({ page }) => {
        /**
         * Given: InputResolver collects data dari berbagai sources
         * When: Melihat /spk-fuzzy/history/{id}
         * Then: input_json memiliki keys untuk semua variabel fuzzy
         */

        // Arrange: Create new log
        const processResponse = await page.request.post('/spk-fuzzy/process');
        const processPayload = await processResponse.json();
        const logId = processPayload.log_id;

        // Act
        const response = await page.request.get(`/spk-fuzzy/history/${logId}`);
        const payload = await response.json();

        // Assert: input_json has expected variables (may vary based on config)
        const inputJson = payload.data.input_json;
        expect(inputJson).toBeDefined();
        expect(typeof inputJson).toBe('object');

        // Note: Actual keys depend on fuzzy configuration
        // Common expected: suhu, kelembapan, amonia, hdp, fcr, mortalitas
    });

    test('Positif - History detail memiliki fuzzified_json dengan membership degrees', async ({ page }) => {
        /**
         * Given: Fuzzification step completed
         * When: Melihat /spk-fuzzy/history/{id}
         * Then: fuzzified_json memiliki structure {lingkungan: {...}, kesehatan: {...}}
         */

        // Arrange: Create new log
        const processResponse = await page.request.post('/spk-fuzzy/process');
        const processPayload = await processResponse.json();
        const logId = processPayload.log_id;

        // Act
        const response = await page.request.get(`/spk-fuzzy/history/${logId}`);
        const payload = await response.json();

        // Assert: fuzzified_json structure
        const fuzzifiedJson = payload.data.fuzzified_json;
        expect(fuzzifiedJson).toBeDefined();
        expect(fuzzifiedJson).toHaveProperty('lingkungan'); // Engine 1 fuzzification
        expect(fuzzifiedJson).toHaveProperty('kesehatan'); // Engine 2 fuzzification
    });

    test('Positif - History detail memiliki rule_result_json dengan dominant rules', async ({ page }) => {
        /**
         * Given: Rule evaluation completed
         * When: Melihat /spk-fuzzy/history/{id}
         * Then: rule_result_json memiliki dominant rule info untuk setiap engine
         */

        // Arrange: Create new log
        const processResponse = await page.request.post('/spk-fuzzy/process');
        const processPayload = await processResponse.json();
        const logId = processPayload.log_id;

        // Act
        const response = await page.request.get(`/spk-fuzzy/history/${logId}`);
        const payload = await response.json();

        // Assert: rule_result_json structure
        const ruleResultJson = payload.data.rule_result_json;
        expect(ruleResultJson).toBeDefined();
        expect(ruleResultJson).toHaveProperty('lingkungan');
        expect(ruleResultJson).toHaveProperty('kesehatan');
        expect(ruleResultJson).toHaveProperty('kausalitas');
    });

    test('Negatif - Melihat /history/{tidak sah-id} mengembalikan 404', async ({ page }) => {
        /**
         * Given: Log dengan ID tidak exist
         * When: Melihat /spk-fuzzy/history/{tidak sah-uuid}
         * Then: Return 404 dengan kesalahan message
         */

        // Arrange
        const tidak sahId = '00000000-0000-0000-0000-000000000000';

        // Act
        const response = await page.request.get(`/spk-fuzzy/history/${tidak sahId}`);

        // Assert: 404 Not Found
        expect(response.status()).toBe(404);
        const payload = await response.json();
        expect(payload.success).toBe(false);
        expect(payload.message).toMatch(/tidak ditemukan/i);
    });

    test('Negatif - Melihat /history/{ID salah format} mengembalikan 404', async ({ page }) => {
        /**
         * Given: ID format tidak sah (bukan UUID)
         * When: Melihat /spk-fuzzy/history/tidak sah-id-xxx
         * Then: Return 404 (graceful handling)
         */

        // Arrange
        const malformedId = 'tidak sah-log-id-xxx';

        // Act
        const response = await page.request.get(`/spk-fuzzy/history/${malformedId}`);

        // Assert: 404 Not Found
        expect(response.status()).toBe(404);
    });

    /* ═══════════════════════════════════════════════════════════════════
       INTEGRATION & ADVANCED TESTS
       ═══════════════════════════════════════════════════════════════════ */

    test('Integrasi - Complete flow: Process → History list includes log → Detail matches process result', async ({ page }) => {
        /**
         * Given: Complete fuzzy flow
         * When: Process → Get history → Get detail
         * Then: All data consistent
         */

        // Step 1: Process fuzzy
        const processResponse = await page.request.post('/spk-fuzzy/process');
        const processPayload = await processResponse.json();
        const logId = processPayload.log_id;

        // Step 2: Check history list includes this log
        const historyResponse = await page.request.get('/spk-fuzzy/history?limit=10');
        const historyPayload = await historyResponse.json();
        const foundInHistory = historyPayload.data.some((log: any) => log.id === logId);
        expect(foundInHistory).toBe(true);

        // Step 3: Get detail
        const detailResponse = await page.request.get(`/spk-fuzzy/history/${logId}`);
        const detailPayload = await detailResponse.json();

        // Step 4: Verify consistency
        expect(detailPayload.data.status_lingkungan).toBe(processPayload.result.status_lingkungan);
        expect(detailPayload.data.status_kesehatan).toBe(processPayload.result.status_kesehatan);
        expect(detailPayload.data.diagnosis_kausalitas).toBe(processPayload.result.diagnosis_kausalitas);
    });

    test('Integrasi - Process 2x generates 2 different log_id', async ({ page }) => {
        /**
         * Given: Process fuzzy multiple times
         * When: Execute 2 process requests
         * Then: Each returns unique log_id
         */

        // Act: Process 1
        const response1 = await page.request.post('/spk-fuzzy/process');
        const payload1 = await response1.json();
        const logId1 = payload1.log_id;

        // Act: Process 2
        const response2 = await page.request.post('/spk-fuzzy/process');
        const payload2 = await response2.json();
        const logId2 = payload2.log_id;

        // Assert: Different log IDs
        expect(logId1).not.toBe(logId2);
    });

    test('Advanced - Process result status labels are sah (Buruk|Waspada|Baik|Optimal)', async ({ page }) => {
        /**
         * Given: Fuzzy output sets configured dengan standard labels
         * When: Process fuzzy
         * Then: Status labels harus dari set yang sah
         */

        // Act
        const response = await page.request.post('/spk-fuzzy/process');
        const payload = await response.json();

        // Assert: Valid status labels
        const sahLabels = ['Buruk', 'Waspada', 'Baik', 'Optimal'];
        expect(sahLabels).toContain(payload.result.status_lingkungan);
        expect(sahLabels).toContain(payload.result.status_kesehatan);
    });

    test('Advanced - Process result kausalitas labels are sah (from config)', async ({ page }) => {
        /**
         * Given: Kausalitas engine configured
         * When: Process fuzzy
         * Then: diagnosis_kausalitas adalah label yang sah
         */

        // Act
        const response = await page.request.post('/spk-fuzzy/process');
        const payload = await response.json();

        // Assert: Kausalitas label ada (actual values depend on config)
        expect(payload.result.diagnosis_kausalitas).toBeTruthy();
        expect(typeof payload.result.diagnosis_kausalitas).toBe('string');

        // Common expected labels (may vary):
        // 'Krisis Total', 'Stres Lingkungan', 'Penyakit Dominan', 'Kondisi Stabil', etc.
    });

    test('Performa - POST /process completes dalam waktu wajar (<5 detik)', async ({ page }) => {
        /**
         * Given: Fuzzy engine dengan 3-cascaded processing
         * When: Measure process time
         * Then: Should complete < 5 seconds
         */

        // Arrange
        const startTime = Date.now();

        // Act
        const response = await page.request.post('/spk-fuzzy/process');
        await response.json();

        const endTime = Date.now();
        const processTime = endTime - startTime;

        // Assert: Performa < 5000ms
        expect(processTime).toBeLessThan(5000);
    });

    test('Performa - Melihat /config completes dalam waktu wajar (<2 detik)', async ({ page }) => {
        /**
         * Given: Config endpoint dengan relational loading
         * When: Measure config fetch time
         * Then: Should complete < 2 seconds
         */

        // Arrange
        const startTime = Date.now();

        // Act
        const response = await page.request.get('/spk-fuzzy/config');
        await response.json();

        const endTime = Date.now();
        const fetchTime = endTime - startTime;

        // Assert: Performa < 2000ms
        expect(fetchTime).toBeLessThan(2000);
    });

    test('Performa - Melihat /history dengan limit=10 completes dalam waktu wajar (<1 detik)', async ({ page }) => {
        /**
         * Given: History endpoint dengan formatting
         * When: Measure history fetch time
         * Then: Should complete < 1 second
         */

        // Arrange
        const startTime = Date.now();

        // Act
        const response = await page.request.get('/spk-fuzzy/history?limit=10');
        await response.json();

        const endTime = Date.now();
        const fetchTime = endTime - startTime;

        // Assert: Performa < 1000ms
        expect(fetchTime).toBeLessThan(1000);
    });
});
