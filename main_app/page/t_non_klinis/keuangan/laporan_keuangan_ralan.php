<?php
require_once __DIR__ . '/laporan_keuangan_ralan_helper.php';

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

$rows = [];
$queryMessage = '';
if ($filterValid) {
    try {
        $rows = aptd_keu_ralan_fetch_rows($mysqli, $startDate, $endDate, $kdPoli);
    } catch (RuntimeException $exception) {
        $queryMessage = 'Data laporan belum dapat dimuat. Silakan coba kembali atau hubungi administrator.';
        error_log($exception->getMessage());
    }
}
$summary = aptd_keu_ralan_summary($rows);

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
    <div class="analytics-card"><div class="analytics-k">Poliklinik</div><div class="analytics-v"><?php echo aptd_number($summary['jumlah_poli']); ?></div><div class="analytics-s">Poli dengan kunjungan BPJS</div></div>
    <div class="analytics-card"><div class="analytics-k">Sudah SEP</div><div class="analytics-v"><?php echo aptd_number($summary['sudah_sep']); ?></div><div class="analytics-s">Kunjungan dengan nomor SEP</div></div>
    <div class="analytics-card"><div class="analytics-k">Total Tagihan</div><div class="analytics-v">Rp <?php echo aptd_currency($summary['total_tagihan']); ?></div><div class="analytics-s">Rata-rata Rp <?php echo aptd_currency($summary['rata_tagihan']); ?> / kunjungan</div></div>
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
        .keu-ralan-table-panel{width:100%;min-width:0;max-width:100%;overflow:hidden}
        .keu-ralan-scroll{display:block;width:100%;max-width:100%;overflow-x:auto;position:relative;isolation:isolate;padding-bottom:10px;background:#fff}
        .keu-ralan-scroll::-webkit-scrollbar{height:14px}
        .keu-ralan-scroll::-webkit-scrollbar-thumb{background:#8b97aa;border-radius:999px;border:2px solid #eef2f7}
        .keu-ralan-scroll .dataTables_wrapper{width:max-content;min-width:100%;max-width:none}
        .keu-ralan-table{width:2397px!important;min-width:2397px;table-layout:fixed;border-collapse:separate!important;border-spacing:0}
        .keu-ralan-table th,.keu-ralan-table td{box-sizing:border-box;vertical-align:middle!important;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .keu-ralan-table thead th{position:sticky;top:0;z-index:7;background:#343a40!important;color:#fff!important}
        .keu-ralan-table .col-claim{min-width:116px;text-align:right}
        .keu-ralan-table .col-claim-source{min-width:96px;text-align:center;font-weight:700}
        .keu-ralan-table thead th:nth-child(1),.keu-ralan-table tbody td:nth-child(1){width:130px;min-width:130px;max-width:130px;position:sticky;left:0;z-index:5;background:#fff!important}
        .keu-ralan-table thead th:nth-child(2),.keu-ralan-table tbody td:nth-child(2){width:145px;min-width:145px;max-width:145px;position:sticky;left:130px;z-index:5;background:#fff!important}
        .keu-ralan-table thead th:nth-child(3),.keu-ralan-table tbody td:nth-child(3){width:80px;min-width:80px;max-width:80px;position:sticky;left:275px;z-index:5;background:#fff!important}
        .keu-ralan-table thead th:nth-child(4),.keu-ralan-table tbody td:nth-child(4){width:190px;min-width:190px;max-width:190px;position:sticky;left:355px;z-index:5;background:#fff!important}
        .keu-ralan-table thead th:nth-child(5),.keu-ralan-table tbody td:nth-child(5){width:260px;min-width:260px;max-width:260px;position:sticky;left:545px;z-index:5;background:#fff!important}
        .keu-ralan-table thead th:nth-child(6),.keu-ralan-table tbody td:nth-child(6){width:185px;min-width:185px;max-width:185px;position:sticky;left:805px;z-index:5;background:#fff!important;border-right:3px solid #2f6fb2!important;box-shadow:10px 0 14px -12px rgba(15,23,42,.95)}
        .keu-ralan-table thead th:nth-child(-n+6){z-index:10;background:#343a40!important;color:#fff!important}
        .keu-ralan-table thead th:nth-child(-n+6),.keu-ralan-table tbody td:nth-child(-n+6){background-clip:padding-box}
        .keu-ralan-table tbody tr:hover td:nth-child(-n+6){background:#eceff3!important}
        .keu-action-cell{width:132px;min-width:132px;max-width:132px;text-align:center;padding-left:6px!important;padding-right:6px!important}
        .keu-calc-btn{display:inline-flex;align-items:center;justify-content:center;min-width:64px;height:24px;border:0;border-radius:4px;background:#15803d;color:#fff;font-size:11px;font-weight:800;padding:2px 9px;cursor:pointer;line-height:1;white-space:nowrap}
        .keu-calc-btn:hover{background:#166534;color:#fff}
        .keu-calc-btn:disabled{background:#9ca3af;cursor:not-allowed}
        @media(max-width:991px){.keu-ralan-cards{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:576px){.keu-ralan-cards{grid-template-columns:minmax(0,1fr)}}
    </style>
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
                <col style="width:130px">
                <col style="width:135px">
                <col style="width:100px">
                <col style="width:132px">
            </colgroup>
            <thead class="thead-dark">
                <tr>
                    <th>Tanggal Kunjungan</th>
                    <th>Nomor Rawat</th>
                    <th>No. RM</th>
                    <th>Nama Pasien</th>
                    <th>Dokter Poliklinik</th>
                    <th>No. SEP</th>
                    <th>Poliklinik</th>
                    <th>Spesialistik</th>
                    <th>Status Periksa</th>
                    <th>Status Bayar</th>
                    <th>Jenis Bayar</th>
                    <th>Total Tagihan</th>
                    <th>Klaim Riwayat</th>
                    <th>Klaim Aktual</th>
                    <th>Klaim Digunakan</th>
                    <th>Sumber</th>
                    <th class="keu-action-cell">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td data-order="<?php echo htmlspecialchars($row['tgl_registrasi'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(date('d-m-Y', strtotime($row['tgl_registrasi'])), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['no_rawat'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['no_rkm_medis'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td title="<?php echo htmlspecialchars($row['nm_pasien'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['nm_pasien'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td title="<?php echo htmlspecialchars($row['nm_dokter'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['nm_dokter'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td title="<?php echo htmlspecialchars($row['no_sep'] !== '' ? $row['no_sep'] : '-', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['no_sep'] !== '' ? $row['no_sep'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['nm_poli'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td title="<?php echo htmlspecialchars($row['nm_sps'] !== '' ? $row['nm_sps'] : '-', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['nm_sps'] !== '' ? $row['nm_sps'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($row['status_periksa'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($row['status_bayar'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['jenis_bayar'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-right" data-order="<?php echo (float) $row['total_tagihan']; ?>">Rp <?php echo aptd_currency($row['total_tagihan']); ?></td>
                        <td class="col-claim" data-order="<?php echo (float) $row['claim_history']; ?>" title="<?php echo htmlspecialchars($row['claim_history_no_rawat'] !== '' ? 'Riwayat: ' . $row['claim_history_no_rawat'] . ' · ' . $row['target_diagnosis_source'] . ': ' . $row['target_diagnosis_code'] : $row['target_diagnosis_source'] . ': ' . ($row['target_diagnosis_code'] !== '' ? $row['target_diagnosis_code'] : '-'), ENT_QUOTES, 'UTF-8'); ?>">Rp <?php echo aptd_currency($row['claim_history']); ?></td>
                        <td class="col-claim" data-order="<?php echo (float) $row['claim_actual']; ?>">Rp <?php echo aptd_currency($row['claim_actual']); ?></td>
                        <td class="col-claim" data-order="<?php echo (float) $row['claim_used']; ?>"><strong>Rp <?php echo aptd_currency($row['claim_used']); ?></strong></td>
                        <td class="col-claim-source"><?php echo htmlspecialchars($row['claim_source'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="keu-action-cell">
                            <?php $actionLabel = aptd_keu_ralan_action_label($row); ?>
                            <?php if ($actionLabel === 'Pakai Riwayat'): ?>
                                <form method="post" style="margin:0">
                                    <input type="hidden" name="use_history_claim" value="1">
                                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="kd_poli" value="<?php echo htmlspecialchars($kdPoli, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="report_page" class="keu-report-page" value="<?php echo (int) $reportPage; ?>">
                                    <input type="hidden" name="history_no_rawat" value="<?php echo htmlspecialchars($row['no_rawat'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" class="keu-calc-btn" title="Pakai estimasi klaim dari riwayat diagnosa" <?php echo $canCalculateKeuangan ? '' : 'disabled aria-disabled="true"'; ?>>Pakai Riwayat</button>
                                </form>
                            <?php else: ?>
                                <form method="post" style="margin:0">
                                    <input type="hidden" name="calculate_keu_row" value="1">
                                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="kd_poli" value="<?php echo htmlspecialchars($kdPoli, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="report_page" class="keu-report-page" value="<?php echo (int) $reportPage; ?>">
                                    <input type="hidden" name="calculate_no_rawat" value="<?php echo htmlspecialchars($row['no_rawat'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php $canHitungRow = $canCalculateKeuangan && (float) $row['claim_used'] > 0; ?>
                                    <button type="submit" class="keu-calc-btn" title="<?php echo $canHitungRow ? ($actionLabel === 'Hitung Ulang' ? 'Hitung ulang data keuangan' : 'Hitung data keuangan') : 'Klaim digunakan belum tersedia'; ?>" <?php echo $canHitungRow ? '' : 'disabled aria-disabled="true"'; ?>><?php echo htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8'); ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if (!empty($rows)): ?>
                <tfoot><tr style="font-weight:bold;background:#f5f8fc"><td colspan="11" class="text-right">Total Tagihan</td><td class="text-right">Rp <?php echo aptd_currency($summary['total_tagihan']); ?></td><td colspan="5"></td></tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (!window.jQuery) { return; }
            var targetPage = <?php echo (int) $reportPage; ?>;
            setTimeout(function() {
                if (!$.fn.DataTable || !$.fn.DataTable.isDataTable('#table4')) { return; }
                var table = $('#table4').DataTable();
                var info = table.page.info();
                if (info.pages > 0 && targetPage > 0) {
                    table.page(Math.min(targetPage, info.pages - 1)).draw(false);
                }
                $('.keu-action-cell form').on('submit', function() {
                    $('.keu-report-page').val(table.page());
                });
            }, 100);
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
