<?php
function aptd_get_routes()
{
    return [
        'beranda' => 'page/beranda.php',
        'diare_data' => 'page/t_diare/diare_data.php',
        'export_diare' => 'page/t_diare/export_diare.php',
        'adime_gizi' => 'page/t_gizi/adime_gizi.php',
        'export_adime_gizi' => 'page/t_gizi/export_adime_gizi.php',

        'kunjungan_data_ralan' => 'page/t_kunjungan/rawat_jalan/kunjungan_data_ralan.php',
        'kunjungan_data_perpoli' => 'page/t_kunjungan/rawat_jalan/kunjungan_data_perpoli.php',
        'kunjungan_data_igd' => 'page/t_kunjungan/rawat_jalan/kunjungan_data_igd.php',
        'kunjungan_data_per_minggu' => 'page/t_kunjungan/rawat_jalan/kunjungan_data_per_minggu.php',
        'top_10_poli_ralan' => 'page/t_kunjungan/rawat_jalan/top_10_poli_ralan.php',
        'waktu_tunggu_poli_ralan' => 'page/t_kunjungan/rawat_jalan/waktu_tunggu_poli_ralan.php',
        'waktu_tunggu_registrasi_perawat_ralan' => 'page/t_kunjungan/rawat_jalan/waktu_tunggu_registrasi_perawat_ralan.php',
        'export_kunjungan' => 'page/t_kunjungan/rawat_jalan/export_kunjungan.php',
        'export_kunjungan_igd' => 'page/t_kunjungan/rawat_jalan/export_kunjungan_igd.php',
        'export_kunjungan_ralan' => 'page/t_kunjungan/rawat_jalan/export_kunjungan_ralan.php',
        'export_kunjungan_per_minggu' => 'page/t_kunjungan/rawat_jalan/export_kunjungan_per_minggu.php',
        'kunjungan_data_perkab_ralan' => 'page/t_kunjungan_perkab/rawat_jalan/kunjungan_ralan_perkab.php',
        'export_kunjungan_perkab' => 'page/t_kunjungan_perkab/rawat_jalan/export_kunjungan_perkab.php',
        'export_blmSEP' => 'page/t_kunjungan/export_t_kunjungan_lanjutan.php',
        'export_sdhSEP' => 'page/t_kunjungan/export_t_kunjungan_lanjutan.php',
        'export_top_10_poli_ralan' => 'page/t_kunjungan/export_t_kunjungan_lanjutan.php',
        'kunjungan_data_blmSEP' => 'page/t_kunjungan/rawat_jalan/kunjungan_data_blmSEP.php',
        'kunjungan_data_sdhSEP' => 'page/t_kunjungan/rawat_jalan/kunjungan_data_sdhSEP.php',
        'kunjungan_data_berdasarkanusia_ralan' => 'page/t_kunjungan_berdasarkan_usia/rawat_jalan/kunjungan_data_berdasarkanusia_ralan.php',
        'kunjungan_data_kecamatan_ralan' => 'page/t_kunjungan/rawat_jalan/kunjungan_data_kecamatan_ralan.php',
        'export_kunjungan_kecamatan_ralan' => 'page/t_kunjungan/rawat_jalan/export_kunjungan_kecamatan_ralan.php',

        'kunjungan_data_perkamar_ranap' => 'page/t_kunjungan/rawat_inap/kunjungan_data_perkamar_ranap.php',
        'kunjungan_data_harian_ranap' => 'page/t_kunjungan/rawat_inap/kunjungan_data_harian_ranap.php',
        'kunjungan_data_perkelas_bayar_ranap' => 'page/t_kunjungan/rawat_inap/kunjungan_data_perkelas_bayar_ranap.php',
        'top_10_kamar_ranap' => 'page/t_kunjungan/rawat_inap/top_10_kamar_ranap.php',
        'kunjungan_data_berdasarkanusia_ranap' => 'page/t_kunjungan_berdasarkan_usia/rawat_inap/kunjungan_data_berdasarkanusia_ranap.php',
        'kunjungan_data_kecamatan_ranap' => 'page/t_kunjungan/rawat_inap/kunjungan_data_kecamatan_ranap.php',
        'export_kunjungan_kecamatan_ranap' => 'page/t_kunjungan/rawat_inap/export_kunjungan_kecamatan_ranap.php',

        '10_penyakit_ralan' => 'page/t_10_penyakit/rawat_jalan/10_penyakit_ralan.php',
        '10_penyakit_ralan_perpoli' => 'page/t_10_penyakit/rawat_jalan/10_penyakit_ralan_perpoli.php',
        '10_penyakit_bedah_ralan' => 'page/t_10_penyakit/rawat_jalan/10_penyakit_bedah_ralan.php',
        '10_penyakit_non_bedah_ralan' => 'page/t_10_penyakit/rawat_jalan/10_penyakit_non_bedah_ralan.php',
        '10_penyakit_ranap' => 'page/t_10_penyakit/rawat_inap/10_penyakit_ranap.php',
        '10_penyakit_bedah_ranap' => 'page/t_10_penyakit/rawat_inap/10_penyakit_bedah_ranap.php',
        '10_penyakit_non_bedah_ranap' => 'page/t_10_penyakit/rawat_inap/10_penyakit_non_bedah_ranap.php',
        'data_pasien_kode_penyakit_bedah_ralan' => 'page/t_kode_penyakit/rawat_jalan/data_pasien_kode_penyakit_bedah_ralan.php',
        'data_pasien_kode_penyakit_non_bedah_ralan' => 'page/t_kode_penyakit/rawat_jalan/data_pasien_kode_penyakit_non_bedah_ralan.php',
        'export_kode_penyakit' => 'page/t_kode_penyakit/export_kode_penyakit.php',
        'data_pasien_kode_penyakit_bedah_ranap' => 'page/t_kode_penyakit/rawat_inap/data_pasien_kode_penyakit_bedah_ranap.php',
        'data_pasien_kode_penyakit_non_bedah_ranap' => 'page/t_kode_penyakit/rawat_inap/data_pasien_kode_penyakit_non_bedah_ranap.php',
        'kode_penyakit_ab_ranap' => 'page/t_kode_penyakit/rawat_inap/kode_penyakit_ab_ranap.php',
        'export_kode_penyakit_ab_ranap' => 'page/t_kode_penyakit/rawat_inap/export_kode_penyakit_ab_ranap.php',

        'rekap_pasien_baru_lama' => 'page/t_analitik/umum/rekap_pasien_baru_lama.php',
        'top_10_dokter_pasien' => 'page/t_analitik/umum/top_10_dokter_pasien.php',
        'los_rawat_inap' => 'page/t_analitik/rawat_inap/los_rawat_inap.php',
        'bor_sederhana' => 'page/t_analitik/rawat_inap/bor_sederhana.php',
        'bor_rawat_inap' => 'page/t_klinis/bor_rawat_inap.php',
        'los_klinis_rawat_inap' => 'page/t_klinis/los_rawat_inap.php',
        'toi_rawat_inap' => 'page/t_klinis/toi_rawat_inap.php',
        'bto_rawat_inap' => 'page/t_klinis/bto_rawat_inap.php',
        'readme_indikator_rawat_inap' => 'page/t_klinis/readme_indikator_rawat_inap.php',
        'pasien_rujukan_masuk_keluar' => 'page/t_analitik/umum/pasien_rujukan_masuk_keluar.php',
        'kunjungan_wilayah_visual' => 'page/t_analitik/wilayah/kunjungan_wilayah_visual.php',
        'laporan_keuangan_ranap' => 'page/t_analitik/keuangan/laporan_keuangan_ranap.php',
        'input_data_claim' => 'page/t_analitik/keuangan/input_data_claim.php',
        'export_laporan_keuangan_ranap' => 'page/t_analitik/keuangan/export_laporan_keuangan_ranap.php',
        'rl32_ranap' => 'page/t_rl_32/rl_32_ranap.php',
        'users_admin' => 'page/t_admin/users.php',
    ];
}

