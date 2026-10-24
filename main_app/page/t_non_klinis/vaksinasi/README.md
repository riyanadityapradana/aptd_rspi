# Data Vaksinasi Haji

- Halaman: `main_app.php?page=data_vaksinasi_haji`, menu Data Non Klinis.
- Akses halaman dan PDF: admin, keuangan, moneta.
- Sumber: reg_periksa, pasien, poliklinik, penjab. Hanya poli U0053,
  status Sudah Bayar, dan kd_pj A09/A96. Tidak ada perubahan data SIMRS.
- Filter GET: tanggal_awal, tanggal_akhir (YYYY-MM-DD, keduanya inklusif),
  jenis_bayar (kosong/A09/A96). Default: awal bulan sampai hari ini.
- Nama jenis bayar diambil dari penjab.png_jawab, bukan label yang di-hardcode.
- Total kunjungan menghitung baris registrasi, pasien unik menghitung nomor RM
  berbeda. Data ini tidak menyatakan jumlah dosis vaksin yang diberikan.
- PDF memakai seluruh hasil filter tanggal/jenis bayar, terlepas dari halaman
  tabel atau pencarian lokal DataTables. Ada kop berulang, header tabel berulang,
  total kunjungan, pasien unik, total per penjamin, dan nomor halaman.
- Identitas kop dibaca dari tabel setting SIMRS. Logo rumah sakit disalin dari
  it-rspi2/assets/img/logo.jpg ke assets/report/vaksinasi-haji-logo.jpg.
- TCPDF 6.10.0 disertakan lokal di assets/report/tcpdf agar aplikasi berdiri
  sendiri; sumber dari library/tcpdf aplikasi it-rspi2. Paket menyertakan inti,
  include, font standar, DejaVu Sans, dan lisensinya. mPDF 6 lama tidak digunakan.

Pemeriksaan filter/akses/PDF tanpa database:
`C:\xampp\php\php.exe tests/vaksinasi_haji_test.php`

Pemeriksaan SQL read-only terhadap koneksi SIMRS terkonfigurasi:
`C:\xampp\php\php.exe tests/vaksinasi_haji_test.php --live`
