# 🐛 BUG REPORT - E2E TESTING VALIDATION

**Tanggal**: 2026-06-01  
**Tester**: Senior QA Engineer  
**Status**: ✅ **BUG CONFIRMED**

---

## 🔴 BUG #1: Form "Tambah Variabel" Tidak Ter-Submit

### **Severity**: CRITICAL  
### **Priority**: HIGH  
### **Status**: OPEN

---

### **📋 Summary**

Modal form "Tambah Variabel" di halaman Fuzzy Config (`/settings/fuzzy`) tidak dapat di-submit. Tombol "Simpan" diklik tetapi form tidak terkirim ke backend dan modal tetap terbuka.

---

### **🔍 Steps to Reproduce**

1. Login sebagai user dengan role `pjawab` (email: `pjawab@email.com`)
2. Navigate ke `/settings`
3. Klik card "Fuzzy Mamdani"
4. Klik tab "Variabel & MF"
5. Klik tombol "Tambah Variabel"
6. Isi form dengan data valid:
   - Nama: `test_variable` (lowercase + underscore)
   - Group: `Lingkungan`
   - Type: `Input`
   - Unit: `ppm`
   - Deskripsi: `Test variable`
7. Klik tombol "Simpan"

---

### **✅ Expected Behavior**

1. Modal form hilang/tertutup
2. Success message muncul: "Variabel berhasil ditambahkan."
3. Variabel baru muncul di list variabel
4. Data tersimpan di database

---

### **❌ Actual Behavior**

1. Modal tetap terbuka ❌
2. Success message TIDAK muncul ❌
3. Variabel TIDAK muncul di list ❌
4. Data TIDAK tersimpan ❌
5. Tidak ada error message yang ditampilkan ❌

---

### **📸 Evidence**

**Screenshot**: `test-results/specs-settings-fuzzy-Modul-03c2f-erta-Membership-Logic-Fuzzy-chromium/test-failed-1.png`

**Video**: `test-results/specs-settings-fuzzy-Modul-03c2f-erta-Membership-Logic-Fuzzy-chromium/video.webm`

**Test Log**:
```
Error: expect(locator).toBeHidden() failed
Locator: locator('h3').filter({ hasText: 'Tambah Variabel' })
Expected: hidden
Received: visible
Timeout: 15000ms
```

---

### **🎯 Root Cause Analysis**

#### **Possible Causes**:

1. **JavaScript Event Handler Issue**:
   - Event listener untuk tombol "Simpan" tidak terpasang dengan benar
   - Alpine.js/Livewire event tidak ter-trigger
   - `@click` handler tidak berfungsi

2. **Form Validation Silent Failure**:
   - Validasi frontend gagal tanpa menampilkan error
   - CSRF token tidak valid atau missing
   - Required fields tidak ter-detect

3. **Network Request Not Sent**:
   - HTTP POST request tidak terkirim ke backend
   - AJAX/Fetch call gagal tanpa error handling
   - CORS atau network issue

4. **Modal State Management Issue**:
   - Alpine.js state tidak ter-update setelah submit
   - Modal `x-show` condition tidak berubah
   - Event propagation ter-block

---

### **🔧 Recommended Fixes**

#### **Fix 1: Check Event Handler (Priority: HIGH)**

**File**: `resources/views/settings/fuzzy-partials/modals.blade.php`

```html
<!-- BEFORE (Possibly broken) -->
<button type="submit">Simpan</button>

<!-- AFTER (Fixed) -->
<button @click="submitVariable()" type="button">Simpan</button>
```

#### **Fix 2: Add Error Handling (Priority: HIGH)**

**File**: `resources/views/settings/fuzzy.blade.php`

```javascript
async submitVariable() {
    try {
        console.log('[DEBUG] Submitting variable...', this.formData);
        
        const response = await fetch('{{ route("settings.fuzzy.variables.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify(this.formData)
        });
        
        console.log('[DEBUG] Response status:', response.status);
        
        if (!response.ok) {
            const errorData = await response.json();
            console.error('[ERROR] Submit failed:', errorData);
            alert('Error: ' + (errorData.message || 'Gagal menyimpan variabel'));
            return;
        }
        
        const data = await response.json();
        console.log('[DEBUG] Success:', data);
        
        // Close modal
        this.modal = null;
        
        // Reset form
        this.formData = {};
        
        // Show success message
        alert('Variabel berhasil ditambahkan!');
        
        // Reload page or update list
        window.location.reload();
        
    } catch (error) {
        console.error('[ERROR] Exception:', error);
        alert('Error: ' + error.message);
    }
}
```

