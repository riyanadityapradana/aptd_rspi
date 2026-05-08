# Kunjungan Pasien Berdasarkan Kecamatan

Dokumentasi ini menjelaskan fitur laporan kunjungan pasien berdasarkan kecamatan untuk rawat jalan dan rawat inap.

Fitur ini dibuat untuk menampilkan rekap pasien dari tiga wilayah:

- Banjarmasin
- Banjarbaru
- Kabupaten Banjar

Setiap wilayah direkap berdasarkan jenis pembayaran:

- Umum (`A09`)
- Asuransi (`A92`)
- BPJS (`BPJ`)

## File Yang Digunakan

### Helper query bersama

```text
main_app/page/t_kunjungan/kunjungan_kecamatan_helper.php
```

File ini menjadi pusat query dan konfigurasi wilayah. Rawat jalan, rawat inap, dan export Excel memakai helper yang sama supaya angka di website dan Excel tetap sama.

### Halaman rawat jalan

```text
main_app/page/t_kunjungan/rawat_jalan/kunjungan_data_kecamatan_ralan.php
```

Page route:

```text
kunjungan_data_kecamatan_ralan
```

### Halaman rawat inap

```text
main_app/page/t_kunjungan/rawat_inap/kunjungan_data_kecamatan_ranap.php
```

Page route:

```text
kunjungan_data_kecamatan_ranap
```

### Export rawat jalan

```text
main_app/page/t_kunjungan/rawat_jalan/export_kunjungan_kecamatan_ralan.php
```

### Export rawat inap

```text
main_app/page/t_kunjungan/rawat_inap/export_kunjungan_kecamatan_ranap.php
```

## Menu Dan Akses

Menu ditambahkan di:

```text
main_app/main_app.php
```

Route dan hak akses ditambahkan di:

```text
config/akses.php
```

Route yang digunakan:

```php
'kunjungan_data_kecamatan_ralan' => 'page/t_kunjungan/rawat_jalan/kunjungan_data_kecamatan_ralan.php',
'export_kunjungan_kecamatan_ralan' => 'page/t_kunjungan/rawat_jalan/export_kunjungan_kecamatan_ralan.php',

'kunjungan_data_kecamatan_ranap' => 'page/t_kunjungan/rawat_inap/kunjungan_data_kecamatan_ranap.php',
'export_kunjungan_kecamatan_ranap' => 'page/t_kunjungan/rawat_inap/export_kunjungan_kecamatan_ranap.php',
```

## Filter Di Website

Setiap halaman memiliki filter:

- Bulan
- Tahun
- Wilayah

Pilihan wilayah:

- Semua Wilayah
- Banjarmasin
- Banjarbaru
- Kabupaten Banjar

Jika memilih `Banjarbaru`, maka angka kartu total, tabel, dan export hanya menghitung Banjarbaru.

## Periode Tanggal

Filter bulan memakai pola tanggal seperti ini:

```sql
rp.tgl_registrasi >= '2026-01-01'
AND rp.tgl_registrasi < '2026-02-01'
```

Contoh di atas berarti seluruh Januari 2026 dihitung, termasuk tanggal 31 Januari 2026.

Jangan memakai:

```sql
rp.tgl_registrasi < '2026-01-31'
```

Karena itu tidak menghitung data tanggal 31 Januari.

## Konfigurasi Wilayah

Konfigurasi wilayah ada di fungsi:

```php
function aptd_kecamatan_wilayah_config()
```

Potongan kode:

```php
function aptd_kecamatan_wilayah_config()
{
    return [
        'Banjarmasin' => [
            'kab_like' => '%masin%',
            'kecamatan_like' => [
                '%Banjarmasin Selatan%',
                '%Banjarmasin Barat%',
                '%Banjarmasin Tengah%',
                '%Banjarmasin Timur%',
                '%Banjarmasin Utara%',
            ],
        ],
        'Banjarbaru' => [
            'kab_like' => '%baru%',
            'kecamatan_like' => [
                '%LANDASAN%',
                '%CEMPAKA%',
                '%BANJARBARU UTARA%',
                '%BANJARBARU SELATAN%',
                '%LIANG ANGGANG%',
            ],
        ],
        'Kabupaten Banjar' => [
            'kab_like' => '%banjar%',
            'kecamatan_like' => [
                '%ALUH%',
                '%ARANIO%',
                '%Astambul%',
                '%Beruntung Baru%',
                '%Cintapuri Darussalam%',
                '%Karang Intan%',
                '%Kertak Hanyar%',
                '%Mataraman%',
                '%Martapura%',
                '%Martapura Barat%',
                '%Martapura Timur%',
                '%Paramasan%',
                '%Pengaron%',
                '%Sambung Makmur%',
                '%Simpang Empat%',
                '%Sungai Pinang%',
                '%Sungai Tabuk%',
                '%Tatah Makmur%',
                '%Telaga Bauntung%',
                '%GAMBUT%',
            ],
        ],
    ];
}
```

