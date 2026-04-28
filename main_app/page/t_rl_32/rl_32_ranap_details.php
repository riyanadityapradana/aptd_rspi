<?php
ob_start();
require_once __DIR__ . '/rl_32_helper.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    $mysqli = rl32_bootstrap();
// 1. Ambil semua parameter
$metric = $_GET['metric'] ?? '';
$service = $_GET['service'] ?? ''; // Ini adalah KODE, contoh: 'KN', 'PERIN', 'Umum'
$bulan = intval($_GET['bulan'] ?? 0);
$tahun = intval($_GET['tahun'] ?? 0);

// 2. Validasi parameter dasar
if (empty($metric) || empty($service) || !$bulan || !$tahun) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap.']);
    exit;
}

$valid_services = ['ICU', 'KN', 'PERIN', 'ISO', 'Umum'];
if (!in_array($service, $valid_services, true)) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Jenis pelayanan tidak valid: ' . htmlspecialchars($service, ENT_QUOTES, 'UTF-8')]);
    exit;
}

$start_date = date('Y-m-d', mktime(0, 0, 0, $bulan, 1, $tahun));
$end_date = date('Y-m-t', strtotime($start_date));
$prev_month_start_date = date('Y-m-d', strtotime('-1 month', strtotime($start_date)));

$bangsal_clause = '';
if ($service === 'Umum') {
    $bangsal_clause = "b.kd_bangsal NOT IN ('ICU', 'KN', 'PERIN', 'ISO')";
} else {
    $bangsal_clause = 'b.kd_bangsal = ?';
}

$sql = '';
$params = [];
$types = '';