#### **Fix 3: Verify CSRF Token (Priority: MEDIUM)**

**File**: `resources/views/layouts/app.blade.php`

```html
<head>
    <!-- Ensure CSRF token meta tag exists -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
```

#### **Fix 4: Check Form Structure (Priority: MEDIUM)**

**File**: `resources/views/settings/fuzzy-partials/modals.blade.php`

```html
<!-- Ensure form has proper structure -->
<form @submit.prevent="submitVariable()">
    @csrf
    
    <!-- Form fields -->
    <input name="name" v-model="formData.name" required>
    <select name="group" v-model="formData.group" required>
        <option value="lingkungan">Lingkungan</option>
        <option value="kesehatan">Kesehatan</option>
        <option value="kausalitas">Kausalitas</option>
    </select>
    
    <!-- Submit button -->
    <button type="submit">Simpan</button>
</form>
```

---

### **🧪 Test Case**

**File**: `tests/e2e/specs/settings-fuzzy.spec.ts`  
**Test**: "Positif - Eksekusi dinamis Menulis dan Menghapus Relasi Variabel Beserta Membership Logic Fuzzy"  
**Line**: 64-120

**Test Status**: ❌ **FAILING** (Expected - Bug Confirmed)

---

### **📊 Impact Assessment**

| Area | Impact | Severity |
|------|--------|----------|
| **User Experience** | Users cannot add new fuzzy variables | 🔴 HIGH |
| **Functionality** | Core feature completely broken | 🔴 CRITICAL |
| **Data Integrity** | No data corruption (nothing saved) | 🟢 LOW |
| **Security** | No security impact | 🟢 LOW |
| **Workaround Available** | No workaround for end users | 🔴 HIGH |

---

### **✅ Verification Steps (After Fix)**

1. Apply the recommended fixes
2. Clear browser cache
3. Run E2E test: `npx playwright test tests/e2e/specs/settings-fuzzy.spec.ts --grep "Eksekusi dinamis"`
4. Manual test:
   - Login as `pjawab@email.com`
   - Navigate to `/settings/fuzzy`
   - Click "Tambah Variabel"
   - Fill form with valid data
   - Click "Simpan"
   - Verify modal closes
   - Verify success message appears
   - Verify variable appears in list
   - Verify data saved in database

---

### **📝 Additional Notes**

- This bug blocks all E2E tests for Fuzzy Config module
- Similar issue might exist in "Tambah Membership Function" form
- Recommend checking all modal forms in the application
- Add client-side validation error messages
- Add server-side error logging

---

### **👥 Assigned To**

**Developer**: [To be assigned]  
**Reviewer**: [To be assigned]  
**QA Tester**: Senior QA Engineer

---

### **🔗 Related Issues**

- None yet

---

### **📅 Timeline**

- **Reported**: 2026-06-01
- **Target Fix**: [To be determined]
- **Verified**: [Pending fix]

---

---

## 🔴 BUG #2: Search Blok Kebun Tidak Berfungsi

### **Severity**: MEDIUM  
### **Priority**: MEDIUM  
### **Status**: OPEN

---

### **📋 Summary**

Fitur pencarian (search) pada halaman Data Master tidak berfungsi dengan benar. Ketika mencari dengan string yang tidak valid/tidak ada, sistem masih menampilkan 1 baris data alih-alih 0 baris.

---

### **🔍 Steps to Reproduce**

1. Login sebagai user dengan role `pjawab`
2. Navigate ke `/data-master`
3. Gunakan fitur search dengan string acak: `"xyz_nonexistent_block_12345"`
4. Observe hasil pencarian

---

### **✅ Expected Behavior**

- Tabel menampilkan 0 baris data
- Atau menampilkan pesan "Data tidak ditemukan"

---

### **❌ Actual Behavior**

- Tabel masih menampilkan 1 baris data
- Search filter tidak bekerja dengan benar

---

### **📸 Evidence**

**Test File**: `tests/e2e/specs/data-master.spec.ts`  
**Test**: "Negatif - Pencarian dengan string acak tidak memicu fatal crash"

