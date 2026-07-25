EVIDENCE — BUG #28: Data Sensor Lingkungan Muncul Padahal IoT Belum Terkoneksi
==============================================================================
Halaman : Analisa SPK / Pusat Analisis & SPK
URL      : 192.168.0.21:8000/spk  (screenshot dari perangkat mobile via LAN)
Role     : Penanggung Jawab (pjawab)
Didokumentasikan di: BUG_REPORT_MODUL_DEV1_SPK_SMART_FARM.md -> BUG #28

RINGKASAN TEMUAN (dari screenshot):
- Environment Logic menampilkan nilai sensor: Suhu Udara 24 C (Nyaman), Kelembapan 60% (Ideal),
  Amonia 5 ppm (Aman), dengan badge "SANGAT NYAMAN - Score 94.3/100" -- PADAHAL IoT belum
  terkoneksi (tidak ada data sensor real).
- Inkonsistensi: Productivity Logic justru 0% semua (HDP 0%, Pakan 0 g/ekor, Mortalitas 0%),
  mencerminkan "tidak ada data". Environment seharusnya juga demikian.

DUGAAN PENYEBAB (hipotesis, perlu dicek Dev):
- Nilai sensor default/dummy/fallback ditampilkan saat tidak ada koneksi/aliran data IoT; atau
- Data sensor lama (stale) dari periode sebelumnya masih dipakai tanpa cek keterhubungan/kesegaran.

Dampak: menyesatkan hasil SPK/diagnosa lingkungan (skor "ideal" tanpa sumber data valid).

FILE SCREENSHOT (mohon letakkan di folder ini):
- Nama file yang disarankan: 01-analisa-spk-sensor-tanpa-koneksi-iot.png
- Cara: drag & drop / simpan gambar bug (yang dikirim ke QA) ke folder ini dengan nama di atas.

Catatan: file PNG belum bisa disimpan otomatis oleh agent karena berasal dari lampiran chat
(bukan file di disk). Setelah gambar diletakkan, evidence BUG #28 lengkap.
