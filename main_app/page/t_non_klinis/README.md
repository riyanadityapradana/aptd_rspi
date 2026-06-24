# README Analitik

Dokumen ini menjelaskan file-file analitik yang dibuat di folder `main_app/page/t_non_klinis`, fungsi masing-masing file, kegunaan laporan, tabel yang dipakai, serta relasi data yang terlibat.

## Struktur Modul

Folder utama:
- `C:\laragon\www\aptd_rspi\main_app\page\t_non_klinis\report_helper.php`
- `C:\laragon\www\aptd_rspi\main_app\page\t_non_klinis\umum\rekap_pasien_baru_lama.php`
- `C:\laragon\www\aptd_rspi\main_app\page\t_non_klinis\umum\top_10_dokter_pasien.php`
- `C:\laragon\www\aptd_rspi\main_app\page\t_non_klinis\umum\pasien_rujukan_masuk_keluar.php`
- `C:\laragon\www\aptd_rspi\main_app\page\t_non_klinis\los_rawat_inap.php`
- `C:\laragon\www\aptd_rspi\main_app\page\t_non_klinis\bor_sederhana.php`
- `C:\laragon\www\aptd_rspi\main_app\page\t_non_klinis\wilayah\kunjungan_wilayah_visual.php`

## Pola Umum Modul Analitik

Semua halaman analitik baru memakai pola yang sama:
- koneksi database dipusatkan lewat `config/koneksi.php`
- helper umum dipanggil lewat `report_helper.php`
- filter memakai `POST`
- hasil query diringkas dulu di SQL agar lebih cepat
- hasil dirender ke 4 area utama: filter, kartu ringkasan, grafik, dan tabel
- format tampilan dipusatkan lewat fungsi `aptd_render_shell()`

## 1. report_helper.php

Path:
- `C:\laragon\www\aptd_rspi\main_app\page\t_non_klinis\report_helper.php`

### Fungsi file
File helper umum untuk seluruh modul analitik.

### Kegunaan
- menyambungkan semua file analitik ke database
- menyediakan helper filter bulan/tahun dan tanggal
- menyediakan helper format angka dan mata uang
- menyediakan template tampilan analitik yang konsisten

### Fungsi utama di dalam file
- `aptd_month_labels_local()`
  Mengembalikan daftar nama bulan dalam bahasa Indonesia.
- `aptd_filter_month_year()`
  Membaca filter `month` dan `year`, lalu menghasilkan tanggal awal dan akhir bulan.
- `aptd_filter_date_range()`
  Membaca filter `start_date` dan `end_date`.
- `aptd_service_options()`
  Menyediakan pilihan layanan `Semua`, `Ralan`, `Ranap`.
- `aptd_selected_service()`
  Mengambil nilai layanan yang dipilih user.
- `aptd_currency()`
  Format angka ke bentuk rupiah sederhana.
- `aptd_number()`
  Format angka ribuan.
- `aptd_render_shell()`
  Template tampilan utama untuk semua halaman analitik.

### Tabel yang dipakai
- Tidak query tabel analitik langsung.
- Hanya memakai koneksi dari `config/koneksi.php`.

### Relasi
- semua file analitik memanggil file ini
- file ini memanggil `config/koneksi.php`

## 2. rekap_pasien_baru_lama.php

Path:
- `C:\laragon\www\aptd_rspi\main_app\page\t_non_klinis\umum\rekap_pasien_baru_lama.php`

### Fungsi file
Menampilkan rekap pasien baru dan pasien lama per hari dalam satu periode bulanan.

### Kegunaan
- melihat komposisi pasien baru vs pasien lama
- membandingkan tren kunjungan pasien baru dan lama
- bisa difilter berdasarkan layanan `Ralan`, `Ranap`, atau semua

### Output utama
- total pasien
- total pasien baru
- total pasien lama
- komposisi dominan
- grafik harian pasien baru vs lama
- tabel detail harian

### Tabel yang dipakai
- `reg_periksa`

### Kolom penting yang dipakai
- `reg_periksa.tgl_registrasi`
- `reg_periksa.stts_daftar`
- `reg_periksa.status_lanjut`
- `reg_periksa.stts`