---

### **🎯 Root Cause Analysis**

#### **Possible Causes**:

1. **Backend Search Logic Issue**:
   - Query SQL tidak memfilter dengan benar
   - LIKE clause tidak case-sensitive atau terlalu permissive
   - Default data selalu ditampilkan

2. **Frontend Filter Issue**:
   - JavaScript filter tidak ter-apply
   - Search input tidak ter-bind ke query parameter

---

### **🔧 Recommended Fixes**

**File**: Backend controller untuk Data Master

```php
// Ensure search query filters correctly
$query = BlokKebun::query();

if ($request->has('search') && !empty($request->search)) {
    $search = $request->search;
    $query->where(function($q) use ($search) {
        $q->where('nama', 'LIKE', "%{$search}%")
          ->orWhere('kode', 'LIKE', "%{$search}%");
    });
}

$results = $query->get();

// Return empty array if no results
return response()->json([
    'data' => $results,
    'count' => $results->count()
]);
```

---

### **📊 Impact Assessment**

| Area | Impact | Severity |
|------|--------|----------|
| **User Experience** | Users cannot search effectively | 🟡 MEDIUM |
| **Functionality** | Search feature not working | 🟡 MEDIUM |
| **Data Integrity** | No impact | 🟢 LOW |
| **Security** | No security impact | 🟢 LOW |

---

## 🔴 BUG #3: Users Management Page Tidak Dapat Diakses

### **Severity**: HIGH  
### **Priority**: HIGH  
### **Status**: OPEN

---

### **📋 Summary**

Halaman Users Management (`/users-management`) tidak dapat diakses. Heading "Manajemen Petugas" tidak ditemukan di halaman.

---

### **🔍 Steps to Reproduce**

1. Login sebagai user dengan role `pjawab`
2. Navigate ke `/users-management`
3. Observe halaman yang dimuat

---

### **✅ Expected Behavior**

- Halaman Users Management termuat dengan heading "Manajemen Petugas"
- Tabel daftar user terlihat
- Tombol tambah user tersedia

---

### **❌ Actual Behavior**

- Heading "Manajemen Petugas" tidak ditemukan
- Halaman mungkin redirect atau error 404/403

---

### **📸 Evidence**

**Test File**: `tests/e2e/specs/users-management.spec.ts`

---

### **🎯 Root Cause Analysis**

#### **Possible Causes**:

1. **Route Not Defined**:
   - Route `/users-management` tidak terdaftar di `web.php`
   
2. **Permission Issue**:
   - User role `pjawab` tidak memiliki akses ke halaman ini
   - Middleware authorization memblokir akses

3. **View Not Found**:
   - Blade template tidak ada atau salah path

---

### **🔧 Recommended Fixes**

**File**: `routes/web.php`

```php
// Ensure route exists and has correct middleware
Route::middleware(['auth'])->group(function () {
    Route::get('/users-management', [UserController::class, 'index'])
        ->name('users.management');
});
```

**File**: Check user permissions in database or policy

---

### **📊 Impact Assessment**

| Area | Impact | Severity |
|------|--------|----------|
| **User Experience** | Cannot manage users | 🔴 HIGH |
| **Functionality** | Core admin feature broken | 🔴 HIGH |
| **Data Integrity** | No impact | 🟢 LOW |
| **Security** | Potential access control issue | 🟡 MEDIUM |

---

## 🔴 BUG #4: Invalid Route Settings Tidak Menampilkan 404

### **Severity**: LOW  
### **Priority**: LOW  
### **Status**: OPEN

---

### **📋 Summary**

Ketika mengakses route settings yang tidak valid (misal: `/settings/invalid-route-xyz`), aplikasi tidak menampilkan halaman 404 yang proper.

---

### **🔍 Steps to Reproduce**

1. Login sebagai user
2. Navigate ke `/settings/invalid-route-xyz`
3. Observe response

---

### **✅ Expected Behavior**

- Halaman 404 Not Found ditampilkan
- Atau redirect ke halaman settings utama dengan error message

---

### **❌ Actual Behavior**

- Behavior tidak konsisten (perlu investigasi lebih lanjut)

---

### **📊 Impact Assessment**

