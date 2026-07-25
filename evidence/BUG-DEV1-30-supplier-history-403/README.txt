EVIDENCE — BUG #30: Link "Lihat Semua History" di Panel Supplier Mengarah ke /dashboard -> 403
=============================================================================================
Halaman : Panel Supplier (dropdown notifikasi)
URL link : http://127.0.0.1:8000/dashboard  (label link: "Lihat Semua History")
Role     : Supplier (supplier.demo@smartfarm.test)
Didokumentasikan di: BUG_REPORT_MODUL_DEV1_SPK_SMART_FARM.md -> BUG #30

RINGKASAN TEMUAN (terverifikasi otomatis):
- Di panel supplier, dropdown notifikasi punya footer link berteks "Lihat Semua History"
  yang href-nya menunjuk ke /dashboard.
- /dashboard untuk role supplier mengembalikan HTTP 403 ("ANDA TIDAK MEMILIKI AKSES KE HALAMAN INI").
- Verifikasi: Playwright response.status() = 403 (lihat 01-dashboard-403.png).
- HTML sumber (dari /supplier):
    <div class="p-2 border-t bg-gray-50 text-center">
      <a href="http://127.0.0.1:8000/dashboard" ...>Lihat Semua History</a>
    </div>

CATATAN:
- Dua masalah: (1) target link salah (berlabel "History" tapi mengarah ke /dashboard),
  (2) untuk supplier menghasilkan 403.

FILE:
- 01-dashboard-403.png  -> screenshot halaman 403 saat supplier membuka /dashboard (evidence utama)
- 02-panel-supplier.png -> screenshot panel supplier