### Logika query
- filter periode dari `tgl_registrasi`
- abaikan data `stts = 'Batal'`
- hitung pasien baru dari `stts_daftar = 'Baru'`
- hitung pasien lama dari `stts_daftar = 'Lama'`
- agregasi per tanggal registrasi

### Relasi tabel
- tidak memakai join
- semua data langsung dari `reg_periksa`

## 3. top_10_dokter_pasien.php

Path:
- `C:\laragon\www\aptd_rspi\main_app\page\t_non_klinis\umum\top_10_dokter_pasien.php`

### Fungsi file
Menampilkan ranking 10 dokter dengan jumlah pasien terbanyak pada periode tertentu.

### Kegunaan
- melihat dokter paling ramai
- membandingkan beban pasien antar dokter
- memantau komposisi penjamin per dokter

### Output utama
- total pasien top 10 dokter
- dokter dengan pasien terbanyak
- komposisi BPJS, Umum, Asuransi
- grafik horizontal top 10 dokter
- tabel ranking dokter

### Tabel yang dipakai
- `reg_periksa`
- `dokter`

### Kolom penting yang dipakai
- `reg_periksa.no_rawat`
- `reg_periksa.tgl_registrasi`
- `reg_periksa.kd_dokter`
- `reg_periksa.kd_pj`
- `reg_periksa.status_lanjut`
- `reg_periksa.stts`
- `dokter.kd_dokter`
- `dokter.nm_dokter`

### Logika query
- filter periode dari `tgl_registrasi`
- abaikan `stts = 'Batal'`
- opsional filter `status_lanjut`
- join ke `dokter` untuk nama dokter
- hitung jumlah pasien unik dengan `COUNT(DISTINCT rp.no_rawat)`
- hitung komposisi penjamin:
  - `A09` dianggap Umum
  - `BPJ` dianggap BPJS
  - `A92` dianggap Asuransi

### Relasi tabel
- `reg_periksa.kd_dokter -> dokter.kd_dokter`

## 4. pasien_rujukan_masuk_keluar.php

Path:
- `C:\laragon\www\aptd_rspi\main_app\page\t_non_klinis\umum\pasien_rujukan_masuk_keluar.php`

### Fungsi file
Menampilkan perbandingan rujukan masuk dan rujukan keluar dalam rentang tanggal tertentu.

### Kegunaan
- memantau arus rujukan rumah sakit
- membandingkan kategori rujukan masuk vs keluar
- audit detail rujukan berdasarkan nomor rujuk, perujuk, dan kode penyakit

### Output utama
- total rujukan masuk
- total rujukan keluar
- selisih masuk vs keluar
- jumlah kategori terbaca
- grafik perbandingan kategori
- tabel detail rujukan masuk
- tabel detail rujukan keluar

### Tabel yang dipakai
- `rujuk_masuk`
- `rujuk`
- `reg_periksa`
- `diagnosa_pasien`

### Kolom penting yang dipakai
Rujukan masuk:
- `rujuk_masuk.no_rawat`
- `rujuk_masuk.perujuk`
- `rujuk_masuk.no_rujuk`
- `rujuk_masuk.kd_penyakit`
- `rujuk_masuk.kategori_rujuk`
- `reg_periksa.tgl_registrasi`

Rujukan keluar:
- `rujuk.no_rawat`
- `rujuk.no_rujuk`
- `rujuk.tgl_rujuk`
- `rujuk.rujuk_ke`
- `rujuk.kat_rujuk`
- `diagnosa_pasien.kd_penyakit`
- `diagnosa_pasien.prioritas`

### Logika query
Rujukan masuk:
- sumber utama tabel `rujuk_masuk`
- join ke `reg_periksa` untuk tanggal registrasi
- agregasi per tanggal, perujuk, no rujuk, kode penyakit, kategori

Rujukan keluar:
- sumber utama tabel `rujuk`
- filter berdasarkan `tgl_rujuk`
- join ke `diagnosa_pasien` untuk mengambil `kd_penyakit` utama
- `rujuk_ke` dipakai sebagai informasi tujuan rujuk
- agregasi per tanggal, tujuan rujuk, no rujuk, kode penyakit, kategori

