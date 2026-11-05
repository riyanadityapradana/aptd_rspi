<?php

function aptd_extra_menu_catalog()
{
    static $catalog = null;
    if ($catalog !== null) { return $catalog; }
    $labels = [
        'menuKunjunganRalan' => 'Master Kunjungan Ralan', 'menuKunjunganRanap' => 'Master Kunjungan Ranap',
        'menuPenyakit' => 'Master Penyakit', 'menuDataKlinis' => 'Data Klinis',
        'menuDataNonKlinis' => 'Data Non Klinis', 'menuSirs' => 'SIRS 6.3', 'menuGizi' => 'Master Gizi',
    ];
    $catalog = [];
    $definitions = aptd_get_menu_definitions();
    foreach ($labels as $key => $label) {
        foreach ($definitions[$key] as $group) {
            foreach ($group as $item) {
                if ((!isset($item['visible']) || $item['visible'] !== false) && !isset($catalog[$item['page']])) {
                    $catalog[$item['page']] = ['label' => $item['label'], 'group' => $label];
                }
            }
        }
    }
    $catalog['input_data_claim'] = ['label' => 'Input Data Claim', 'group' => 'Input Data'];
    $catalog['diagnosa_awal_sementara_ranap'] = ['label' => 'Input Diagnosa Awal', 'group' => 'Input Data'];
    return $catalog;
}

function aptd_extra_menu_dependencies()
{
    return [
        'diare_data' => ['export_diare'],
        'adime_gizi' => ['export_adime_gizi'],
        'kunjungan_usia_ranap_gizi' => ['export_kunjungan_usia_ranap_gizi'],
        'data_vaksinasi_haji' => ['export_vaksinasi_haji_pdf'],
        'rekap_resep_fornas_non_fornas' => ['export_rekap_resep_fornas_pdf'],
        'kunjungan_data_ralan' => ['export_kunjungan_ralan'],
        'kunjungan_data_perpoli' => ['export_kunjungan'],
        'kunjungan_data_igd' => ['export_kunjungan_igd'],
        'kunjungan_data_per_minggu' => ['export_kunjungan_per_minggu'],
        'kunjungan_data_perkab_ralan' => ['export_kunjungan_perkab'],
        'kunjungan_data_berdasarkanusia_ralan' => ['export_kunjungan_usia'],
        'kunjungan_data_kecamatan_ralan' => ['export_kunjungan_kecamatan_ralan'],
        'kunjungan_data_kecamatan_ranap' => ['export_kunjungan_kecamatan_ranap'],
        'kunjungan_data_blmSEP' => ['export_blmSEP'], 'kunjungan_data_sdhSEP' => ['export_sdhSEP'],
        'top_10_poli_ralan' => ['export_top_10_poli_ralan'],
        'evaluasi_task4_terawal_bpjs' => ['export_evaluasi_task4_terawal_bpjs'],
        'indikator_waktu_tunggu_poli_task_2_5' => ['export_indikator_waktu_tunggu_poli_task_2_5'],
        '10_penyakit_ugd_ponek' => ['export_10_penyakit_ugd_ponek'],
        'laporan_bulanan_hais' => ['export_laporan_bulanan_hais'],
        'rekap_pasien_prmrj' => ['rekap_pasien_prmrj_detail', 'export_rekap_pasien_prmrj_pdf'],
        'data_pasien_kode_penyakit_bedah_ralan' => ['export_kode_penyakit'],
        'data_pasien_kode_penyakit_non_bedah_ralan' => ['export_kode_penyakit'],
        'data_pasien_kode_penyakit_bedah_ranap' => ['export_kode_penyakit'],
        'data_pasien_kode_penyakit_non_bedah_ranap' => ['export_kode_penyakit'],
        'kode_penyakit_ab_ranap' => ['export_kode_penyakit_ab_ranap'],
        'laporan_keuangan_ranap' => ['export_laporan_keuangan_ranap'],
        'laporan_keuangan_ralan' => ['export_laporan_keuangan_ralan'],
        'indikator_rawat_inap' => ['bor_rawat_inap', 'los_klinis_rawat_inap', 'toi_rawat_inap', 'bto_rawat_inap'],
    ];
}

function aptd_validate_extra_menus($menus)
{
    if (!is_array($menus) || count($menus) > count(aptd_extra_menu_catalog())) {
        throw new InvalidArgumentException('Pilihan akses tambahan tidak valid.');
    }
    $catalog = aptd_extra_menu_catalog();
    $validated = [];
    foreach ($menus as $menu) {
        if (!is_string($menu) || !isset($catalog[$menu])) {
            throw new InvalidArgumentException('Menu akses tambahan tidak valid.');
        }
        $validated[$menu] = $menu;
    }
    return array_values($validated);
}

function aptd_extra_access_pages(array $menus)
{
    $catalog = aptd_extra_menu_catalog();
    $dependencies = aptd_extra_menu_dependencies();
    $pages = [];
    foreach ($menus as $menu) {
        if (is_string($menu) && isset($catalog[$menu])) {
            $pages[] = $menu;
            foreach (isset($dependencies[$menu]) ? $dependencies[$menu] : [] as $page) {
                $pages[] = $page;
            }
        }
    }
    return array_values(array_unique($pages));
}

function aptd_decode_extra_menus($json)
{
    if ($json === null || $json === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }
    // ID yang telah dihapus dari katalog tidak dapat membuka halaman lain.
    return array_values(array_filter($decoded, function ($value) {
        return is_string($value) && isset(aptd_extra_menu_catalog()[$value]);
    }));
}

