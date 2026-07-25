EVIDENCE — BUG #26: Tombol Aksi Ganda pada Modal "Registrasi Device Baru" (IoT Device Management)
=================================================================================================
Halaman : Device Management (IoT)
URL      : localhost:8000/iot/devices
Role     : Petugas
Modal    : "Registrasi Device Baru"
Didokumentasikan di: BUG_REPORT_MODUL_DEV1_SPK_SMART_FARM.md -> BUG #26

RINGKASAN TEMUAN (dari screenshot):
- Modal "Registrasi Device Baru" menampilkan DUA pasang tombol aksi:
  (1) "Batal" + "Simpan Device"
  (2) di bawahnya lagi "Batal" + "Simpan"
- Tombol duplikat/ganda -> membingungkan; kemungkinan dua footer (footer bawaan modal + footer kustom)
  ter-render bersamaan.
- Catatan: berada di tampilan UI LAMA (pre-existing), bukan bagian perubahan merge terbaru.

FILE SCREENSHOT (mohon letakkan di folder ini):
- Nama file yang disarankan: 01-registrasi-device-tombol-ganda.png
- Cara: drag & drop / simpan gambar bug (yang dikirim ke QA) ke folder ini dengan nama di atas.

Catatan: file PNG belum bisa disimpan otomatis oleh agent karena berasal dari lampiran chat
(bukan file di disk). Setelah gambar diletakkan, evidence BUG #26 lengkap.