### Relasi tabel
- `rujuk_masuk.no_rawat -> reg_periksa.no_rawat`
- `rujuk.no_rawat -> reg_periksa.no_rawat`
- `rujuk.no_rawat -> diagnosa_pasien.no_rawat`

### Catatan penting
- pada rujukan keluar, kolom “Perujuk” yang tampil berasal dari `rujuk.rujuk_ke`
- ini secara makna lebih dekat ke tujuan rujuk daripada perujuk asal
- `KD Penyakit` rujukan keluar diambil dari `diagnosa_pasien` dengan `prioritas = '1'`

## 5. los_rawat_inap.php

Path:
- `C:\laragon\www\aptd_rspi\main_app\page\t_non_klinis\los_rawat_inap.php`

### Fungsi file
Menampilkan LOS rawat inap per bangsal.

### Kegunaan
- memantau rata-rata lama dirawat
- melihat total hari rawat
- membandingkan LOS antar bangsal
- mengetahui bangsal dengan LOS tertinggi

### Output utama
- rata-rata LOS global
- total pasien rawat inap
- total hari rawat
- bangsal dengan LOS tertinggi
- grafik LOS per bangsal
- tabel detail LOS per bangsal

### Tabel yang dipakai
- `kamar_inap`
- `kamar`
- `bangsal`
- `reg_periksa`

### Kolom penting yang dipakai
- `kamar_inap.no_rawat`
- `kamar_inap.kd_kamar`
- `kamar_inap.tgl_masuk`
- `kamar_inap.lama`
- `kamar_inap.stts_pulang`
- `kamar.kd_kamar`
- `kamar.kd_bangsal`
- `bangsal.kd_bangsal`
- `bangsal.nm_bangsal`
- `reg_periksa.no_rawat`
- `reg_periksa.status_lanjut`

### Logika query
- filter periode dari `kamar_inap.tgl_masuk`
- hanya ambil `status_lanjut = 'Ranap'`
- abaikan `stts_pulang = 'Pindah Kamar'`
- hitung:
  - jumlah pasien unik
  - total hari rawat
  - rata-rata LOS
  - LOS maksimum
- grup per bangsal

### Relasi tabel
- `kamar_inap.kd_kamar -> kamar.kd_kamar`
- `kamar.kd_bangsal -> bangsal.kd_bangsal`
- `kamar_inap.no_rawat -> reg_periksa.no_rawat`

## 6. bor_sederhana.php

Path:
- `C:\laragon\www\aptd_rspi\main_app\page\t_non_klinis\bor_sederhana.php`

### Fungsi file
Menampilkan BOR sederhana per bangsal atau per kamar.

### Kegunaan
- melihat keterisian bangsal atau kamar secara cepat
- memantau unit dengan BOR tertinggi
- membaca efisiensi pemakaian tempat tidur secara sederhana

### Output utama
- rata-rata BOR
- BOR tertinggi
- jumlah unit aktif
- jumlah hari dalam periode
- grafik BOR top unit
- tabel BOR detail

### Tabel yang dipakai
- `bangsal`
- `kamar`
- `kamar_inap`

### Kolom penting yang dipakai
- `bangsal.kd_bangsal`
- `bangsal.nm_bangsal`
- `kamar.kd_kamar`
- `kamar.kd_bangsal`
- `kamar_inap.kd_kamar`
- `kamar_inap.no_rawat`
- `kamar_inap.tgl_masuk`
- `kamar_inap.tgl_keluar`
- `kamar_inap.stts_pulang`

### Logika query
- mode tampilan bisa `bangsal` atau `kamar`
- hitung jumlah kamar per bangsal atau satu kamar tunggal
- hitung `hari_terpakai` berdasarkan irisan tanggal rawat dengan periode terpilih
- abaikan `Pindah Kamar`
- hitung `hari_tersedia = jumlah_kamar x jumlah_hari_bulan`
- hitung `BOR = hari_terpakai / hari_tersedia x 100`

### Relasi tabel
- `bangsal.kd_bangsal -> kamar.kd_bangsal`
- `kamar.kd_kamar -> kamar_inap.kd_kamar`

