# README Dashboard Indikator Rawat Inap

Dashboard ini menghitung BOR, LOS, TOI, dan BTO menggunakan satu kontrak data bersama berdasarkan Juknis SIRS Revisi 6.3.

## Sumber data

- `kamar`: jumlah tempat tidur dan kelas perawatan.
- `bangsal`: nama unit rawat inap.
- `kamar_inap`: periode pemakaian kamar, tanggal masuk, tanggal keluar, dan status pulang.

## Filter umum

- Bulan dan tahun laporan.
- Bangsal, opsional.
- Jumlah hari periode mengikuti jumlah hari aktual bulan terpilih, termasuk 29 hari untuk Februari pada tahun kabisat.

## Jumlah tempat tidur

Tempat tidur dihitung dari baris unik `kamar.kd_kamar` dengan ketentuan:

- `kamar.statusdata = '1'`.
- `kamar.kd_bangsal <> 'test'`.

## Jumlah hari perawatan

Hari perawatan dihitung untuk setiap segmen pemakaian kamar yang overlap dengan bulan laporan:

```text
Hari Perawatan = (Tanggal Akhir Perawatan - Tanggal Awal Perawatan) + 1
```

Tanggal awal dan akhir dibatasi ke awal/akhir bulan laporan. Baris `Pindah Kamar` tetap dihitung sebagai pemakaian tempat tidur. Baris tanpa tanggal keluar hanya dianggap masih dirawat jika `stts_pulang = '-'`.

Agregasi kelas:

- VVIP
- VIP
- I
- II
- III
- Kelas Khusus (`Kelas Utama` atau kelas lain di luar lima kelas utama)

Sistem menampilkan validasi bahwa Jumlah Hari Perawatan tidak kurang dari Jumlah Lama Dirawat dan tidak melebihi kapasitas bed-days.

## Pasien keluar

Pasien keluar dihitung satu kali per `no_rawat` jika:

- Tanggal keluar berada di bulan laporan, termasuk pasien yang masuk pada bulan sebelumnya.
- `stts_pulang <> '-'`.
- `stts_pulang <> 'Pindah Kamar'`.
- Kamar terkait aktif dan bukan bangsal `test`.

## Jumlah lama dirawat

Jumlah lama dirawat hanya berasal dari pasien keluar pada bulan laporan:

```text
Lama Dirawat Pasien = Tanggal Keluar Akhir - Tanggal Masuk Pertama
```

Perhitungan tidak memakai nilai tersimpan `kamar_inap.lama`, sehingga perpindahan kamar pada satu `no_rawat` tidak memotong durasi rawat rumah sakit.

Dashboard juga menghitung Pasien Awal Bulan, Pasien Masuk, dan Pasien Pindahan untuk memvalidasi bahwa Jumlah Lama Dirawat tidak kurang dari akumulasi ketiga variabel alur pasien tersebut.

## Rumus indikator

```text
BOR = (Jumlah Hari Perawatan / (Jumlah Tempat Tidur x Jumlah Hari Periode)) x 100%

LOS = Jumlah Lama Dirawat / Jumlah Pasien Keluar

TOI = ((Jumlah Tempat Tidur x Jumlah Hari Periode) - Jumlah Hari Perawatan)
      / Jumlah Pasien Keluar

BTO = Jumlah Pasien Keluar / Jumlah Tempat Tidur
```

## Nilai ideal

- BOR: 60–85%.
- LOS: 6–9 hari.
- TOI: 1–3 hari.
- BTO: 2–4 kali per bulan.

Warna hijau menunjukkan nilai dalam rentang ideal. Warna merah menunjukkan nilai di luar rentang ideal. Jika denominator tidak tersedia, dashboard menampilkan status belum cukup data.