## Query Rawat Jalan

Query rawat jalan dibentuk di helper `aptd_kecamatan_fetch()` saat parameter `$jenisRawat` bernilai `ralan`.

Struktur query rawat jalan:

```sql
SELECT
  kec.nm_kec,
  CASE rp.kd_pj
    WHEN 'A09' THEN 'Umum'
    WHEN 'A92' THEN 'Asuransi'
    WHEN 'BPJ' THEN 'BPJS'
  END AS kategori,
  COUNT(DISTINCT rp.no_rawat) AS total
FROM reg_periksa rp
JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
JOIN penjab pj ON pj.kd_pj = rp.kd_pj
JOIN kabupaten kab ON kab.kd_kab = p.kd_kab
JOIN kecamatan kec ON kec.kd_kec = p.kd_kec
WHERE rp.tgl_registrasi >= ?
    AND rp.tgl_registrasi < ?
    AND rp.status_lanjut = 'Ralan'
    AND rp.stts <> 'Batal'
    AND kab.nm_kab LIKE '$kabLike'
    AND ($kecamatanWhere)
    AND rp.kd_pj IN ('A09','A92','BPJ')
GROUP BY kec.nm_kec, kategori
ORDER BY kec.nm_kec, FIELD(kategori,'Umum','Asuransi','BPJS')
```

Contoh query SQL Yog untuk Banjarbaru rawat jalan Januari 2026:

```sql
SELECT
  kec.nm_kec,
  CASE rp.kd_pj
    WHEN 'A09' THEN 'Umum'
    WHEN 'A92' THEN 'Asuransi'
    WHEN 'BPJ' THEN 'BPJS'
  END AS kategori,
  COUNT(DISTINCT rp.no_rawat) AS total
FROM reg_periksa rp
JOIN pasien p      ON p.no_rkm_medis = rp.no_rkm_medis
JOIN penjab pj     ON pj.kd_pj = rp.kd_pj
JOIN kabupaten kab ON kab.kd_kab = p.kd_kab
JOIN kecamatan kec ON kec.kd_kec = p.kd_kec
WHERE
    rp.tgl_registrasi >= '2026-01-01'
    AND rp.tgl_registrasi < '2026-02-01'
    AND rp.status_lanjut = 'Ralan'
    AND rp.stts <> 'Batal'
    AND kab.nm_kab LIKE '%baru%'
    AND (
      kec.nm_kec LIKE '%LANDASAN%' OR
      kec.nm_kec LIKE '%CEMPAKA%' OR
      kec.nm_kec LIKE '%BANJARBARU UTARA%' OR
      kec.nm_kec LIKE '%BANJARBARU SELATAN%' OR
      kec.nm_kec LIKE '%LIANG ANGGANG%'
    )
    AND rp.kd_pj IN ('A09','A92','BPJ')
GROUP BY kec.nm_kec, kategori
ORDER BY kec.nm_kec, FIELD(kategori,'Umum','Asuransi','BPJS');
```

## Query Rawat Inap

Query rawat inap juga dibentuk di helper `aptd_kecamatan_fetch()` saat parameter `$jenisRawat` bernilai `ranap`.

Perbedaan utamanya:

- `status_lanjut = 'Ranap'`
- join ke `kamar_inap`, `kamar`, dan `bangsal`
- filter `ki.stts_pulang NOT IN ('Pindah Kamar', '-')`

Struktur query rawat inap:

```sql
SELECT
  kec.nm_kec,
  CASE rp.kd_pj
    WHEN 'A09' THEN 'Umum'
    WHEN 'A92' THEN 'Asuransi'
    WHEN 'BPJ' THEN 'BPJS'
  END AS kategori,
  COUNT(DISTINCT rp.no_rawat) AS total
FROM reg_periksa rp
JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
JOIN penjab pj ON pj.kd_pj = rp.kd_pj
JOIN kamar_inap ki ON ki.no_rawat = rp.no_rawat
JOIN kamar k ON k.kd_kamar = ki.kd_kamar
JOIN bangsal b ON b.kd_bangsal = k.kd_bangsal
JOIN kabupaten kab ON kab.kd_kab = p.kd_kab
JOIN kecamatan kec ON kec.kd_kec = p.kd_kec
WHERE rp.tgl_registrasi >= ?
    AND rp.tgl_registrasi < ?
    AND rp.status_lanjut = 'Ranap'
    AND rp.stts <> 'Batal'
    AND ki.stts_pulang NOT IN ('Pindah Kamar', '-')
    AND kab.nm_kab LIKE '$kabLike'
    AND ($kecamatanWhere)
    AND rp.kd_pj IN ('A09','A92','BPJ')
GROUP BY kec.nm_kec, kategori
ORDER BY kec.nm_kec, FIELD(kategori,'Umum','Asuransi','BPJS')
```

Contoh query SQL Yog untuk Banjarbaru rawat inap Januari 2026:

