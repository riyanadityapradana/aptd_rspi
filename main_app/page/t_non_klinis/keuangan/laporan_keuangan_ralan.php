<?php
require_once __DIR__ . '/laporan_keuangan_ralan_helper.php';

function aptd_keu_ralan_datatable_html($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function aptd_keu_ralan_datatable_money($value)
{
    return 'Rp ' . aptd_currency($value);
}

function aptd_keu_ralan_datatable_action(
    array $row,
    $startDate,
    $endDate,
    $kdPoli,
    $reportPage,
    $canCalculateKeuangan
) {
    $actionLabel = aptd_keu_ralan_action_label($row);
    $html = '<form method="post" style="margin:0">';
    if ($actionLabel === 'Pakai Riwayat') {
        $html .= '<input type="hidden" name="use_history_claim" value="1">'
            . '<input type="hidden" name="history_no_rawat" value="'
            . aptd_keu_ralan_datatable_html($row['no_rawat']) . '">';
        $title = 'Pakai estimasi klaim dari riwayat diagnosa';
        $enabled = $canCalculateKeuangan;
    } else {
        $html .= '<input type="hidden" name="calculate_keu_row" value="1">'
            . '<input type="hidden" name="calculate_no_rawat" value="'
            . aptd_keu_ralan_datatable_html($row['no_rawat']) . '">';
        $enabled = $canCalculateKeuangan && (float) $row['claim_used'] > 0;
        if (!$enabled) {
            $title = 'Klaim digunakan belum tersedia';
        } elseif (!empty($row['calculation_stale'])) {
            $title = 'Klaim Aktual sudah tersedia. Hitung ulang untuk memperbarui seluruh kalkulasi.';
        } else {
            $title = $actionLabel === 'Hitung Ulang'
                ? 'Hitung ulang data keuangan'
                : 'Hitung data keuangan';
        }
    }

    $html .= '<input type="hidden" name="start_date" value="' . aptd_keu_ralan_datatable_html($startDate) . '">'
        . '<input type="hidden" name="end_date" value="' . aptd_keu_ralan_datatable_html($endDate) . '">'
        . '<input type="hidden" name="kd_poli" value="' . aptd_keu_ralan_datatable_html($kdPoli) . '">'
        . '<input type="hidden" name="report_page" class="keu-report-page" value="' . (int) $reportPage . '">'
        . '<button type="submit" class="keu-calc-btn" title="' . aptd_keu_ralan_datatable_html($title) . '"'
        . ($enabled ? '' : ' disabled aria-disabled="true"') . '>'
        . aptd_keu_ralan_datatable_html($actionLabel) . '</button></form>';
    return $html;
}

function aptd_keu_ralan_datatable_response(mysqli $mysqli)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/akses.php';
    header('Content-Type: application/json; charset=UTF-8');

    $level = isset($_SESSION['level']) ? $_SESSION['level'] : '';
    if (
        !isset($_SESSION['login_aptd_rspi'])
        || $_SESSION['login_aptd_rspi'] !== true
        || !aptd_can_access($level, 'laporan_keuangan_ralan')
    ) {
        http_response_code(403);
        echo json_encode(['error' => 'Anda tidak memiliki hak akses untuk membuka laporan ini.']);
        return;
    }

    list($startDate, $endDate, $kdPoli, $filterValid, $filterMessage) = aptd_keu_ralan_filters();
    if (!$filterValid) {
        http_response_code(400);
        echo json_encode(['error' => $filterMessage]);
        return;
    }

    $request = array_merge($_GET, $_POST);
    $draw = isset($request['draw']) ? max(0, (int) $request['draw']) : 0;
    $start = isset($request['start']) ? max(0, (int) $request['start']) : 0;
    $length = isset($request['length']) ? max(1, min(100, (int) $request['length'])) : 10;
    $search = isset($request['search']['value']) ? trim((string) $request['search']['value']) : '';
    $orderColumn = isset($request['order'][0]['column']) ? (int) $request['order'][0]['column'] : 0;
    $orderDirection = isset($request['order'][0]['dir']) ? $request['order'][0]['dir'] : 'asc';
    $reportPage = (int) floor($start / $length);
    $canCalculateKeuangan = in_array($level, ['admin', 'keuangan'], true);

    try {
        $recordsTotal = aptd_keu_ralan_count_rows($mysqli, $startDate, $endDate, $kdPoli);
        $recordsFiltered = $search === ''
            ? $recordsTotal
            : aptd_keu_ralan_count_rows($mysqli, $startDate, $endDate, $kdPoli, $search);
        $rows = aptd_keu_ralan_fetch_rows(
            $mysqli,
            $startDate,
            $endDate,
            $kdPoli,
            '',
            $search,
            $length,
            $start,
            $orderColumn,
            $orderDirection
        );

        $data = [];
        $pageTotal = 0;
        $pageMargin = 0;
        foreach ($rows as $row) {
            $historyTitle = $row['claim_history_no_rawat'] !== ''
                ? 'Riwayat: ' . $row['claim_history_no_rawat']
                    . ' · via ' . $row['claim_history_match_source']
                    . ' · ' . $row['target_diagnosis_source'] . ': ' . $row['target_diagnosis_code']
                : $row['target_diagnosis_source'] . ': '
                    . ($row['target_diagnosis_code'] !== '' ? $row['target_diagnosis_code'] : '-');
            $jdTitle = aptd_keu_ralan_datatable_html($row['jd_rule']);
            $jdPemeriksaan = (float) $row['jd_pemeriksaan'] > 0
                ? aptd_keu_ralan_datatable_money($row['jd_pemeriksaan'])
                : '-';
            $jdProsedur = (float) $row['jd_prosedur'] > 0
                ? aptd_keu_ralan_datatable_money($row['jd_prosedur'])
                : '-';
            $jdDokterAnestesi = (float) $row['jd_dokter_anestesi'] > 0
                ? aptd_keu_ralan_datatable_money($row['jd_dokter_anestesi'])
                : '-';
            $jdDokterAnak = (float) $row['jd_dokter_anak'] > 0
                ? aptd_keu_ralan_datatable_money($row['jd_dokter_anak'])
                : '-';
            $jdHd = (float) $row['jd_hd'] > 0
                ? aptd_keu_ralan_datatable_money($row['jd_hd'])
                : '-';
            $jdUsg = (float) $row['jd_usg'] > 0
                ? aptd_keu_ralan_datatable_money($row['jd_usg'])
                : '-';
            $jdRontgen = (float) $row['jd_rontgen'] > 0
                ? aptd_keu_ralan_datatable_money($row['jd_rontgen'])
                : '-';
            $jdLab = (float) $row['jd_lab'] > 0
                ? aptd_keu_ralan_datatable_money($row['jd_lab'])
                : '-';
            $jdPa = (float) $row['jd_pa'] > 0
                ? aptd_keu_ralan_datatable_money($row['jd_pa'])
                : '-';
            $bhpLabPk = aptd_keu_ralan_datatable_money($row['bhp_lab_pk']);
            $bhpLabPa = aptd_keu_ralan_datatable_money($row['bhp_lab_pa']);
            $bhpRadUsg = aptd_keu_ralan_datatable_money($row['bhp_rad_usg']);
            $bhpRontgen = aptd_keu_ralan_datatable_money($row['bhp_rontgen']);
            $jasaKaryawan = (float) $row['jasa_karyawan'] > 0
                ? aptd_keu_ralan_datatable_money($row['jasa_karyawan'])
                : '-';
            $biayaBhp = aptd_keu_ralan_datatable_money($row['biaya_bhp']);
            $biayaObat = aptd_keu_ralan_datatable_money($row['biaya_obat']);
            $biayaEkg = aptd_keu_ralan_datatable_money($row['biaya_ekg']);
            $biayaDarah = aptd_keu_ralan_datatable_money($row['biaya_darah']);
            $makanJumlah = aptd_keu_ralan_datatable_money($row['makan_jumlah']);
            $makanHarga = aptd_keu_ralan_datatable_money($row['makan_harga']);
            $makanKali = aptd_keu_ralan_datatable_html(aptd_number($row['makan_kali']));
            $biayaFototheraphy = aptd_keu_ralan_datatable_money($row['biaya_fototheraphy']);
            $biayaOksigen = aptd_keu_ralan_datatable_money($row['biaya_oksigen']);
            $biayaSpirometri = aptd_keu_ralan_datatable_money($row['biaya_spirometri']);
            $total = aptd_keu_ralan_datatable_money($row['total']);
            $margin = aptd_keu_ralan_datatable_money($row['margin']);
            $keteranganDarah = aptd_keu_ralan_datatable_html(aptd_number($row['keterangan_darah']));
            $keteranganAlbumin = aptd_keu_ralan_datatable_html(aptd_number($row['keterangan_albumin']));
            $keteranganTindakan = trim((string) $row['keterangan_tindakan']) !== ''
                ? (string) $row['keterangan_tindakan']
                : '-';
            $marginClass = (float) $row['margin'] < 0 ? ' keu-negative' : '';
            $pageTotal += (float) $row['total'];
            $pageMargin += (float) $row['margin'];

            $data[] = [
                aptd_keu_ralan_datatable_html(date('d-m-Y', strtotime($row['tgl_registrasi']))),
                aptd_keu_ralan_datatable_html($row['no_rawat']),
                aptd_keu_ralan_datatable_html($row['no_rkm_medis']),
                '<span title="' . aptd_keu_ralan_datatable_html($row['nm_pasien']) . '">'
                    . aptd_keu_ralan_datatable_html($row['nm_pasien']) . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['nm_dokter']) . '">'
                    . aptd_keu_ralan_datatable_html($row['nm_dokter']) . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['no_sep'] !== '' ? $row['no_sep'] : '-') . '">'
                    . aptd_keu_ralan_datatable_html($row['no_sep'] !== '' ? $row['no_sep'] : '-') . '</span>',
                aptd_keu_ralan_datatable_html($row['nm_poli']),
                aptd_keu_ralan_datatable_html($row['nm_sps'] !== '' ? $row['nm_sps'] : '-'),
                aptd_keu_ralan_datatable_html($row['status_periksa']),
                aptd_keu_ralan_datatable_html($row['status_bayar']),
                aptd_keu_ralan_datatable_html($row['jenis_bayar']),
                '<span title="' . aptd_keu_ralan_datatable_html($historyTitle) . '">'
                    . aptd_keu_ralan_datatable_money($row['claim_history']) . '</span>',
                aptd_keu_ralan_datatable_money($row['claim_actual']),
                '<strong>' . aptd_keu_ralan_datatable_money($row['claim_used']) . '</strong>',
                '<span title="' . $jdTitle . '">' . $jdPemeriksaan . '</span>',
                '<span title="' . $jdTitle . '">' . $jdProsedur . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['jd_anestesi_rule']) . '">'
                    . $jdDokterAnestesi . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['jd_anak_rule']) . '">'
                    . $jdDokterAnak . '</span>',
                '<span title="' . $jdTitle . '">' . $jdHd . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['jd_usg_rule']) . '">'
                    . $jdUsg . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['jd_rontgen_rule']) . '">'
                    . $jdRontgen . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['jd_lab_rule']) . '">'
                    . $jdLab . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['jd_pa_rule']) . '">'
                    . $jdPa . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['bhp_lab_pk_rule']) . '">'
                    . $bhpLabPk . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['bhp_lab_pa_rule']) . '">'
                    . $bhpLabPa . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['bhp_rad_usg_rule']) . '">'
                    . $bhpRadUsg . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['bhp_rontgen_rule']) . '">'
                    . $bhpRontgen . '</span>',
                '<span title="15% dari Klaim Digunakan">' . $jasaKaryawan . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['biaya_bhp_rule']) . '">'
                    . $biayaBhp . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['biaya_obat_rule']) . '">'
                    . $biayaObat . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['biaya_ekg_rule']) . '">'
                    . $biayaEkg . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['biaya_darah_rule']) . '">'
                    . $biayaDarah . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['makan_rule']) . '">'
                    . $makanJumlah . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['makan_rule']) . '">'
                    . $makanHarga . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['makan_rule']) . '">'
                    . $makanKali . '</span>',
                $biayaFototheraphy,
                '<span title="' . aptd_keu_ralan_datatable_html($row['biaya_oksigen_rule']) . '">'
                    . $biayaOksigen . '</span>',
                '<span title="' . aptd_keu_ralan_datatable_html($row['biaya_spirometri_rule']) . '">'
                    . $biayaSpirometri . '</span>',
                '<strong title="' . aptd_keu_ralan_datatable_html($row['total_rule']) . '">'
                    . $total . '</strong>',
                '<strong class="' . trim($marginClass) . '" title="'
                    . aptd_keu_ralan_datatable_html($row['margin_rule']) . '">'
                    . $margin . '</strong>',
                $keteranganDarah,
                $keteranganAlbumin,
                '<span title="' . aptd_keu_ralan_datatable_html($keteranganTindakan) . '">'
                    . aptd_keu_ralan_datatable_html($keteranganTindakan) . '</span>',
                aptd_keu_ralan_datatable_html($row['claim_source']),
                aptd_keu_ralan_datatable_action(
                    $row,
                    $startDate,
                    $endDate,
                    $kdPoli,
                    $reportPage,
                    $canCalculateKeuangan
                ),
            ];
        }

        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
            'pageTotal' => round($pageTotal, 2),
            'pageMargin' => round($pageMargin, 2),
        ]);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        http_response_code(500);
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => 'Data laporan belum dapat dimuat.',
        ]);
    }
}

