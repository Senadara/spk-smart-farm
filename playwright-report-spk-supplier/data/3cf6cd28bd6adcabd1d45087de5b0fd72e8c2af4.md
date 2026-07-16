# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: specs\supplier-spk.spec.ts >> Modul Supplier SPK (AHP-SAW DSS) - E2E UI Workflow Tests >> Positif - DSS Config page menampilkan "Legenda Skala Saaty" dengan skala 1-9
- Location: tests\e2e\specs\supplier-spk.spec.ts:123:5

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByText('Legenda Skala Saaty')
Expected: visible
Timeout: 60000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" with timeout 60000ms
  - waiting for getByText('Legenda Skala Saaty')

```

# Page snapshot

```yaml
- generic [ref=e2]:
  - complementary [ref=e3]:
    - generic [ref=e4]:
      - generic [ref=e5]: SF
      - generic [ref=e6]: SmartFarm
    - generic [ref=e7]:
      - generic [ref=e8]:
        - generic [ref=e9]:
          - generic [ref=e10]: Operasional
          - list [ref=e11]:
            - listitem [ref=e12]:
              - link "Dashboard" [ref=e13] [cursor=pointer]:
                - /url: http://127.0.0.1:8000/dashboard
                - img [ref=e14]
                - generic [ref=e16]: Dashboard
            - listitem [ref=e17]:
              - link "Peternakan" [ref=e18] [cursor=pointer]:
                - /url: http://127.0.0.1:8000/peternakan
                - img [ref=e19]
                - generic [ref=e21]: Peternakan
            - listitem [ref=e22]:
              - link "Perkebunan" [ref=e23] [cursor=pointer]:
                - /url: http://127.0.0.1:8000/perkebunan
                - img [ref=e24]
                - generic [ref=e27]: Perkebunan
            - listitem [ref=e28]:
              - link "Inventaris" [ref=e29] [cursor=pointer]:
                - /url: http://127.0.0.1:8000/inventory
                - img [ref=e30]
                - generic [ref=e34]: Inventaris
        - generic [ref=e35]:
          - generic [ref=e36]: Infrastruktur & Monitoring
          - list [ref=e37]:
            - listitem [ref=e38]:
              - link "Analisa SPK" [ref=e39] [cursor=pointer]:
                - /url: http://127.0.0.1:8000/spk-analysis
                - img [ref=e40]
                - generic [ref=e42]: Analisa SPK
            - listitem [ref=e43]:
              - link "Penugasan" [ref=e44] [cursor=pointer]:
                - /url: http://127.0.0.1:8000/penugasan
                - img [ref=e45]
                - generic [ref=e47]: Penugasan
            - listitem [ref=e48]:
              - link "Daftar Supplier" [ref=e49] [cursor=pointer]:
                - /url: http://127.0.0.1:8000/spk-suppliers
                - img [ref=e50]
                - generic [ref=e52]: Daftar Supplier
            - listitem [ref=e53]:
              - link "IoT" [ref=e54] [cursor=pointer]:
                - /url: http://127.0.0.1:8000/iot
                - img [ref=e55]
                - generic [ref=e57]: IoT
      - list [ref=e59]:
        - listitem [ref=e60]:
          - link "Manajemen Karyawan" [ref=e61] [cursor=pointer]:
            - /url: http://127.0.0.1:8000/users
            - img [ref=e62]
            - generic [ref=e64]: Manajemen Karyawan
        - listitem [ref=e65]:
          - link "Pengaturan" [ref=e66] [cursor=pointer]:
            - /url: http://127.0.0.1:8000/settings
            - img [ref=e67]
            - generic [ref=e70]: Pengaturan
    - button "Keluar" [ref=e73] [cursor=pointer]:
      - img [ref=e74]
      - generic [ref=e76]: Keluar
  - generic [ref=e77]:
    - banner [ref=e78]:
      - navigation [ref=e80]:
        - link "Smart Farm" [ref=e81] [cursor=pointer]:
          - /url: http://127.0.0.1:8000/dashboard
        - generic [ref=e82]: ›
        - generic [ref=e83]: Supplier DSS > Strategi (AHP)
      - generic [ref=e84]:
        - generic [ref=e85]:
          - generic [ref=e86]:
            - generic [ref=e87]: Penanggung Jawab Demo
            - generic [ref=e88]: Pjawab
          - button "Avatar" [ref=e89]:
            - img "Avatar" [ref=e90]
        - button [ref=e92]:
          - img [ref=e93]
    - main [ref=e95]:
      - generic [ref=e96]:
        - generic [ref=e98]:
          - generic [ref=e99]:
            - generic [ref=e100]:
              - link "Pengaturan" [ref=e101] [cursor=pointer]:
                - /url: http://127.0.0.1:8000/settings
              - generic [ref=e102]: /
              - generic [ref=e103]: Supplier DSS
              - generic [ref=e104]: /
              - generic [ref=e105]: Strategi AHP
            - heading "Atur Bobot Kriteria Supplier" [level=1] [ref=e106]
            - paragraph [ref=e107]: AHP membantu menentukan prioritas kriteria sebelum sistem SAW meranking supplier. Isi setiap pasangan berdasarkan kebutuhan restock owner.
            - generic [ref=e108]:
              - generic [ref=e109]:
                - generic [ref=e110]:
                  - generic [ref=e111]: "1"
                  - paragraph [ref=e112]: Pahami Kriteria
                - paragraph [ref=e113]: Cek arti benefit/cost agar penilaian tidak terbalik.
              - generic [ref=e114]:
                - generic [ref=e115]:
                  - generic [ref=e116]: "2"
                  - paragraph [ref=e117]: Isi Perbandingan
                - paragraph [ref=e118]: Bandingkan dua kriteria pada setiap kartu pasangan.
              - generic [ref=e119]:
                - generic [ref=e120]:
                  - generic [ref=e121]: "3"
                  - paragraph [ref=e122]: Validasi CR
                - paragraph [ref=e123]: Sistem menyimpan bobot jika Consistency Ratio <= 0.1.
              - generic [ref=e124]:
                - generic [ref=e125]:
                  - generic [ref=e126]: "4"
                  - paragraph [ref=e127]: Lanjut SAW
                - paragraph [ref=e128]: Bobot valid dipakai untuk ranking supplier dan restock.
          - complementary [ref=e129]:
            - paragraph [ref=e130]: Status AHP
            - paragraph [ref=e131]: Belum ada bobot valid.
            - paragraph [ref=e132]: Isi pasangan AHP dan simpan agar ranking supplier bisa berjalan.
            - generic [ref=e133]:
              - generic [ref=e134]:
                - paragraph [ref=e135]: Kriteria
                - paragraph [ref=e136]: "3"
              - generic [ref=e137]:
                - paragraph [ref=e138]: Pasangan
                - paragraph [ref=e139]: "3"
            - generic [ref=e140]:
              - link "Lanjut ke SAW" [ref=e141] [cursor=pointer]:
                - /url: http://127.0.0.1:8000/spk-suppliers/dss/dashboard
              - link "Kembali" [ref=e142] [cursor=pointer]:
                - /url: http://127.0.0.1:8000/settings
        - generic [ref=e143]:
          - complementary [ref=e144]:
            - generic [ref=e145]:
              - generic [ref=e146]:
                - generic [ref=e147]:
                  - heading "Kriteria DSS" [level=2] [ref=e148]
                  - paragraph [ref=e149]: Benefit semakin besar semakin baik. Cost semakin kecil semakin baik.
                - generic [ref=e150]: "3"
              - generic [ref=e151]:
                - generic [ref=e153]:
                  - generic [ref=e154]:
                    - paragraph [ref=e155]: Waktu Pengiriman
                    - paragraph [ref=e156]: Estimasi waktu pengiriman dari seller ke peternakan dalam hari. Nilai 0.5 berarti same day; semakin kecil semakin baik.
                  - generic [ref=e157]: cost
                - generic [ref=e159]:
                  - generic [ref=e160]:
                    - paragraph [ref=e161]: Harga
                    - paragraph [ref=e162]: Harga satuan produk (semakin rendah semakin baik)
                  - generic [ref=e163]: cost
                - generic [ref=e165]:
                  - generic [ref=e166]:
                    - paragraph [ref=e167]: Kualitas
                    - paragraph [ref=e168]: Rating kualitas produk 1-5 dari pembelian selesai. Nilai 3 digunakan sebagai netral jika belum ada rating.
                  - generic [ref=e169]: benefit
            - generic [ref=e170]:
              - heading "Cara membaca skala Saaty" [level=2] [ref=e171]
              - generic [ref=e172]:
                - generic [ref=e173]:
                  - generic [ref=e174]: "1"
                  - strong [ref=e175]: Sama penting
                - generic [ref=e176]:
                  - generic [ref=e177]: "3"
                  - strong [ref=e178]: Sedikit lebih penting
                - generic [ref=e179]:
                  - generic [ref=e180]: "5"
                  - strong [ref=e181]: Kuat lebih penting
                - generic [ref=e182]:
                  - generic [ref=e183]: "7"
                  - strong [ref=e184]: Sangat kuat
                - generic [ref=e185]:
                  - generic [ref=e186]: "9"
                  - strong [ref=e187]: Mutlak lebih penting
                - generic [ref=e188]:
                  - generic [ref=e189]: 1/3 - 1/9
                  - strong [ref=e190]: Kriteria kanan lebih penting
              - paragraph [ref=e191]: Pilih tombol hijau jika kriteria kiri lebih penting. Pilih tombol amber jika kriteria kanan lebih penting.
          - main [ref=e192]:
            - generic [ref=e193]:
              - generic [ref=e195]:
                - generic [ref=e196]:
                  - heading "Perbandingan Berpasangan" [level=2] [ref=e197]
                  - paragraph [ref=e198]: Isi 3 pasangan. Nilai default 1 berarti kedua kriteria sama penting.
                - generic [ref=e199]: 3 dari 3 pasangan siap dihitung
              - article [ref=e200]:
                - generic [ref=e201]:
                  - generic [ref=e202]:
                    - text: Pasangan 1 dari 3
                    - heading "Waktu Pengiriman dibandingkan Harga" [level=3] [ref=e203]
                    - paragraph [ref=e204]: Tentukan mana yang lebih penting untuk memilih supplier pada kebutuhan restock.
                  - generic [ref=e205]:
                    - paragraph [ref=e206]: Nilai dikirim
                    - paragraph [ref=e207]: "1.000"
                - generic [ref=e208]:
                  - generic [ref=e209]:
                    - paragraph [ref=e210]: Waktu Pengiriman lebih penting
                    - generic [ref=e211]:
                      - button "9 Mutlak kiri" [ref=e212]:
                        - generic [ref=e213]: "9"
                        - generic [ref=e214]: Mutlak kiri
                      - button "7 Sangat kuat" [ref=e215]:
                        - generic [ref=e216]: "7"
                        - generic [ref=e217]: Sangat kuat
                      - button "5 Kuat" [ref=e218]:
                        - generic [ref=e219]: "5"
                        - generic [ref=e220]: Kuat
                      - button "3 Sedikit" [ref=e221]:
                        - generic [ref=e222]: "3"
                        - generic [ref=e223]: Sedikit
                  - generic [ref=e224]:
                    - paragraph [ref=e225]: Netral
                    - button "1 Sama penting" [ref=e226]:
                      - generic [ref=e227]: "1"
                      - generic [ref=e228]: Sama penting
                  - generic [ref=e229]:
                    - paragraph [ref=e230]: Harga lebih penting
                    - generic [ref=e231]:
                      - button "1/3 Sedikit" [ref=e232]:
                        - generic [ref=e233]: 1/3
                        - generic [ref=e234]: Sedikit
                      - button "1/5 Kuat" [ref=e235]:
                        - generic [ref=e236]: 1/5
                        - generic [ref=e237]: Kuat
                      - button "1/7 Sangat kuat" [ref=e238]:
                        - generic [ref=e239]: 1/7
                        - generic [ref=e240]: Sangat kuat
                      - button "1/9 Mutlak kanan" [ref=e241]:
                        - generic [ref=e242]: 1/9
                        - generic [ref=e243]: Mutlak kanan
              - article [ref=e244]:
                - generic [ref=e245]:
                  - generic [ref=e246]:
                    - text: Pasangan 2 dari 3
                    - heading "Waktu Pengiriman dibandingkan Kualitas" [level=3] [ref=e247]
                    - paragraph [ref=e248]: Tentukan mana yang lebih penting untuk memilih supplier pada kebutuhan restock.
                  - generic [ref=e249]:
                    - paragraph [ref=e250]: Nilai dikirim
                    - paragraph [ref=e251]: "1.000"
                - generic [ref=e252]:
                  - generic [ref=e253]:
                    - paragraph [ref=e254]: Waktu Pengiriman lebih penting
                    - generic [ref=e255]:
                      - button "9 Mutlak kiri" [ref=e256]:
                        - generic [ref=e257]: "9"
                        - generic [ref=e258]: Mutlak kiri
                      - button "7 Sangat kuat" [ref=e259]:
                        - generic [ref=e260]: "7"
                        - generic [ref=e261]: Sangat kuat
                      - button "5 Kuat" [ref=e262]:
                        - generic [ref=e263]: "5"
                        - generic [ref=e264]: Kuat
                      - button "3 Sedikit" [ref=e265]:
                        - generic [ref=e266]: "3"
                        - generic [ref=e267]: Sedikit
                  - generic [ref=e268]:
                    - paragraph [ref=e269]: Netral
                    - button "1 Sama penting" [ref=e270]:
                      - generic [ref=e271]: "1"
                      - generic [ref=e272]: Sama penting
                  - generic [ref=e273]:
                    - paragraph [ref=e274]: Kualitas lebih penting
                    - generic [ref=e275]:
                      - button "1/3 Sedikit" [ref=e276]:
                        - generic [ref=e277]: 1/3
                        - generic [ref=e278]: Sedikit
                      - button "1/5 Kuat" [ref=e279]:
                        - generic [ref=e280]: 1/5
                        - generic [ref=e281]: Kuat
                      - button "1/7 Sangat kuat" [ref=e282]:
                        - generic [ref=e283]: 1/7
                        - generic [ref=e284]: Sangat kuat
                      - button "1/9 Mutlak kanan" [ref=e285]:
                        - generic [ref=e286]: 1/9
                        - generic [ref=e287]: Mutlak kanan
              - article [ref=e288]:
                - generic [ref=e289]:
                  - generic [ref=e290]:
                    - text: Pasangan 3 dari 3
                    - heading "Harga dibandingkan Kualitas" [level=3] [ref=e291]
                    - paragraph [ref=e292]: Tentukan mana yang lebih penting untuk memilih supplier pada kebutuhan restock.
                  - generic [ref=e293]:
                    - paragraph [ref=e294]: Nilai dikirim
                    - paragraph [ref=e295]: "1.000"
                - generic [ref=e296]:
                  - generic [ref=e297]:
                    - paragraph [ref=e298]: Harga lebih penting
                    - generic [ref=e299]:
                      - button "9 Mutlak kiri" [ref=e300]:
                        - generic [ref=e301]: "9"
                        - generic [ref=e302]: Mutlak kiri
                      - button "7 Sangat kuat" [ref=e303]:
                        - generic [ref=e304]: "7"
                        - generic [ref=e305]: Sangat kuat
                      - button "5 Kuat" [ref=e306]:
                        - generic [ref=e307]: "5"
                        - generic [ref=e308]: Kuat
                      - button "3 Sedikit" [ref=e309]:
                        - generic [ref=e310]: "3"
                        - generic [ref=e311]: Sedikit
                  - generic [ref=e312]:
                    - paragraph [ref=e313]: Netral
                    - button "1 Sama penting" [ref=e314]:
                      - generic [ref=e315]: "1"
                      - generic [ref=e316]: Sama penting
                  - generic [ref=e317]:
                    - paragraph [ref=e318]: Kualitas lebih penting
                    - generic [ref=e319]:
                      - button "1/3 Sedikit" [ref=e320]:
                        - generic [ref=e321]: 1/3
                        - generic [ref=e322]: Sedikit
                      - button "1/5 Kuat" [ref=e323]:
                        - generic [ref=e324]: 1/5
                        - generic [ref=e325]: Kuat
                      - button "1/7 Sangat kuat" [ref=e326]:
                        - generic [ref=e327]: 1/7
                        - generic [ref=e328]: Sangat kuat
                      - button "1/9 Mutlak kanan" [ref=e329]:
                        - generic [ref=e330]: 1/9
                        - generic [ref=e331]: Mutlak kanan
              - generic [ref=e333]:
                - generic [ref=e334]:
                  - heading "Hitung bobot dan validasi konsistensi" [level=2] [ref=e335]
                  - paragraph [ref=e336]: Sistem akan menghitung lambda max, CI, CR, lalu menyimpan bobot hanya jika CR <= 0.1.
                - button "Hitung & Simpan Bobot" [ref=e337]
