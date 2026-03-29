# APTD RSPI - Panduan Pengembangan

## Gambaran singkat alur aplikasi

Aplikasi ini memakai alur sederhana seperti berikut:

1. `index.php`
   Mengarahkan user ke halaman login.
2. `login/login.php`
   Menampilkan form login dan form buat account.
3. `login/proses_login.php`
   Mengecek username dan password, lalu menyimpan data user ke `$_SESSION`.
4. `main_app/main_app.php`
   Menampilkan layout utama aplikasi dan menu navigasi.
5. `main_app/content.php`
   Memuat halaman berdasarkan parameter `?page=`.
6. `config/akses.php`
   Menentukan:
   - daftar route page
   - hak akses tiap level user

## Struktur folder utama

- `config/`
  Berisi konfigurasi database dan hak akses.
- `login/`
  Berisi login, logout, proses login, dan proses registrasi.
- `main_app/`
  Berisi layout utama aplikasi.
- `main_app/page/`
  Berisi semua halaman laporan / modul.

Contoh subfolder di dalam `main_app/page/`:

- `t_diare/`
- `t_kunjungan/`
- `t_kunjungan_perkab/`
- `t_kunjungan_berdasarkan_usia/`
- `t_10_penyakit/`

## File penting yang wajib dipahami

### 1. `config/koneksi.php`
Dipakai untuk koneksi database.

### 2. `config/akses.php`
Ini file paling penting untuk role dan route.

Di file ini ada 3 fungsi utama:

- `aptd_get_routes()`
  Daftar semua page yang bisa dibuka.
- `aptd_get_access_map()`
  Daftar hak akses tiap `level`.
- `aptd_can_access($level, $page)`
  Mengecek apakah suatu level boleh membuka page tertentu.

### 3. `main_app/main_app.php`
Dipakai untuk menampilkan menu navbar.
Kalau page tidak dimunculkan di sini, user tidak akan melihat menu itu walaupun route-nya ada.

### 4. `main_app/content.php`
Dipakai untuk memuat file halaman berdasarkan `?page=`.
Sekarang file ini sudah otomatis cek route dan hak akses.

### 5. `login/proses_register.php`
Dipakai saat membuat account baru.
Kalau menambah level user baru, file ini juga harus ikut diupdate.

---

# Cara menambah level user baru

Contoh: ingin menambah level baru bernama `farmasi`.

## Langkah 1 - Ubah struktur tabel `tb_users`
Kolom `level` sekarang bertipe `enum`, jadi level baru harus ditambahkan juga di database.

Contoh SQL:

```sql
ALTER TABLE tb_users
MODIFY level ENUM('admin','manajemen','kepegawaian','medis','non medis','users','farmasi') DEFAULT NULL;
```

Kalau level baru tidak ditambahkan ke database, data tidak akan bisa disimpan dengan benar.

## Langkah 2 - Tambahkan level di `login/proses_register.php`
Cari variabel berikut:

```php
$allowedLevels = ['admin', 'manajemen', 'kepegawaian', 'medis', 'non medis', 'users'];
```

Lalu tambahkan level baru:

```php
$allowedLevels = ['admin', 'manajemen', 'kepegawaian', 'medis', 'non medis', 'users', 'farmasi'];
```

## Langkah 3 - Tambahkan level di form register `login/login.php`
Cari bagian ini:

```php
$opsiLevel = ['admin', 'manajemen', 'kepegawaian', 'medis', 'non medis', 'users'];
```

Tambahkan level baru:

```php
$opsiLevel = ['admin', 'manajemen', 'kepegawaian', 'medis', 'non medis', 'users', 'farmasi'];
```

Kalau tidak ditambahkan di sini, level baru tidak akan muncul di dropdown form Buat Account.

## Langkah 4 - Tambahkan hak akses di `config/akses.php`
Cari fungsi `aptd_get_access_map()` lalu tambahkan mapping level baru.

Contoh:

```php
'farmasi' => [
    'beranda',
    'diare_data',
    '10_penyakit_ralan',
],
```

Kalau level baru tidak dimasukkan ke access map, user level tersebut tidak akan bisa membuka menu apa pun.

## Checklist tambah level baru

Kalau menambah level baru, cek 4 hal ini:

1. Database `tb_users.level`
2. `login/proses_register.php`
3. `login/login.php`
4. `config/akses.php`

---

# Cara menambah halaman baru

Contoh: ingin menambah halaman baru bernama `laporan_farmasi.php`.

## Langkah 1 - Tentukan folder halaman
Misalnya file mau disimpan di:

- `main_app/page/t_farmasi/laporan_farmasi.php`

Kalau folder `t_farmasi` belum ada, buat dulu foldernya.

## Langkah 2 - Buat file halaman
Contoh sederhana isi file:

```php
<?php
echo '<h3>Laporan Farmasi</h3>';
?>
```

## Langkah 3 - Daftarkan route di `config/akses.php`
Cari fungsi `aptd_get_routes()` lalu tambahkan page baru.

Contoh:

```php
'laporan_farmasi' => 'page/t_farmasi/laporan_farmasi.php',
```

Keterangan:

- kiri: nama page yang dipanggil dari URL
- kanan: lokasi file relatif dari folder `main_app/`

Contoh URL nanti:

```text
main_app.php?page=laporan_farmasi
```

Kalau route belum didaftarkan, halaman tidak akan bisa dibuka.

## Langkah 4 - Tambahkan hak akses page baru di `config/akses.php`
Cari fungsi `aptd_get_access_map()` lalu tambahkan page tersebut ke level yang boleh mengakses.

Contoh:

```php
'admin' => ['*'],
'medis' => [
    'beranda',
    'laporan_farmasi',
],
```

