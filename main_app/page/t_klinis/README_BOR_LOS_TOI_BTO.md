# README Indikator Rawat Inap: BOR, LOS, TOI, BTO

Dokumen ini menjelaskan dasar perhitungan indikator rawat inap yang dipakai pada menu Data Non Klinis aplikasi APTD RSPI.

## Sumber tabel

Halaman BOR, LOS, TOI, dan BTO mengambil data dari tabel SIMRS Khanza berikut:

- `bangsal`: master bangsal/unit rawat inap.
  - Kolom utama: `kd_bangsal`, `nm_bangsal`, `status`.
- `kamar`: master kamar/tempat tidur yang terhubung ke bangsal.
  - Kolom utama: `kd_kamar`, `kd_bangsal`.
- `kamar_inap`: riwayat pasien rawat inap per kamar.
  - Kolom utama: `no_rawat`, `kd_kamar`, `tgl_masuk`, `tgl_keluar`, `stts_pulang`, `lama`.

## Filter umum

Semua indikator memakai filter:

- Bulan dan tahun periode laporan.
- Bangsal, opsional. Jika kosong, data dihitung untuk semua bangsal aktif.
- Bangsal aktif diambil dari `bangsal.status = '1'`.

Data pasien pindah kamar tidak dihitung sebagai pasien keluar rumah sakit. Karena itu baris `kamar_inap` dengan `stts_pulang = 'Pindah Kamar'` dikeluarkan dari perhitungan pasien keluar.

## BOR (Bed Occupancy Rate)

Rumus:

```text
BOR = (Jumlah Hari Perawatan / (Jumlah Tempat Tidur x Jumlah Hari dalam Periode)) x 100%
```

Logika data:

- Jumlah tempat tidur dihitung dari `COUNT(DISTINCT kamar.kd_kamar)` per bangsal.
- Jumlah hari dalam periode adalah jumlah hari pada bulan yang dipilih.
- Jumlah hari perawatan dihitung dari hari rawat pasien yang overlap dengan periode laporan.
- Jika pasien masuk sebelum periode dan keluar di dalam/setelah periode, hanya hari dalam periode yang dihitung.
- Jika pasien masih dirawat, tanggal keluar dianggap sampai akhir periode laporan.

Makna umum:

BOR menunjukkan persentase pemakaian tempat tidur. Semakin tinggi BOR, semakin besar tingkat keterisian tempat tidur.

## LOS (Length of Stay)

Rumus:

```text
LOS = Jumlah Lama Dirawat Pasien Keluar (Hidup + Meninggal) / Jumlah Pasien Keluar (Hidup + Meninggal)
```

Logika data:

- Pasien keluar diambil dari `kamar_inap.tgl_keluar` dalam periode laporan.
- Pasien dengan `stts_pulang = 'Pindah Kamar'`, `'-'`, atau kosong tidak dihitung sebagai pasien keluar rumah sakit.
- Lama dirawat diambil dari `kamar_inap.lama`.
- Kode penyakit, diagnosa, atau jenis bayar tidak memengaruhi LOS pada halaman ini.

Makna umum:

LOS menunjukkan rata-rata lama pasien dirawat sampai keluar dari rumah sakit.

## TOI (Turn Over Interval)

Rumus:

```text
TOI = ((Jumlah Tempat Tidur x Jumlah Hari dalam Periode) - Jumlah Hari Perawatan) / Jumlah Pasien Keluar
```

Logika data:

- Jumlah tempat tidur dan jumlah hari perawatan memakai dasar BOR.
- Jumlah pasien keluar memakai dasar LOS.
- Jika tidak ada pasien keluar, TOI ditampilkan 0 untuk menghindari pembagian dengan nol.

Makna umum:

TOI menunjukkan rata-rata lama tempat tidur kosong sebelum dipakai pasien berikutnya.

## BTO (Bed Turn Over)

Rumus:

```text
BTO = Jumlah Pasien Keluar (Hidup + Meninggal) / Jumlah Tempat Tidur
```

Logika data:

- Jumlah pasien keluar memakai dasar LOS.
- Jumlah tempat tidur memakai `COUNT(DISTINCT kamar.kd_kamar)`.
- Jika jumlah tempat tidur 0, BTO ditampilkan 0.

Makna umum:

BTO menunjukkan berapa kali rata-rata satu tempat tidur dipakai oleh pasien keluar dalam periode laporan.

## Catatan pertanggungjawaban

Angka indikator ini bergantung pada kedisiplinan input data `kamar_inap`, terutama:

- `tgl_masuk`
- `tgl_keluar`
- `stts_pulang`
- `lama`
- relasi `kamar.kd_bangsal`

Jika data pasien pindah kamar, pasien belum keluar, atau status pulang tidak konsisten, hasil indikator dapat berubah. Untuk audit, validasi dapat dilakukan dengan membandingkan baris detail `kamar_inap` pada periode yang sama.