| Area | Impact | Severity |
|------|--------|----------|
| **User Experience** | Minor UX issue | 🟢 LOW |
| **Functionality** | Edge case | 🟢 LOW |
| **Security** | No security impact | 🟢 LOW |

---

## 🔴 BUG #5: Device Management Page Tidak Dapat Diakses

### **Severity**: HIGH  
### **Priority**: HIGH  
### **Status**: OPEN

---

### **📋 Summary**

Halaman Device Management di modul IoT tidak dapat diakses. Heading "Device Management" tidak ditemukan ketika navigate ke halaman devices.

---

### **🔍 Steps to Reproduce**

1. Login sebagai user dengan role `pjawab`
2. Navigate ke IoT module
3. Klik tab atau link ke "Devices" atau "Device Management"
4. Observe halaman yang dimuat

---

### **✅ Expected Behavior**

- Halaman Device Management termuat dengan heading "Device Management"
- Form pendaftaran IoT device terlihat
- Tabel daftar devices tersedia

---

### **❌ Actual Behavior**

- Heading "Device Management" tidak ditemukan
- Timeout setelah 12 detik
- Halaman tidak termuat dengan benar

---

### **📸 Evidence**

**Test File**: `tests/e2e/specs/iot.spec.ts`  
**Test**: "Negatif - Simpan Pendaftaran IoT tanpa Code memicu alert validasi mandatory"  
**Error**: `expect(locator).toBeVisible() failed - Locator: h1 'Device Management'`

---

### **🎯 Root Cause Analysis**

#### **Possible Causes**:

1. **Route Issue**:
   - Route untuk device management tidak terdaftar
   - URL path salah

2. **View Rendering Issue**:
   - Blade template error
   - JavaScript error mencegah rendering

3. **Permission Issue**:
   - User tidak memiliki akses ke halaman ini

---

### **📊 Impact Assessment**

| Area | Impact | Severity |
|------|--------|----------|
| **User Experience** | Cannot manage IoT devices | 🔴 HIGH |
| **Functionality** | Core IoT feature broken | 🔴 HIGH |
| **Data Integrity** | No impact | 🟢 LOW |

---

## 🔴 BUG #6: Tombol "Simpan Protokol" Tidak Dapat Diklik (IoT Module)

### **Severity**: CRITICAL  
### **Priority**: HIGH  
### **Status**: OPEN

---

### **📋 Summary**

Tombol "Simpan Protokol" di halaman IoT Protocol Configuration tidak dapat diklik. Test timeout setelah 90 detik menunggu tombol menjadi clickable.

---

### **🔍 Steps to Reproduce**

1. Login sebagai user dengan role `pjawab`
2. Navigate ke IoT module
3. Klik tab "Protocols" atau "Konfigurasi Protokol"
4. Klik tombol "Tambah Protokol"
5. Isi form dengan data valid:
   - Protocol Name: `test_protocol`
   - Description: `Test protocol description`
6. Klik tombol "Simpan Protokol"

---

### **✅ Expected Behavior**

- Tombol dapat diklik
- Form ter-submit
- Protocol baru tersimpan
- Success message muncul

---

### **❌ Actual Behavior**

- Tombol tidak dapat diklik (timeout 90 detik)
- Form tidak ter-submit
- Data tidak tersimpan

---

### **📸 Evidence**

**Test File**: `tests/e2e/specs/iot.spec.ts`  
**Test**: "Positif - Validasi E2E end-to-end penambahan perangkat IoT baru success"  
**Error**: `locator.click: Test timeout of 90000ms exceeded - waiting for button 'Simpan Protokol'`

**Also affects**: `tests/e2e/specs/iot-webhook.spec.ts`

---

### **🎯 Root Cause Analysis**

#### **Possible Causes**:

1. **Button Disabled State**:
   - Tombol ter-disable dan tidak pernah menjadi enabled
   - JavaScript validation mencegah enable

2. **Modal/Overlay Issue**:
   - Element lain menutupi tombol
   - Z-index issue
   - Modal tidak fully rendered

3. **Event Handler Issue**:
   - Click handler tidak terpasang
   - Alpine.js/Livewire state issue

---

### **🔧 Recommended Fixes**

**File**: View file untuk IoT Protocol Configuration

