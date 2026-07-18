<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
require_once __DIR__ . '/kunjungan_kecamatan_mingguan_ranap_helper.php';

$conn = $mysqli;
list($selectedMonth, $filterYear, $filterMonth, $startDate, $endDate) = aptd_kec_mingguan_period_from_request();
$monthLabels = aptd_kec_mingguan_month_labels();
$paymentLabels = aptd_kec_mingguan_payment_labels();
$weeks = aptd_kec_mingguan_weeks($filterYear, $filterMonth);
$report = aptd_kec_mingguan_fetch_ranap($conn, $startDate, $endDate, $weeks);
$rows = $report['rows'];
$totals = $report['totals'];
$weekColumnCount = count($weeks) * count($paymentLabels);
$levelLogin = isset($_SESSION['level']) ? $_SESSION['level'] : '';
$isAdmin = strtolower(trim($levelLogin)) === 'admin';
$exportExcelAction = 'page/t_non_klinis/rawat_inap/export_kunjungan_kecamatan_mingguan_ranap_excel.php';
$exportPdfAction = 'page/t_non_klinis/rawat_inap/export_kunjungan_kecamatan_mingguan_ranap_pdf.php';
?>
<br>
<style>
.kecm-wrap{display:grid;gap:14px}.kecm-panel{background:#fff;border:1px solid rgba(80,114,160,.18);box-shadow:0 10px 24px rgba(48,73,107,.08);border-radius:8px;padding:16px}.kecm-title{margin:0 0 4px;font-size:24px;font-weight:800;color:#27496d}.kecm-sub{margin:0;color:#62758d;font-size:13px}.kecm-filter{display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin-top:14px}.kecm-filter .form-control{min-width:170px}.kecm-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px}.kecm-badge{display:inline-flex;padding:6px 10px;border-radius:8px;background:#edf6ee;color:#287244;font-weight:700;font-size:12px}.kecm-table{min-width:<?php echo 420 + ($weekColumnCount * 82); ?>px}.kecm-table th,.kecm-table td{vertical-align:middle!important}.kecm-table thead th{background:#2f3944;color:#fff;text-align:center}.kecm-week{background:#1f5f8b!important}.kecm-subhead{background:#3e7ba8!important}.kecm-total-cell{background:#fff3cd;font-weight:800}.kecm-grand td{background:#ff8c42!important;color:#fff;font-weight:800}@media(max-width:576px){.kecm-title{font-size:20px}.kecm-filter{display:block}.kecm-filter .form-group,.kecm-filter .btn{width:100%;margin-top:8px}.kecm-head{display:block}.kecm-badge{margin-top:8px}}
</style>
<div class="kecm-wrap">
    <section class="kecm-panel">
        <h1 class="kecm-title">Data Kunjungan Pasien Rawat Inap per Kecamatan per Minggu</h1>
        <p class="kecm-sub">Periode minggu mengikuti Selasa sampai Senin dan dibatasi di dalam bulan registrasi yang dipilih.</p>
        <form id="filterKecMingguanRanapForm" method="post" class="kecm-filter">
            <div class="form-group mb-0">
                <label for="bulan"><strong>Bulan</strong></label>
                <input type="month" name="bulan" id="bulan" class="form-control form-control-sm" value="<?php echo aptd_kec_mingguan_h($selectedMonth); ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button>
            <?php if ($isAdmin): ?>
                <button type="button" class="btn btn-success btn-sm px-4" id="btnExportKecMingguanRanapExcel">Export Excel</button>
            <?php endif; ?>
            <button type="button" class="btn btn-danger btn-sm px-4" id="btnExportKecMingguanRanapPdf">Export PDF</button>
        </form>
    </section>

    <section class="kecm-panel">
        <div class="kecm-head">
            <div>
                <h2 class="kecm-title" style="font-size:18px;">Rekap Kecamatan Rawat Inap per Minggu</h2>
                <p class="kecm-sub">Total kunjungan rawat inap sesuai mapping kecamatan: <?php echo number_format($totals['total'], 0, ',', '.'); ?> pasien.</p>
            </div>
            <span class="kecm-badge"><?php echo aptd_kec_mingguan_h($monthLabels[$filterMonth] . ' ' . $filterYear); ?></span>
        </div>
        <div class="table-responsive-sm">
            <table class="table table-sm table-bordered table-hover kecm-table" style="width:100%;font-size:12px;">
                <thead>
                    <tr>
                        <th rowspan="2" style="width:45px;">NO</th>
                        <th rowspan="2">KAB/KOTA</th>
                        <th rowspan="2">KECAMATAN</th>
                        <th rowspan="2">TOTAL</th>
                        <?php foreach ($weeks as $week): ?>
                            <th class="kecm-week" colspan="<?php echo count($paymentLabels); ?>"><?php echo aptd_kec_mingguan_h($week['label']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($weeks as $week): ?>
                            <?php foreach ($paymentLabels as $payment): ?>
                                <th class="kecm-subhead"><?php echo aptd_kec_mingguan_h(strtoupper($payment)); ?></th>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($rows as $row): ?>
                        <?php if ($row['counts']['total'] <= 0) { continue; } ?>
                        <tr>
                            <td style="text-align:center;"><?php echo $no++; ?></td>
                            <td><?php echo aptd_kec_mingguan_h($row['kabupaten']); ?></td>
                            <td><?php echo aptd_kec_mingguan_h($row['kecamatan']); ?></td>
                            <td class="kecm-total-cell" style="text-align:center;"><?php echo number_format($row['counts']['total'], 0, ',', '.'); ?></td>
                            <?php foreach ($weeks as $weekIdx => $week): ?>
                                <?php foreach ($paymentLabels as $payment): ?>
                                    <td style="text-align:center;"><?php echo number_format($row['counts']['weeks'][$weekIdx][$payment], 0, ',', '.'); ?></td>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($no === 1): ?>
                        <tr><td colspan="<?php echo 4 + $weekColumnCount; ?>" style="text-align:center;color:#777;">Tidak ada data.</td></tr>
                    <?php endif; ?>
                    <tr class="kecm-grand">
                        <td colspan="3">Grand Total</td>
                        <td style="text-align:center;"><?php echo number_format($totals['total'], 0, ',', '.'); ?></td>
                        <?php foreach ($weeks as $weekIdx => $week): ?>
                            <?php foreach ($paymentLabels as $payment): ?>
                                <td style="text-align:center;"><?php echo number_format($totals['weeks'][$weekIdx][$payment], 0, ',', '.'); ?></td>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>
<script>
(function(){
    var form = document.getElementById('filterKecMingguanRanapForm');
    if (!form) return;
    form.querySelectorAll('input[type="month"]').forEach(function(item){
        item.addEventListener('change', function(){ form.submit(); });
    });

    function submitExport(action) {
        var exportForm = form.cloneNode(true);
        exportForm.id = '';
        exportForm.method = 'post';
        exportForm.action = action;
        exportForm.style.display = 'none';
        exportForm.querySelectorAll('button').forEach(function(button){
            button.parentNode.removeChild(button);
        });
        document.body.appendChild(exportForm);
        exportForm.submit();
        document.body.removeChild(exportForm);
    }

    var excelButton = document.getElementById('btnExportKecMingguanRanapExcel');
    if (excelButton) {
        excelButton.addEventListener('click', function(){
            submitExport('<?php echo $exportExcelAction; ?>');
        });
    }

    var pdfButton = document.getElementById('btnExportKecMingguanRanapPdf');
    if (pdfButton) {
        pdfButton.addEventListener('click', function(){
            submitExport('<?php echo $exportPdfAction; ?>');
        });
    }
})();
</script>
