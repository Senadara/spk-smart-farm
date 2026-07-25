EVIDENCE — BUG #27: Gambar/Foto Kandang Tidak Muncul di Halaman Detail Kandang
==============================================================================
Halaman : Peternakan / Detail Kandang (contoh: "Kandang A")
URL      : 192.168.0.21:8000/peternakan/{id}  (screenshot diambil dari perangkat mobile via LAN)
           (setara localhost:8000/peternakan/{id} pada desktop)
Role     : Penanggung Jawab (pjawab)
Didokumentasikan di: BUG_REPORT_MODUL_DEV1_SPK_SMART_FARM.md -> BUG #27

RINGKASAN TEMUAN (dari screenshot):
- Di header detail kandang, tempat foto/gambar kandang seharusnya tampil, yang muncul adalah
  ikon "broken image" dengan teks alt "Kandang A" -> gambar gagal dimuat.
- Sisa konten (info kandang, KPI, konteks kesehatan, tren sensor) tampil normal.

DUGAAN PENYEBAB (hipotesis, perlu dicek Dev):
- src gambar 404 / path salah;
- symlink storage belum dibuat (php artisan storage:link);
- URL gambar absolut menunjuk host yang tak terjangkau dari mobile (mis. localhost/127.0.0.1)
  saat diakses via IP LAN 192.168.0.21.

FILE SCREENSHOT (mohon letakkan di folder ini):
- Nama file yang disarankan: 01-detail-kandang-gambar-broken.png
- Cara: drag & drop / simpan gambar bug (yang dikirim ke QA) ke folder ini dengan nama di atas.

Catatan: file PNG belum bisa disimpan otomatis oleh agent karena berasal dari lampiran chat
(bukan file di disk). Setelah gambar diletakkan, evidence BUG #27 lengkap.
