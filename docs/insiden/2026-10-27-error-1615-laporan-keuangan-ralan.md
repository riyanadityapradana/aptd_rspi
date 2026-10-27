# Catatan Penanganan Error 1615 — Laporan Keuangan Rawat Jalan

Tanggal pencatatan: **27 Oktober 2026**  
Aplikasi: **APTD RSPI → Data Non Klinis → Laporan Keuangan Rawat Jalan**  
Status: **Perbaikan kode tahap 1–2 selesai; pemeriksaan baca tahap 3 berhasil; konfigurasi database belum diubah.**

## 1. Ringkasan kejadian

Pengguna melaporkan halaman laporan keuangan Ralan berhenti dan menampilkan pesan fatal error. Bukti awal berupa foto `WhatsApp Image 2026-10-27 at 14.40.54.jpeg`.

Pesan utamanya:

```text
Fatal error: Uncaught mysqli_sql_exception:
Prepared statement needs to be re-prepared

Next RuntimeException:
Query laporan tidak dapat dijalankan:
Prepared statement needs to be re-prepared
```

Pada foto, error muncul di `laporan_keuangan_ralan_helper.php`, sekitar baris 930 dan 944 pada versi saat kejadian. Nomor baris berubah setelah kode dirapikan.

Filter tanggal pada foto adalah **12 Oktober 2026**. Stack trace menunjukkan alur:

```text
Tombol Pakai Riwayat
→ aptd_keu_ralan_use_history_claim()
→ aptd_keu_ralan_fetch_rows()
→ mysqli_stmt->execute()
→ error 1615
→ exception diteruskan sampai halaman utama
```

Halaman tidak menampilkan notifikasi kegagalan yang terkendali karena exception pada aksi tersebut belum ditangkap. Berdasarkan stack trace, kegagalan terjadi pada pembacaan data sebelum proses penyimpanan klaim riwayat dijalankan pada alur itu.

## 2. Lingkup sistem dan batas kewenangan

| Komponen | Identitas | Dasar informasi |
|---|---|---|
| Server web pada bukti pengguna | `192.168.1.108` | Alamat pada foto |
| Server database yang diperiksa | `192.168.1.4` | Konfigurasi koneksi dan pemeriksaan langsung |
| Database | `sik9` | Koneksi aktif |
| Hostname database | `itrspi-martapura` | Query identitas server |
| Versi database | `10.1.25-MariaDB` | Query identitas server |
| Workspace perbaikan | `C:\xampp\htdocs\aptd_rspi` | Berkas kode yang diedit dan diperiksa |

Pengguna menegaskan bahwa database sedang digunakan untuk pelayanan. Karena itu, pekerjaan dibagi menjadi beberapa tahap. Persetujuan memperbaiki kode aplikasi **tidak dianggap sebagai persetujuan mengubah database**.

Untuk perubahan database selanjutnya, wajib dijelaskan terlebih dahulu: target, perintah, nilai lama dan usulan, dampak, waktu pelaksanaan, serta cara pemulihan. Pelaksanaan menunggu konfirmasi khusus pengguna.

## 3. Hasil penelusuran penyebab

### 3.1 Penyebab langsung tampilan fatal error — terverifikasi

Query laporan mengalami error `1615`, yaitu prepared statement perlu disiapkan ulang. Sebelum perbaikan, helper sudah mencoba menyiapkan dan menjalankan query maksimal dua kali, tetapi tanpa jeda antarpencobaan.

Jika percobaan berikutnya tetap gagal, helper melempar `RuntimeException`. Pemanggilan aksi **Pakai Riwayat** dan **Hitung** belum dilindungi `try/catch` pada halaman laporan. Akibatnya, exception tampil sebagai fatal error beserta stack trace, dan alur redirect/notifikasi tidak terselesaikan.

### 3.2 Pemicu di sisi database — indikasi kuat, belum terbukti tunggal

Pemeriksaan awal menemukan:

| Indikator | Hasil saat pemeriksaan awal |
|---|---:|
| `table_definition_cache` | 400 |
| `table_open_cache` | 2.000 |
| Jumlah tabel pada `sik9` | 1.262 |
| `Open_tables` | 2.000 |
| Kenaikan `Opened_table_definitions` dalam 45 detik | 29.394 |

Angka tersebut menunjukkan aktivitas pembukaan ulang metadata/tabel yang tinggi dan menjadi alasan untuk mengevaluasi kapasitas cache. Penggantian metadata dalam cache dapat memicu penyiapan ulang prepared statement.

