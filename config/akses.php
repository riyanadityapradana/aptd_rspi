<?php
function aptd_get_routes()
{
    return [
        'beranda' => 'page/beranda.php',
        'diare_data' => 'page/t_diare/diare_data.php',
        'export_diare' => 'page/t_diare/export_diare.php',

        'kunjungan_data_ralan' => 'page/t_kunjungan/rawat_jalan/kunjungan_data_ralan.php',
        'kunjungan_data_perpoli' => 'page/t_kunjungan/rawat_jalan/kunjungan_data_perpoli.php',
        'kunjungan_data_per_minggu' => 'page/t_kunjungan/rawat_jalan/kunjungan_data_per_minggu.php',
        'top_10_poli_ralan' => 'page/t_kunjungan/rawat_jalan/top_10_poli_ralan.php',
        'export_kunjungan' => 'page/t_kunjungan/rawat_jalan/export_kunjungan.php',
        'export_kunjungan_ralan' => 'page/t_kunjungan/rawat_jalan/export_kunjungan_ralan.php',
        'export_kunjungan_per_minggu' => 'page/t_kunjungan/rawat_jalan/export_kunjungan_per_minggu.php',
        'kunjungan_data_perkab_ralan' => 'page/t_kunjungan_perkab/rawat_jalan/kunjungan_ralan_perkab.php',
        'export_kunjungan_perkab' => 'page/t_kunjungan_perkab/rawat_jalan/export_kunjungan_perkab.php',
        'kunjungan_data_blmSEP' => 'page/t_kunjungan/rawat_jalan/kunjungan_data_blmSEP.php',
        'kunjungan_data_sdhSEP' => 'page/t_kunjungan/rawat_jalan/kunjungan_data_sdhSEP.php',
        'kunjungan_data_berdasarkanusia_ralan' => 'page/t_kunjungan_berdasarkan_usia/rawat_jalan/kunjungan_data_berdasarkanusia_ralan.php',

        'kunjungan_data_perkamar_ranap' => 'page/t_kunjungan/rawat_inap/kunjungan_data_perkamar_ranap.php',
        'kunjungan_data_harian_ranap' => 'page/t_kunjungan/rawat_inap/kunjungan_data_harian_ranap.php',
        'top_10_kamar_ranap' => 'page/t_kunjungan/rawat_inap/top_10_kamar_ranap.php',
        'kunjungan_data_berdasarkanusia_ranap' => 'page/t_kunjungan_berdasarkan_usia/rawat_inap/kunjungan_data_berdasarkanusia_ranap.php',

        '10_penyakit_ralan' => 'page/t_10_penyakit/rawat_jalan/10_penyakit_ralan.php',
        '10_penyakit_bedah_ralan' => 'page/t_10_penyakit/rawat_jalan/10_penyakit_bedah_ralan.php',
        '10_penyakit_non_bedah_ralan' => 'page/t_10_penyakit/rawat_jalan/10_penyakit_non_bedah_ralan.php',
        '10_penyakit_ranap' => 'page/t_10_penyakit/rawat_inap/10_penyakit_ranap.php',
        '10_penyakit_bedah_ranap' => 'page/t_10_penyakit/rawat_inap/10_penyakit_bedah_ranap.php',
        '10_penyakit_non_bedah_ranap' => 'page/t_10_penyakit/rawat_inap/10_penyakit_non_bedah_ranap.php',
        'data_pasien_kode_penyakit_bedah_ralan' => 'page/t_kode_penyakit/rawat_jalan/data_pasien_kode_penyakit_bedah_ralan.php',
        'data_pasien_kode_penyakit_non_bedah_ralan' => 'page/t_kode_penyakit/rawat_jalan/data_pasien_kode_penyakit_non_bedah_ralan.php',
        'data_pasien_kode_penyakit_bedah_ranap' => 'page/t_kode_penyakit/rawat_inap/data_pasien_kode_penyakit_bedah_ranap.php',
        'data_pasien_kode_penyakit_non_bedah_ranap' => 'page/t_kode_penyakit/rawat_inap/data_pasien_kode_penyakit_non_bedah_ranap.php',
    ];
}

function aptd_get_access_map()
{
    return [
        'admin' => ['*'],
        'manajemen' => [
            'beranda',
            'kunjungan_data_ralan',
            'kunjungan_data_perpoli',
            'kunjungan_data_per_minggu',
            'top_10_poli_ralan',
            'kunjungan_data_perkab_ralan',
            'kunjungan_data_blmSEP',
            'kunjungan_data_sdhSEP',
            'kunjungan_data_berdasarkanusia_ralan',
            'kunjungan_data_perkamar_ranap',
            'kunjungan_data_harian_ranap',
            'top_10_kamar_ranap',
            'kunjungan_data_berdasarkanusia_ranap',
            'export_kunjungan',
            'export_kunjungan_ralan',
            'export_kunjungan_per_minggu',
            'export_kunjungan_perkab',
            '10_penyakit_ralan',
            '10_penyakit_bedah_ralan',
            '10_penyakit_non_bedah_ralan',
            '10_penyakit_ranap',
            '10_penyakit_bedah_ranap',
            '10_penyakit_non_bedah_ranap',
            'data_pasien_kode_penyakit_bedah_ralan',
            'data_pasien_kode_penyakit_non_bedah_ralan',
            'data_pasien_kode_penyakit_bedah_ranap',
            'data_pasien_kode_penyakit_non_bedah_ranap',
        ],
        'kepegawaian' => [
            'beranda',
            'kunjungan_data_ralan',
            'kunjungan_data_perpoli',
            'kunjungan_data_per_minggu',
            'top_10_poli_ralan',
            'kunjungan_data_berdasarkanusia_ralan',
            'kunjungan_data_perkamar_ranap',
            'kunjungan_data_harian_ranap',
            'top_10_kamar_ranap',
            'kunjungan_data_berdasarkanusia_ranap',
            'export_kunjungan',
            'export_kunjungan_ralan',
            'export_kunjungan_per_minggu',
        ],
        'medis' => [
            'beranda',
            'diare_data',
            'export_diare',
        ],
        'non medis' => [
            'beranda',
            'diare_data',
            'kunjungan_data_ralan',
            'kunjungan_data_perpoli',
            'kunjungan_data_per_minggu',
            'top_10_poli_ralan',
            'kunjungan_data_perkab_ralan',
            'kunjungan_data_berdasarkanusia_ralan',
            'export_diare',
            'export_kunjungan',
            'export_kunjungan_ralan',
            'export_kunjungan_per_minggu',
            'export_kunjungan_perkab',
        ],
        'users' => [
            'beranda',
        ],
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