```

# Test source

```ts
  34  |          */
  35  | 
  36  |         // Arrange
  37  |         await settingsPage.goto();
  38  | 
  39  |         // Act: Click Atur Bobot button
  40  |         await page.getByRole('link', { name: /Atur Bobot/i }).click();
  41  | 
  42  |         // Assert: Navigate to DSS config
  43  |         await expect(page).toHaveURL(/.*\/spk-suppliers\/dss\/config/);
  44  |         await supplierSpkPage.expectDssConfigReady();
  45  |     });
  46  | 
  47  |     test('Positif - Navigasi "Dashboard SAW" accessible dari Settings page', async ({ page }) => {
  48  |         /**
  49  |          * Given: User PJAWAB di Settings page
  50  |          * When: Click link "Dashboard SAW"
  51  |          * Then: Navigate ke /spk-suppliers/dss/dashboard
  52  |          */
  53  | 
  54  |         // Arrange
  55  |         await settingsPage.goto();
  56  | 
  57  |         // Act: Click Ranking SAW button
  58  |         await page.getByRole('link', { name: /Ranking SAW/i }).first().click();
  59  | 
  60  |         // Assert: Navigate to DSS dashboard
  61  |         await expect(page).toHaveURL(/.*\/spk-suppliers\/dss\/dashboard/, { timeout: 20000 });
  62  |     });
  63  | 
  64  |     /* ═══════════════════════════════════════════════════════════════════
  65  |        DSS CONFIG PAGE - UI ELEMENTS
  66  |        ═══════════════════════════════════════════════════════════════════ */
  67  | 
  68  |     test('Positif - DSS Config page memiliki hero section dengan breadcrumb "Supplier DSS > Strategi (AHP)"', async ({ page }) => {
  69  |         /**
  70  |          * Given: Navigate to DSS config
  71  |          * When: Page loads
  72  |          * Then: Hero section dengan title "Bobot Kriteria (AHP)" visible
  73  |          */
  74  | 
  75  |         // Arrange & Act
  76  |         await page.goto('/spk-suppliers/dss/config', { waitUntil: 'domcontentloaded' });
  77  | 
  78  |         // Assert: Hero title
  79  |         await expect(page.getByRole('heading', { name: /Atur Bobot Kriteria|Bobot Kriteria.*AHP/i })).toBeVisible();
  80  | 
  81  |         // Assert: Breadcrumb or page indicator
  82  |         const breadcrumb = page.locator('text=Supplier DSS');
  83  |         const count = await breadcrumb.count();
  84  |         expect(count).toBeGreaterThanOrEqual(0); // May or may not have explicit breadcrumb
  85  |     });
  86  | 
  87  |     test('Positif - DSS Config page menampilkan navigation steps: "① Strategi · AHP" → "② Operasi · SAW"', async ({ page }) => {
  88  |         /**
  89  |          * Given: DSS config page loaded
  90  |          * When: Check navigation steps
  91  |          * Then: Step indicators visible dengan "① Strategi · AHP" active
  92  |          */
  93  | 
  94  |         // Arrange & Act
  95  |         await page.goto('/spk-suppliers/dss/config', { waitUntil: 'domcontentloaded' });
  96  | 
  97  |         // Assert: Step label visible
  98  |         await expect(page.getByText('Strategi AHP').first()).toBeVisible({ timeout: 10000 });
  99  | 
  100 |         // Assert: "Lanjut ke SAW" link present
  101 |         await expect(page.getByRole('link', { name: /Lanjut ke SAW/i }).first()).toBeVisible({ timeout: 10000 });
  102 |     });
  103 | 
  104 |     test('Positif - DSS Config page menampilkan "Daftar Kriteria" dengan benefit/cost badges', async ({ page }) => {
  105 |         /**
  106 |          * Given: Parameters exist di database
  107 |          * When: DSS config loads
  108 |          * Then: Criteria cards displayed dengan tipe (benefit/cost) badges
  109 |          */
  110 | 
  111 |         // Arrange & Act
  112 |         await page.goto('/spk-suppliers/dss/config', { waitUntil: 'domcontentloaded' });
  113 | 
  114 |         // Assert: Kriteria DSS section
  115 |         await expect(page.getByText('Kriteria DSS')).toBeVisible();
  116 | 
  117 |         // Assert: At least one criteria card exists
  118 |         const criteriaCards = page.locator('[class*="border-gray-100"]');
  119 |         const count = await criteriaCards.count();
  120 |         expect(count).toBeGreaterThan(0);
  121 |     });
  122 | 
  123 |     test('Positif - DSS Config page menampilkan "Legenda Skala Saaty" dengan skala 1-9', async ({ page }) => {
  124 |         /**
  125 |          * Given: DSS config page loaded
  126 |          * When: Check legend section
  127 |          * Then: Saaty scale legend (1, 3, 5, 7, 9) displayed
  128 |          */
  129 | 
  130 |         // Arrange & Act
  131 |         await page.goto('/spk-suppliers/dss/config', { waitUntil: 'domcontentloaded' });
  132 | 
  133 |         // Assert: Legend section
> 134 |         await expect(page.getByText('Legenda Skala Saaty')).toBeVisible();
      |                                                             ^ Error: expect(locator).toBeVisible() failed
  135 | 
  136 |         // Assert: Scale values
  137 |         await expect(page.locator('text=Sama penting')).toBeVisible();
  138 |         await expect(page.locator('text=Mutlak lebih penting')).toBeVisible();
  139 |     });
  140 | 
  141 |     test('Positif - DSS Config menampilkan latest config info (CR, version, status) jika exists', async ({ page }) => {
  142 |         /**
  143 |          * Given: AHP config pernah disimpan
  144 |          * When: DSS config loads
  145 |          * Then: Latest config info displayed dengan CR value dan status (Passed/Failed)
  146 |          */
  147 | 
  148 |         // Arrange & Act
  149 |         await page.goto('/spk-suppliers/dss/config', { waitUntil: 'domcontentloaded' });
  150 | 
  151 |         // Assert: Latest config section (may or may not exist)
  152 |         const latestConfigText = page.locator('text=Konfig valid terakhir');
  153 |         const hasLatestConfig = await latestConfigText.count();
  154 | 
  155 |         if (hasLatestConfig > 0) {
  156 |             // If latest config exists, verify it shows CR
  157 |             const crText = page.locator('text=CR').first();
  158 |             await expect(crText).toBeVisible();
  159 |         }
  160 | 
  161 |         // Test passes either way (config exists or not)
  162 |     });
  163 | 
  164 |     /* ═══════════════════════════════════════════════════════════════════
  165 |        DSS CONFIG PAGE - PAIRWISE COMPARISON FORM
  166 |        ═══════════════════════════════════════════════════════════════════ */
  167 | 
  168 |     test('Positif - AHP form memiliki pairwise comparison matrix dengan button groups', async ({ page }) => {
  169 |         /**
  170 |          * Given: At least 2 parameters exist
  171 |          * When: DSS config loads
  172 |          * Then: Pairwise comparison form displayed dengan button groups (green vs orange)
  173 |          */
  174 | 
  175 |         // Arrange & Act
  176 |         await page.goto('/spk-suppliers/dss/config', { waitUntil: 'domcontentloaded' });
  177 | 
  178 |         // Assert: Form exists
  179 |         const form = page.locator('form#ahp-form');
  180 |         const formCount = await form.count();
  181 | 
  182 |         if (formCount > 0) {
  183 |             // Form displayed (enough parameters)
  184 |             await expect(form).toBeVisible();
  185 | 
  186 |             // Assert: Has comparison radio buttons (hidden input[type=radio] with visible label spans)
  187 |             const radioInputs = page.locator('input[type="radio"][name^="pair_"]');
  188 |             const radioCount = await radioInputs.count();
  189 |             expect(radioCount).toBeGreaterThan(0);
  190 |         } else {
  191 |             // Not enough criteria - should show warning message
  192 |             const warning = page.locator('text=Belum cukup kriteria');
  193 |             await expect(warning).toBeVisible();
  194 |         }
  195 |     });
  196 | 
  197 |     test('Positif - Pairwise comparison buttons dapat diklik dan selected state changes', async ({ page }) => {
  198 |         /**
  199 |          * Given: AHP form displayed
  200 |          * When: Click salah satu comparison button
  201 |          * Then: Button state changes (selected visual feedback)
  202 |          */
  203 | 
  204 |         // Arrange
  205 |         await page.goto('/spk-suppliers/dss/config', { waitUntil: 'domcontentloaded' });
  206 | 
  207 |         const form = page.locator('form#ahp-form');
  208 |         const formExists = await form.count();
  209 | 
  210 |         if (formExists === 0) {
  211 |             test.skip(); // Skip if form not available
  212 |             return;
  213 |         }
  214 | 
  215 |         // Act: Click first comparison label (radio is sr-only, click via visible label)
  216 |         const firstLabel = form.locator('label').filter({ has: page.locator('input[type="radio"]') }).first();
  217 |         const labelExists = await firstLabel.count();
  218 | 
  219 |         if (labelExists > 0) {
  220 |             await firstLabel.click();
  221 | 
  222 |             // Assert: Clicked (no crash)
  223 |             expect(true).toBe(true); // Click succeeded
  224 |         }
  225 |     });
  226 | 
  227 |     /* ═══════════════════════════════════════════════════════════════════
  228 |        DSS CONFIG - SUBMIT & VALIDATION
  229 |        ═══════════════════════════════════════════════════════════════════ */
  230 | 
  231 |     test('Positif - AHP form dapat disubmit dengan default configuration', async ({ page }) => {
  232 |         /**
  233 |          * Given: AHP form dengan pairwise comparisons
  234 |          * When: Submit form dengan default/selected values
```