```html
<!-- Ensure button is not disabled by default -->
<button 
    type="submit" 
    @click="submitProtocol()"
    :disabled="isSubmitting"
    class="btn btn-primary">
    Simpan Protokol
</button>
```

**File**: JavaScript/Alpine.js component

```javascript
submitProtocol() {
    // Ensure button is enabled
    this.isSubmitting = false;
    
    // Add validation
    if (!this.protocolName || !this.description) {
        alert('Mohon lengkapi semua field');
        return;
    }
    
    this.isSubmitting = true;
    
    // Submit logic here
}
```

---

### **📊 Impact Assessment**

| Area | Impact | Severity |
|------|--------|----------|
| **User Experience** | Cannot add IoT protocols | 🔴 CRITICAL |
| **Functionality** | Core IoT feature completely broken | 🔴 CRITICAL |
| **Data Integrity** | No impact | 🟢 LOW |
| **Workaround Available** | No workaround | 🔴 HIGH |

---

## 🔴 BUG #7: Database Error di Modul Penugasan (SQLSTATE HY000)

### **Severity**: CRITICAL  
### **Priority**: CRITICAL  
### **Status**: OPEN

---

### **📋 Summary**

Modul Penugasan mengalami database error ketika mencoba membuat atau membuka task. Error message: `SQLSTATE[HY000]: General error: 1366 Incorrect in...` terlihat di UI.

---

### **🔍 Steps to Reproduce**

1. Login sebagai user dengan role `pjawab`
2. Navigate ke `/penugasan`
3. Klik tombol "Tambah Task"
4. Isi form dengan data valid
5. Submit form
6. Observe error message di UI

---

### **✅ Expected Behavior**

- Task berhasil dibuat
- Task muncul di task board
- Dapat membuka detail task

---

### **❌ Actual Behavior**

- Error message muncul: `SQLSTATE[HY000]: General error: 1366 Incorrect in...`
- Task tidak dapat dibuat atau dibuka
- UI menampilkan error di tempat judul task

---

### **📸 Evidence**

**Test File**: `tests/e2e/specs/penugasan.spec.ts`  
**Test**: "Positif - Simulasi Skrip E2E CRUD dan Proses Lifecycle penuh satu task penugasan"  
**Error**: Element shows `SQLSTATE[HY000]: General error: 1366 Incorrect in...`

---

### **🎯 Root Cause Analysis**

#### **Possible Causes**:

1. **Character Encoding Issue**:
   - Database column charset tidak sesuai
   - UTF-8 encoding issue
   - Special characters tidak ter-handle

2. **Data Type Mismatch**:
   - Insert data dengan tipe yang salah
   - Integer expected tapi string diberikan
   - Date format tidak sesuai

3. **Database Migration Issue**:
   - Column definition tidak sesuai
   - Constraint violation

---

### **🔧 Recommended Fixes**

**Step 1**: Check database charset