function aptd_access_current_user($refresh = false)
{
    static $loadedId = null;
    static $user = null;
    if ($refresh) {
        $loadedId = null;
        $user = null;
    }
    $id = isset($_SESSION['id_user']) ? (int) $_SESSION['id_user']
        : (isset($_SESSION['id_users']) ? (int) $_SESSION['id_users'] : 0);
    if (empty($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true || $id <= 0) {
        return null;
    }
    if ($loadedId === $id) {
        return $user;
    }
    global $mysqli;
    if (!isset($mysqli)) {
        require __DIR__ . '/koneksi.php';
    }
    $loadedId = $id;
    $user = ['valid' => false, 'level' => '', 'menus' => []];
    try {
        $statement = $mysqli->prepare('SELECT level, akses_menu FROM tb_users WHERE id_users = ? LIMIT 1');
        $statement->bind_param('i', $id);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();
        if ($row && isset(aptd_get_access_map()[$row['level']])) {
            $user = ['valid' => true, 'level' => $row['level'], 'menus' => aptd_decode_extra_menus($row['akses_menu'])];
            $_SESSION['level'] = $row['level'];
        }
    } catch (Throwable $exception) {
        error_log('APTD akses user: ' . $exception->getMessage());
    }
    return $user;
}

function aptd_has_extra_menu($page)
{
    $user = aptd_access_current_user();
    return $user !== null && $user['valid'] && in_array($page, $user['menus'], true);
}

function aptd_direct_report_pages($relative, array $request)
{
    $diseaseModes = [
        'ralan' => '10_penyakit_ralan', 'ralan_bedah' => '10_penyakit_bedah_ralan',
        'ralan_non_bedah' => '10_penyakit_non_bedah_ralan', 'ranap' => '10_penyakit_ranap',
        'ranap_bedah' => '10_penyakit_bedah_ranap', 'ranap_non_bedah' => '10_penyakit_non_bedah_ranap',
    ];
    $codeModes = [
        'ralan_bedah' => 'data_pasien_kode_penyakit_bedah_ralan',
        'ralan_non_bedah' => 'data_pasien_kode_penyakit_non_bedah_ralan',
        'ranap_bedah' => 'data_pasien_kode_penyakit_bedah_ranap',
        'ranap_non_bedah' => 'data_pasien_kode_penyakit_non_bedah_ranap',
    ];
    $mode = isset($request['mode']) && is_string($request['mode']) ? $request['mode'] : '';
    if ($relative === 'page/t_klinis/t_10_penyakit/detail_penyakit.php') {
        return isset($diseaseModes[$mode]) ? [$diseaseModes[$mode]] : [];
    }
    if ($relative === 'page/t_klinis/t_kode_penyakit/kode_penyakit_details.php'
        || $relative === 'page/t_klinis/t_kode_penyakit/export_kode_penyakit.php') {
        if ($mode === '' && $relative === 'page/t_klinis/t_kode_penyakit/export_kode_penyakit.php') {
            $mode = 'ralan_non_bedah';
        }
        return isset($codeModes[$mode]) ? [$codeModes[$mode]] : [];
    }
    $extraFiles = [
        'page/t_kunjungan/rawat_inap/evaluasi_task4_bpjs_api.php' => 'evaluasi_task4_bpjs',
        'page/t_kunjungan/rawat_inap/evaluasi_task4_terawal_bpjs_api.php' => 'evaluasi_task4_terawal_bpjs',
        'page/t_kunjungan/rawat_jalan/indikator_waktu_tunggu_poli_task_2_5_api.php' => 'indikator_waktu_tunggu_poli_task_2_5',
        'page/t_klinis/diagnosa_sementara_search_penyakit.php' => 'diagnosa_awal_sementara_ranap',
        'page/t_klinis/t_kode_penyakit/rawat_inap/kode_penyakit_ab_ranap_details.php' => 'kode_penyakit_ab_ranap',
        'page/t_rl_32/rl_32_ranap_details.php' => 'rl32_ranap',
        'page/t_rl_32/rl_32_ranap_export.php' => 'rl32_ranap',
        'page/t_non_klinis/rawat_jalan/export_kunjungan_kecamatan_mingguan_ralan_excel.php' => 'kunjungan_kecamatan_mingguan_ralan',
        'page/t_non_klinis/rawat_jalan/export_kunjungan_kecamatan_mingguan_ralan_pdf.php' => 'kunjungan_kecamatan_mingguan_ralan',
        'page/t_non_klinis/rawat_inap/export_kunjungan_kecamatan_mingguan_ranap_excel.php' => 'kunjungan_kecamatan_mingguan_ranap',
        'page/t_non_klinis/rawat_inap/export_kunjungan_kecamatan_mingguan_ranap_pdf.php' => 'kunjungan_kecamatan_mingguan_ranap',
    ];
    if (isset($extraFiles[$relative])) {
        return [$extraFiles[$relative]];
    }
    return array_keys(aptd_get_routes(), $relative, true);
}

function aptd_guard_direct_report_request()
{
    if (PHP_SAPI === 'cli' || empty($_SERVER['SCRIPT_FILENAME'])) {
        return;
    }
    $base = str_replace('\\', '/', dirname(__DIR__) . '/main_app/');
    $script = str_replace('\\', '/', (string) realpath($_SERVER['SCRIPT_FILENAME']));
    if (stripos($script, $base . 'page/') !== 0) {
        return;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $pages = aptd_direct_report_pages(substr($script, strlen($base)), array_merge($_GET, $_POST));
    if (isset($_SESSION['login_aptd_rspi']) && $_SESSION['login_aptd_rspi'] === true) {
        foreach ($pages as $page) {
            if (aptd_can_access(isset($_SESSION['level']) ? $_SESSION['level'] : '', $page)) {
                return;
            }
        }
    }
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Anda tidak memiliki hak akses ke laporan ini.');
}
