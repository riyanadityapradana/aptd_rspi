# AR-186 — Rekap Pasien Rawat Inap dengan Tindakan Operasi

Acuan: [System Design Document](https://pelitainsani.atlassian.net/wiki/spaces/IR/pages/95813635), versi 1, dan acceptance criteria AR-187 sampai AR-191.

## Penggunaan

Login sebagai Admin atau Medis, lalu pilih **Data Klinis → Rekap Pasien Rawat Inap dengan Tindakan Operasi**.

- Halaman: `main_app/main_app.php?page=rekap_ranap_operasi`.
- Filter GET: `start_date`, `end_date` (`YYYY-MM-DD`), `date_type` (`masuk`/`keluar`), `kd_bangsal` (opsional).
- Default: awal/akhir bulan berjalan, tanggal masuk, semua bangsal.
- Ekspor: `main_app/page/t_klinis/export_rekap_ranap_operasi.php`, dengan parameter filter yang sama. Route `main_app.php?page=export_rekap_ranap_operasi` juga tersedia.
- XLSX memiliki sheet **Rekapitulasi** dan **Rincian Pasien**, masing-masing dengan periode, basis tanggal, bangsal, dan waktu unduhan.
- Pencarian/paginasi DataTables berlaku pada rincian di layar. Ekspor berisi seluruh hasil filter tanggal/bangsal yang diterapkan.

## Aturan data

1. Satu episode `no_rawat` dihitung satu kali, bukan satu kali per nomor RM atau per tindakan. Minimal ada satu record `operasi` pada episode itu, sesuai SDD; tidak ditambahkan pembatasan berdasarkan tanggal/status operasi.
2. Kamar akhir dipilih dari status selain `-` dan `Pindah Kamar`, dengan tanggal keluar bukan NULL/tanggal nol. Urutan: tanggal keluar, jam keluar (NULL dianggap `00:00:00`), tanggal masuk, jam masuk; seluruhnya menurun. Dua kolom terakhir adalah penentu tambahan berdasarkan PK aktual agar hasil selalu satu baris.
3. Pemilihan kamar akhir dilakukan sebelum filter periode keluar dan bangsal. Pasien tidak masuk bangsal lamanya hanya karena bangsal akhir tidak cocok filter.
4. Pasien tanpa catatan keluar valid, termasuk yang masih dirawat, tidak masuk pada kedua basis tanggal, mengikuti SDD.
5. Basis masuk dan lama rawat memakai `reg_periksa.tgl_registrasi`. Ini bukan `kamar_inap.tgl_masuk`. Lama rawat adalah `DATEDIFF(tanggal_keluar, tanggal_registrasi)` tanpa tambahan satu hari.
6. `BPJ` = BPJS; `A09` = Umum; selainnya = Asuransi. Rekap dikelompokkan berdasarkan kode bangsal dan menampilkan nama bangsal.
7. Dropdown menampilkan bangsal aktif. Semua bangsal tetap mencakup bangsal historis yang sudah tidak aktif agar rekap historis tidak hilang.
8. Tindakan unik yang sudah di-TRIM digabung dengan koma, seperti `GROUP_CONCAT(DISTINCT ...)` pada SDD. Jika nama paket tidak tersedia, kode paket ditampilkan agar episode dengan record operasi tetap dihitung.

## Implementasi dan kompatibilitas

- `rekap_ranap_operasi_helper.php`: validasi filter, prepared SELECT, kamar akhir, tindakan, dan agregasi bersama untuk UI/Excel.
- `rekap_ranap_operasi.php`: form filter, rekap bangsal, rincian DataTables, serta penanganan hasil kosong/kesalahan.
- `rekap_ranap_operasi_excel.php`: pembentukan workbook, nomor RM sebagai teks (nol awal dipertahankan), teks sumber tidak menjadi formula Excel.
- `export_rekap_ranap_operasi.php`: autentikasi/izin ekspor, unduhan XLSX tanpa layout HTML.
- `config/akses.php`, `config/menu.php`, `config/user_access.php`: route, menu, hak bawaan Admin/Medis, serta dependensi ekspor untuk akses menu tambahan. Hak tambahan tetap menambah hak bawaan role.

Database terverifikasi MariaDB **10.1.25**. Contoh CTE/`ROW_NUMBER()` pada SDD diganti `NOT EXISTS` yang menggunakan indeks PK `kamar_inap`. `SELECT STRAIGHT_JOIN` memulai join dari tabel dengan filter tanggal: `reg_periksa` untuk masuk dan `kamar_inap` untuk keluar. Uji awal tanpa pengaturan urutan join mencapai sekitar 5 detik; setelah perbaikan, indeks tanggal yang sudah ada dipakai.

Batas `GROUP_CONCAT` aktual adalah 1.024 byte. Jika database melaporkan pemotongan (warning 1260), helper membaca ulang nama tindakan dengan prepared SELECT dan menggabungkannya di PHP. Tidak ada perubahan `group_concat_max_len`, indeks, tabel permanen, konfigurasi database, atau transaksi SIMRS.

## Validasi pada 20 November 2026

- PHP lint pada file yang diubah/ditambah dan `git diff --check` lulus.
- Regresi akses umum: **1.414 pemeriksaan** lulus.
- Fixture AR-186: **38 pemeriksaan** lulus, termasuk mutasi, tanggal keluar sama, beberapa tindakan, nomor RM sama pada episode berbeda, tindakan panjang, tanggal nol, pasien aktif, bangsal historis, filter invalid, hasil kosong, dan round-trip XLSX. Fixture hanya memakai temporary tables pada koneksi pengujian; MariaDB memerlukan salinan temporary table untuk referensi berulang pada subquery.
- Live read-only: **319 pemeriksaan** lulus. Oktober 2026: **137 episode** berdasarkan tanggal masuk dan **142 episode** berdasarkan tanggal keluar. Kamar akhir dan cohort cocok dengan referensi independen yang memilih kamar di PHP. Semua filter bangsal pada hasil diperiksa. Query bulanan sekitar **0,01–0,03 detik** saat pemeriksaan; waktu dapat berubah mengikuti beban/data.
- HTTP/unduhan: **66 pemeriksaan** lulus melalui server uji lokal, termasuk Admin/Medis, pengguna tanpa izin, anonim, kedua jalur ekspor, kedua basis tanggal, MIME/signature XLSX, kesamaan seluruh isi sheet dengan helper, validasi 400, dan hasil kosong.
- Browser Edge headless: **11 pemeriksaan** lulus pada desktop 1440 px dan mobile 390 px: menu, jumlah rincian, pencarian, pagination, basis keluar, unduhan, hasil kosong, tidak ada overflow halaman mobile, dan tidak ada exception JavaScript. Screenshot untuk pemeriksaan visual memakai hasil kosong tanpa data pasien.
- Pengujian memakai sesi sementara dari akun yang sudah ada tanpa mengubah akun, waktu login, atau data transaksi. Server uji menggunakan penyimpanan sesi terpisah.

Menjalankan ulang pengujian:

```powershell
C:\xampp\php\php.exe tests\user_access_test.php
C:\xampp\php\php.exe -d extension=zip tests\rekap_ranap_operasi_test.php --fixtures
C:\xampp\php\php.exe tests\rekap_ranap_operasi_test.php --live
```

`--fixtures` memerlukan koneksi database untuk membuat tabel sementara saja. `--live` hanya membaca data SIMRS. `-d extension=zip` diperlukan untuk pembacaan ulang XLSX pada PHP CLI saat ekstensi ZIP belum dimuat; opsi ini tidak mengubah `php.ini`.
