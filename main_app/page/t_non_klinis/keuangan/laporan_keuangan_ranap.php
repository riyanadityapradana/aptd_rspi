<?php
require_once __DIR__ . '/laporan_keuangan_ranap_helper.php';

list($month, $year, $startDate, $endDate) = aptd_keu_ranap_date_filter();
$monthLabels = aptd_month_labels_local();
$saveMessage = null;
$levelLogin = isset($_SESSION['level']) ? $_SESSION['level'] : '';
$canEditClaim = in_array($levelLogin, ['admin', 'rekammedis'], true);
$canCalculateKeuangan = in_array($levelLogin, ['admin', 'keuangan'], true);
$isReportRowAction = (isset($_POST['calculate_keu_row']) && $_POST['calculate_keu_row'] === '1')
    || (isset($_POST['save_keu_manual']) && $_POST['save_keu_manual'] === '1');
if ($isReportRowAction) {
    $reportPage = isset($_POST['report_page']) ? max(0, (int) $_POST['report_page']) : 0;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportPage = 0;
} else {
    $reportPage = isset($_GET['report_page']) ? max(0, (int) $_GET['report_page']) : 0;
}

if (isset($_POST['save_keu_manual']) && $_POST['save_keu_manual'] === '1') {
    $saveMessage = aptd_keu_ranap_save_manual(
        $mysqli,
        isset($_POST['manual_no_rawat']) ? $_POST['manual_no_rawat'] : '',
        isset($_POST['manual_claim']) ? $_POST['manual_claim'] : 0,
        isset($_POST['manual_jd_operator']) ? $_POST['manual_jd_operator'] : 0,
        $canEditClaim
    );
    $_SESSION['keu_ranap_flash'] = $saveMessage;
    $redirectUrl = 'main_app.php?page=laporan_keuangan_ranap&month=' . rawurlencode($month) . '&year=' . rawurlencode($year) . '&report_page=' . rawurlencode($reportPage);
    echo '<script>window.location.href=' . json_encode($redirectUrl) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    return;
}
if (isset($_POST['calculate_keu_row']) && $_POST['calculate_keu_row'] === '1') {
    if ($canCalculateKeuangan) {
        $saveMessage = aptd_keu_ranap_calculate_and_store(
            $mysqli,
            isset($_POST['calculate_no_rawat']) ? $_POST['calculate_no_rawat'] : '',
            $startDate,
            $endDate
        );
    } else {
        $saveMessage = ['success' => false, 'message' => 'Level Anda tidak memiliki akses untuk menghitung data keuangan.'];
    }
    $_SESSION['keu_ranap_flash'] = $saveMessage;
    $redirectUrl = 'main_app.php?page=laporan_keuangan_ranap&month=' . rawurlencode($month) . '&year=' . rawurlencode($year) . '&report_page=' . rawurlencode($reportPage);
    echo '<script>window.location.href=' . json_encode($redirectUrl) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    return;
}
if (isset($_SESSION['keu_ranap_flash'])) {
    $saveMessage = $_SESSION['keu_ranap_flash'];
    unset($_SESSION['keu_ranap_flash']);
}
$rows = aptd_keu_ranap_fetch_report_rows($mysqli, $startDate, $endDate);
$summary = aptd_keu_ranap_summary($rows);

ob_start(); ?>
<form method="post" class="analytics-filter">
    <div class="form-group mb-0">
        <label for="month"><strong>Bulan</strong></label>
        <select name="month" id="month" class="form-control form-control-sm">
            <?php foreach ($monthLabels as $n => $label): ?>
                <option value="<?php echo $n; ?>" <?php echo $month === $n ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group mb-0">
        <label for="year"><strong>Tahun</strong></label>
        <select name="year" id="year" class="form-control form-control-sm">
            <?php for ($y = 2020; $y <= ((int) date('Y') + 1); $y++): ?>
                <option value="<?php echo $y; ?>" <?php echo $year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button>
    <a class="btn btn-success btn-sm px-4" href="<?php echo htmlspecialchars(aptd_keu_ranap_export_url($month, $year), ENT_QUOTES, 'UTF-8'); ?>">Export Excel</a>
</form>
<?php $filters = ob_get_clean();