### Catatan penting
- ini BOR versi sederhana, bukan BOR sensus harian penuh
- cocok untuk pemantauan cepat, bukan pengganti indikator resmi bila rumah sakit punya rumus khusus sendiri

## 7. kunjungan_wilayah_visual.php

Path:
- `C:\laragon\www\aptd_rspi\main_app\page\t_non_klinis\wilayah\kunjungan_wilayah_visual.php`

### Fungsi file
Menampilkan kunjungan rawat jalan berdasarkan kabupaten atau kecamatan dalam bentuk visual.

### Kegunaan
- melihat konsentrasi asal kunjungan pasien
- membandingkan wilayah dengan kunjungan tertinggi
- membaca komposisi pembayaran per wilayah

### Output utama
- total kunjungan
- wilayah teratas
- jumlah wilayah yang tampil
- grafik total kunjungan wilayah
- grafik stacked pembayaran per wilayah
- tabel wilayah

### Tabel yang dipakai
- `reg_periksa`
- `pasien`
- `kabupaten`
- `kecamatan`

### Kolom penting yang dipakai
- `reg_periksa.no_rkm_medis`
- `reg_periksa.tgl_registrasi`
- `reg_periksa.status_lanjut`
- `reg_periksa.status_bayar`
- `reg_periksa.kd_pj`
- `reg_periksa.stts`
- `pasien.no_rkm_medis`
- `pasien.kd_kab`
- `pasien.kd_kec`
- `kabupaten.kd_kab`
- `kabupaten.nm_kab`
- `kecamatan.kd_kec`
- `kecamatan.nm_kec`

### Logika query
- hanya ambil rawat jalan
- hanya ambil data `Sudah Bayar`
- abaikan data `Batal`
- pilihan visualisasi:
  - per kabupaten
  - per kecamatan
- hitung total kunjungan dan komposisi penjamin
- tampilkan top 12 wilayah

### Relasi tabel
- `reg_periksa.no_rkm_medis -> pasien.no_rkm_medis`
- `pasien.kd_kab -> kabupaten.kd_kab`
- `pasien.kd_kec -> kecamatan.kd_kec`

## Ringkasan Relasi Antar Tabel

Relasi yang paling sering dipakai pada modul analitik ini:
- `reg_periksa.no_rawat` terhubung ke `kamar_inap.no_rawat`, `rujuk_masuk.no_rawat`, `rujuk.no_rawat`, `diagnosa_pasien.no_rawat`
- `reg_periksa.no_rkm_medis` terhubung ke `pasien.no_rkm_medis`
- `reg_periksa.kd_dokter` terhubung ke `dokter.kd_dokter`
- `kamar_inap.kd_kamar` terhubung ke `kamar.kd_kamar`
- `kamar.kd_bangsal` terhubung ke `bangsal.kd_bangsal`
- `pasien.kd_kab` terhubung ke `kabupaten.kd_kab`
- `pasien.kd_kec` terhubung ke `kecamatan.kd_kec`

## Cara Menambah Halaman Analitik Baru

Langkah yang disarankan:
1. Buat file baru di folder `t_non_klinis` sesuai kelompoknya: `umum`, `rawat_inap`, atau `wilayah`.
2. Panggil `require_once dirname(__DIR__) . '/report_helper.php';` jika file ada di subfolder langsung di bawah `t_non_klinis`.
3. Tentukan jenis filter:
   - bulanan: `aptd_filter_month_year()`
   - rentang tanggal: `aptd_filter_date_range()`
4. Susun query dengan agregasi langsung di SQL.
5. Render output dengan pola:
   - `$filters`
   - `$cards`
   - `$panels`
   - `$table`
6. Panggil `aptd_render_shell([...])`.
7. Daftarkan route baru di `config/akses.php`.
8. Tambahkan menu baru di `main_app/main_app.php`.

## Saran Pengembangan Berikutnya

Beberapa peningkatan yang bisa dilakukan nanti:
- tambah tombol export Excel per modul
- standarisasi mapping penjamin lewat tabel master, bukan kode hardcoded
- tambah validasi role access khusus menu analitik
- buat helper query penjamin agar kode `A09`, `BPJ`, `A92` tidak berulang di banyak file
- buat helper chart agar script JavaScript lebih ringkas dan seragam
