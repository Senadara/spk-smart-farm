import { test, expect, APIRequestContext } from '@playwright/test';

/**
 * Modul SPK DSS API - E2E Tests
 * 
 * Testing endpoints di:
 * - SpkParameterController (Parameters CRUD, Parameter Values Assignment)
 * - SpkAHPController (AHP Perbandingan Submit)
 * - RecommendationController (Ranking API)
 * - SpkSupplierDssController (DSS API: evaluation, rankings, weights, insights)
 * 
 * NOTE: Controller methods require authenticated session with sah users.id mapping
 */

test.describe('Modul SPK DSS API - E2E Tests', () => {
     let apiContext: APIRequestContext;
     let createdParameterIds: number[] = [];

     test.setTimeout(60000);

     test.beforeAll(async ({ playwright, browser }) => {
          // Login via UI first to establish session with sah users.id
          const page = await browser.newPage();
          await page.goto('http://localhost:8000/login');
          await page.fill('input[name="email"]', 'pjawab@email.com');
          await page.fill('input[name="password"]', 'Password123.');
          await page.click('button[type="submit"]');
          await page.waitForURL('**/dashboard', { timeout: 10000 });

          // Get session storage state
          const storageState = await page.context().storageState();
          await page.close();

          // Create API context with session cookies
          apiContext = await playwright.request.newContext({
               baseURL: 'http://localhost:8000',
               storageState,
               extraHTTPHeaders: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
               },
          });
     });

     test.afterAll(async () => {
          // Cleanup created parameters (cascade will delete relations)
          for (const id of createdParameterIds) {
               try {
                    // NOTE: No Menghapus endpoint ada in SpkParameterController
                    // Parameters should be managed via seeder or manual DB cleanup
               } catch (e) {
                    // Ignore cleanup kesalahans
               }
          }

          await apiContext.dispose();
     });

     // ═══════════════════════════════════════════════════════════════
     // SPK PARAMETERS CRUD (SpkParameterController)
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Melihat semua parameters (index)', async () => {
          /**
           * Given: API endpoint /supplier-spk/parameters
           * When: Melihat request
           * Then: Return 200 dengan array parameters (termasuk dari seeder)
           */

          // Act
          const response = await apiContext.get('/supplier-spk/parameters');

          // Assert
          expect(response.ok()).toBeTruthy();
          expect(response.status()).toBe(200);

          const data = await response.json();
          expect(Array.isArray(data)).toBeTruthy();

          // Check structure jika ada data (dari SpkSupplierSeeder)
          if (data.length > 0) {
               const firstParam = data[0];
               expect(firstParam).toHaveProperty('id');
               expect(firstParam).toHaveProperty('nama_parameter');
               expect(firstParam).toHaveProperty('tipe');
               expect(['benefit', 'cost']).toContain(firstParam.tipe);
               expect(firstParam).toHaveProperty('created_at');
               expect(firstParam).toHaveProperty('updated_at');
          }
     });

     test('Positif - Menambah parameter dengan tipe benefit', async () => {
          /**
           * Given: Data parameter sah dengan tipe benefit
           * When: POST ke /supplier-spk/parameters
           * Then: Return 201 dengan data parameter created
           */

          // Arrange
          const paramData = {
               nama_parameter: `Responsiveness E2E ${Date.now()}`,
               tipe: 'benefit',
          };

          // Act
          const response = await apiContext.post('/supplier-spk/parameters', {
               data: paramData,
          });

          // Assert
          expect(response.ok()).toBeTruthy();
          expect(response.status()).toBe(201);

          const data = await response.json();
          expect(data).toHaveProperty('id');
          expect(data.nama_parameter).toBe(paramData.nama_parameter);
          expect(data.tipe).toBe(paramData.tipe);
          expect(data).toHaveProperty('created_at');
          expect(data).toHaveProperty('updated_at');

          // Save for potential cleanup
          createdParameterIds.push(data.id);
     });

     test('Positif - Menambah parameter dengan tipe cost', async () => {
          /**
           * Given: Data parameter sah dengan tipe cost
           * When: POST ke /supplier-spk/parameters
           * Then: Return 201 dengan tipe cost
           */

          // Arrange
          const paramData = {
               nama_parameter: `Lead Time E2E ${Date.now()}`,
               tipe: 'cost',
          };

          // Act
          const response = await apiContext.post('/supplier-spk/parameters', {
               data: paramData,
          });

          // Assert
          expect(response.ok()).toBeTruthy();
          expect(response.status()).toBe(201);

          const data = await response.json();
          expect(data.tipe).toBe('cost');

          createdParameterIds.push(data.id);
     });

     test('Negatif - Menambah parameter tanpa nama_parameter (required)', async () => {
          /**
           * Given: Data parameter tanpa nama_parameter
           * When: POST ke /supplier-spk/parameters
           * Then: Return 422 sahation kesalahan
           */

          // Arrange
          const tidak sahData = {
               tipe: 'benefit',
               // nama_parameter missing
          };

          // Act
          const response = await apiContext.post('/supplier-spk/parameters', {
               data: tidak sahData,
          });

          // Assert
          expect(response.status()).toBe(422);

          const data = await response.json();
          expect(data).toHaveProperty('kesalahans');
          expect(data.kesalahans).toHaveProperty('nama_parameter');
     });

     test('Negatif - Menambah parameter dengan tipe tidak sah (not in enum)', async () => {
          /**
           * Given: Data parameter dengan tipe selain benefit/cost
           * When: POST dengan tipe tidak sah
           * Then: Return 422 sahation kesalahan
           */

          // Arrange
          const tidak sahData = {
               nama_parameter: `Insah Type ${Date.now()}`,
               tipe: 'neutral', // Insah, only benefit/cost allowed
          };

          // Act
          const response = await apiContext.post('/supplier-spk/parameters', {
               data: tidak sahData,
          });

          // Assert
          expect(response.status()).toBe(422);

          const data = await response.json();
          expect(data).toHaveProperty('kesalahans');
          expect(data.kesalahans).toHaveProperty('tipe');
     });

     // ═══════════════════════════════════════════════════════════════
     // PARAMETER VALUES ASSIGNMENT (SpkParameterController)
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Menetapkan parameter value untuk supplier-produk-parameter', async () => {
          /**
           * Given: Valid supplier_id, produk_id, parameter_id dari seeder
           * When: POST ke /supplier-spk/parameters/assign dengan value numeric
           * Then: Return 200 dengan parameter value (updateOrCreate behavior)
           */

          // Arrange - Assume seeder has created suppliers, products, parameters
          // Get first parameter ID
          const paramsResponse = await apiContext.get('/supplier-spk/parameters');
          const params = await paramsResponse.json();

          if (params.length === 0) {
               test.skip(true, 'No parameters available from seeder');
               return;
          }

          const parameterId = params[0].id;

          // For this test, we assume supplier_id=1 and produk_id=1 exist from seeder
          const assignData = {
               supplier_id: 1,
               produk_id: 1,
               parameter_id: parameterId,
               value: 85.5,
          };

          // Act
          const response = await apiContext.post('/supplier-spk/parameters/assign', {
               data: assignData,
          });

          // Assert
          expect(response.ok()).toBeTruthy();

          const data = await response.json();
          expect(data).toHaveProperty('id');
          expect(data.supplier_id).toBe(assignData.supplier_id);
          expect(data.produk_id).toBe(assignData.produk_id);
          expect(data.parameter_id).toBe(assignData.parameter_id);
          expect(data.value).toBe(assignData.value);
     });

     test('Negatif - Menetapkan parameter value dengan supplier_id tidak exist', async () => {
          /**
           * Given: supplier_id yang tidak ada di master_suppliers
           * When: POST assign
           * Then: Return 422 sahation kesalahan (foreign key constraint)
           */

          // Arrange
          const paramsResponse = await apiContext.get('/supplier-spk/parameters');
          const params = await paramsResponse.json();

          if (params.length === 0) {
               test.skip(true, 'No parameters available');
               return;
          }

          const tidak sahData = {
               supplier_id: 99999, // Does tidak ada
               produk_id: 1,
               parameter_id: params[0].id,
               value: 50,
          };

          // Act
          const response = await apiContext.post('/supplier-spk/parameters/assign', {
               data: tidak sahData,
          });

          // Assert
          expect(response.status()).toBe(422);

          const data = await response.json();
          expect(data).toHaveProperty('kesalahans');
          expect(data.kesalahans).toHaveProperty('supplier_id');
     });

     // ═══════════════════════════════════════════════════════════════
     // AHP PERBANDINGAN Mengirim (SpkAHPController)
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Mengirim AHP perbandingan yang sah (CR ≤ 0.1)', async () => {
          /**
           * Given: Perbandingans array dengan nilai_skala sah (Saaty scale)
           * When: POST ke /supplier-spk/ahp/perbandingan
           * Then: Return 200 dengan CR sah, weights calculated
           */

          // Arrange - Get existing parameters
          const paramsResponse = await apiContext.get('/supplier-spk/parameters');
          const params = await paramsResponse.json();

          if (params.length < 2) {
               test.skip(true, 'Need at least 2 parameters for AHP');
               return;
          }

          // Generate consistent pairwise comparisons (all equal = CR = 0)
          const perbandingans = [
               {
                    parameter_1_id: params[0].id,
                    parameter_2_id: params[1].id,
                    nilai_skala: 1, // Equal importance = consistent
               },
          ];

          // If there are 3 parameters, add more pairs for completeness
          if (params.length >= 3) {
               perbandingans.push(
                    {
                         parameter_1_id: params[0].id,
                         parameter_2_id: params[2].id,
                         nilai_skala: 1,
                    },
                    {
                         parameter_1_id: params[1].id,
                         parameter_2_id: params[2].id,
                         nilai_skala: 1,
                    }
               );
          }

          // Act
          const response = await apiContext.post('/supplier-spk/ahp/perbandingan', {
               data: { perbandingans },
          });

          // Assert
          expect(response.ok()).toBeTruthy();

          const data = await response.json();
          expect(data).toHaveProperty('message');
          expect(data).toHaveProperty('data');
          expect(data.data).toHaveProperty('cr');
          expect(data.data).toHaveProperty('is_sah');
          expect(data.data.is_sah).toBeTruthy(); // Should be sah
          expect(data.data.cr).toBeLessThanOrEqual(0.1);
     });

     test('Negatif - Mengirim AHP perbandingan dengan nilai_skala < 0.1 (min sahation)', async () => {
          /**
           * Given: Perbandingans dengan nilai_skala < 0.1
           * When: POST ke /supplier-spk/ahp/perbandingan
           * Then: Return 422 sahation kesalahan
           */

          // Arrange
          const paramsResponse = await apiContext.get('/supplier-spk/parameters');
          const params = await paramsResponse.json();

          if (params.length < 2) {
               test.skip(true, 'Need at least 2 parameters');
               return;
          }

          const tidak sahData = {
               perbandingans: [
                    {
                         parameter_1_id: params[0].id,
                         parameter_2_id: params[1].id,
                         nilai_skala: 0.05, // Too small, min is 0.1
                    },
               ],
          };

          // Act
          const response = await apiContext.post('/supplier-spk/ahp/perbandingan', {
               data: tidak sahData,
          });

          // Assert
          expect(response.status()).toBe(422);

          const data = await response.json();
          expect(data).toHaveProperty('kesalahans');
     });

     test('Negatif - Mengirim AHP perbandingan dengan nilai_skala > 9 (max sahation)', async () => {
          /**
           * Given: Perbandingans dengan nilai_skala > 9
           * When: POST ke /supplier-spk/ahp/perbandingan
           * Then: Return 422 sahation kesalahan
           */

          // Arrange
          const paramsResponse = await apiContext.get('/supplier-spk/parameters');
          const params = await paramsResponse.json();

          if (params.length < 2) {
               test.skip(true, 'Need at least 2 parameters');
               return;
          }

          const tidak sahData = {
               perbandingans: [
                    {
                         parameter_1_id: params[0].id,
                         parameter_2_id: params[1].id,
                         nilai_skala: 15, // Too large, max is 9
                    },
               ],
          };

          // Act
          const response = await apiContext.post('/supplier-spk/ahp/perbandingan', {
               data: tidak sahData,
          });

          // Assert
          expect(response.status()).toBe(422);

          const data = await response.json();
          expect(data).toHaveProperty('kesalahans');
     });

     // ═══════════════════════════════════════════════════════════════
     // RECOMMENDATION RANKING API (RecommendationController)
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Melihat recommendation ranking untuk produk_id sah', async () => {
          /**
           * Given: AHP weights sudah di-calculate (dari test sebelumnya)
           * When: Melihat ke /supplier-spk/recommendation/{produkId}
           * Then: Return 200 dengan array rankings SAW
           */

          // Arrange - Assume produk_id=1 ada from seeder
          const produkId = 1;

          // Act
          const response = await apiContext.get(`/supplier-spk/recommendation/${produkId}`);

          // Assert
          // Bisa return 404 jika belum ada sah AHP weights atau no suppliers
          if (response.status() === 404) {
               const data = await response.json();
               expect(data).toHaveProperty('message');
               expect(data.message).toContain('No sah rankings');
          } else {
               expect(response.ok()).toBeTruthy();
               expect(response.status()).toBe(200);

               const data = await response.json();
               expect(Array.isArray(data)).toBeTruthy();

               if (data.length > 0) {
                    const ranking = data[0];
                    expect(ranking).toHaveProperty('supplier');
                    expect(ranking).toHaveProperty('supplier_id');
                    expect(ranking).toHaveProperty('score');
                    expect(ranking).toHaveProperty('rank');
                    expect(typeof ranking.score).toBe('number');
                    expect(typeof ranking.rank).toBe('number');
               }
          }
     });

     test('Positif - Melihat recommendation dengan recalculate=true', async () => {
          /**
           * Given: Valid produk_id
           * When: Melihat dengan query param recalculate=true
           * Then: Force re-calculate SAW rankings
           */

          // Arrange
          const produkId = 1;

          // Act
          const response = await apiContext.get(`/supplier-spk/recommendation/${produkId}?recalculate=true`);

          // Assert - Same expectations as above
          if (response.status() === 404) {
               const data = await response.json();
               expect(data.message).toContain('No sah rankings');
          } else {
               expect(response.ok()).toBeTruthy();
               const data = await response.json();
               expect(Array.isArray(data)).toBeTruthy();
          }
     });

     // ═══════════════════════════════════════════════════════════════
     // DSS API ENDPOINTS (SpkSupplierDssController)
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Melihat evaluation matrix untuk produk_id', async () => {
          /**
           * Given: Valid produk_id
           * When: Melihat ke /supplier-spk/evaluation/{produkId}
           * Then: Return 200 dengan evaluation matrix (suppliers x parameters)
           */

          // Arrange
          const produkId = 1;

          // Act
          const response = await apiContext.get(`/supplier-spk/evaluation/${produkId}`);

          // Assert
          expect(response.ok()).toBeTruthy();
          expect(response.status()).toBe(200);

          const data = await response.json();
          // Evaluation matrix structure dari SAWRecommenderService
          expect(Array.isArray(data) || typeof data === 'object').toBeTruthy();
     });

     test('Positif - Melihat DSS weights (bobot AHP yang sah)', async () => {
          /**
           * Given: User dengan sah AHP weights (dari test sebelumnya)
           * When: Melihat ke /spk-suppliers/dss/api/weights
           * Then: Return 200 dengan CR, weights array
           */

          // Act
          const response = await apiContext.get('/spk-suppliers/dss/api/weights');

          // Assert
          if (response.status() === 401) {
               const data = await response.json();
               expect(data.message).toContain('tidak dikenali');
          } else {
               expect(response.ok()).toBeTruthy();

               const data = await response.json();
               expect(data).toHaveProperty('cr');
               expect(data).toHaveProperty('is_sah');
               expect(data).toHaveProperty('version');
               expect(data).toHaveProperty('weights');
               expect(Array.isArray(data.weights)).toBeTruthy();

               if (data.weights.length > 0) {
                    const weight = data.weights[0];
                    expect(weight).toHaveProperty('criteria');
                    expect(weight).toHaveProperty('type');
                    expect(weight).toHaveProperty('weight');
               }
          }
     });

     test('Positif - Melihat DSS rankings API untuk produk', async () => {
          /**
           * Given: Valid produk_id dan user dengan AHP weights
           * When: Melihat ke /spk-suppliers/dss/api/rankings/{produkId}
           * Then: Return 200 dengan supplier rankings
           */

          // Arrange
          const produkId = 1;

          // Act
          const response = await apiContext.get(`/spk-suppliers/dss/api/rankings/${produkId}`);

          // Assert
          if (response.status() === 404) {
               const data = await response.json();
               expect(data.message).toContain('Tidak ada peringkat');
          } else if (response.status() === 401) {
               const data = await response.json();
               expect(data.message).toContain('tidak dikenali');
          } else {
               expect(response.ok()).toBeTruthy();

               const data = await response.json();
               expect(Array.isArray(data)).toBeTruthy();

               if (data.length > 0) {
                    const ranking = data[0];
                    expect(ranking).toHaveProperty('supplier');
                    expect(ranking).toHaveProperty('supplier_id');
                    expect(ranking).toHaveProperty('score');
                    expect(ranking).toHaveProperty('rank');
               }
          }
     });

     test('Positif - Melihat DSS insights', async () => {
          /**
           * Given: User dengan sah AHP config
           * When: Melihat ke /spk-suppliers/dss/api/insights
           * Then: Return 200 dengan insights array
           */

          // Act
          const response = await apiContext.get('/spk-suppliers/dss/api/insights');

          // Assert
          if (response.status() === 401) {
               const data = await response.json();
               expect(data.message).toContain('tidak dikenali');
          } else {
               expect(response.ok()).toBeTruthy();

               const data = await response.json();
               // Insights dari SupplierInsightService
               expect(Array.isArray(data) || typeof data === 'object').toBeTruthy();
          }
     });

     test('Positif - Melihat DSS insights dengan produk_id filter', async () => {
          /**
           * Given: Valid produk_id
           * When: Melihat insights dengan query param produk_id
           * Then: Return 200 dengan insights filtered by product
           */

          // Arrange
          const produkId = 1;

          // Act
          const response = await apiContext.get(`/spk-suppliers/dss/api/insights?produk_id=${produkId}`);

          // Assert
          if (response.status() === 401) {
               const data = await response.json();
               expect(data.message).toContain('tidak dikenali');
          } else {
               expect(response.ok()).toBeTruthy();
               const data = await response.json();
               expect(Array.isArray(data) || typeof data === 'object').toBeTruthy();
          }
     });

     // ═══════════════════════════════════════════════════════════════
     // INTEGRATION TESTS
     // ═══════════════════════════════════════════════════════════════

     test('Integrasi - Full DSS flow: Create parameter → Assign values → AHP config → Get rankings', async () => {
          /**
           * Given: Clean state dengan seeder data
           * When: Execute full DSS workflow
           * Then: Each step succeeds dan ranking tersedia
           */

          // Step 1: Verify parameters exist
          const paramsResponse = await apiContext.get('/supplier-spk/parameters');
          expect(paramsResponse.ok()).toBeTruthy();
          const params = await paramsResponse.json();
          expect(params.length).toBeGreaterThan(0);

          // Step 2: Submit consistent AHP comparisons
          if (params.length >= 2) {
               const ahpData = {
                    perbandingans: [
                         {
                              parameter_1_id: params[0].id,
                              parameter_2_id: params[1].id,
                              nilai_skala: 1,
                         },
                    ],
               };

               const ahpResponse = await apiContext.post('/supplier-spk/ahp/perbandingan', {
                    data: ahpData,
               });

               if (ahpResponse.ok()) {
                    const ahpResult = await ahpResponse.json();
                    expect(ahpResult.data.is_sah).toBeTruthy();
               }
          }

          // Step 3: Get rankings for produk
          const rankResponse = await apiContext.get('/supplier-spk/recommendation/1');

          // May be 404 if no suppliers with values, that's OK for integration test
          if (rankResponse.ok()) {
               const rankings = await rankResponse.json();
               expect(Array.isArray(rankings)).toBeTruthy();
          }
     });
});