```sql
SELECT
  kec.nm_kec,
  CASE rp.kd_pj
    WHEN 'A09' THEN 'Umum'
    WHEN 'A92' THEN 'Asuransi'
    WHEN 'BPJ' THEN 'BPJS'
  END AS kategori,
  COUNT(DISTINCT rp.no_rawat) AS total
FROM reg_periksa rp
JOIN pasien p      ON p.no_rkm_medis = rp.no_rkm_medis
JOIN penjab pj     ON pj.kd_pj = rp.kd_pj
JOIN kamar_inap ki ON ki.no_rawat = rp.no_rawat
JOIN kamar k       ON k.kd_kamar = ki.kd_kamar
JOIN bangsal b     ON b.kd_bangsal = k.kd_bangsal
JOIN kabupaten kab ON kab.kd_kab = p.kd_kab
JOIN kecamatan kec ON kec.kd_kec = p.kd_kec
WHERE
    rp.tgl_registrasi >= '2026-01-01'
    AND rp.tgl_registrasi < '2026-02-01'
    AND rp.status_lanjut = 'Ranap'
    AND rp.stts <> 'Batal'
    AND ki.stts_pulang NOT IN ('Pindah Kamar', '-')
    AND kab.nm_kab LIKE '%baru%'
    AND (
      kec.nm_kec LIKE '%LANDASAN%' OR
      kec.nm_kec LIKE '%CEMPAKA%' OR
      kec.nm_kec LIKE '%BANJARBARU UTARA%' OR
      kec.nm_kec LIKE '%BANJARBARU SELATAN%' OR
      kec.nm_kec LIKE '%LIANG ANGGANG%'
    )
    AND rp.kd_pj IN ('A09','A92','BPJ')
GROUP BY kec.nm_kec, kategori
ORDER BY kec.nm_kec, FIELD(kategori,'Umum','Asuransi','BPJS');
```

## Cara Website Mengambil Data

Halaman rawat jalan memanggil helper seperti ini:

```php
$report = aptd_kecamatan_fetch($conn, 'ralan', $startDate, $endDate, $selectedWilayah);
```

Halaman rawat inap memanggil helper seperti ini:

```php
$report = aptd_kecamatan_fetch($conn, 'ranap', $startDate, $endDate, $selectedWilayah);
```

Data yang dikembalikan helper:

```php
return [
    'wilayah_list' => $wilayahList,
    'data' => $data,
    'raw_rows' => $rawRows,
    'total_wilayah' => $totalWilayah,
    'total_kategori' => $totalKategori,
    'grand_total' => array_sum($totalKategori),
];
```

## Export Excel

Tombol export ada di masing-masing halaman laporan.

Rawat jalan:

```php
$exportAction = 'page/t_kunjungan/rawat_jalan/export_kunjungan_kecamatan_ralan.php';
```

Rawat inap:

```php
$exportAction = 'page/t_kunjungan/rawat_inap/export_kunjungan_kecamatan_ranap.php';
```

Export Excel menghasilkan 2 sheet:

### 1. `Pivot`

Sheet ini mengikuti tampilan website:

```text
No | Wilayah | Kecamatan | Umum | Asuransi | BPJS | Total
```

### 2. `Format SQLYog`

Sheet ini mengikuti hasil query SQL Yog:

```text
wilayah | nm_kec | kategori | total
```

Sheet `Format SQLYog` berguna untuk mencocokkan hasil website dengan hasil query manual di SQL Yog.

## Cara Mencocokkan Hasil Dengan SQL Yog

1. Buka halaman laporan.
2. Pilih bulan dan tahun yang sama dengan query SQL Yog.
3. Pilih wilayah yang sama, misalnya `Banjarbaru`.
4. Di SQL Yog, gunakan periode awal bulan sampai sebelum awal bulan berikutnya.
5. Bandingkan hasil SQL Yog dengan sheet Excel `Format SQLYog`.

Contoh periode Januari 2026:

```sql
rp.tgl_registrasi >= '2026-01-01'
AND rp.tgl_registrasi < '2026-02-01'
```

## Catatan Pengembangan

Jika ingin menambah wilayah baru:

1. Tambahkan wilayah di `aptd_kecamatan_wilayah_config()`.
2. Isi `kab_like` sesuai nama kabupaten/kota di tabel `kabupaten`.
3. Isi `kecamatan_like` sesuai daftar kecamatan di tabel `kecamatan`.
4. Tidak perlu mengubah halaman rawat jalan, rawat inap, atau export selama masih memakai helper yang sama.

Jika ingin menambah kategori pembayaran:

1. Tambahkan label di `aptd_kecamatan_payment_labels()`.
2. Tambahkan `CASE rp.kd_pj` di query helper.
3. Tambahkan kode penjamin di filter `rp.kd_pj IN (...)`.
4. Sesuaikan tampilan tabel dan export jika jumlah kolom kategori berubah.