function aptd_keu_ralan_bulk_daily_response(mysqli $mysqli)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/akses.php';
    header('Content-Type: application/json; charset=UTF-8');

    $level = isset($_SESSION['level']) ? $_SESSION['level'] : '';
    if (
        !isset($_SESSION['login_aptd_rspi'])
        || $_SESSION['login_aptd_rspi'] !== true
        || !aptd_can_access($level, 'laporan_keuangan_ralan')
        || !in_array($level, ['admin', 'keuangan'], true)
    ) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses untuk menghitung data keuangan.']);
        return;
    }
    if (strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
        return;
    }

    try {
        $result = aptd_keu_ralan_calculate_daily_batch(
            $mysqli,
            isset($_POST['bulk_date']) ? $_POST['bulk_date'] : '',
            isset($_POST['offset']) ? $_POST['offset'] : 0,
            isset($_POST['batch_size']) ? $_POST['batch_size'] : 10,
            isset($_SESSION['username']) ? $_SESSION['username'] : ''
        );
        if (!$result['success']) {
            http_response_code(400);
        }
        echo json_encode($result);
    } catch (Throwable $exception) {
        error_log('Kalkulasi massal Ralan gagal: ' . $exception->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Kalkulasi massal belum dapat diselesaikan. Silakan coba kembali.',
        ]);
    }
}

