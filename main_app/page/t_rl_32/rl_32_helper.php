<?php
if (!function_exists('rl32_root_path')) {
    function rl32_root_path()
    {
        return dirname(__DIR__, 3);
    }
}

if (!function_exists('rl32_bootstrap')) {
    function rl32_bootstrap($requirePageAccess = true)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        require_once rl32_root_path() . '/config/koneksi.php';
        require_once rl32_root_path() . '/config/akses.php';

        $connection = null;
        if (isset($mysqli) && $mysqli instanceof mysqli) {
            $connection = $mysqli;
        } elseif (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) {
            $connection = $GLOBALS['mysqli'];
        }

        if (!($connection instanceof mysqli)) {
            throw new RuntimeException('Koneksi database RL 3.2 tidak berhasil diinisialisasi.');
        }

        if (!isset($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true) {
            http_response_code(403);
            exit('Akses ditolak. Silakan login terlebih dahulu.');
        }

        if ($requirePageAccess) {
            $levelLogin = isset($_SESSION['level']) ? $_SESSION['level'] : '';
            if (!aptd_can_access($levelLogin, 'rl32_ranap')) {
                http_response_code(403);
                exit('Anda tidak memiliki hak akses ke modul RL 3.2.');
            }
        }

        return $connection;
    }
}

if (!function_exists('rl32_service_labels')) {
    function rl32_service_labels()
    {
        return [
            'ICU' => 'ICU',
            'KN' => 'NICU',
            'PERIN' => 'Perinatologi',
            'ISO' => 'Isolasi',
            'Umum' => 'Umum',
        ];
    }
}

if (!function_exists('rl32_metric_map')) {
    function rl32_metric_map()
    {
        return [
            'Pasien Awal Bulan' => 'pasien_awal',
            'Pasien Masuk' => 'pasien_masuk',
            'Pasien Pindahan' => 'pasien_pindahan',
            'Pasien Dipindahkan' => 'pasien_dipindahkan',
            'Pasien Keluar Hidup' => 'pasien_keluar_hidup',
            'Pasien Laki-Laki Keluar Mati <48 jam' => 'laki_mati_under_48',
            'Pasien Laki-Laki Keluar Mati >=48 jam' => 'laki_mati_over_48',
            'Pasien Perempuan Keluar Mati <48 jam' => 'perempuan_mati_under_48',
            'Pasien Perempuan Keluar Mati >=48 jam' => 'perempuan_mati_over_48',
            'Jumlah Lama Dirawat' => 'jumlah_lama_dirawat',
            'Pasien Akhir Bulan' => 'pasien_akhir',
            'Jumlah Hari Perawatan' => 'jumlah_hari_perawatan',
            'Jumlah alokasi tempat tidur awal bulan' => 'alokasi_tempat_tidur',
        ];
    }
}