Namun, data pemeriksaan ini **tidak membuktikan pemicu persis pada saat foto diambil**. Tidak ada bukti yang dikumpulkan bahwa satu perintah `ALTER TABLE`, `FLUSH TABLES`, atau aplikasi tertentu menjadi pemicunya. Nilai konfigurasi cache dan penghitung pembukaan tabel juga tidak boleh digunakan sendirian untuk memastikan penyebab.

Saat query untuk tanggal pada foto diuji dalam pemeriksaan awal, query berhasil tiga kali. Hal ini menunjukkan gangguan tidak selalu muncul pada setiap permintaan.

### 3.3 Persiapan struktur tabel dalam akses laporan — terverifikasi

Helper sebelumnya memiliki fungsi yang dapat menjalankan:

- `CREATE TABLE IF NOT EXISTS lap_keuangan_bpjs_ralan`;
- `CREATE TABLE IF NOT EXISTS klaim_apotek_online_manual`;
- pemeriksaan kolom dan `ALTER TABLE ... ADD COLUMN` jika kolom cache belum tersedia.

Fungsi tersebut dipanggil oleh alur penggunaan laporan. Mekanisme ini kemudian dipisahkan agar akses rutin tidak menjalankan pemeliharaan struktur secara otomatis.

Keberadaan mekanisme tersebut **bukan bukti bahwa ALTER TABLE benar-benar terjadi saat error pada foto**. Pemisahan dilakukan untuk memperjelas batas antara penggunaan aplikasi dan perubahan struktur database.

### 3.4 Hubungan dengan perubahan JD AR-180

Perubahan AR-180 diperiksa kembali. Perubahan itu menambahkan penerusan `kd_poli`, deteksi ejaan Phaco/Pacho, dan aturan JD operasi mata non-Phaco sebesar 19%.

Stack trace kejadian berhenti pada query baca laporan, sebelum kalkulasi JD. Tidak ditemukan bukti yang menghubungkan rumus JD 19% secara langsung dengan error `1615` ini. Investigasi difokuskan pada prepared statement, kesiapan struktur, dan penanganan exception.

## 4. Tahap 1 — Penanganan retry dan kegagalan aplikasi

Perubahan pada kode PHP:

1. Percobaan query baca laporan dibatasi maksimal **tiga kali**.
2. Khusus error `1615`/pesan reprepare, statement lama ditutup lalu query disiapkan ulang.
3. Diberikan jeda **0,1 detik** sebelum percobaan kedua dan **0,2 detik** sebelum percobaan ketiga.
4. Kesalahan SQL lain tidak otomatis diulang melalui mekanisme tersebut.
5. Retry hanya mencakup query `SELECT` laporan; proses simpan tidak diulang otomatis.
6. Aksi **Pakai Riwayat** dan **Hitung** diberi penanganan exception.
7. Jika gagal, aplikasi menyimpan notifikasi kegagalan, mempertahankan filter dan posisi halaman, lalu kembali melalui alur redirect yang sudah ada.
8. Detail error dicatat pada log PHP; detail SQL/stack trace tidak diteruskan ke notifikasi pengguna dari penanganan ini.

Pengujian tahap 1 dilakukan secara offline menggunakan simulasi koneksi/statement. Skenario mencakup query normal, error 1615 satu atau dua kali, kegagalan seluruh percobaan, kegagalan saat prepare, error SQL lain, aksi sukses/gagal, serta pemeriksaan hak akses dan filter.

**Hasil:** pemeriksaan sintaks dan simulasi lulus, tanpa koneksi database pelayanan.

## 5. Tahap 2 — Pemisahan validasi struktur dan pemeliharaan tabel

Persiapan struktur otomatis diganti menjadi validasi baca saja melalui `SHOW COLUMNS`.

Perilaku setelah perubahan:

- Aplikasi memeriksa keberadaan kolom wajib dan primary key pada dua tabel penyimpanan laporan.
- Hasil validasi sukses digunakan kembali selama request yang sama, terpisah untuk setiap objek koneksi.
- Jika struktur belum siap, proses dihentikan dan pengguna diminta menghubungi administrator.
- Pesan tersebut diteruskan pada tampilan laporan, aksi per baris, kalkulasi harian, impor apotek, dan penanganan ekspor.
- Kalkulasi harian memeriksa kesiapan struktur sebelum membaca daftar pasien.
- Definisi kolom dan nilai default dipindahkan ke berkas bersama agar aplikasi dan pemeliharaan menggunakan acuan yang sama.

Skrip pemeliharaan CLI disiapkan secara terpisah:

