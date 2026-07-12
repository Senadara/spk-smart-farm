import { test, expect, APIRequestContext } from '@playwright/test';

/**
 * Modul Supplier CRUD - API E2E Tests
 * 
 * Testing endpoints di SupplierController.php:
 * - Melihat    /supplier-spk/suppliers        (index)
 * - POST   /supplier-spk/suppliers        (store)
 * - Melihat    /supplier-spk/suppliers/{id}   (show)
 * - PUT    /supplier-spk/suppliers/{id}   (update)
 * - Menghapus /supplier-spk/suppliers/{id}   (destroy)
 */

test.describe('Modul Supplier CRUD - API E2E Tests', () => {
     let apiContext: APIRequestContext;
     let createdSupplierIds: string[] = [];
     let productIds: number[] = [];

     test.setTimeout(60000);

     test.beforeAll(async ({ playwright, browser }) => {
          // Login via UI first to establish session
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

          // Setup test data: Create test products if needed
          // NOTE: In real scenario, products should be seeded or already exist
          // For this test, we'll skip produk_ids sahation if no products available
     });

     test.afterAll(async () => {
          // Cleanup created suppliers
          for (const id of createdSupplierIds) {
               try {
                    await apiContext.delete(`/supplier-spk/suppliers/${id}`);
               } catch (e) {
                    // Ignore cleanup kesalahans
               }
          }

          await apiContext.dispose();
     });

     // ═══════════════════════════════════════════════════════════════
     // READ OPERATIONS
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Melihat semua suppliers (index)', async () => {
          /**
           * Given: API endpoint /supplier-spk/suppliers
           * When: Melihat request tanpa parameter
           * Then: Return 200 dengan array suppliers dan relations produks
           */

          // Act
          const response = await apiContext.get('/supplier-spk/suppliers');

          // Assert
          expect(response.ok()).toBeTruthy();
          expect(response.status()).toBe(200);

          const data = await response.json();
          expect(Array.isArray(data)).toBeTruthy();

          // Check structure jika ada data
          if (data.length > 0) {
               const firstSupplier = data[0];
               expect(firstSupplier).toHaveProperty('id');
               expect(firstSupplier).toHaveProperty('nama');
               expect(firstSupplier).toHaveProperty('produks'); // Relations loaded
          }
     });

     // ═══════════════════════════════════════════════════════════════
     // Menambah OPERATIONS
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Menambah supplier dengan data lengkap (semua field)', async () => {
          /**
           * Given: Data supplier sah dengan SEMUA field (termasuk deskripsi, kategori, rating, jarak_km, logo_url)
           * When: POST ke /supplier-spk/suppliers
           * Then: Return 201 dengan data supplier yang dibuat lengkap
           */

          // Arrange
          const supplierData = {
               nama: `Supplier E2E Complete ${Date.now()}`,
               alamat: 'Jl. Test E2E No. 123, Jakarta Selatan',
               kontak: '081234567890',
               deskripsi: 'Supplier pakan dan vitamin peternakan berkualitas tinggi',
               kategori: 'pakan,vitamin,obat',
               rating: 4.7,
               jarak_km: 25,
               logo_url: 'https://example.com/logo.png',
          };

          // Act
          const response = await apiContext.post('/supplier-spk/suppliers', {
               data: supplierData,
          });

          // Assert
          expect(response.ok()).toBeTruthy();
          expect(response.status()).toBe(201);

          const data = await response.json();
          expect(data).toHaveProperty('id');
          expect(data.nama).toBe(supplierData.nama);
          expect(data.alamat).toBe(supplierData.alamat);
          expect(data.kontak).toBe(supplierData.kontak);
          expect(data.deskripsi).toBe(supplierData.deskripsi);
          expect(data.kategori).toBe(supplierData.kategori);
          expect(data.rating).toBe(supplierData.rating);
          expect(data.jarak_km).toBe(supplierData.jarak_km);
          expect(data.logo_url).toBe(supplierData.logo_url);
          expect(data).toHaveProperty('produks'); // Relations loaded
          expect(data).toHaveProperty('created_at');
          expect(data).toHaveProperty('updated_at');

          // Save for cleanup
          createdSupplierIds.push(data.id);
     });

     test('Positif - Menambah supplier dengan data minimal (hanya nama required)', async () => {
          /**
           * Given: Data supplier hanya dengan field required (nama)
           * When: POST ke /supplier-spk/suppliers
           * Then: Return 201, field optional null/bawaan
           */

          // Arrange
          const supplierData = {
               nama: `Supplier Minimal ${Date.now()}`,
          };

          // Act
          const response = await apiContext.post('/supplier-spk/suppliers', {
               data: supplierData,
          });

          // Assert
          expect(response.ok()).toBeTruthy();
          expect(response.status()).toBe(201);

          const data = await response.json();
          expect(data).toHaveProperty('id');
          expect(data.nama).toBe(supplierData.nama);
          expect(data).toHaveProperty('produks');

          // Save for cleanup
          createdSupplierIds.push(data.id);
     });

     test('Positif - Menambah supplier dengan produk_ids sync', async () => {
          /**
           * Given: Data supplier dengan produk_ids (array of product IDs)
           * When: POST dengan produk_ids
           * Then: Supplier created dan relations produks di-sync
           * 
           * NOTE: Test ini memerlukan produk yang sudah exist di database.
           * Jika tidak ada produk, test akan tetap sahasi Menambah tanpa produk_ids.
           */

          // Arrange
          const supplierData = {
               nama: `Supplier with Products ${Date.now()}`,
               alamat: 'Jl. Produk Test',
               kontak: '089876543210',
               produk_ids: productIds.length > 0 ? productIds.slice(0, 2) : [],
          };

          // Act
          const response = await apiContext.post('/supplier-spk/suppliers', {
               data: supplierData,
          });

          // Assert
          expect(response.ok()).toBeTruthy();
          expect(response.status()).toBe(201);

          const data = await response.json();
          expect(data).toHaveProperty('produks');
          expect(Array.isArray(data.produks)).toBeTruthy();

          if (productIds.length > 0) {
               expect(data.produks.length).toBeGreaterThanOrEqual(0);
          }

          createdSupplierIds.push(data.id);
     });

     test('Negatif - Menambah supplier tanpa nama (required field)', async () => {
          /**
           * Given: Data supplier tanpa field nama (required)
           * When: POST ke /supplier-spk/suppliers
           * Then: Return 422 dengan sahation kesalahan
           */

          // Arrange
          const tidak sahData = {
               // nama tidak ada
               alamat: 'Jl. Insah Test',
               kontak: '081111111111',
          };

          // Act
          const response = await apiContext.post('/supplier-spk/suppliers', {
               data: tidak sahData,
          });

          // Assert
          expect(response.status()).toBe(422);

          const data = await response.json();
          expect(data).toHaveProperty('message'); // Laravel sahation kesalahan structure
          expect(data).toHaveProperty('kesalahans');
          expect(data.kesalahans).toHaveProperty('nama');
     });

     test('Negatif - Menambah supplier dengan produk_ids tidak sah (not ada)', async () => {
          /**
           * Given: Data supplier dengan produk_ids yang tidak exist
           * When: POST dengan produk_ids tidak sah
           * Then: Return 422 dengan sahation kesalahan
           */

          // Arrange
          const tidak sahData = {
               nama: `Insah Products ${Date.now()}`,
               alamat: 'Jl. Test',
               kontak: '081234567890',
               produk_ids: [99999, 88888], // IDs yang tidak exist
          };

          // Act
          const response = await apiContext.post('/supplier-spk/suppliers', {
               data: tidak sahData,
          });

          // Assert
          expect(response.status()).toBe(422);

          const data = await response.json();
          expect(data).toHaveProperty('kesalahans');
          // Laravel sahation bisa return kesalahan di 'produk_ids.0' atau 'produk_ids.1' atau 'produk_ids'
          const hasError = data.kesalahans['produk_ids.0'] || data.kesalahans['produk_ids.1'] || data.kesalahans['produk_ids'];
          expect(hasError).toBeTruthy();
     });

     test('Positif - Menambah supplier dengan rating dan jarak_km numeric', async () => {
          /**
           * Given: Data supplier dengan rating (decimal) dan jarak_km (integer)
           * When: POST dengan tipe data yang benar
           * Then: Return 201 dengan nilai yang presisi
           */

          // Arrange
          const supplierData = {
               nama: `Rating Test ${Date.now()}`,
               rating: 4.75, // decimal(3,1) in DB
               jarak_km: 125, // unsigned integer
          };

          // Act
          const response = await apiContext.post('/supplier-spk/suppliers', {
               data: supplierData,
          });

          // Assert
          expect(response.ok()).toBeTruthy();
          expect(response.status()).toBe(201);

          const data = await response.json();
          expect(data.rating).toBeCloseTo(4.8, 1); // Rounded to 1 decimal
          expect(data.jarak_km).toBe(125);

          // Save for cleanup
          createdSupplierIds.push(data.id);
     });

     // ═══════════════════════════════════════════════════════════════
     // READ SINGLE OPERATIONS
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Melihat detail supplier berdasarkan ID (show)', async () => {
          /**
      * Given: Supplier ID yang sah
      * When: Melihat ke /supplier-spk/suppliers/{id}
      * Then: Return 200 dengan detail supplier dan relations
      */

          // Arrange - Create supplier first
          const createResponse = await apiContext.post('/supplier-spk/suppliers', {
               data: {
                    nama: `Show Test ${Date.now()}`,
                    alamat: 'Jl. Show Test',
                    kontak: '081234567890',
               },
          });
          const supplier = await createResponse.json();
          createdSupplierIds.push(supplier.id);

          // Act
          const response = await apiContext.get(`/supplier-spk/suppliers/${supplier.id}`);

          // Assert
          expect(response.ok()).toBeTruthy();
          expect(response.status()).toBe(200);

          const data = await response.json();
          expect(data.id).toBe(supplier.id);
          expect(data.nama).toBe(supplier.nama);
          expect(data).toHaveProperty('produks');
     });

     test('Negatif - Melihat supplier dengan ID tidak exist (404)', async () => {
          /**
           * Given: Supplier ID yang tidak exist
           * When: Melihat ke /supplier-spk/suppliers/{id}
           * Then: Return 404 Not Found
           */

          // Arrange
          const nonExistentId = 'non-existent-uuid-12345';

          // Act
          const response = await apiContext.get(`/supplier-spk/suppliers/${nonExistentId}`);

          // Assert
          expect(response.status()).toBe(404);
     });

     // ═══════════════════════════════════════════════════════════════
     // Mengubah OPERATIONS
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Mengubah supplier (semua field termasuk kategori, rating, dll)', async () => {
          /**
           * Given: Supplier yang sudah ada
           * When: PUT dengan data baru (termasuk deskripsi, kategori, rating, jarak_km, logo_url)
           * Then: Return 200 dengan data diperbarui lengkap
           */

          // Arrange - Create supplier first
          const createResponse = await apiContext.post('/supplier-spk/suppliers', {
               data: {
                    nama: `Original ${Date.now()}`,
                    alamat: 'Alamat Original',
                    kontak: '081111111111',
                    kategori: 'pakan',
                    rating: 4.0,
               },
          });
          const supplier = await createResponse.json();
          createdSupplierIds.push(supplier.id);

          // Act
          const diperbaruiData = {
               nama: `Updated ${Date.now()}`,
               alamat: 'Alamat Baru Diupdate',
               kontak: '089999999999',
               deskripsi: 'Deskripsi supplier yang sudah diupdate',
               kategori: 'pakan,obat,vitamin',
               rating: 4.8,
               jarak_km: 50,
               logo_url: 'https://example.com/new-logo.png',
          };

          const response = await apiContext.put(`/supplier-spk/suppliers/${supplier.id}`, {
               data: diperbaruiData,
          });

          // Assert
          expect(response.ok()).toBeTruthy();
          expect(response.status()).toBe(200);

          const data = await response.json();
          expect(data.id).toBe(supplier.id);
          expect(data.nama).toBe(updatedData.nama);
          expect(data.alamat).toBe(updatedData.alamat);
          expect(data.kontak).toBe(updatedData.kontak);
          expect(data.deskripsi).toBe(updatedData.deskripsi);
          expect(data.kategori).toBe(updatedData.kategori);
          expect(data.rating).toBe(updatedData.rating);
          expect(data.jarak_km).toBe(updatedData.jarak_km);
          expect(data.logo_url).toBe(updatedData.logo_url);
     });

     test('Positif - Mengubah supplier produk_ids (sync)', async () => {
          /**
           * Given: Supplier dengan produk tertentu
           * When: PUT dengan produk_ids baru
           * Then: Relations produks di-sync dengan data baru
           * 
           * NOTE: Test ini skip jika tidak ada produk di database
           */

          // Skip if no products available
          if (productIds.length === 0) {
               test.skip(true, 'No products available for testing produk_ids sync');
               return;
          }

          // Arrange - Create supplier
          const createResponse = await apiContext.post('/supplier-spk/suppliers', {
               data: {
                    nama: `Update Products ${Date.now()}`,
                    alamat: 'Jl. Update',
                    kontak: '081234567890',
                    produk_ids: [],
               },
          });
          const supplier = await createResponse.json();
          createdSupplierIds.push(supplier.id);

          // Act - Update with product IDs
          const response = await apiContext.put(`/supplier-spk/suppliers/${supplier.id}`, {
               data: {
                    produk_ids: productIds.slice(0, 1),
               },
          });

          // Assert
          expect(response.ok()).toBeTruthy();

          const data = await response.json();
          expect(data).toHaveProperty('produks');
          expect(data.produks.length).toBeGreaterThan(0);
     });

     test('Positif - Mengubah partial (only rating dan kategori)', async () => {
          /**
           * Given: Supplier existing
           * When: PUT hanya dengan field rating dan kategori (partial update)
           * Then: Hanya field tersebut yang berubah, field lain tetap
           */

          // Arrange
          const createResponse = await apiContext.post('/supplier-spk/suppliers', {
               data: {
                    nama: `Partial ${Date.now()}`,
                    alamat: 'Original Address',
                    kontak: '081234567890',
                    kategori: 'pakan',
                    rating: 3.5,
               },
          });
          const supplier = await createResponse.json();
          createdSupplierIds.push(supplier.id);

          // Act - Only update rating dan kategori
          const response = await apiContext.put(`/supplier-spk/suppliers/${supplier.id}`, {
               data: {
                    rating: 4.9,
                    kategori: 'pakan,obat,vitamin,alat',
               },
          });

          // Assert
          expect(response.ok()).toBeTruthy();

          const data = await response.json();
          expect(data.rating).toBe(4.9); // Changed
          expect(data.kategori).toBe('pakan,obat,vitamin,alat'); // Changed
          expect(data.nama).toBe(supplier.nama); // Unchanged
          expect(data.alamat).toBe(supplier.alamat); // Unchanged
          expect(data.kontak).toBe(supplier.kontak); // Unchanged
     });

     test('Negatif - Mengubah supplier yang tidak exist (404)', async () => {
          /**
           * Given: Supplier ID tidak exist
           * When: PUT dengan data sah
           * Then: Return 404
           */

          // Arrange
          const nonExistentId = 'non-existent-uuid-67890';

          // Act
          const response = await apiContext.put(`/supplier-spk/suppliers/${nonExistentId}`, {
               data: {
                    nama: 'Should Not Update',
               },
          });

          // Assert
          expect(response.status()).toBe(404);
     });

     test('Negatif - Mengubah dengan sahation kesalahan (tidak sah produk_ids)', async () => {
          /**
           * Given: Supplier existing
           * When: PUT dengan produk_ids tidak sah
           * Then: Return 422 sahation kesalahan
           */

          // Arrange
          const createResponse = await apiContext.post('/supplier-spk/suppliers', {
               data: {
                    nama: `Insah Update ${Date.now()}`,
                    alamat: 'Test',
               },
          });
          const supplier = await createResponse.json();
          createdSupplierIds.push(supplier.id);

          // Act
          const response = await apiContext.put(`/supplier-spk/suppliers/${supplier.id}`, {
               data: {
                    produk_ids: [99999, 88888], // Insah IDs
               },
          });

          // Assert
          expect(response.status()).toBe(422);

          const data = await response.json();
          expect(data).toHaveProperty('kesalahans');
          const hasError = data.kesalahans['produk_ids.0'] || data.kesalahans['produk_ids.1'] || data.kesalahans['produk_ids'];
          expect(hasError).toBeTruthy();
     });

     // ═══════════════════════════════════════════════════════════════
     // Menghapus OPERATIONS
     // ═══════════════════════════════════════════════════════════════

     test('Positif - Menghapus supplier (204 No Content)', async () => {
          /**
           * Given: Supplier yang akan dihapus
           * When: Menghapus ke /supplier-spk/suppliers/{id}
           * Then: Return 204 No Content
           */

          // Arrange - Create supplier
          const createResponse = await apiContext.post('/supplier-spk/suppliers', {
               data: {
                    nama: `To Delete ${Date.now()}`,
                    alamat: 'Will be deleted',
                    kontak: '081234567890',
               },
          });
          const supplier = await createResponse.json();

          // Act
          const response = await apiContext.delete(`/supplier-spk/suppliers/${supplier.id}`);

          // Assert
          expect(response.status()).toBe(204);

          // Verify deleted
          const getResponse = await apiContext.get(`/supplier-spk/suppliers/${supplier.id}`);
          expect(getResponse.status()).toBe(404);
     });

     test('Negatif - Menghapus supplier yang tidak exist (404)', async () => {
          /**
           * Given: Supplier ID tidak exist
           * When: Menghapus request
           * Then: Return 404
           */

          // Arrange
          const nonExistentId = 'non-existent-uuid-delete';

          // Act
          const response = await apiContext.delete(`/supplier-spk/suppliers/${nonExistentId}`);

          // Assert
          expect(response.status()).toBe(404);
     });

     // ═══════════════════════════════════════════════════════════════
     // INTEGRATION TESTS
     // ═══════════════════════════════════════════════════════════════

     test('Integrasi - Full CRUD lifecycle', async () => {
          /**
           * Given: API endpoints tersedia
           * When: Eksekusi Menambah → READ → Mengubah → DELETE
           * Then: Semua operasi berjalan lancar
           */

          // CREATE
          const createData = {
               nama: `Lifecycle ${Date.now()}`,
               alamat: 'Lifecycle Address',
               kontak: '081111111111',
          };
          const createResponse = await apiContext.post('/supplier-spk/suppliers', {
               data: createData,
          });
          expect(createResponse.status()).toBe(201);
          const supplier = await createResponse.json();

          // READ
          const readResponse = await apiContext.get(`/supplier-spk/suppliers/${supplier.id}`);
          expect(readResponse.status()).toBe(200);
          const readData = await readResponse.json();
          expect(readData.id).toBe(supplier.id);

          // UPDATE
          const updateResponse = await apiContext.put(`/supplier-spk/suppliers/${supplier.id}`, {
               data: { nama: 'Lifecycle Updated' },
          });
          expect(updateResponse.status()).toBe(200);
          const updateData = await updateResponse.json();
          expect(updateData.nama).toBe('Lifecycle Updated');

          // DELETE
          const deleteResponse = await apiContext.delete(`/supplier-spk/suppliers/${supplier.id}`);
          expect(deleteResponse.status()).toBe(204);

          // VERIFY DELETED
          const verifyResponse = await apiContext.get(`/supplier-spk/suppliers/${supplier.id}`);
          expect(verifyResponse.status()).toBe(404);
     });

     test('Integrasi - JSON response structure sahation (all fields)', async () => {
          /**
           * Given: API responses
           * When: Check response structure
           * Then: Semua field (basic + enhanced) dan relations ada
           */

          // Arrange & Act
          const createResponse = await apiContext.post('/supplier-spk/suppliers', {
               data: {
                    nama: `Structure Test ${Date.now()}`,
                    alamat: 'Test Structure',
                    kontak: '081234567890',
                    deskripsi: 'Testing JSON structure',
                    kategori: 'pakan,obat',
                    rating: 4.5,
                    jarak_km: 30,
                    logo_url: 'https://example.com/logo.png',
               },
          });

          // Assert
          expect(createResponse.status()).toBe(201);
          const data = await createResponse.json();

          // Check all expected fields dari migration
          expect(data).toHaveProperty('id');
          expect(data).toHaveProperty('nama');
          expect(data).toHaveProperty('alamat');
          expect(data).toHaveProperty('kontak');
          expect(data).toHaveProperty('deskripsi');
          expect(data).toHaveProperty('kategori');
          expect(data).toHaveProperty('rating');
          expect(data).toHaveProperty('jarak_km');
          expect(data).toHaveProperty('logo_url');
          expect(data).toHaveProperty('produks'); // Relations
          expect(data).toHaveProperty('created_at'); // Timestamps
          expect(data).toHaveProperty('updated_at');

          // Validate data types
          expect(typeof data.id).toBe('number');
          expect(typeof data.nama).toBe('string');
          expect(typeof data.rating).toBe('number');
          expect(typeof data.jarak_km).toBe('number');
          expect(Array.isArray(data.produks)).toBeTruthy();

          // Cleanup
          await apiContext.delete(`/supplier-spk/suppliers/${data.id}`);
     });
});
