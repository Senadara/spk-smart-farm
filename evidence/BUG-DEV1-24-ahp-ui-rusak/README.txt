EVIDENCE — BUG #24: Tampilan Halaman AHP (Strategi Supplier DSS) Berantakan / Layout Rusak
==========================================================================================
Halaman   : Supplier DSS > Strategi (AHP) — perbandingan berpasangan (PASANGAN 1/3, Harga vs Kualitas)
URL        : localhost:8000/spk-suppliers/dss/config (perlu konfirmasi)
Role       : Penanggung Jawab (pjawab)
 Didokumentasikan di: BUG_REPORT_MODUL_DEV1_SPK_SMART_FARM.md -> BUG #24

RINGKASAN TEMUAN (dari screenshot):
- Layout berantakan: konten menumpuk di pita sempit bagian atas, area kosong besar di bawah/kanan.
- Tombol skala perbandingan (intensitas 1-9) kecil & tidak rata, seperti belum ter-style.
- Muncul teks mentah/monospace "nilai kirim = 0.111" yang terlihat seperti nilai debug.
- Indikasi CSS/grid komponen AHP tidak ter-render dengan benar.

FILE SCREENSHOT (mohon letakkan di folder ini):
- Nama file yang disarankan: 01-ahp-strategi-ui-rusak.png
- Cara: drag & drop / simpan gambar bug UI (yang dikirim ke QA) ke folder ini dengan nama di atas.

Catatan: file PNG belum bisa disimpan otomatis oleh agent karena berasal dari lampiran chat
(bukan file di disk). Setelah gambar diletakkan, evidence BUG #24 lengkap.
