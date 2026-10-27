# Pemeliharaan struktur laporan keuangan Ralan

Halaman laporan, Hitung, Hitung Harian, impor apotek, dan ekspor sekarang hanya
memvalidasi tabel melalui `SHOW COLUMNS`. Tidak ada lagi pembuatan atau
penambahan kolom otomatis saat fitur digunakan. Pemeriksaan sukses digunakan
kembali selama request yang sama, terpisah untuk setiap koneksi.

Jika tabel, kolom wajib, atau primary key belum sesuai, aplikasi menghentikan
proses tersebut dengan pemberitahuan kepada pengguna untuk menghubungi
administrator. Detail struktur yang kurang dicatat di log PHP.

Definisi bersama ada di
`main_app/page/t_non_klinis/keuangan/laporan_keuangan_ralan_schema.php`.
Definisi kolom, nominal default, dan primary key mengikuti struktur sebelumnya.
Kolom tambahan yang sudah ada tidak dihapus atau diubah.

## Penggunaan CLI

Jalankan dari direktori aplikasi. Skrip menolak akses melalui browser.

Tanpa koneksi database dan tanpa menjalankan SQL:

```powershell
C:\xampp\php\php.exe maintenance/keuangan_ralan_schema.php --plan
```

Mode tersebut hanya mencetak definisi tabel baru. Untuk pemeriksaan terhadap
database yang dikonfigurasi aplikasi, mode berikut membuka koneksi dan hanya
menjalankan `SHOW COLUMNS`:

```powershell
C:\xampp\php\php.exe maintenance/keuangan_ralan_schema.php --check
```

Exit code: `0` jika struktur lengkap; `2` jika ada rencana/perlu peninjauan;
`1` jika pemeriksaan gagal. Mode `--check` tidak mengeksekusi rencana SQL.

## Penerapan hanya setelah konfirmasi khusus

Sebelum mode `--apply` digunakan, sampaikan target server/database, SQL hasil
`--check`, dan dampaknya kepada pengelola, lalu tunggu persetujuan khusus.
Persetujuan perubahan kode aplikasi bukan persetujuan perubahan database.

```powershell
C:\xampp\php\php.exe maintenance/keuangan_ralan_schema.php --apply --confirm-server=HOST_YANG_DISETUJUI --confirm-database=DB_YANG_DISETUJUI
```

Mode ini membutuhkan kedua parameter konfirmasi dan menolak target yang berbeda
dengan `config/koneksi.php` beserta environment aplikasi. Jika tabel belum ada,
rencananya berupa `CREATE TABLE IF NOT EXISTS`. Jika tabel sudah ada, rencananya
hanya `ADD COLUMN` untuk kolom yang belum tersedia. Primary key yang berbeda
menghentikan seluruh penerapan sebelum DDL pertama; koreksinya perlu analisis
manual. Skrip tidak mengubah tipe kolom yang sudah ada, data pasien, isi
kalkulasi, konfigurasi cache, atau layanan database.

DDL dapat menunggu metadata lock/mengganggu request yang memakai tabel tersebut
dan dapat melakukan commit implisit. Beberapa perintah dapat sudah berhasil
sebelum perintah berikutnya gagal; jangan menganggap transaksi dapat membatalkan
DDL. Jadwalkan penerapan setelah SQL, backup, serta waktu pelaksanaan disetujui,
kemudian ulangi `--check`.

Tahap pemisahan kode ini tidak memerlukan menjalankan `--check` ataupun
`--apply` terhadap database pelayanan. Pengujian offline:

```powershell
C:\xampp\php\php.exe tests/keuangan_ralan_schema_test.php
```