switch ($metric) {
    case 'pasien_awal':
        $sql = "SELECT 
                    p.no_rkm_medis AS 'No. RM', 
                    p.nm_pasien AS 'Nama Pasien', 
                    ki.tgl_masuk AS 'Tgl Masuk', 
                    ki.tgl_keluar AS 'Tgl Keluar',
                    b.nm_bangsal AS 'Bangsal'
                FROM kamar_inap ki
                INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                LEFT JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
                LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                WHERE {$bangsal_clause} AND ki.tgl_masuk < ? AND (ki.tgl_keluar >= ? OR ki.tgl_keluar IS NULL)";
        $params = ($service === 'Umum') ? [$start_date, $start_date] : [$service, $start_date, $start_date];
        $types = ($service === 'Umum') ? 'ss' : 'sss';
        break;

    case 'pasien_masuk':
        $sql = "SELECT
                    p.no_rkm_medis AS 'No. RM',
                    p.nm_pasien AS 'Nama Pasien',
                    ki.tgl_masuk AS 'Tgl Masuk',
                    b.nm_bangsal AS 'Bangsal'
                FROM kamar_inap ki
                INNER JOIN (
                    SELECT no_rawat, MIN(CONCAT(tgl_masuk, ' ', jam_masuk)) as min_datetime
                    FROM kamar_inap
                    GROUP BY no_rawat
                ) first_entry ON ki.no_rawat = first_entry.no_rawat 
                               AND CONCAT(ki.tgl_masuk, ' ', ki.jam_masuk) = first_entry.min_datetime
                INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
                INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                WHERE {$bangsal_clause}
                    AND ki.tgl_masuk BETWEEN ? AND ?";
        $params = ($service === 'Umum') ? [$start_date, $end_date] : [$service, $start_date, $end_date];
        $types = ($service === 'Umum') ? 'ss' : 'sss';
        break;

    case 'pasien_keluar_hidup':
        $sql = "SELECT p.no_rkm_medis AS 'No. RM', p.nm_pasien AS 'Nama Pasien', ki.tgl_keluar AS 'Tgl Keluar', ki.stts_pulang AS 'Status Pulang', b.nm_bangsal AS 'Bangsal'
                FROM kamar_inap ki
                INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
                INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                INNER JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                WHERE {$bangsal_clause} AND ki.tgl_keluar BETWEEN ? AND ? AND ki.stts_pulang NOT IN ('Meninggal', 'Pindah Kamar')";
        $params = ($service === 'Umum') ? [$start_date, $end_date] : [$service, $start_date, $end_date];
        $types = ($service === 'Umum') ? 'ss' : 'sss';
        break;

    case 'laki_mati_under_48':
    case 'laki_mati_over_48':
    case 'perempuan_mati_under_48':
    case 'perempuan_mati_over_48':
        $jk = (strpos($metric, 'laki') !== false) ? 'L' : 'P';
        $kondisi_jam = (strpos($metric, 'under_48') !== false) ? '< 48' : '>= 48';

        $sql = "SELECT p.no_rkm_medis AS 'No. RM', p.nm_pasien AS 'Nama Pasien', p.jk AS 'JK', ki.tgl_keluar AS 'Tgl Keluar', TIMESTAMPDIFF(HOUR, CONCAT(ki.tgl_masuk, ' ', ki.jam_masuk), CONCAT(ki.tgl_keluar, ' ', ki.jam_keluar)) AS 'Jam Rawat'
                FROM kamar_inap ki
                INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
                INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                WHERE {$bangsal_clause} AND ki.tgl_keluar BETWEEN ? AND ? AND ki.stts_pulang = 'Meninggal' AND p.jk = ? AND TIMESTAMPDIFF(HOUR, CONCAT(ki.tgl_masuk, ' ', ki.jam_masuk), CONCAT(ki.tgl_keluar, ' ', ki.jam_keluar)) {$kondisi_jam}";
        $params = ($service === 'Umum') ? [$start_date, $end_date, $jk] : [$service, $start_date, $end_date, $jk];
        $types = ($service === 'Umum') ? 'sss' : 'ssss';
        break;

    case 'jumlah_lama_dirawat':
        $sql = "SELECT p.no_rkm_medis AS 'No. RM', p.nm_pasien AS 'Nama Pasien', ki.tgl_masuk AS 'Tgl Masuk', ki.tgl_keluar AS 'Tgl Keluar', ki.lama AS 'Lama Dirawat (Hari)'
                FROM kamar_inap ki
                INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
                INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                WHERE {$bangsal_clause} AND ki.tgl_keluar BETWEEN ? AND ?";
        $params = ($service === 'Umum') ? [$start_date, $end_date] : [$service, $start_date, $end_date];
        $types = ($service === 'Umum') ? 'ss' : 'sss';
        break;

    case 'jumlah_hari_perawatan':
        $sql = "
            SELECT
                p.no_rkm_medis AS 'No. RM',
                p.nm_pasien AS 'Nama Pasien',
                ki.tgl_masuk AS 'Tgl Masuk RS',
                ki.tgl_keluar AS 'Tgl Keluar RS',
                k.kelas AS 'Kelas',
                DATEDIFF(
                    LEAST(ki.tgl_keluar, ?),
                    GREATEST(ki.tgl_masuk, ?)
                ) + 1 AS 'Hari Perawatan di Bulan Laporan'
            FROM kamar_inap ki
            INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
            INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
            INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
            INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
            WHERE
                {$bangsal_clause}
                AND ki.tgl_masuk <= ?
                AND ki.tgl_keluar >= ?
                AND ki.tgl_keluar <> '0000-00-00' AND ki.tgl_keluar IS NOT NULL 
        ";

        if ($service === 'Umum') {
            $params = [$end_date, $start_date, $end_date, $start_date];
            $types = 'ssss';
        } else {
            $params = [$end_date, $start_date, $service, $end_date, $start_date];
            $types = 'sssss';
        }
        break;

    case 'pasien_pindahan':
        $bangsal_clause_baru = ($service === 'Umum')
            ? "bangsal_baru.kd_bangsal NOT IN ('ICU', 'KN', 'PERIN', 'ISO')"
            : 'bangsal_baru.kd_bangsal = ?';

        $sql = "SELECT p.no_rkm_medis AS 'No. RM', p.nm_pasien AS 'Nama Pasien', baru.tgl_masuk AS 'Tgl Pindah', bangsal_lama.nm_bangsal AS 'Dari Bangsal', bangsal_baru.nm_bangsal AS 'Ke Bangsal'
                FROM kamar_inap AS lama
                INNER JOIN kamar_inap AS baru ON lama.no_rawat = baru.no_rawat AND lama.tgl_keluar = baru.tgl_masuk AND lama.jam_keluar = baru.jam_masuk
                INNER JOIN kamar AS kamar_lama ON lama.kd_kamar = kamar_lama.kd_kamar
                INNER JOIN bangsal AS bangsal_lama ON kamar_lama.kd_bangsal = bangsal_lama.kd_bangsal
                INNER JOIN kamar AS kamar_baru ON baru.kd_kamar = kamar_baru.kd_kamar
                INNER JOIN bangsal AS bangsal_baru ON kamar_baru.kd_bangsal = bangsal_baru.kd_bangsal
                LEFT JOIN reg_periksa rp ON baru.no_rawat = rp.no_rawat
                LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                WHERE lama.stts_pulang = 'Pindah Kamar' AND baru.tgl_masuk BETWEEN ? AND ?
                AND {$bangsal_clause_baru}
                AND (
                    CASE 
                        WHEN bangsal_lama.kd_bangsal = 'ICU' THEN 'ICU' 
                        WHEN bangsal_lama.kd_bangsal = 'KN' THEN 'NICU'
                        WHEN bangsal_lama.kd_bangsal = 'ISO' THEN 'Isolasi'
                        WHEN bangsal_lama.kd_bangsal = 'PERIN' THEN 'Perinatologi'
                        ELSE 'Umum' 
                    END
                ) <> (
                    CASE 
                        WHEN bangsal_baru.kd_bangsal = 'ICU' THEN 'ICU' 
                        WHEN bangsal_baru.kd_bangsal = 'KN' THEN 'NICU'
                        WHEN bangsal_baru.kd_bangsal = 'ISO' THEN 'Isolasi'
                        WHEN bangsal_baru.kd_bangsal = 'PERIN' THEN 'Perinatologi'
                        ELSE 'Umum' 
                    END
                )";

        $params = ($service === 'Umum') ? [$start_date, $end_date] : [$start_date, $end_date, $service];
        $types = ($service === 'Umum') ? 'ss' : 'sss';
        break;

    case 'pasien_dipindahkan':
        $bangsal_clause_lama = ($service === 'Umum')
            ? "bangsal_lama.kd_bangsal NOT IN ('ICU', 'KN', 'PERIN', 'ISO')"
            : 'bangsal_lama.kd_bangsal = ?';

        $sql = "SELECT p.no_rkm_medis AS 'No. RM', p.nm_pasien AS 'Nama Pasien', lama.tgl_keluar AS 'Tgl Pindah', bangsal_lama.nm_bangsal AS 'Dari Bangsal', bangsal_baru.nm_bangsal AS 'Ke Bangsal'
                FROM kamar_inap AS lama
                INNER JOIN kamar_inap AS baru ON lama.no_rawat = baru.no_rawat AND lama.tgl_keluar = baru.tgl_masuk AND lama.jam_keluar = baru.jam_masuk
                INNER JOIN kamar AS kamar_lama ON lama.kd_kamar = kamar_lama.kd_kamar
                INNER JOIN bangsal AS bangsal_lama ON kamar_lama.kd_bangsal = bangsal_lama.kd_bangsal
                INNER JOIN kamar AS kamar_baru ON baru.kd_kamar = kamar_baru.kd_kamar
                INNER JOIN bangsal AS bangsal_baru ON kamar_baru.kd_bangsal = bangsal_baru.kd_bangsal
                LEFT JOIN reg_periksa rp ON lama.no_rawat = rp.no_rawat
                LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                WHERE lama.stts_pulang = 'Pindah Kamar' AND lama.tgl_keluar BETWEEN ? AND ?
                AND {$bangsal_clause_lama}
                AND (
                    CASE 
                        WHEN bangsal_lama.kd_bangsal = 'ICU' THEN 'ICU' 
                        WHEN bangsal_lama.kd_bangsal = 'KN' THEN 'NICU'
                        WHEN bangsal_lama.kd_bangsal = 'ISO' THEN 'Isolasi'
                        WHEN bangsal_lama.kd_bangsal = 'PERIN' THEN 'Perinatologi'
                        ELSE 'Umum' 
                    END
                ) <> (
                    CASE 
                        WHEN bangsal_baru.kd_bangsal = 'ICU' THEN 'ICU' 
                        WHEN bangsal_baru.kd_bangsal = 'KN' THEN 'NICU'
                        WHEN bangsal_baru.kd_bangsal = 'ISO' THEN 'Isolasi'
                        WHEN bangsal_baru.kd_bangsal = 'PERIN' THEN 'Perinatologi'
                        ELSE 'Umum' 
                    END
                )";

        $params = ($service === 'Umum') ? [$start_date, $end_date] : [$start_date, $end_date, $service];
        $types = ($service === 'Umum') ? 'ss' : 'sss';
        break;

    case 'pasien_akhir':
        $sql = "SELECT 
                    p.no_rkm_medis AS 'No. RM', 
                    p.nm_pasien AS 'Nama Pasien', 
                    ki.tgl_masuk AS 'Tgl Masuk', 
                    ki.tgl_keluar AS 'Tgl Keluar',
                    b.nm_bangsal AS 'Bangsal'
                FROM kamar_inap ki
                INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                LEFT JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
                LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                WHERE {$bangsal_clause} AND ki.tgl_masuk <= ? AND (ki.tgl_keluar > ? OR ki.tgl_keluar IS NULL)";

        $params = ($service === 'Umum') ? [$end_date, $end_date] : [$service, $end_date, $end_date];
        $types = ($service === 'Umum') ? 'ss' : 'sss';
        break;

    case 'alokasi_tempat_tidur':
        $sql = "SELECT b.nm_bangsal AS 'Bangsal', k.kd_kamar AS 'Kode Kamar', k.kelas AS 'Kelas', k.status AS 'Status Kamar'
                FROM kamar k
                INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                WHERE k.statusdata = '1' AND {$bangsal_clause}";

        $params = ($service === 'Umum') ? [] : [$service];
        $types = ($service === 'Umum') ? '' : 's';
        break;

    default:
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Metrik tidak dikenal: ' . htmlspecialchars($metric, ENT_QUOTES, 'UTF-8')]);
        exit;
}

$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Gagal mempersiapkan statement: ' . $mysqli->error]);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Gagal mengeksekusi statement: ' . $stmt->error]);
    exit;
}

$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

    ob_end_clean();
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi error pada endpoint rincian.',
        'debug' => [
            'exception' => $e->getMessage()
        ]
    ]);
    exit;
}