if (isset($_GET['keu_ralan_mode']) && $_GET['keu_ralan_mode'] === 'datatable') {
    aptd_keu_ralan_datatable_response($mysqli);
    exit;
}
if (isset($_GET['keu_ralan_mode']) && $_GET['keu_ralan_mode'] === 'bulk_daily') {
    aptd_keu_ralan_bulk_daily_response($mysqli);
    exit;
}

list($startDate, $endDate, $kdPoli, $filterValid, $filterMessage) = aptd_keu_ralan_filters();
$levelLogin = isset($_SESSION['level']) ? $_SESSION['level'] : '';
$canCalculateKeuangan = in_array($levelLogin, ['admin', 'keuangan'], true);
$saveMessage = null;
$isReportRowAction = (isset($_POST['calculate_keu_row']) && $_POST['calculate_keu_row'] === '1')
    || (isset($_POST['use_history_claim']) && $_POST['use_history_claim'] === '1');
$reportPage = $isReportRowAction
    ? (isset($_POST['report_page']) ? max(0, (int) $_POST['report_page']) : 0)
    : (isset($_GET['report_page']) ? max(0, (int) $_GET['report_page']) : 0);

if ($isReportRowAction) {
    if (!$filterValid) {
        $saveMessage = ['success' => false, 'message' => $filterMessage];
    } elseif (!$canCalculateKeuangan) {
        $saveMessage = ['success' => false, 'message' => 'Level Anda tidak memiliki akses untuk menjalankan aksi keuangan.'];
    } elseif (isset($_POST['use_history_claim']) && $_POST['use_history_claim'] === '1') {
        $saveMessage = aptd_keu_ralan_use_history_claim(
            $mysqli,
            isset($_POST['history_no_rawat']) ? $_POST['history_no_rawat'] : '',
            $startDate,
            $endDate,
            $kdPoli,
            isset($_SESSION['username']) ? $_SESSION['username'] : ''
        );
    } else {
        $saveMessage = aptd_keu_ralan_calculate_claim(
            $mysqli,
            isset($_POST['calculate_no_rawat']) ? $_POST['calculate_no_rawat'] : '',
            $startDate,
            $endDate,
            $kdPoli,
            isset($_SESSION['username']) ? $_SESSION['username'] : ''
        );
    }

    $_SESSION['keu_ralan_flash'] = $saveMessage;
    $redirectUrl = 'main_app.php?page=laporan_keuangan_ralan&'
        . aptd_keu_ralan_filter_query($startDate, $endDate, $kdPoli)
        . '&report_page=' . rawurlencode($reportPage);
    echo '<script>window.location.href=' . json_encode($redirectUrl) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    return;
}

