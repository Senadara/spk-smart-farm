import { test, expect } from '@playwright/test';

test.describe('Modul API Endpoint SPK Fuzzy - E2E QA', () => {
    test.setTimeout(90000);

    test('Positif - Konfigurasi Fuzzy Config Dictionary di response JSON utuh', async ({ page }) => {
        /**
         * Given service SPK backend aktif
         * When pengguna meng-query API GET ke rute '/spk-fuzzy/config'
         * Then Server memberikan HTTP CODE 200 beserta JSON payload (variabel & rules)
         */

        // Arrange & Act
        const response = await page.request.get('/spk-fuzzy/config');
        
        // Assert
        expect(response.ok()).toBeTruthy();

        const payload = await response.json();
        expect(payload.success).toBe(true);
        expect(Array.isArray(payload.variables)).toBe(true);
        expect(Array.isArray(payload.rules)).toBe(true);
    });

    test('Positif - Endpoints Logging History Data Valid', async ({ page }) => {
        /**
         * Given SPK Fuzzy logger beroperasi menyimpan tracking log
         * When diakses index data via API request (limit=5)
         * Then server menerbitkan array payload record logs
         */

        // Arrange & Act
        const historyResponse = await page.request.get('/spk-fuzzy/history?limit=5');
        
        // Assert
        expect(historyResponse.ok()).toBeTruthy();

        const historyPayload = await historyResponse.json();
        expect(historyPayload.success).toBe(true);
        expect(Array.isArray(historyPayload.data)).toBe(true);
    });

    test('Negatif - Permintaan Detail Log Fiktif memicu HTTP Status Bebas-Crash (404 Not Found)', async ({ page }) => {
        /**
         * Given layanan service detail API id History
         * When API dilempari argumen id yang tidak ter-hash / tak eksis seperti 'invalid-log-xxx'
         * Then backend tidak menampilkan throw error 500, melainkan dengan aman merespon standard HTTP 404.
         */

        // Arrange & Act
        const dummyId = 'nonexistent-log-id';
        const detailResponse = await page.request.get(`/spk-fuzzy/history/${dummyId}`);
        
        // Assert
        expect(detailResponse.status()).toBe(404);
    });
});