ob_start(); ?>
<section class="analytics-cards">
    <div class="analytics-card"><div class="analytics-k">Pasien BPJS Ranap</div><div class="analytics-v"><?php echo aptd_number($summary['jumlah_pasien']); ?></div><div class="analytics-s"><?php echo htmlspecialchars($monthLabels[$month] . ' ' . $year, ENT_QUOTES, 'UTF-8'); ?></div></div>
    <div class="analytics-card"><div class="analytics-k">Total Claim</div><div class="analytics-v"><?php echo aptd_currency($summary['total_claim']); ?></div><div class="analytics-s">Manual / fallback INA-CBG</div></div>
    <div class="analytics-card"><div class="analytics-k">Total Jasa Dokter</div><div class="analytics-v"><?php echo aptd_currency($summary['total_jasa_dokter']); ?></div><div class="analytics-s">Akumulasi kolom jasa dokter</div></div>
    <div class="analytics-card"><div class="analytics-k">Total Obat</div><div class="analytics-v"><?php echo aptd_currency($summary['total_obat']); ?></div><div class="analytics-s">Akumulasi biaya obat pasien</div></div>
</section>
<?php $cards = ob_get_clean();

ob_start(); ?>
<section class="analytics-panel keu-ranap-panel">
    <?php if ($saveMessage): ?>
        <div class="alert <?php echo $saveMessage['success'] ? 'alert-success' : 'alert-danger'; ?> mb-3" id="info">
            <?php echo htmlspecialchars($saveMessage['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>
    <div class="analytics-head">
        <div>
            <h2 class="analytics-h">Catatan Query</h2>
            <p class="analytics-d">Kolom biaya dipisah di helper agar aturan perhitungan bisa ditambah bertahap seperti file Excel.</p>
        </div>
        <span class="analytics-pill"><?php echo htmlspecialchars($startDate . ' s.d. ' . $endDate, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <div class="analytics-note">Filter awal mengikuti arahan gambar: pasien rawat inap BPJS, ada di <code>kamar_inap</code>, dan status pulang bukan <code>Pindah Kamar</code>. CLAIM manual diprioritaskan; jika belum tersedia, sistem memakai tarif INA-CBG. Perhitungan detail dijalankan per nomor rawat melalui tombol <code>Hitung</code>.</div>
</section>
<?php $panels = ob_get_clean();

ob_start(); ?>
<section class="analytics-panel keu-ranap-table-panel">
    <div class="analytics-head">
        <div>
            <h2 class="analytics-h">Laporan Keuangan Rawat Inap</h2>
            <p class="analytics-d">Format detail mengikuti kebutuhan rekap Excel per pasien dan per nomor rawat.</p>
        </div>
    </div>
    <style>
        .keu-ranap-table-panel{overflow:hidden;max-width:100%}
        .keu-ranap-scroll{display:block;width:100%;max-width:100%;overflow-x:scroll;overflow-y:hidden;padding-bottom:12px;background:#fff}
        .keu-ranap-scroll::-webkit-scrollbar{height:14px}
        .keu-ranap-scroll::-webkit-scrollbar-thumb{background:#8b97aa;border-radius:999px;border:2px solid #eef2f7}
        .keu-ranap-scroll .dataTables_wrapper{width:max-content;min-width:100%;max-width:none}
        .keu-ranap-scroll .dataTables_wrapper>.row{width:100%;min-width:7675px;max-width:none;margin-left:0;margin-right:0;position:relative;z-index:12;background:#fff;padding:6px 0;display:flex;align-items:center;flex-wrap:nowrap}
        .keu-ranap-scroll .dataTables_wrapper>.row>[class^="col-"],.keu-ranap-scroll .dataTables_wrapper>.row>[class*=" col-"]{padding-left:0;padding-right:0;max-width:none}
        .keu-ranap-scroll .dataTables_wrapper>.row>[class^="col-"]:first-child,.keu-ranap-scroll .dataTables_wrapper>.row>[class*=" col-"]:first-child{flex:1 0 auto}
        .keu-ranap-scroll .dataTables_wrapper>.row>[class^="col-"]:last-child,.keu-ranap-scroll .dataTables_wrapper>.row>[class*=" col-"]:last-child{position:sticky;right:0;z-index:16;flex:0 0 auto;width:auto!important;max-width:calc(100vw - 96px);background:#fff;padding-left:12px!important;box-shadow:-14px 0 18px -18px rgba(15,23,42,.85)}
        .keu-ranap-scroll .dataTables_wrapper>.row:first-child{top:0;border-bottom:1px solid #e5e7eb}
        .keu-ranap-scroll .dataTables_wrapper>.row:last-child{bottom:0;border-top:1px solid #e5e7eb}
        .keu-ranap-scroll .dataTables_filter{padding-bottom:0;font-size:13px;text-align:right!important}
        .keu-ranap-scroll .dataTables_filter input{height:34px;font-size:13px}
        .keu-ranap-scroll .dataTables_info{padding-top:8px;font-size:13px;white-space:normal}
        .keu-ranap-scroll .dataTables_paginate{padding-top:4px;font-size:13px;text-align:right!important}
        .keu-ranap-scroll .dataTables_paginate .pagination{justify-content:flex-end!important;flex-wrap:wrap}
        .keu-ranap-table{min-width:7675px;width:7675px!important;margin:0!important;white-space:nowrap;border-collapse:separate!important;border-spacing:0;table-layout:fixed;background:#fff;color:#000}
        .keu-ranap-table th,.keu-ranap-table td{vertical-align:middle!important;border:1px solid #111!important;padding:6px 8px!important;line-height:1.25}
        .keu-ranap-table thead th{background:#d9e2f3!important;color:#000!important;text-align:center;font-size:13px;font-weight:800;white-space:normal;line-height:1.15}
        .keu-ranap-table thead tr:first-child th{background:#cfd8e8!important}
        .keu-ranap-table tbody td{font-size:13px;background:#fff}
        .keu-ranap-table .col-rawat{width:155px;min-width:155px;max-width:155px}
        .keu-ranap-table .col-rm{width:88px;min-width:88px;max-width:88px}
        .keu-ranap-table .col-name{width:260px;min-width:260px;max-width:260px;overflow:hidden;text-overflow:ellipsis}
        .keu-ranap-table .col-diagnosa{width:230px;min-width:230px;max-width:230px;overflow:hidden;text-overflow:ellipsis}
        .keu-ranap-table .col-date{width:104px;min-width:104px;text-align:center}
        .keu-ranap-table .col-status{width:128px;min-width:128px}
        .keu-ranap-table .col-dpjp{width:230px;min-width:230px;max-width:230px;overflow:hidden;text-overflow:ellipsis}
        .keu-ranap-table .col-ket-dpjp{width:230px;min-width:230px;max-width:230px;overflow:hidden;text-overflow:ellipsis}
        .keu-ranap-table .col-ket-anestesi{width:250px;min-width:250px;max-width:250px;overflow:hidden;text-overflow:ellipsis}
        .keu-ranap-table .col-ket-anak{width:250px;min-width:250px;max-width:250px;overflow:hidden;text-overflow:ellipsis}
        .keu-ranap-table .col-ket-visite{width:280px;min-width:280px;max-width:280px;overflow:hidden;text-overflow:ellipsis}
        .keu-ranap-table .col-ket-telp{width:280px;min-width:280px;max-width:280px;overflow:hidden;text-overflow:ellipsis}
        .keu-ranap-table .col-kamar{width:132px;min-width:132px;max-width:132px}
        .keu-ranap-table .col-lama-dirawat{width:110px;min-width:110px;max-width:110px;text-align:center}
        .keu-ranap-table .col-sep{width:190px;min-width:190px;max-width:190px;overflow:hidden;text-overflow:ellipsis;text-align:left}
        .keu-ranap-table .col-obat-dasar{width:140px;min-width:140px;max-width:140px}
        .keu-ranap-table .col-obat-margin{width:112px;min-width:112px;max-width:112px}
        .keu-ranap-table .num{width:104px;min-width:104px;text-align:right}
        .keu-ranap-table .col-claim{width:88px;min-width:88px;max-width:88px;text-align:right}
        .keu-ranap-table .flag{width:72px;min-width:72px;text-align:center}
        .keu-ranap-table .col-ket-tindakan{width:260px;min-width:260px;max-width:260px;overflow:hidden;text-overflow:ellipsis;text-align:left}
        .keu-ranap-table .margin-negative{color:#b91c1c;font-weight:800}
        .keu-ranap-table .margin-positive{color:#166534;font-weight:800}
        .keu-action-cell{width:124px;min-width:124px;max-width:124px;text-align:center;padding-left:6px!important;padding-right:6px!important}
        .keu-calc-btn{display:inline-flex;align-items:center;justify-content:center;min-width:64px;height:24px;border:0;border-radius:4px;background:#15803d;color:#fff;font-size:11px;font-weight:800;padding:2px 9px;cursor:pointer;line-height:1}
        .keu-calc-btn:hover{background:#166534;color:#fff}
        .keu-calc-btn:disabled{background:#9ca3af;cursor:not-allowed}
        .keu-not-counted{font-weight:700;color:#64748b;text-align:left!important;background:#f8fafc!important}
        .keu-ranap-table thead tr:first-child th:nth-child(1),.keu-ranap-table tbody td:nth-child(1){position:sticky;left:0;z-index:5;background:#fff!important}
        .keu-ranap-table thead tr:first-child th:nth-child(2),.keu-ranap-table tbody td:nth-child(2){position:sticky;left:155px;z-index:5;background:#fff!important}
        .keu-ranap-table thead tr:first-child th:nth-child(3),.keu-ranap-table tbody td:nth-child(3){position:sticky;left:243px;z-index:5;background:#fff!important}
        .keu-ranap-table thead tr:first-child th:nth-child(4),.keu-ranap-table tbody td:nth-child(4){position:sticky;left:503px;z-index:5;background:#fff!important;box-shadow:8px 0 12px -10px rgba(15,23,42,.8)}
        .keu-ranap-table thead tr:first-child th:nth-child(1),.keu-ranap-table thead tr:first-child th:nth-child(2),.keu-ranap-table thead tr:first-child th:nth-child(3),.keu-ranap-table thead tr:first-child th:nth-child(4){z-index:8;background:#cfd8e8!important}
        .keu-rawat-btn{display:inline-flex;align-items:center;gap:4px;border:0;border-radius:4px;background:#256ec7;color:#fff;font-size:12px;font-weight:800;padding:4px 8px;cursor:pointer}
        .keu-rawat-btn:hover{background:#174f94;color:#fff}
        @media (max-width: 991.98px){
            .keu-ranap-scroll{margin-left:-8px;margin-right:-8px;width:calc(100% + 16px);max-width:calc(100% + 16px)}
            .keu-ranap-scroll .dataTables_wrapper>.row{min-width:7675px;padding-left:8px;padding-right:8px}
            .keu-ranap-scroll .dataTables_wrapper>.row>[class^="col-"]:last-child,.keu-ranap-scroll .dataTables_wrapper>.row>[class*=" col-"]:last-child{max-width:calc(100vw - 24px);padding-right:8px!important}
            .keu-ranap-table{min-width:7675px;width:7675px!important}
            .keu-ranap-table th,.keu-ranap-table td{padding:5px 7px!important}
            .keu-ranap-table thead th,.keu-ranap-table tbody td{font-size:12px}
            .keu-rawat-btn{font-size:11px;padding:3px 7px}
        }
    </style>
    <div class="keu-ranap-scroll">
        <table class="table table-sm table-bordered table-hover analytics-table keu-ranap-table" id="table4" style="width:100%;font-size:13px;">
            <colgroup>
                <col style="width:155px">
                <col style="width:88px">
                <col style="width:260px">
                <col style="width:190px">
                <col style="width:230px">
                <col style="width:230px">
                <col style="width:104px">
                <col style="width:104px">
                <col style="width:190px">
                <col style="width:230px">
                <col style="width:132px">
                <col style="width:110px">
                <col style="width:88px">
                <col style="width:124px">
                <col style="width:104px">
                <col style="width:104px">
                <col style="width:230px">
                <col style="width:104px">
                <col style="width:104px">
                <col style="width:250px">
                <col style="width:104px">
                <col style="width:250px">
                <col style="width:104px">
                <col style="width:120px">
                <col style="width:104px">
                <col style="width:140px">
                <col style="width:280px">
                <col style="width:104px">
                <col style="width:104px">
                <col style="width:280px">
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <col style="width:104px">
                <?php endfor; ?>
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <col style="width:104px">
                <?php endfor; ?>
                <col style="width:140px">
                <col style="width:112px">
                <?php for ($i = 0; $i < 7; $i++): ?>
                    <col style="width:104px">
                <?php endfor; ?>
                <col style="width:104px">
                <col style="width:104px">
                <col style="width:72px">
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <col style="width:104px">
                <?php endfor; ?>
                <col style="width:72px">
                <col style="width:72px">
                <col style="width:260px">
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2" class="col-rawat">No Rawat</th>
                    <th rowspan="2" class="col-rm">No RM</th>
                    <th rowspan="2" class="col-name">Nama Pasien</th>
                    <th rowspan="2" class="col-sep">SEP</th>
                    <th rowspan="2" class="col-diagnosa">Diagnosa Awal</th>
                    <th rowspan="2" class="col-diagnosa">Diagnosa Akhir</th>
                    <th rowspan="2" class="col-date">Tanggal Masuk</th>
                    <th rowspan="2" class="col-date">Tanggal Keluar</th>
                    <th rowspan="2" class="col-status">Status Pulang</th>
                    <th rowspan="2" class="col-dpjp">DPJP</th>
                    <th rowspan="2" class="col-kamar">Kamar</th>
                    <th rowspan="2" class="col-lama-dirawat">Lama Dirawat</th>
                    <th rowspan="2" class="col-claim">CLAIM</th>
                    <th rowspan="2" class="keu-action-cell">Hitung</th>
                    <th colspan="21">Jasa Dokter</th>
                    <th rowspan="2" class="num">JK</th>
                    <th rowspan="2" class="num">BHP</th>
                    <th rowspan="2" class="num">OBAT</th>
                    <th rowspan="2" class="num col-obat-dasar">Total Harga Dasar</th>
                    <th rowspan="2" class="num col-obat-margin">15% Dasar</th>
                    <th colspan="7">Penunjang</th>
                    <th colspan="3">MAKAN</th>
                    <th rowspan="2" class="num">Phototherapy</th>
                    <th rowspan="2" class="num">Oksigen</th>
                    <th rowspan="2" class="num">Spirometri</th>
                    <th rowspan="2" class="num">TOTAL</th>
                    <th rowspan="2" class="num">MARGIN</th>
                    <th colspan="3">Keterangan</th>
                </tr>
                <tr>
                    <th class="num">Dokter UGD</th>
                    <th class="num">JD DPJP</th>
                    <th class="col-ket-dpjp">Ket. JD DPJP</th>
                    <th class="num">JD Operator</th>
                    <th class="num">JD Anestesi</th>
                    <th class="col-ket-anestesi">Ket. JD Anestesi</th>
                    <th class="num">JD Anak</th>
                    <th class="col-ket-anak">Ket. JD Anak</th>
                    <th class="num">JD Visite</th>
                    <th class="num">JD Visite Umum</th>
                    <th class="num">JD Visite Spesialis</th>
                    <th class="num">JD Visite Pengganti</th>
                    <th class="col-ket-visite">Ket. JD Visite</th>
                    <th class="num">JD Telp</th>
                    <th class="num">JD Telpon Pengganti</th>
                    <th class="col-ket-telp">Ket. JD Telp</th>
                    <th class="num">JD USG</th>
                    <th class="num">JD Rontgen</th>
                    <th class="num">JD Lab</th>
                    <th class="num">JD PA</th>
                    <th class="num">HD</th>
                    <th class="num">LAB PK</th>
                    <th class="num">LAB PA</th>
                    <th class="num">Rad USG</th>
                    <th class="num">Rontgen</th>
                    <th class="num">Fisio</th>
                    <th class="num">EKG</th>
                    <th class="num">Darah</th>
                    <th class="num">Jumlah</th>
                    <th class="num">Harga</th>
                    <th class="flag">Kali</th>
                    <th class="flag">DARAH</th>
                    <th class="flag">ALBU</th>
                    <th class="col-ket-tindakan">TINDAKAN</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="58" class="analytics-empty">Tidak ada data pada periode ini.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <?php
                    $hasHitung = isset($row['has_hitung']) && (int) $row['has_hitung'] === 1;
                    $canHitungRow = $canCalculateKeuangan && (float) $row['claim'] > 0;
                    ?>
                    <tr>
                        <td class="col-rawat">
                            <button type="button"
                                    class="keu-rawat-btn"
                                    data-toggle="modal"
                                    data-target="#modalKeuManual"
                                    data-no-rawat="<?php echo htmlspecialchars($row['no_rawat'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-claim="<?php echo htmlspecialchars((string) $row['claim'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-jd-operator="<?php echo htmlspecialchars((string) $row['jd_operator'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($row['no_rawat'], ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                        </td>
                        <td class="col-rm"><?php echo htmlspecialchars($row['no_rkm_medis'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-name"><?php echo htmlspecialchars($row['nama_pasien_umur'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-sep" title="<?php echo htmlspecialchars($row['no_sep'] ?: '-', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['no_sep'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-diagnosa"><?php echo htmlspecialchars($row['diagnosa_awal'] ?: $row['diagnosa_sep'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-diagnosa"><?php echo htmlspecialchars($row['diagnosa_akhir'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-date"><?php echo htmlspecialchars($row['tanggal_masuk'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-date"><?php echo htmlspecialchars($row['tanggal_keluar'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['status_pulang'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-dpjp" title="<?php echo htmlspecialchars($row['dpjp'] ?: '-', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['dpjp'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['kamar'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-lama-dirawat"><?php echo $row['lama_dirawat'] === null ? '-' : (int) $row['lama_dirawat']; ?></td>
                        <td class="col-claim"><?php echo aptd_currency($row['claim']); ?></td>
                        <td class="keu-action-cell">
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="calculate_keu_row" value="1">
                                <input type="hidden" name="month" value="<?php echo (int) $month; ?>">
                                <input type="hidden" name="year" value="<?php echo (int) $year; ?>">
                                <input type="hidden" name="report_page" class="keu-report-page" value="<?php echo (int) $reportPage; ?>">
                                <input type="hidden" name="calculate_no_rawat" value="<?php echo htmlspecialchars($row['no_rawat'], ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit"
                                        class="keu-calc-btn"
                                        title="<?php echo !$canHitungRow ? 'CLAIM belum tersedia' : ($hasHitung ? 'Hitung ulang data keuangan' : 'Hitung data keuangan'); ?>"
                                        <?php echo $canHitungRow ? '' : 'disabled aria-disabled="true"'; ?>>
                                    <?php echo $hasHitung ? 'Hitung Ulang' : 'Hitung'; ?>
                                </button>
                            </form>
                        </td>
                        <?php if (!$hasHitung): ?>
                            <td class="keu-not-counted">Belum dihitung</td>
                            <?php for ($emptyCol = 1; $emptyCol < 44; $emptyCol++): ?>
                                <td></td>
                            <?php endfor; ?>
                        <?php else: ?>
                        <td class="num"><?php echo aptd_currency($row['dokter_ugd']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_dpjp']); ?></td>
                        <td class="col-ket-dpjp" title="<?php echo htmlspecialchars($row['ket_dpjp'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['ket_dpjp'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_operator']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_anestesi']); ?></td>
                        <td class="col-ket-anestesi" title="<?php echo htmlspecialchars($row['ket_anestesi'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['ket_anestesi'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_anak']); ?></td>
                        <td class="col-ket-anak" title="<?php echo htmlspecialchars($row['ket_anak'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['ket_anak'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_visit']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_visit_umum']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_visit_spesialis']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_visit_pengganti']); ?></td>
                        <td class="col-ket-visite" title="<?php echo htmlspecialchars($row['ket_visit'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['ket_visit'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_telpon']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_telpon_pengganti']); ?></td>
                        <td class="col-ket-telp" title="<?php echo htmlspecialchars($row['ket_telpon'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['ket_telpon'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_usg']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_rontgen']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_lab']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_pa']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['hd']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jk']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['bhp']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['obat']); ?></td>
                        <td class="num col-obat-dasar"><?php echo aptd_currency($row['total_harga_dasar_obat']); ?></td>
                        <td class="num col-obat-margin"><?php echo aptd_currency($row['markup_obat_bhp']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['lab_pk']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['lab_pa']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['rad_usg']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['rontgen']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['fisio']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['ekg']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['darah']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['makan_jumlah']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['makan_harga']); ?></td>
                        <td class="flag"><?php echo aptd_number($row['makan_kali']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['phototherapy']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['oksigen']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['spirometri']); ?></td>
                        <td class="num" style="font-weight:bold;"><?php echo aptd_currency($row['total_biaya_laporan']); ?></td>
                        <td class="num <?php echo (float) $row['margin'] < 0 ? 'margin-negative' : 'margin-positive'; ?>"><?php echo aptd_currency($row['margin']); ?></td>
                        <td class="flag"><?php echo htmlspecialchars($row['ket_darah'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="flag"><?php echo htmlspecialchars($row['ket_albumin'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-ket-tindakan" title="<?php echo htmlspecialchars($row['ket_tindakan'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['ket_tindakan'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="modal fade" id="modalKeuManual" tabindex="-1" role="dialog" aria-labelledby="modalKeuManualLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" class="modal-content">
                <input type="hidden" name="save_keu_manual" value="1">
                <input type="hidden" name="month" value="<?php echo (int) $month; ?>">
                <input type="hidden" name="year" value="<?php echo (int) $year; ?>">
                <input type="hidden" name="report_page" class="keu-report-page" value="<?php echo (int) $reportPage; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalKeuManualLabel">Input Data Keuangan BPJS</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="manual_no_rawat"><strong>No Rawat</strong></label>
                        <input type="text" class="form-control" name="manual_no_rawat" id="manual_no_rawat" readonly>
                    </div>
                    <div class="form-group">
                        <label for="manual_claim"><strong>Nilai / Jumlah Claim</strong></label>
                        <input type="number" class="form-control" name="manual_claim" id="manual_claim" min="0" step="0.01" <?php echo $canEditClaim ? '' : 'readonly'; ?> required>
                    </div>
                    <div class="form-group mb-0">
                        <label for="manual_jd_operator"><strong>Nilai / Jumlah JD Operator</strong></label>
                        <input type="number" class="form-control" name="manual_jd_operator" id="manual_jd_operator" min="0" step="0.01" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        (function(){
            document.addEventListener('DOMContentLoaded', function() {
                if (!window.jQuery) { return; }
                var targetPage = <?php echo (int) $reportPage; ?>;
                var pageLength = 10;
                var language = {
                    decimal: '',
                    sEmptyTable: 'Tidak ada data yang tersedia pada tabel ini',
                    sProcessing: 'Sedang memproses...',
                    sLengthMenu: 'Tampilkan _MENU_ entri',
                    sZeroRecords: 'Tidak ditemukan data yang sesuai',
                    sInfo: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ entri',
                    sInfoEmpty: 'Menampilkan 0 sampai 0 dari 0 entri',
                    sInfoFiltered: '(disaring dari _MAX_ entri keseluruhan)',
                    sInfoPostFix: '',
                    sSearch: '',
                    searchPlaceholder: 'Cari Data..',
                    sUrl: '',
                    oPaginate: {
                        sFirst: 'Pertama',
                        sPrevious: 'Sebelumnya',
                        sNext: 'Selanjutnya',
                        sLast: 'Terakhir'
                    }
                };

                var initReportTable = function() {
                    if (!$.fn.DataTable || !$('#table4').length) { return false; }
                    if (!$.fn.DataTable.isDataTable('#table4')) {
                        $('#table4').DataTable({
                            lengthChange: false,
                            paging: true,
                            pagingType: 'numbers',
                            scrollCollapse: true,
                            ordering: true,
                            info: true,
                            displayStart: Math.max(0, targetPage) * pageLength,
                            language: language
                        });
                    }
                    return true;
                };

                var restoreReportPage = function() {
                    if (!$.fn.DataTable || !$.fn.DataTable.isDataTable('#table4')) { return false; }
                    var table = $('#table4').DataTable();
                    var pageInfo = table.page.info();
                    if (pageInfo.pages > 0 && targetPage > 0) {
                        table.page(Math.min(targetPage, pageInfo.pages - 1)).draw(false);
                    }
                    return true;
                };

                if (!initReportTable() || !restoreReportPage()) {
                    setTimeout(function() {
                        initReportTable();
                        restoreReportPage();
                    }, 50);
                    setTimeout(restoreReportPage, 150);
                }

                var syncReportPage = function() {
                    if ($.fn.DataTable && $.fn.DataTable.isDataTable('#table4')) {
                        $('.keu-report-page').val($('#table4').DataTable().page());
                    }
                };

                $('#table4').on('page.dt draw.dt', syncReportPage);

                $('#modalKeuManual').on('show.bs.modal', function(event) {
                    syncReportPage();
                    var button = $(event.relatedTarget);
                    $('#manual_no_rawat').val(button.data('no-rawat') || '');
                    $('#manual_claim').val(button.data('claim') || 0);
                    $('#manual_jd_operator').val(button.data('jd-operator') || 0);
                });

                $('.keu-action-cell form, #modalKeuManual form').on('submit', syncReportPage);
            });
        })();
    </script>
</section>
<?php $table = ob_get_clean();

aptd_render_shell([
    'title' => 'Laporan Keuangan Rawat Inap',
    'subtitle' => 'Tarikan detail pasien BPJS rawat inap untuk rekap jasa dokter dan komponen keuangan per nomor rawat.',
    'filters' => $filters,
    'cards' => $cards,
    'panels' => $panels,
    'table' => $table,
]);
?>