```sql
-- Check table charset
SHOW CREATE TABLE tasks;

-- Fix charset if needed
ALTER TABLE tasks CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**Step 2**: Check column definitions

```sql
-- Ensure all columns have correct data types
DESCRIBE tasks;
```

**Step 3**: Check application code

```php
// Ensure data is properly sanitized before insert
$task = new Task();
$task->title = strip_tags($request->title);
$task->description = strip_tags($request->description);
$task->save();
```

---

### **📊 Impact Assessment**

| Area | Impact | Severity |
|------|--------|----------|
| **User Experience** | Cannot create or manage tasks | 🔴 CRITICAL |
| **Functionality** | Core feature completely broken | 🔴 CRITICAL |
| **Data Integrity** | Database error | 🔴 CRITICAL |
| **Workaround Available** | No workaround | 🔴 CRITICAL |

---

## 🔴 BUG #8: Dashboard SPK Tidak Dapat Diakses

### **Severity**: HIGH  
### **Priority**: HIGH  
### **Status**: OPEN

---

### **📋 Summary**

Halaman Dashboard SPK (`/spk` atau `/analisa-spk`) tidak dapat diakses. Heading "Analisa SPK" atau "Dashboard SPK" tidak ditemukan.

---

### **🔍 Steps to Reproduce**

1. Login sebagai user dengan role `pjawab`
2. Navigate ke `/spk` atau menu "Analisa SPK"
3. Observe halaman yang dimuat

---

### **✅ Expected Behavior**

- Halaman Dashboard SPK termuat dengan heading "Analisa SPK" atau "Dashboard SPK"
- Komponen analisis SPK terlihat
- Tabel hasil kalkulasi tersedia

---

### **❌ Actual Behavior**

- Heading tidak ditemukan
- Timeout setelah 15 detik
- Halaman tidak termuat dengan benar

---

### **📸 Evidence**

**Test File**: `tests/e2e/specs/spk.spec.ts`  
**Test**: "Positif - Verifikasi UI Dasbor Analisa SPK"  
**Error**: `expect(locator).toBeVisible() failed - Locator: h1 /Analisa SPK|Dashboard SPK/i`

---

### **📊 Impact Assessment**

| Area | Impact | Severity |
|------|--------|----------|
| **User Experience** | Cannot access SPK analysis | 🔴 HIGH |
| **Functionality** | Core SPK feature broken | 🔴 HIGH |
| **Data Integrity** | No impact | 🟢 LOW |

---

## 🔴 BUG #9: Tabel Hasil Kalkulasi SPK Tidak Muncul

### **Severity**: HIGH  
### **Priority**: HIGH  
### **Status**: OPEN

---

### **📋 Summary**

Setelah memilih filter dan parameter kalkulasi SPK, tabel hasil kalkulasi tidak muncul di halaman.

---

### **🔍 Steps to Reproduce**

1. Login sebagai user dengan role `pjawab`
2. Navigate ke halaman SPK
3. Pilih komoditas dari dropdown filter
4. Pilih parameter lainnya
5. Observe tabel hasil

---

### **✅ Expected Behavior**

- Tabel hasil kalkulasi SPK muncul
- Data ranking supplier terlihat
- Skor dan bobot ditampilkan

---

### **❌ Actual Behavior**

- Tabel tidak muncul
- Timeout setelah 10 detik
- Element `<table>` tidak ditemukan

---

### **📸 Evidence**

**Test File**: `tests/e2e/specs/spk.spec.ts`  
**Test**: "Positif - Dropdown dan parameter Filter Kalkulasi SPK memuat ulang kalkulasi"  
**Error**: `expect(locator).toBeVisible() failed - Locator: table`

---

### **📊 Impact Assessment**

| Area | Impact | Severity |
|------|--------|----------|
| **User Experience** | Cannot see SPK results | 🔴 HIGH |
| **Functionality** | Core SPK calculation broken | 🔴 HIGH |
| **Data Integrity** | No impact | 🟢 LOW |

---

## 🔴 BUG #10: Halaman Konfigurasi AHP Tidak Dapat Diakses

### **Severity**: HIGH  
### **Priority**: HIGH  
### **Status**: OPEN

---

### **📋 Summary**

Halaman Konfigurasi AHP untuk Supplier SPK tidak dapat diakses. Heading "Konfigurasi AHP — Bobot Supplier" tidak ditemukan.

---

### **🔍 Steps to Reproduce**

1. Login sebagai user dengan role `pjawab`
2. Navigate ke Settings
3. Klik menu atau card "Konfigurasi AHP" atau "Supplier SPK"
4. Observe halaman yang dimuat

---

### **✅ Expected Behavior**

- Halaman Konfigurasi AHP termuat dengan heading "Konfigurasi AHP — Bobot Supplier"
- Form konfigurasi bobot terlihat
- Dapat mengatur bobot kriteria AHP

---

### **❌ Actual Behavior**

- Heading tidak ditemukan
- Timeout setelah 60 detik
- Halaman tidak termuat dengan benar

---

### **📸 Evidence**

**Test File**: `tests/e2e/specs/supplier-spk.spec.ts`  
**Test**: "Positif - Navigasi Konfigurasi AHP terekspos dan dapat diakses dari menu Settings"  
**Error**: `expect(locator).toBeVisible() failed - Locator: heading 'Konfigurasi AHP — Bobot Supplier'`

---

### **📊 Impact Assessment**

| Area | Impact | Severity |
|------|--------|----------|
| **User Experience** | Cannot configure AHP weights | 🔴 HIGH |
| **Functionality** | Core SPK configuration broken | 🔴 HIGH |
| **Data Integrity** | No impact | 🟢 LOW |

---

**Prepared by**: Senior QA Engineer  
**Date**: June 1, 2026  
**Version**: 2.0
