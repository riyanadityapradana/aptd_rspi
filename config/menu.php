<?php
function aptd_get_menu_definitions()
{
$menuKunjunganRalan = [
    [
        ['page' => 'kunjungan_data_ralan', 'label' => 'Kunjungan Pasien Rawat Jalan'],
        ['page' => 'kunjungan_data_perkab_ralan', 'label' => 'Kunjungan Pasien Rawat Jalan Perkab'],
        ['page' => 'kunjungan_data_per_minggu', 'label' => 'Kunjungan Pasien Rawat Jalan Per Minggu'],
        ['page' => 'top_10_poli_ralan', 'label' => 'Top 10 Poliklinik Pasien Tertinggi'],
    ],
    [
        ['page' => 'kunjungan_data_blmSEP', 'label' => 'Kunjungan Pasien Rawat Jalan Belum SEP'],
        ['page' => 'kunjungan_data_sdhSEP', 'label' => 'Kunjungan Pasien Rawat Jalan Sudah SEP'],
        // AR-171: menu kepatuhan BPJS lama disembunyikan sementara.
        // Ubah visible menjadi true untuk menampilkannya kembali tanpa mengubah route/fitur.
        ['page' => 'waktu_tunggu_poli_ralan', 'label' => 'Waktu Tunggu Poli BPJS', 'visible' => false],
        ['page' => 'waktu_tunggu_registrasi_perawat_ralan', 'label' => 'Waktu Tunggu Registrasi ke Perawat BPJS', 'visible' => false],
        ['page' => 'evaluasi_task4_bpjs', 'label' => 'Evaluasi Task ID 4 BPJS', 'visible' => false],
        ['page' => 'evaluasi_task4_terawal_bpjs', 'label' => 'Indikator Kesesuaian Jadwal (Task 4 Terawal)'],
        ['page' => 'indikator_waktu_tunggu_poli_task_2_5', 'label' => 'Indikator Waktu Tunggu Poli (Task ID 2-5)'],
    ],
    [
        ['page' => 'kunjungan_data_kecamatan_ralan', 'label' => 'Kunjungan Pasien Rawat Jalan Berdasarkan Kecamatan'],
    ],
];

$menuKunjunganRanap = [
    [
        ['page' => 'kunjungan_data_perkamar_ranap', 'label' => 'Kunjungan Pasien Rawat Inap Perkamar'],
        ['page' => 'kunjungan_data_harian_ranap', 'label' => 'Kunjungan Pasien Rawat Inap Harian'],
        ['page' => 'kunjungan_data_perkelas_bayar_ranap', 'label' => 'Tarikan Rawat Inap Per Jenis Kelas'],
        ['page' => 'top_10_kamar_ranap', 'label' => 'Top 10 Kamar Pasien Tertinggi'],
    ],
    [
        ['page' => 'kunjungan_data_blmSEP', 'label' => 'Kunjungan Pasien Rawat Inap Belum SEP'],
        ['page' => 'kunjungan_data_sdhSEP', 'label' => 'Kunjungan Pasien Rawat Inap Sudah SEP'],
    ],
    [
        ['page' => 'kunjungan_data_berdasarkanusia_ranap', 'label' => 'Kunjungan Pasien Rawat Inap Berdasarkan Usia'],
        ['page' => 'kunjungan_data_kecamatan_ranap', 'label' => 'Kunjungan Pasien Rawat Inap Berdasarkan Kecamatan'],
    ],
];

$menuPenyakit = [
    [
        ['page' => '10_penyakit_bedah_ralan', 'label' => '10 Penyakit Bedah Tertinggi Rawat Jalan'],
        ['page' => '10_penyakit_non_bedah_ralan', 'label' => '10 Penyakit Non Bedah Tertinggi Rawat Jalan'],
    ],
    [
        ['page' => '10_penyakit_ranap', 'label' => '10 Penyakit Tertinggi Rawat Inap'],
        ['page' => '10_penyakit_bedah_ranap', 'label' => '10 Penyakit Bedah Tertinggi Rawat Inap'],
        ['page' => '10_penyakit_non_bedah_ranap', 'label' => '10 Penyakit Non Bedah Tertinggi Rawat Inap'],
    ],
    [
        ['page' => 'data_pasien_kode_penyakit_bedah_ralan', 'label' => 'Data Pasien Kode Penyakit Bedah Ralan'],
        ['page' => 'data_pasien_kode_penyakit_non_bedah_ralan', 'label' => 'Data Pasien Kode Penyakit Non Bedah Ralan'],
        ['page' => 'data_pasien_kode_penyakit_bedah_ranap', 'label' => 'Data Pasien Kode Penyakit Bedah Ranap'],
        ['page' => 'data_pasien_kode_penyakit_non_bedah_ranap', 'label' => 'Data Pasien Kode Penyakit Non Bedah Ranap'],
        ['page' => 'kode_penyakit_ab_ranap', 'label' => 'Rekap Kode Penyakit A/B Rawat Inap'],
    ],
];

$menuGizi = [
    [
        ['page' => 'adime_gizi', 'label' => 'Monitoring ADIME Gizi Rawat Inap'],
        ['page' => 'kunjungan_data_berdasarkanusia_ralan', 'label' => 'Kunjungan Pasien Rawat Jalan Berdasarkan Usia'],
        ['page' => 'kunjungan_usia_ranap_gizi', 'label' => 'Kunjungan Rawat Inap Berdasarkan Usia'],
    ],
];

$menuDataKlinis = [
    [
        ['page' => '10_penyakit_ralan', 'label' => '10 Besar Penyakit Rawat Jalan'],
        ['page' => '10_penyakit_ralan_perpoli', 'label' => '10 Besar Penyakit Rawat Jalan Perpoli'],
        ['page' => '10_penyakit_ugd_ponek', 'label' => '10 Penyakit Terbanyak UGD & Ponek'],
        ['page' => 'laporan_bulanan_hais', 'label' => 'Laporan Bulanan Data HAIs'],
        ['page' => 'rekap_pasien_prmrj', 'label' => 'Rekap Pasien PRMRJ'],
    ],
];

$menuDataNonKlinis = [
    [
        ['page' => 'data_vaksinasi_haji', 'label' => 'Data Vaksinasi Haji'],
        ['page' => 'rekap_resep_fornas_non_fornas', 'label' => 'Rekap Item Obat Formularium'],
        ['page' => 'diare_data', 'label' => 'Data Pasien Diagnosa Diare'],
        ['page' => 'top_10_dokter_pasien', 'label' => 'Top 10 Dokter Paling Banyak Pasien'],
        ['page' => 'pasien_rujukan_masuk_keluar', 'label' => 'Pasien Rujukan Masuk / Keluar'],
        ['page' => 'kunjungan_wilayah_visual', 'label' => 'Kunjungan Berdasarkan Kecamatan/Kabupaten'],
        ['page' => 'kunjungan_kecamatan_mingguan_ralan', 'label' => 'Kunjungan Ralan Per Minggu Berdasarkan Kecamatan'],
        ['page' => 'kunjungan_kecamatan_mingguan_ranap', 'label' => 'Kunjungan Ranap Per Minggu Berdasarkan Kecamatan'],
    ],
    [
        ['page' => 'laporan_keuangan_ranap', 'label' => 'Laporan Keuangan Rawat Inap'],
        ['page' => 'laporan_keuangan_ralan', 'label' => 'Laporan Keuangan Rawat Jalan'],
    ],
    [
        ['page' => 'rekap_tindakan_lab', 'label' => 'Rekap Tindakan Pemeriksaan Laboratorium'],
        ['page' => 'rekap_tindakan_radiologi', 'label' => 'Rekap Tindakan Pemeriksaan Radiologi'],
        ['page' => 'rekap_tindakan_operasi', 'label' => 'Rekap Tindakan Operasi/Bedah'],
    ],
    [
        ['page' => 'kunjungan_data_perpoli', 'label' => 'Kunjungan Rawat Jalan Perpoli'],
        ['page' => 'kunjungan_data_igd', 'label' => 'Kunjungan UGD & Ponek'],
    ],
    [
        ['page' => 'indikator_rawat_inap', 'label' => 'Dashboard BOR, LOS, TOI, BTO'],
        ['page' => 'readme_indikator_rawat_inap', 'label' => 'README BOR, LOS, TOI, BTO'],
    ],
];

$menuSirs = [
    [
        ['page' => 'rl32_ranap', 'label' => 'RL 3.2 Ranap'],
        ['page' => 'rl34_pengunjung', 'label' => 'RL 3.4 Rekapitulasi Pengunjung'],
    ],
];

$menuAdmin = [
    [
        ['page' => 'users_admin', 'label' => 'CRUD User'],
    ],
];
    return [
        'menuKunjunganRalan' => $menuKunjunganRalan,
        'menuKunjunganRanap' => $menuKunjunganRanap,
        'menuPenyakit' => $menuPenyakit,
        'menuGizi' => $menuGizi,
        'menuDataKlinis' => $menuDataKlinis,
        'menuDataNonKlinis' => $menuDataNonKlinis,
        'menuSirs' => $menuSirs,
        'menuAdmin' => $menuAdmin,
    ];
}