| Mode | Perilaku |
|---|---|
| Default / `--plan` | Mencetak definisi SQL tanpa koneksi database |
| `--check` | Membuka koneksi untuk membaca metadata dan mencetak rencana; tidak menerapkan perubahan |
| `--apply` | Memerlukan target server/database eksplisit dan dapat menerapkan DDL; penggunaannya tetap membutuhkan persetujuan khusus |

Skrip menolak akses melalui browser. Primary key yang berbeda tidak diperbaiki otomatis; kondisi tersebut memerlukan peninjauan manual. Mode penerapan tidak dijalankan dalam penanganan ini.

**Hasil pengujian:** 65 pemeriksaan offline lulus dan seluruh berkas PHP terkait lolos pemeriksaan sintaks. Simulasi mencakup struktur lengkap, tabel/kolom hilang, primary key tidak sesuai, cache validasi antarkoneksi, penolakan SQL di luar pembacaan metadata pada fixture, notifikasi aksi, dan pembatasan mode CLI.

Mode CLI tanpa database diuji dengan pembatasan akses berkas yang mencegah pemuatan `config/koneksi.php`. Tidak ada koneksi database pelayanan dalam pengujian tahap 2.

## 6. Tahap 3 — Pemeriksaan baca pada database pelayanan

Setelah pengguna menyetujui pemeriksaan baca saja, dilakukan pengecekan metadata dan pengujian backend terhadap `192.168.1.4` / `sik9`.

Pengujian laporan dijalankan dalam **transaksi READ ONLY**, lalu transaksi ditutup dengan rollback. Rollback pada tahap ini menutup transaksi baca; tidak ada uji INSERT/UPDATE di dalamnya.

### 6.1 Hasil uji backend

Filter tanggal: **12 Oktober 2026**.

| Pemeriksaan | Hasil | Waktu terukur |
|---|---|---:|
| Validasi struktur kedua tabel penyimpanan | Berhasil | 0,002 detik |
| Pembacaan halaman pertama, 10 baris | Berhasil | 0,273 detik |
| Pembacaan halaman kedua, 10 baris | Berhasil | 1,356 detik |
| Pembacaan ulang halaman pertama, 10 baris | Berhasil | 0,038 detik |
| Pembacaan jumlah kunjungan | Berhasil, 265 kunjungan | 0,003 detik |
| Pembacaan summary | Berhasil | 0,009 detik |

Tidak ada error `1615` yang muncul pada pengujian tersebut. Waktu di atas merupakan durasi pemanggilan backend saat sampel diambil, bukan jaminan waktu respons untuk semua periode atau semua pengguna.

### 6.2 Hasil dua sampel statistik

Durasi pengamatan: **56 detik**, dihitung dari selisih nilai `Uptime` server.

| Statistik global | Sampel awal | Sampel akhir | Selisih |
|---|---:|---:|---:|
| `Com_stmt_reprepare` | 971.798 | 971.798 | 0 |
| `Com_stmt_prepare` | 39.636.553 | 39.636.679 | 126 |
| `Com_stmt_execute` | 67.138.256 | 67.138.382 | 126 |
| `Opened_tables` | 13.541.127.166 | 13.541.166.477 | 39.311 |
| `Opened_table_definitions` | 13.537.655.120 | 13.537.694.339 | 39.219 |
| `Open_tables` | 2.000 | 2.000 | 0 |
| `Open_table_definitions` | 1.986 | 1.995 | 9 |

Konfigurasi yang dibaca kembali pada akhir pemeriksaan tetap:

| Parameter | Nilai |
|---|---:|
| `table_definition_cache` | 400 |
| `table_open_cache` | 2.000 |
| `innodb_open_files` | 2.000 |
| `open_files_limit` | 4.411 |

Interpretasi:

- Tidak teramati tambahan reprepare otomatis selama interval sampel.
- `Com_stmt_reprepare` adalah penghitung penyiapan ulang otomatis, bukan penghitung seluruh fatal error aplikasi.
- Aktivitas pembukaan ulang tabel/definisi masih tinggi dan memerlukan evaluasi lebih lanjut.
- Statistik bersifat global: mencakup aktivitas aplikasi lain serta pemeriksaan ini. Selisih tidak dapat diatribusikan seluruhnya kepada APTD.
- Tidak adanya error dalam pengamatan singkat tidak membuktikan gangguan sudah hilang permanen.

## 7. Berkas yang berubah atau ditambahkan

Path berikut relatif terhadap `C:\xampp\htdocs\aptd_rspi`.