function aptd_get_access_map()
{
    return [
        'admin' => ['*'],
        'manajemen' => [
            'beranda', 'kunjungan_data_ralan', 'kunjungan_data_perpoli', 'kunjungan_data_igd', 'kunjungan_data_per_minggu', 'top_10_poli_ralan',
            //'waktu_tunggu_poli_ralan',
            //'waktu_tunggu_registrasi_perawat_ralan',
            'kunjungan_data_perkab_ralan', 'kunjungan_data_blmSEP', 'kunjungan_data_sdhSEP', 'kunjungan_data_berdasarkanusia_ralan', 'kunjungan_data_kecamatan_ralan', 'export_kunjungan_kecamatan_ralan',
            'kunjungan_data_perkamar_ranap', 'kunjungan_data_harian_ranap', 'kunjungan_data_perkelas_bayar_ranap', 'top_10_kamar_ranap', 'kunjungan_data_berdasarkanusia_ranap', 'kunjungan_data_kecamatan_ranap', 'export_kunjungan_kecamatan_ranap',
            'export_kunjungan', 'export_kunjungan_igd', 'export_kunjungan_ralan', 'export_kunjungan_per_minggu', 'export_kunjungan_perkab', 'export_blmSEP', 'export_sdhSEP', 'export_top_10_poli_ralan', 'export_kunjungan_perkamar_usia_ranap', 'export_kunjungan_harian_ranap', 'export_kunjungan_perkelas_bayar_ranap', 'export_top_10_kamar_ranap',
            '10_penyakit_ralan', '10_penyakit_ralan_perpoli', '10_penyakit_bedah_ralan', '10_penyakit_non_bedah_ralan',
            '10_penyakit_ranap', '10_penyakit_bedah_ranap', '10_penyakit_non_bedah_ranap',
            'data_pasien_kode_penyakit_bedah_ralan', 'data_pasien_kode_penyakit_non_bedah_ralan', 'data_pasien_kode_penyakit_bedah_ranap', 'data_pasien_kode_penyakit_non_bedah_ranap', 'kode_penyakit_ab_ranap', 'export_kode_penyakit_ab_ranap', 'export_kode_penyakit',
            'rekap_pasien_baru_lama', 'top_10_dokter_pasien', 'los_rawat_inap', 'bor_sederhana', 'bor_rawat_inap', 'los_klinis_rawat_inap', 'toi_rawat_inap', 'bto_rawat_inap', 'readme_indikator_rawat_inap', 'pasien_rujukan_masuk_keluar', 'kunjungan_wilayah_visual', 'laporan_keuangan_ranap', 'export_laporan_keuangan_ranap',
        ],
        'kepegawaian' => [
            'beranda', 'kunjungan_data_ralan', 'kunjungan_data_perpoli', 'kunjungan_data_per_minggu', 'top_10_poli_ralan',
            //'waktu_tunggu_poli_ralan',
            //'waktu_tunggu_registrasi_perawat_ralan',
            'kunjungan_data_berdasarkanusia_ralan', 'kunjungan_data_perkamar_ranap', 'kunjungan_data_harian_ranap', 'kunjungan_data_perkelas_bayar_ranap', 'top_10_kamar_ranap',
            'kunjungan_data_berdasarkanusia_ranap', 'kunjungan_data_kecamatan_ranap', 'export_kunjungan_kecamatan_ranap', 'export_kunjungan', 'export_kunjungan_ralan', 'export_kunjungan_per_minggu', 'export_blmSEP', 'export_sdhSEP', 'export_top_10_poli_ralan', 'export_kunjungan_perkamar_usia_ranap', 'export_kunjungan_harian_ranap', 'export_kunjungan_perkelas_bayar_ranap', 'export_top_10_kamar_ranap',
            'rekap_pasien_baru_lama', 'top_10_dokter_pasien', 'los_rawat_inap', 'bor_sederhana', 'kunjungan_wilayah_visual',
        ],
        'medis' => ['beranda', 'diare_data', 'export_diare', 'kode_penyakit_ab_ranap', 'export_kode_penyakit_ab_ranap'],
        'non medis' => [
            'beranda', 'diare_data', 'kunjungan_data_ralan', 'kunjungan_data_perpoli', 'kunjungan_data_per_minggu', 'top_10_poli_ralan',
            //'waktu_tunggu_poli_ralan',
            //'waktu_tunggu_registrasi_perawat_ralan',
            'kunjungan_data_perkab_ralan', 'kunjungan_data_berdasarkanusia_ralan', 'kunjungan_data_kecamatan_ralan', 'export_kunjungan_kecamatan_ralan', 'export_diare', 'export_kunjungan',
            'export_kunjungan_ralan', 'export_kunjungan_per_minggu', 'export_kunjungan_perkab',
            'rekap_pasien_baru_lama', 'top_10_dokter_pasien', 'kunjungan_wilayah_visual', 'pasien_rujukan_masuk_keluar',
        ],
        'users' => ['beranda'],
        'rekammedis' => ['beranda', 'rl32_ranap', 'input_data_claim'],
        'keuangan' => ['beranda', 'laporan_keuangan_ranap', 'export_laporan_keuangan_ranap', 'input_data_claim'],
        'gizi' => ['beranda', 'adime_gizi', 'export_adime_gizi'],
    ];
}

function aptd_can_access($level, $page)
{
    $accessMap = aptd_get_access_map();
    $routes = aptd_get_routes();

    if (!isset($accessMap[$level]) || !isset($routes[$page])) {
        return false;
    }

    return in_array('*', $accessMap[$level], true) || in_array($page, $accessMap[$level], true);
}
