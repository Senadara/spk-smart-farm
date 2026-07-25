EVIDENCE — BUG #29: Duplikasi Kartu pada "Tindakan yang Disarankan" (Dashboard)
==============================================================================
Halaman : Dashboard
URL      : localhost:8000/dashboard
Role     : Penanggung Jawab (pjawab) — "PJ Demo"
Didokumentasikan di: BUG_REPORT_MODUL_DEV1_SPK_SMART_FARM.md -> BUG #29

RINGKASAN TEMUAN (dari screenshot):
- Section "Tindakan yang Disarankan" (badge "4 kandidat") menampilkan 4 kartu yang isinya IDENTIK:
  Urgent, Global, "Tidak Diketahui", "Lakukan evaluasi manual", tombol Detail SPK + Buat Penugasan.
- Dua kartu bertimestamp 01:20 dan dua kartu 01:15, namun konten sama persis -> duplikat/redundant.

DUGAAN PENYEBAB (hipotesis, perlu dicek Dev):
- Rekomendasi digenerate/looping tanpa deduplikasi; kandidat yang sama dirender berulang.

FILE SCREENSHOT (mohon letakkan di folder ini):
- Nama file yang disarankan: 01-dashboard-tindakan-disarankan-duplikat.png
- Cara: drag & drop / simpan gambar bug (yang dikirim ke QA) ke folder ini dengan nama di atas.

Catatan: file PNG belum bisa disimpan otomatis oleh agent karena berasal dari lampiran chat
(bukan file di disk). Setelah gambar diletakkan, evidence BUG #29 lengkap.