if (isset($_SESSION['keu_ralan_flash'])) {
    $saveMessage = $_SESSION['keu_ralan_flash'];
    unset($_SESSION['keu_ralan_flash']);
}

$poliOptions = aptd_keu_ralan_fetch_poli($mysqli);
$poliLabel = 'Semua Poliklinik';
foreach ($poliOptions as $option) {
    if ($option['kd_poli'] === $kdPoli) {
        $poliLabel = $option['nm_poli'];
        break;
    }
}

$queryMessage = '';
$summary = aptd_keu_ralan_summary([]);
if ($filterValid) {
    try {
        $summary = aptd_keu_ralan_fetch_summary($mysqli, $startDate, $endDate, $kdPoli);
    } catch (Throwable $exception) {
        $queryMessage = 'Data laporan belum dapat dimuat. Silakan coba kembali atau hubungi administrator.';
        error_log($exception->getMessage());
    }
}

ob_start(); ?>
<form method="get" action="main_app.php" class="analytics-filter">
    <input type="hidden" name="page" value="laporan_keuangan_ralan">
    <div class="form-group mb-0">
        <label for="start_date"><strong>Tanggal Awal</strong></label>
        <input type="date" name="start_date" id="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>
    <div class="form-group mb-0">
        <label for="end_date"><strong>Tanggal Akhir</strong></label>
        <input type="date" name="end_date" id="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>
    <div class="form-group mb-0" style="min-width:260px">
        <label for="kd_poli"><strong>Poliklinik</strong></label>
        <select name="kd_poli" id="kd_poli" class="form-control form-control-sm select2">
            <option value="">Semua Poliklinik</option>
            <?php foreach ($poliOptions as $option): ?>
                <option value="<?php echo htmlspecialchars($option['kd_poli'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $option['kd_poli'] === $kdPoli ? 'selected' : ''; ?>><?php echo htmlspecialchars($option['nm_poli'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button>
    <?php if ($filterValid): ?>
        <a class="btn btn-success btn-sm px-4" href="<?php echo htmlspecialchars(aptd_keu_ralan_export_url($startDate, $endDate, $kdPoli), ENT_QUOTES, 'UTF-8'); ?>">Export Excel (.xlsx)</a>
    <?php endif; ?>
</form>
<?php $filters = ob_get_clean();

ob_start(); ?>
<section class="analytics-cards keu-ralan-cards">
    <div class="analytics-card"><div class="analytics-k">Kunjungan BPJS</div><div class="analytics-v"><?php echo aptd_number($summary['jumlah_kunjungan']); ?></div><div class="analytics-s">Rawat jalan pada periode terpilih</div></div>
    <div class="analytics-card"><div class="analytics-k">Total Klaim</div><div class="analytics-v">Rp <?php echo aptd_currency($summary['total_klaim']); ?></div><div class="analytics-s">Akumulasi klaim digunakan</div></div>
    <div class="analytics-card"><div class="analytics-k">Total Jasa Dokter</div><div class="analytics-v">Rp <?php echo aptd_currency($summary['total_jasa_dokter']); ?></div><div class="analytics-s">Akumulasi seluruh jasa dokter</div></div>
    <div class="analytics-card"><div class="analytics-k">Total Obat</div><div class="analytics-v">Rp <?php echo aptd_currency($summary['total_obat']); ?></div><div class="analytics-s">Akumulasi biaya dasar obat</div></div>
</section>
<?php $cards = ob_get_clean();