if (!function_exists('rl32_get_main_report')) {
    function rl32_get_main_report($mysqli, $tahun, $bulan)
    {
        $tahun = (int) $tahun;
        $bulan = (int) $bulan;

        $mysqli->query("SET @TAHUN = {$tahun}");
        $mysqli->query("SET @BULAN = {$bulan}");
        $mysqli->query("SET @START_DATE = DATE(CONCAT(@TAHUN, '-', LPAD(@BULAN, 2, '0'), '-01'))");
        $mysqli->query("SET @END_DATE = LAST_DAY(@START_DATE)");
        $mysqli->query("SET @PREV_MONTH_START_DATE = CASE WHEN @BULAN = 1 THEN DATE(CONCAT(@TAHUN - 1, '-12-01')) ELSE DATE(CONCAT(@TAHUN, '-', LPAD(@BULAN - 1, 2, '0'), '-01')) END");
        $mysqli->query("SET @row_num = 0");

        $sql = "
SELECT
    (@row_num := @row_num + 1) AS 'No',
    mk.jenis_pelayanan AS 'Jenis Pelayanan',
    COALESCE(pab.jumlah, 0) AS 'Pasien Awal Bulan',
    COALESCE(pm.jumlah, 0) AS 'Pasien Masuk',
    COALESCE(ppm.jumlah, 0) AS 'Pasien Pindahan',
    COALESCE(ppk.jumlah, 0) AS 'Pasien Dipindahkan',
    COALESCE(pkh.jumlah, 0) AS 'Pasien Keluar Hidup',
    COALESCE(p_mati.laki_mati_under_48, 0) AS 'Pasien Laki-Laki Keluar Mati <48 jam',
    COALESCE(p_mati.laki_mati_over_48, 0) AS 'Pasien Laki-Laki Keluar Mati >=48 jam',
    COALESCE(p_mati.perempuan_mati_under_48, 0) AS 'Pasien Perempuan Keluar Mati <48 jam',
    COALESCE(p_mati.perempuan_mati_over_48, 0) AS 'Pasien Perempuan Keluar Mati >=48 jam',
    COALESCE(jld.jumlah, 0) AS 'Jumlah Lama Dirawat',
    COALESCE(p_akhir.jumlah, 0) AS 'Pasien Akhir Bulan',
    COALESCE(rhp.JUMLAH_HARI_PERAWATAN, 0) AS 'Jumlah Hari Perawatan',
    COALESCE(rhp.VVIP, 0) AS 'VVIP',
    COALESCE(rhp.VIP, 0) AS 'VIP',
    COALESCE(rhp.Kelas_1, 0) AS 'Kelas 1',
    COALESCE(rhp.Kelas_2, 0) AS 'Kelas 2',
    COALESCE(rhp.Kelas_3, 0) AS 'Kelas 3',
    COALESCE(rhp.Kelas_Khusus, 0) AS 'Kelas Khusus',
    COALESCE(att.jumlah, 0) AS 'Jumlah alokasi tempat tidur awal bulan'
FROM
    (SELECT DISTINCT CASE WHEN kd_bangsal = 'ICU' THEN 'ICU' WHEN kd_bangsal = 'KN' THEN 'KN' WHEN kd_bangsal = 'PERIN' THEN 'PERIN' WHEN kd_bangsal = 'ISO' THEN 'ISO' ELSE 'Umum' END AS jenis_pelayanan FROM bangsal) AS mk
LEFT JOIN
    (SELECT CASE WHEN b.kd_bangsal IN ('ICU','KN','PERIN','ISO') THEN b.kd_bangsal ELSE 'Umum' END AS jenis_pelayanan, COUNT(ki.no_rawat) AS jumlah FROM kamar_inap ki INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal WHERE ki.tgl_masuk < @START_DATE AND ki.tgl_masuk >= @PREV_MONTH_START_DATE AND (ki.tgl_keluar >= @START_DATE OR ki.tgl_keluar IS NULL) GROUP BY jenis_pelayanan) AS pab ON mk.jenis_pelayanan = pab.jenis_pelayanan
LEFT JOIN
    (SELECT CASE WHEN b.kd_bangsal IN ('ICU','KN','PERIN','ISO') THEN b.kd_bangsal ELSE 'Umum' END AS jenis_pelayanan, COUNT(ki.no_rawat) AS jumlah FROM kamar_inap ki INNER JOIN (SELECT no_rawat, MIN(CONCAT(tgl_masuk, ' ', jam_masuk)) AS min_datetime FROM kamar_inap GROUP BY no_rawat) first_entry ON ki.no_rawat = first_entry.no_rawat AND CONCAT(ki.tgl_masuk, ' ', ki.jam_masuk) = first_entry.min_datetime INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal WHERE ki.tgl_masuk BETWEEN @START_DATE AND @END_DATE GROUP BY jenis_pelayanan) AS pm ON mk.jenis_pelayanan = pm.jenis_pelayanan
LEFT JOIN
    (SELECT CASE WHEN bangsal_baru.kd_bangsal = 'ICU' THEN 'ICU' WHEN bangsal_baru.kd_bangsal = 'KN' THEN 'KN' WHEN bangsal_baru.kd_bangsal = 'PERIN' THEN 'PERIN' WHEN bangsal_baru.kd_bangsal = 'ISO' THEN 'ISO' ELSE 'Umum' END AS jenis_pelayanan, COUNT(baru.no_rawat) AS jumlah FROM kamar_inap AS lama INNER JOIN kamar_inap AS baru ON lama.no_rawat = baru.no_rawat AND lama.tgl_keluar = baru.tgl_masuk AND lama.jam_keluar = baru.jam_masuk INNER JOIN kamar AS kamar_lama ON lama.kd_kamar = kamar_lama.kd_kamar INNER JOIN bangsal AS bangsal_lama ON kamar_lama.kd_bangsal = bangsal_lama.kd_bangsal INNER JOIN kamar AS kamar_baru ON baru.kd_kamar = kamar_baru.kd_kamar INNER JOIN bangsal AS bangsal_baru ON kamar_baru.kd_bangsal = bangsal_baru.kd_bangsal WHERE lama.stts_pulang = 'Pindah Kamar' AND baru.tgl_masuk BETWEEN @START_DATE AND @END_DATE AND (CASE WHEN bangsal_lama.kd_bangsal = 'ICU' THEN 'ICU' WHEN bangsal_lama.kd_bangsal = 'KN' THEN 'NICU' WHEN bangsal_lama.kd_bangsal = 'ISO' THEN 'Isolasi' WHEN bangsal_lama.kd_bangsal = 'PERIN' THEN 'Perinatologi' ELSE 'Umum' END) <> (CASE WHEN bangsal_baru.kd_bangsal = 'ICU' THEN 'ICU' WHEN bangsal_baru.kd_bangsal = 'KN' THEN 'NICU' WHEN bangsal_baru.kd_bangsal = 'ISO' THEN 'Isolasi' WHEN bangsal_baru.kd_bangsal = 'PERIN' THEN 'Perinatologi' ELSE 'Umum' END) GROUP BY jenis_pelayanan) AS ppm ON mk.jenis_pelayanan = ppm.jenis_pelayanan
LEFT JOIN
    (SELECT CASE WHEN bangsal_lama.kd_bangsal = 'ICU' THEN 'ICU' WHEN bangsal_lama.kd_bangsal = 'KN' THEN 'KN' WHEN bangsal_lama.kd_bangsal = 'PERIN' THEN 'PERIN' WHEN bangsal_lama.kd_bangsal = 'ISO' THEN 'ISO' ELSE 'Umum' END AS jenis_pelayanan, COUNT(lama.no_rawat) AS jumlah FROM kamar_inap AS lama INNER JOIN kamar_inap AS baru ON lama.no_rawat = baru.no_rawat AND lama.tgl_keluar = baru.tgl_masuk AND lama.jam_keluar = baru.jam_masuk INNER JOIN kamar AS kamar_lama ON lama.kd_kamar = kamar_lama.kd_kamar INNER JOIN bangsal AS bangsal_lama ON kamar_lama.kd_bangsal = bangsal_lama.kd_bangsal INNER JOIN kamar AS kamar_baru ON baru.kd_kamar = kamar_baru.kd_kamar INNER JOIN bangsal AS bangsal_baru ON kamar_baru.kd_bangsal = bangsal_baru.kd_bangsal WHERE lama.stts_pulang = 'Pindah Kamar' AND lama.tgl_keluar BETWEEN @START_DATE AND @END_DATE AND (CASE WHEN bangsal_lama.kd_bangsal = 'ICU' THEN 'ICU' WHEN bangsal_lama.kd_bangsal = 'KN' THEN 'NICU' WHEN bangsal_lama.kd_bangsal = 'ISO' THEN 'Isolasi' WHEN bangsal_lama.kd_bangsal = 'PERIN' THEN 'Perinatologi' ELSE 'Umum' END) <> (CASE WHEN bangsal_baru.kd_bangsal = 'ICU' THEN 'ICU' WHEN bangsal_baru.kd_bangsal = 'KN' THEN 'NICU' WHEN bangsal_baru.kd_bangsal = 'ISO' THEN 'Isolasi' WHEN bangsal_baru.kd_bangsal = 'PERIN' THEN 'Perinatologi' ELSE 'Umum' END) GROUP BY jenis_pelayanan) AS ppk ON mk.jenis_pelayanan = ppk.jenis_pelayanan
LEFT JOIN
    (SELECT CASE WHEN b.kd_bangsal IN ('ICU','KN','PERIN','ISO') THEN b.kd_bangsal ELSE 'Umum' END AS jenis_pelayanan, COUNT(ki.no_rawat) AS jumlah FROM kamar_inap ki INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal WHERE ki.tgl_keluar BETWEEN @START_DATE AND @END_DATE AND ki.stts_pulang NOT IN ('Meninggal', 'Pindah Kamar') GROUP BY jenis_pelayanan) AS pkh ON mk.jenis_pelayanan = pkh.jenis_pelayanan
LEFT JOIN
    (SELECT CASE WHEN b.kd_bangsal IN ('ICU','KN','PERIN','ISO') THEN b.kd_bangsal ELSE 'Umum' END AS jenis_pelayanan, SUM(CASE WHEN p.jk = 'L' AND TIMESTAMPDIFF(HOUR, CONCAT(ki.tgl_masuk, ' ', ki.jam_masuk), CONCAT(ki.tgl_keluar, ' ', ki.jam_keluar)) < 48 THEN 1 ELSE 0 END) AS laki_mati_under_48, SUM(CASE WHEN p.jk = 'L' AND TIMESTAMPDIFF(HOUR, CONCAT(ki.tgl_masuk, ' ', ki.jam_masuk), CONCAT(ki.tgl_keluar, ' ', ki.jam_keluar)) >= 48 THEN 1 ELSE 0 END) AS laki_mati_over_48, SUM(CASE WHEN p.jk = 'P' AND TIMESTAMPDIFF(HOUR, CONCAT(ki.tgl_masuk, ' ', ki.jam_masuk), CONCAT(ki.tgl_keluar, ' ', ki.jam_keluar)) < 48 THEN 1 ELSE 0 END) AS perempuan_mati_under_48, SUM(CASE WHEN p.jk = 'P' AND TIMESTAMPDIFF(HOUR, CONCAT(ki.tgl_masuk, ' ', ki.jam_masuk), CONCAT(ki.tgl_keluar, ' ', ki.jam_keluar)) >= 48 THEN 1 ELSE 0 END) AS perempuan_mati_over_48 FROM kamar_inap ki INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis WHERE ki.tgl_keluar BETWEEN @START_DATE AND @END_DATE AND ki.stts_pulang = 'Meninggal' GROUP BY jenis_pelayanan) AS p_mati ON mk.jenis_pelayanan = p_mati.jenis_pelayanan
LEFT JOIN
    (SELECT CASE WHEN b.kd_bangsal IN ('ICU','KN','PERIN','ISO') THEN b.kd_bangsal ELSE 'Umum' END AS jenis_pelayanan, SUM(ki.lama) AS jumlah FROM kamar_inap ki INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal WHERE ki.tgl_keluar BETWEEN @START_DATE AND @END_DATE GROUP BY jenis_pelayanan) AS jld ON mk.jenis_pelayanan = jld.jenis_pelayanan
LEFT JOIN
    (SELECT CASE WHEN b.kd_bangsal IN ('ICU','KN','PERIN','ISO') THEN b.kd_bangsal ELSE 'Umum' END AS jenis_pelayanan, COUNT(ki.no_rawat) AS jumlah FROM kamar_inap ki INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal WHERE ki.tgl_masuk <= @END_DATE AND (ki.tgl_keluar > @END_DATE OR ki.tgl_keluar IS NULL) GROUP BY jenis_pelayanan) AS p_akhir ON mk.jenis_pelayanan = p_akhir.jenis_pelayanan
LEFT JOIN
    (SELECT T.jenis_pelayanan, SUM(CASE WHEN T.kelas_perawatan = 'Kelas VVIP' THEN T.days_in_period ELSE 0 END) AS 'VVIP', SUM(CASE WHEN T.kelas_perawatan = 'Kelas VIP' THEN T.days_in_period ELSE 0 END) AS 'VIP', SUM(CASE WHEN T.kelas_perawatan = 'Kelas 1' THEN T.days_in_period ELSE 0 END) AS 'Kelas_1', SUM(CASE WHEN T.kelas_perawatan = 'Kelas 2' THEN T.days_in_period ELSE 0 END) AS 'Kelas_2', SUM(CASE WHEN T.kelas_perawatan = 'Kelas 3' THEN T.days_in_period ELSE 0 END) AS 'Kelas_3', SUM(CASE WHEN T.kelas_perawatan NOT IN ('Kelas VVIP','Kelas VIP','Kelas 1','Kelas 2','Kelas 3') THEN T.days_in_period ELSE 0 END) AS 'Kelas_Khusus', SUM(T.days_in_period) AS 'JUMLAH_HARI_PERAWATAN' FROM (SELECT CASE WHEN b.kd_bangsal IN ('ICU','KN','PERIN','ISO') THEN b.kd_bangsal ELSE 'Umum' END AS jenis_pelayanan, k.kelas AS kelas_perawatan, DATEDIFF(LEAST(IFNULL(ki.tgl_keluar, @END_DATE), @END_DATE), GREATEST(ki.tgl_masuk, @START_DATE)) + 1 AS days_in_period FROM kamar_inap ki JOIN kamar k ON ki.kd_kamar = k.kd_kamar JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal WHERE ki.tgl_masuk <= @END_DATE AND (ki.tgl_keluar >= @START_DATE OR ki.tgl_keluar IS NULL OR ki.tgl_keluar = '0000-00-00')) AS T GROUP BY T.jenis_pelayanan) AS rhp ON mk.jenis_pelayanan = rhp.jenis_pelayanan
LEFT JOIN
    (SELECT CASE WHEN b.kd_bangsal IN ('ICU','KN','PERIN','ISO') THEN b.kd_bangsal ELSE 'Umum' END AS jenis_pelayanan, COUNT(k.kd_kamar) AS jumlah FROM kamar k INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal WHERE k.statusdata = '1' GROUP BY jenis_pelayanan) AS att ON mk.jenis_pelayanan = att.jenis_pelayanan
ORDER BY mk.jenis_pelayanan
";

        $result = $mysqli->query($sql);
        if (!$result) {
            throw new RuntimeException('Query RL 3.2 gagal: ' . $mysqli->error);
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }
}