Kalau tidak ditambahkan ke access map, halaman akan ditolak walaupun file dan route sudah ada.

## Langkah 5 - Tambahkan menu di `main_app/main_app.php`
Kalau halaman ingin muncul di navbar, tambahkan di menu.

Ada 2 pola menu saat ini:

### A. Menu link biasa
Contoh:

```php
<?php renderMenuLink('laporan_farmasi', 'Laporan Farmasi', $page); ?>
```

### B. Menu dropdown
Kalau mau masuk ke dropdown, tambahkan ke array menu terkait.

Contoh:

```php
$menuFarmasi = [
    [
        ['page' => 'laporan_farmasi', 'label' => 'Laporan Farmasi'],
    ],
];
```

Lalu render:

```php
<?php renderDropdownMenu('navbarDropdownFarmasi', 'Master Farmasi', $menuFarmasi, $page); ?>
```

Kalau tidak ditambahkan di `main_app.php`, halaman tetap bisa dibuka lewat URL manual selama route dan aksesnya ada.

## Checklist tambah halaman baru

Kalau menambah halaman baru, cek 4 hal ini:

1. Buat file di `main_app/page/...`
2. Tambah route di `config/akses.php`
3. Tambah hak akses page di `config/akses.php`
4. Tambah menu di `main_app/main_app.php` jika ingin tampil di navbar

---

# Cara menambah folder baru di dalam `main_app/page`

Contoh: mau buat folder baru `t_farmasi`.

## Yang harus dilakukan

1. Buat folder:

```text
main_app/page/t_farmasi/
```

2. Simpan file-file halaman di dalam folder itu.

Contoh:

- `main_app/page/t_farmasi/laporan_farmasi.php`
- `main_app/page/t_farmasi/export_farmasi.php`

3. Daftarkan semua file page yang memang bisa diakses user ke `config/akses.php` pada fungsi `aptd_get_routes()`.

Contoh:

```php
'laporan_farmasi' => 'page/t_farmasi/laporan_farmasi.php',
'export_farmasi' => 'page/t_farmasi/export_farmasi.php',
```

4. Daftarkan aksesnya di `aptd_get_access_map()`.

Kalau folder baru dibuat tetapi route tidak ditambahkan, file tersebut tidak akan pernah dipakai oleh sistem.

---

# Cara menambah file export baru

Misalnya membuat file export Excel baru.

Contoh file:

- `main_app/page/t_farmasi/export_farmasi.php`

## Yang perlu ditambahkan

1. File export-nya
2. Route di `config/akses.php`
3. Hak akses di `config/akses.php`
4. Tombol / link dari halaman utama yang mengarah ke export tersebut

Contoh route:

```php
'export_farmasi' => 'page/t_farmasi/export_farmasi.php',
```

---

# Urutan berpikir saat menambah fitur baru

Supaya aman, pakai urutan ini:

1. Buat file halaman dulu
2. Daftarkan route di `config/akses.php`
3. Tentukan level mana saja yang boleh akses
4. Tambahkan menu di `main_app/main_app.php`
5. Tes login dengan level yang berbeda

---

# Contoh kasus lengkap

## Kasus
Ingin membuat menu baru `Laporan Farmasi` yang hanya bisa dibuka oleh `admin` dan `farmasi`.

## Langkah

### 1. Buat folder dan file

- `main_app/page/t_farmasi/laporan_farmasi.php`

### 2. Tambah route di `config/akses.php`

```php
'laporan_farmasi' => 'page/t_farmasi/laporan_farmasi.php',
```

### 3. Tambah level baru jika belum ada

- update enum database
- update `login/login.php`
- update `login/proses_register.php`
- update `config/akses.php`

### 4. Tambah hak akses

```php
'farmasi' => [
    'beranda',
    'laporan_farmasi',
],
```

### 5. Tambah menu di `main_app/main_app.php`

```php
<?php renderMenuLink('laporan_farmasi', 'Laporan Farmasi', $page); ?>
```

---

# Tips penting

- `admin` sekarang memakai `['*']`, artinya boleh semua route yang ada di `aptd_get_routes()`.
- Kalau page baru belum muncul, cek dulu apakah route sudah ditambahkan.
- Kalau page muncul tapi saat dibuka ditolak, cek `aptd_get_access_map()`.
- Kalau level baru tidak bisa disimpan saat register, cek enum database dan `$allowedLevels`.
- Kalau level baru tidak muncul di modal Buat Account, cek `$opsiLevel` di `login/login.php`.
- Jangan hanya membuat file saja. Di proyek ini file baru hampir selalu butuh update route dan hak akses.

---

# Rekomendasi pengembangan berikutnya

Kalau aplikasi makin besar, nanti bagus kalau dinaikkan ke tahap berikut:

1. Hak akses disimpan di database, bukan hardcode di file.
2. Menu dibuat dari konfigurasi pusat, bukan manual per blok.
3. Dibuat halaman khusus `403 Forbidden` agar tampilan akses ditolak lebih rapi.
4. Registrasi account dibatasi, misalnya hanya admin yang boleh membuat user baru.

---

# Ringkasan super singkat

## Kalau tambah level baru:

Ubah ini:

- database `tb_users.level`
- `login/login.php`
- `login/proses_register.php`
- `config/akses.php`

## Kalau tambah halaman baru:

Ubah ini:

- buat file di `main_app/page/...`
- `config/akses.php` bagian route
- `config/akses.php` bagian access map
- `main_app/main_app.php` kalau mau tampil di menu

## Kalau tambah folder baru:

Ubah ini:

- buat folder di `main_app/page/...`
- simpan file di dalam folder itu
- daftarkan route file-file tersebut di `config/akses.php`

