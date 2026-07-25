EVIDENCE — BUG #25: Elemen Tumpang Tindih di Halaman Konfigurasi Fuzzy Mamdani
==============================================================================
Halaman : Pengaturan / Fuzzy Mamdani (Konfigurasi Fuzzy)
URL      : localhost:8000/settings/fuzzy
Role     : Penanggung Jawab (pjawab) — "Penanggung Jawab Demo"
Didokumentasikan di: BUG_REPORT_MODUL_DEV1_SPK_SMART_FARM.md -> BUG #25

RINGKASAN TEMUAN (dari screenshot):
- Kartu langkah/tab bernomor (1 Variabel & Set / 11 variabel, 2 Sumber Data / 6 mapping,
  3 Rule IF-THEN / 52 rule) TUMPANG TINDIH dengan daftar baris variabel di baliknya
  (kelembapan INPUT %, status_lingkungan OUTPUT score, suhu INPUT C).
- Kartu langkah melayang menimpa list sehingga sebagian baris variabel tertutup/berhimpitan.
- Dugaan: masalah positioning / z-index / layout grid pada komponen step/tab
  (area "active tab styling").

FILE SCREENSHOT (mohon letakkan di folder ini):
- Nama file yang disarankan: 01-fuzzy-variabel-tab-overlap.png
- Cara: drag & drop / simpan gambar bug tampilan (yang dikirim ke QA) ke folder ini dengan nama di atas.

Catatan: file PNG belum bisa disimpan otomatis oleh agent karena berasal dari lampiran chat
(bukan file di disk). Setelah gambar diletakkan, evidence BUG #25 lengkap.