ob_start(); ?>
<section class="analytics-panel keu-ralan-table-panel">
    <div class="analytics-head">
        <div>
            <h2 class="analytics-h">Detail Keuangan Rawat Jalan</h2>
            <p class="analytics-d">Periode <?php echo htmlspecialchars(date('d-m-Y', strtotime($startDate)) . ' s.d. ' . date('d-m-Y', strtotime($endDate)), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($poliLabel, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <span class="analytics-pill">Jenis bayar: BPJS</span>
    </div>

    <?php if ($saveMessage): ?>
        <div class="alert <?php echo $saveMessage['success'] ? 'alert-success' : 'alert-danger'; ?>" id="info"><?php echo htmlspecialchars($saveMessage['message'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (!$filterValid): ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars($filterMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php elseif ($queryMessage !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($queryMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <style>
        .keu-ralan-cards{width:100%;min-width:0;grid-template-columns:repeat(4,minmax(0,1fr))}
        .keu-ralan-cards .analytics-card{min-width:0;overflow:hidden}
        .keu-ralan-cards .analytics-v,.keu-ralan-cards .analytics-s{overflow-wrap:anywhere}
        .keu-ralan-table-panel{width:100%;min-width:0;max-width:100%;overflow:hidden;margin-bottom:52px}
        .keu-ralan-scroll{display:block;width:100%;max-width:100%;overflow-x:auto;position:relative;isolation:isolate;padding-bottom:10px;background:#fff}
        .keu-ralan-scroll::-webkit-scrollbar{height:14px}
        .keu-ralan-scroll::-webkit-scrollbar-thumb{background:#8b97aa;border-radius:999px;border:2px solid #eef2f7}
        .keu-ralan-scroll .dataTables_wrapper{width:max-content;min-width:100%;max-width:none}
        .keu-ralan-table-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;min-width:0;padding:0 0 10px;background:#fff;position:relative;z-index:12}
        .keu-ralan-bulk{display:flex;align-items:center;gap:8px;min-width:0;flex-wrap:wrap}
        .keu-ralan-bulk input{width:158px;height:34px}
        .keu-ralan-bulk button{height:34px;white-space:nowrap}
        .keu-ralan-bulk-status{font-size:12px;font-weight:700;color:#526581}
        .keu-ralan-bulk-status.is-success{color:#15803d}
        .keu-ralan-bulk-status.is-error{color:#dc2626}
        .keu-ralan-search{display:flex;justify-content:flex-end;min-width:0;margin-left:auto}
        .keu-ralan-search .dataTables_filter{float:none!important;margin:0!important;text-align:right}
        .keu-ralan-search .dataTables_filter label{display:flex;align-items:center;margin:0!important}
        .keu-ralan-search .dataTables_filter input{width:220px;max-width:100%;height:34px;margin-left:0!important}
        .keu-ralan-pagination-bar{display:flex;align-items:center;justify-content:space-between;gap:14px;width:100%;min-width:0;padding:10px 0 4px;background:#fff;position:relative;z-index:12}
        .keu-ralan-pagination-info{min-width:0;color:#526581;font-size:13px}
        .keu-ralan-pagination-info .dataTables_info{padding-top:0!important;white-space:normal}
        .keu-ralan-pagination-actions{display:flex;justify-content:flex-end;min-width:0;margin-left:auto}
        .keu-ralan-pagination-actions .dataTables_paginate{float:none!important;margin:0!important;padding-top:0!important;white-space:nowrap}
        .keu-ralan-pagination-actions .pagination{margin:0!important;justify-content:flex-end;flex-wrap:wrap}
        .keu-ralan-table{width:5947px!important;min-width:5947px;table-layout:fixed;border-collapse:separate!important;border-spacing:0}
        .keu-ralan-table th,.keu-ralan-table td{box-sizing:border-box;vertical-align:middle!important;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .keu-ralan-table thead th{background:#343a40!important;color:#fff!important}
        .keu-ralan-table thead tr:first-child>th.keu-jd-group,.keu-ralan-table thead tr:first-child>th.keu-bhp-group,.keu-ralan-table thead tr:first-child>th.keu-makan-group,.keu-ralan-table thead tr:first-child>th.keu-keterangan-group{height:38px;border-bottom:2px solid #8ba4c7!important;letter-spacing:.2px}
        .keu-ralan-table thead tr:first-child>th.keu-jd-group{background:#203a5f!important}
        .keu-ralan-table thead tr:first-child>th.keu-bhp-group{background:#203a5f!important}
        .keu-ralan-table thead tr:first-child>th.keu-makan-group{background:#203a5f!important}
        .keu-ralan-table thead tr:first-child>th.keu-keterangan-group{background:#203a5f!important}
        .keu-ralan-table thead tr:nth-child(2)>th{height:52px;background:#34495e!important;white-space:normal;line-height:1.2;padding:7px 6px!important}
        .keu-ralan-table .col-claim{min-width:116px;text-align:right}
        .keu-ralan-table .col-claim-source{min-width:96px;text-align:center;font-weight:700}
        .keu-ralan-table .keu-jd-cell{text-align:right;color:#64748b;background:#f8fafc}
        .keu-ralan-table .keu-bhp-cell{text-align:right;color:#64748b;background:#f8fafc}
        .keu-ralan-table .keu-makan-cell{text-align:right;color:#64748b;background:#f8fafc}
        .keu-ralan-table .keu-keterangan-cell{background:#f8fafc}
        .keu-ralan-table .keu-negative{color:#dc2626!important}
        .keu-ralan-table thead tr:first-child>th:nth-child(1),.keu-ralan-table tbody td:nth-child(1){width:130px;min-width:130px;max-width:130px;position:sticky;left:0;z-index:5;background:#fff!important}
        .keu-ralan-table thead tr:first-child>th:nth-child(2),.keu-ralan-table tbody td:nth-child(2){width:145px;min-width:145px;max-width:145px;position:sticky;left:130px;z-index:5;background:#fff!important}
        .keu-ralan-table thead tr:first-child>th:nth-child(3),.keu-ralan-table tbody td:nth-child(3){width:80px;min-width:80px;max-width:80px;position:sticky;left:275px;z-index:5;background:#fff!important}
        .keu-ralan-table thead tr:first-child>th:nth-child(4),.keu-ralan-table tbody td:nth-child(4){width:190px;min-width:190px;max-width:190px;position:sticky;left:355px;z-index:5;background:#fff!important}
        .keu-ralan-table thead tr:first-child>th:nth-child(5),.keu-ralan-table tbody td:nth-child(5){width:260px;min-width:260px;max-width:260px;position:sticky;left:545px;z-index:5;background:#fff!important}
        .keu-ralan-table thead tr:first-child>th:nth-child(6),.keu-ralan-table tbody td:nth-child(6){width:185px;min-width:185px;max-width:185px;position:sticky;left:805px;z-index:5;background:#fff!important;border-right:3px solid #2f6fb2!important;box-shadow:10px 0 14px -12px rgba(15,23,42,.95)}
        .keu-ralan-table thead tr:first-child>th:nth-child(-n+6){z-index:10;background:#343a40!important;color:#fff!important}
        .keu-ralan-table thead tr:first-child>th:nth-child(-n+6),.keu-ralan-table tbody td:nth-child(-n+6){background-clip:padding-box}
        .keu-ralan-table tbody tr:hover td:nth-child(-n+6){background:#eceff3!important}
        .keu-ralan-table tfoot td{background:#f5f8fc!important}
        .keu-ralan-table tfoot td:nth-child(1){position:sticky;left:0;z-index:5}
        .keu-ralan-table tfoot td:nth-child(2){position:sticky;left:130px;z-index:5}
        .keu-ralan-table tfoot td:nth-child(3){position:sticky;left:275px;z-index:5}
        .keu-ralan-table tfoot td:nth-child(4){position:sticky;left:355px;z-index:5}
        .keu-ralan-table tfoot td:nth-child(5){position:sticky;left:545px;z-index:5}
        .keu-ralan-table tfoot td:nth-child(6){position:sticky;left:805px;z-index:5;border-right:3px solid #2f6fb2!important;box-shadow:10px 0 14px -12px rgba(15,23,42,.95)}
        .keu-ralan-table tfoot td:nth-child(-n+6){background:#f5f8fc!important;background-clip:padding-box}
        .keu-action-cell{width:132px;min-width:132px;max-width:132px;text-align:center;padding-left:6px!important;padding-right:6px!important}
        .keu-calc-btn{display:inline-flex;align-items:center;justify-content:center;min-width:64px;height:24px;border:0;border-radius:4px;background:#15803d;color:#fff;font-size:11px;font-weight:800;padding:2px 9px;cursor:pointer;line-height:1;white-space:nowrap}
        .keu-calc-btn:hover{background:#166534;color:#fff}
        .keu-calc-btn:disabled{background:#9ca3af;cursor:not-allowed}
        @media(max-width:991px){.keu-ralan-cards{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:576px){
            .keu-ralan-cards{grid-template-columns:minmax(0,1fr)}
            .keu-ralan-table-toolbar{align-items:stretch;flex-direction:column}
            .keu-ralan-bulk,.keu-ralan-bulk input,.keu-ralan-bulk button{width:100%}
            .keu-ralan-search,.keu-ralan-search .dataTables_filter,.keu-ralan-search .dataTables_filter label{width:100%}
            .keu-ralan-search .dataTables_filter input{width:100%}
            .keu-ralan-pagination-bar{align-items:flex-end;flex-direction:column}
            .keu-ralan-pagination-info,.keu-ralan-pagination-actions{width:100%}
            .keu-ralan-pagination-actions{justify-content:flex-end}
        }
    </style>
    <div class="keu-ralan-table-toolbar" aria-label="Pencarian laporan">
        <?php if ($canCalculateKeuangan): ?>
            <div class="keu-ralan-bulk" aria-label="Kalkulasi massal harian">
                <label for="keuRalanBulkDate" class="sr-only">Tanggal kunjungan</label>
                <input
                    type="date"
                    id="keuRalanBulkDate"
                    class="form-control form-control-sm"
                    value="<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>"
                >
                <button type="button" class="btn btn-primary btn-sm px-3" id="keuRalanBulkButton">
                    Hitung Data Harian
                </button>
                <span class="keu-ralan-bulk-status" id="keuRalanBulkStatus" role="status" aria-live="polite"></span>
            </div>
        <?php endif; ?>
        <div class="keu-ralan-search" id="keuRalanSearch"></div>
    </div>
    <div class="table-responsive keu-ralan-scroll">
        <table class="table table-sm table-bordered table-hover analytics-table keu-ralan-table" id="table4" style="width:100%;font-size:12px">
            <colgroup>
                <col style="width:130px">
                <col style="width:145px">
                <col style="width:80px">
                <col style="width:190px">
                <col style="width:260px">
                <col style="width:185px">
                <col style="width:180px">
                <col style="width:150px">
                <col style="width:110px">
                <col style="width:110px">
                <col style="width:100px">
                <col style="width:130px">
                <col style="width:130px">
                <col style="width:135px">
                <col style="width:140px">
                <col style="width:210px">
                <col style="width:140px">
                <col style="width:120px">
                <col style="width:105px">
                <col style="width:105px">
                <col style="width:125px">
                <col style="width:105px">
                <col style="width:105px">
                <col style="width:125px">
                <col style="width:125px">
                <col style="width:125px">
                <col style="width:125px">
                <col style="width:125px">
                <col style="width:120px">
                <col style="width:130px">
                <col style="width:120px">
                <col style="width:120px">
                <col style="width:120px">
                <col style="width:120px">
                <col style="width:120px">
                <col style="width:130px">
                <col style="width:120px">
                <col style="width:120px">
                <col style="width:130px">
                <col style="width:130px">
                <col style="width:90px">
                <col style="width:90px">
                <col style="width:240px">
                <col style="width:100px">
                <col style="width:132px">
            </colgroup>
            <thead class="thead-dark">
                <tr>
                    <th rowspan="2">Tanggal Kunjungan</th>
                    <th rowspan="2">Nomor Rawat</th>
                    <th rowspan="2">No. RM</th>
                    <th rowspan="2">Nama Pasien</th>
                    <th rowspan="2">Dokter Poliklinik</th>
                    <th rowspan="2">No. SEP</th>
                    <th rowspan="2">Poliklinik</th>
                    <th rowspan="2">Spesialistik</th>
                    <th rowspan="2">Status Periksa</th>
                    <th rowspan="2">Status Bayar</th>
                    <th rowspan="2">Jenis Bayar</th>
                    <th rowspan="2">Klaim Riwayat</th>
                    <th rowspan="2">Klaim Aktual</th>
                    <th rowspan="2">Klaim Digunakan</th>
                    <th colspan="9" class="keu-jd-group">Jasa Dokter</th>
                    <th colspan="4" class="keu-bhp-group">BHP Penunjang</th>
                    <th rowspan="2">JK</th>
                    <th rowspan="2">BHP</th>
                    <th rowspan="2">Obat</th>
                    <th rowspan="2">EKG</th>
                    <th rowspan="2">Darah</th>
                    <th colspan="3" class="keu-makan-group">Makan</th>
                    <th rowspan="2">Fototheraphy</th>
                    <th rowspan="2">Oksigen</th>
                    <th rowspan="2">Spirometri</th>
                    <th rowspan="2">TOTAL</th>
                    <th rowspan="2">MARGIN</th>
                    <th colspan="3" class="keu-keterangan-group">Keterangan</th>
                    <th rowspan="2">Sumber</th>
                    <th rowspan="2" class="keu-action-cell">Aksi</th>
                </tr>
                <tr>
                    <th>JD Pemeriksaan</th>
                    <th>JD dgn Prosedur atau Tindakan</th>
                    <th>Dokter Anestesi</th>
                    <th>Dokter Anak</th>
                    <th>JD HD</th>
                    <th>JD USG</th>
                    <th>JD Rontgen</th>
                    <th>JD Lab</th>
                    <th>JD PA</th>
                    <th>LAB PK</th>
                    <th>LAB PA</th>
                    <th>Rad USG</th>
                    <th>Rontgen</th>
                    <th>Jumlah</th>
                    <th>Harga</th>
                    <th>Kali</th>
                    <th>Darah</th>
                    <th>Albumin</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody></tbody>
            <?php if ($summary['jumlah_kunjungan'] > 0): ?>
                <tfoot>
                    <tr style="font-weight:bold;background:#f5f8fc">
                        <?php for ($footerColumn = 0; $footerColumn < 37; $footerColumn++): ?>
                            <td></td>
                        <?php endfor; ?>
                        <td class="text-right">Total Halaman</td>
                        <td class="text-right" id="keuRalanFooterTotal">Rp 0</td>
                        <td class="text-right" id="keuRalanFooterMargin">Rp 0</td>
                        <?php for ($footerColumn = 40; $footerColumn < 45; $footerColumn++): ?>
                            <td></td>
                        <?php endfor; ?>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <div class="keu-ralan-pagination-bar" aria-label="Navigasi halaman laporan">
        <div class="keu-ralan-pagination-info" id="keuRalanPaginationInfo"></div>
        <div class="keu-ralan-pagination-actions" id="keuRalanPaginationActions"></div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var reportReady = <?php echo $filterValid && $queryMessage === '' ? 'true' : 'false'; ?>;
            if (!reportReady) { return; }
            if (!window.jQuery || !$.fn.DataTable || !$('#table4').length) { return; }
            var targetPage = <?php echo (int) $reportPage; ?>;
            var pageLength = 10;
            var pageTotals = { total: 0, margin: 0 };
            var formatRupiah = function(value) {
                value = Number(value) || 0;
                var sign = value < 0 ? '-' : '';
                return 'Rp ' + sign + Math.round(Math.abs(value)).toLocaleString('id-ID');
            };
            var placeTableControls = function() {
                var filter = $('#table4_filter');
                var info = $('#table4_info');
                var paginate = $('#table4_paginate');
                if (filter.length) {
                    filter.appendTo('#keuRalanSearch');
                }
                if (info.length) {
                    info.appendTo('#keuRalanPaginationInfo');
                }
                if (paginate.length) {
                    paginate.appendTo('#keuRalanPaginationActions');
                }
            };
            var language = {
                decimal: '',
                sEmptyTable: 'Tidak ada data yang tersedia pada tabel ini',
                sProcessing: 'Sedang memproses...',
                sLengthMenu: 'Tampilkan _MENU_ entri',
                sZeroRecords: 'Tidak ditemukan data yang sesuai',
                sInfo: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ entri',
                sInfoEmpty: 'Menampilkan 0 sampai 0 dari 0 entri',
                sInfoFiltered: '(disaring dari _MAX_ entri keseluruhan)',
                sSearch: '',
                searchPlaceholder: 'Cari Data...',
                oPaginate: {
                    sFirst: 'Pertama',
                    sPrevious: 'Sebelumnya',
                    sNext: 'Selanjutnya',
                    sLast: 'Terakhir'
                }
            };

            if ($.fn.DataTable.isDataTable('#table4')) {
                var existing = $('#table4').DataTable();
                if (existing.settings()[0].oFeatures.bServerSide) {
                    placeTableControls();
                    return;
                }
                existing.destroy();
                $('#table4 tbody').empty();
            }

            var table = $('#table4').DataTable({
                processing: true,
                serverSide: true,
                deferRender: true,
                searchDelay: 350,
                lengthChange: false,
                pageLength: pageLength,
                displayStart: Math.max(0, targetPage) * pageLength,
                paging: true,
                pagingType: 'numbers',
                scrollCollapse: true,
                ordering: true,
                order: [[0, 'asc']],
                info: true,
                language: language,
                ajax: {
                    url: 'page/t_non_klinis/keuangan/laporan_keuangan_ralan.php?keu_ralan_mode=datatable',
                    type: 'POST',
                    dataType: 'json',
                    data: function(data) {
                        data.start_date = <?php echo json_encode($startDate); ?>;
                        data.end_date = <?php echo json_encode($endDate); ?>;
                        data.kd_poli = <?php echo json_encode($kdPoli); ?>;
                    },
                    dataSrc: function(json) {
                        pageTotals.total = Number(json.pageTotal) || 0;
                        pageTotals.margin = Number(json.pageMargin) || 0;
                        return json.data || [];
                    }
                },
                footerCallback: function() {
                    $('#keuRalanFooterTotal').html('<strong>' + formatRupiah(pageTotals.total) + '</strong>');
                    $('#keuRalanFooterMargin')
                        .toggleClass('keu-negative', pageTotals.margin < 0)
                        .html('<strong>' + formatRupiah(pageTotals.margin) + '</strong>');
                },
                columnDefs: [
                    { targets: [8, 9, 40, 41, 43, 44], className: 'text-center' },
                    { targets: [11, 12, 13], className: 'text-right col-claim' },
                    { targets: [14, 15, 16, 17, 18, 19, 20, 21, 22], className: 'text-right keu-jd-cell' },
                    { targets: [23, 24, 25, 26], className: 'text-right keu-bhp-cell' },
                    { targets: [27, 28, 29, 30, 31], className: 'text-right col-claim' },
                    { targets: [32, 33, 34], className: 'text-right keu-makan-cell' },
                    { targets: [35, 36, 37, 38, 39], className: 'text-right col-claim' },
                    { targets: [40, 41], className: 'text-center keu-keterangan-cell' },
                    { targets: 42, className: 'keu-keterangan-cell' },
                    { targets: 43, className: 'text-center col-claim-source' },
                    { targets: 44, className: 'text-center keu-action-cell' },
                    { targets: [11, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44], orderable: false }
                ]
            });
            placeTableControls();

            var bulkButton = $('#keuRalanBulkButton');
            var bulkDate = $('#keuRalanBulkDate');
            var bulkStatus = $('#keuRalanBulkStatus');
            var setBulkStatus = function(message, state) {
                bulkStatus
                    .removeClass('is-success is-error')
                    .addClass(state === 'success' ? 'is-success' : (state === 'error' ? 'is-error' : ''))
                    .text(message);
            };
            var finishBulk = function() {
                bulkButton.prop('disabled', false).text('Hitung Data Harian');
                bulkDate.prop('disabled', false);
            };
            bulkButton.on('click', function() {
                var visitDate = bulkDate.val();
                if (!visitDate) {
                    setBulkStatus('Pilih tanggal kunjungan terlebih dahulu.', 'error');
                    return;
                }

                var totals = { processed: 0, skipped: 0, failed: 0 };
                bulkButton.prop('disabled', true).text('Memproses...');
                bulkDate.prop('disabled', true);
                setBulkStatus('Menyiapkan kalkulasi...', '');

                var runBatch = function(offset) {
                    $.ajax({
                        url: 'page/t_non_klinis/keuangan/laporan_keuangan_ralan.php?keu_ralan_mode=bulk_daily',
                        type: 'POST',
                        dataType: 'json',
                        timeout: 120000,
                        data: {
                            bulk_date: visitDate,
                            offset: offset,
                            batch_size: 10
                        }
                    }).done(function(response) {
                        if (!response || response.success !== true) {
                            setBulkStatus(
                                response && response.message ? response.message : 'Kalkulasi massal gagal.',
                                'error'
                            );
                            finishBulk();
                            return;
                        }

                        totals.processed += Number(response.processed) || 0;
                        totals.skipped += Number(response.skipped) || 0;
                        totals.failed += Number(response.failed) || 0;
                        setBulkStatus(
                            'Memproses ' + Math.min(Number(response.next_offset) || 0, Number(response.total) || 0)
                                + ' dari ' + (Number(response.total) || 0) + ' kunjungan...',
                            ''
                        );

                        if (response.done) {
                            var message = 'Selesai: ' + totals.processed + ' dihitung, '
                                + totals.skipped + ' dilewati';
                            if (totals.failed > 0) {
                                message += ', ' + totals.failed + ' gagal';
                            }
                            message += '.';
                            setBulkStatus(message, totals.failed > 0 ? 'error' : 'success');
                            finishBulk();
                            table.ajax.reload(null, false);
                            return;
                        }
                        window.setTimeout(function() {
                            runBatch(Number(response.next_offset) || 0);
                        }, 25);
                    }).fail(function(xhr) {
                        var response = xhr.responseJSON || {};
                        setBulkStatus(
                            response.message || 'Koneksi kalkulasi massal terputus. Silakan coba kembali.',
                            'error'
                        );
                        finishBulk();
                    });
                };

                runBatch(0);
            });

            var syncReportPage = function() {
                placeTableControls();
                $('.keu-report-page').val(table.page());
            };
            $('#table4').on('page.dt draw.dt', syncReportPage);
            $('#table4').on('submit', '.keu-action-cell form', syncReportPage);
        });
    </script>
</section>
<?php $table = ob_get_clean();

aptd_render_shell([
    'title' => 'Laporan Keuangan Rawat Jalan',
    'subtitle' => 'Rekap kunjungan dan tagihan pasien rawat jalan dengan jenis bayar BPJS.',
    'filters' => $filters,
    'cards' => $cards,
    'table' => $table,
]);
?>