| Berkas | Peran |
|---|---|
| [laporan_keuangan_ralan_helper.php](../../main_app/page/t_non_klinis/keuangan/laporan_keuangan_ralan_helper.php) | Retry baca laporan, penggunaan validasi bersama, preflight kalkulasi harian |
| [laporan_keuangan_ralan.php](../../main_app/page/t_non_klinis/keuangan/laporan_keuangan_ralan.php) | Penanganan exception, notifikasi, pemeriksaan kesiapan halaman |
| [export_laporan_keuangan_ralan.php](../../main_app/page/t_non_klinis/keuangan/export_laporan_keuangan_ralan.php) | Penanganan kegagalan ekspor dan pesan kesiapan struktur |
| [laporan_keuangan_ralan_schema.php](../../main_app/page/t_non_klinis/keuangan/laporan_keuangan_ralan_schema.php) | Definisi bersama dan validasi metadata baca saja |
| [keuangan_ralan_schema.php](../../maintenance/keuangan_ralan_schema.php) | Skrip pemeliharaan CLI terpisah |
| [README-keuangan-ralan.md](../../maintenance/README-keuangan-ralan.md) | Panduan mode pemeliharaan dan persetujuan perubahan |
| [keuangan_ralan_schema_test.php](../../tests/keuangan_ralan_schema_test.php) | Pengujian regresi offline |

Kode pada workspace telah diperiksa. Pengujian tahap 3 memanggil backend dari workspace tersebut terhadap database aktif. Pengujian browser dengan akun pengguna serta verifikasi terpisah bahwa seluruh berkas telah terpasang pada server web di foto **belum dilakukan**.

## 8. Tindakan yang tidak dilakukan dalam penanganan insiden ini

- Tidak menjalankan INSERT, UPDATE, DELETE, atau penghitungan ulang data pelayanan sebagai bagian dari diagnosis tahap 1–3.
- Tidak menjalankan tombol Hitung, Hitung Ulang, Hitung Data Harian, atau impor terhadap database pelayanan selama pemeriksaan.
- Tidak menjalankan CREATE TABLE, ALTER TABLE, DROP, atau TRUNCATE pada database pelayanan.
- Tidak menjalankan mode penerapan `--apply` pada skrip pemeliharaan.
- Tidak menjalankan SET GLOBAL, mengubah nilai cache, atau mengedit konfigurasi server database.
- Tidak menjalankan FLUSH TABLES atau restart layanan database.

Catatan transparansi riwayat: sebelum insiden ini, pengujian tiket AR-175 dan AR-180 pernah memakai uji INSERT/UPDATE pada tabel hasil dalam transaksi yang kemudian di-rollback, dan sisa penanda uji diverifikasi 0 baris. Pengujian lama tersebut berbeda dari pemeriksaan baca saja pada penanganan insiden ini.

## 9. Status akhir dan tindak lanjut

**Yang sudah selesai:** penguatan penanganan error pada kode, pemisahan pemeliharaan struktur, pemeriksaan sintaks, pengujian offline, dan sampel pengujian backend baca saja.

**Yang belum dinyatakan selesai:** pembuktian bahwa error tidak pernah berulang, evaluasi kapasitas cache menyeluruh, perubahan konfigurasi database, dan uji browser pada server web yang digunakan pengguna.

Tindak lanjut yang disarankan:

1. Verifikasi berkas baru ikut terpasang bersama perubahan helper dan halaman pada server web yang dituju.
2. Pantau penggunaan normal: membuka laporan, pencarian, dan perpindahan halaman; kumpulkan waktu, filter, aksi, serta pesan error jika terulang.
3. Pantau log PHP dan statistik cache pada interval penggunaan yang representatif. Jangan menyimpulkan kebutuhan konfigurasi hanya dari satu sampel.
4. Jika diperlukan penyesuaian server, susun usulan parameter, nilai lama dan baru, dampak lintas aplikasi, kebutuhan sumber daya, waktu pelaksanaan, serta rencana pemulihan.
5. Tunggu konfirmasi khusus pengguna sebelum menjalankan perubahan database atau konfigurasi server, terutama selama pelayanan berlangsung.

## 10. Referensi teknis

- [MySQL: Caching of Prepared Statements and Stored Programs](https://dev.mysql.com/doc/refman/8.0/en/statement-caching.html) — mekanisme penyiapan ulang ketika metadata berubah atau tabel keluar dari cache. Referensi mekanisme umum; versi server yang diperiksa adalah MariaDB 10.1.25.
- [MariaDB: Optimizing table_open_cache](https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/system-variables/optimizing-table_open_cache) — evaluasi pembukaan ulang tabel, penggunaan cache, serta kaitannya dengan batas file descriptor.

Dokumen ini merekam hasil penanganan sampai tahap pemeriksaan baca saja. Dokumen ini bukan persetujuan untuk menerapkan perubahan pada database.
