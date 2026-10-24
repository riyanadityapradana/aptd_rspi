<?php
$projectRoot = dirname(__DIR__, 4);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once $projectRoot . '/config/akses.php';
if (empty($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true
    || !aptd_can_access(isset($_SESSION['level']) ? $_SESSION['level'] : '', 'data_vaksinasi_haji')) {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses ke Data Vaksinasi Haji.');
}
require_once $projectRoot . '/config/koneksi.php';
require_once __DIR__ . '/vaksinasi_haji_helper.php';

$filters = aptd_vh_filters();
$report = aptd_vh_empty_report();
$paymentOptions = ['A09' => 'A09', 'A96' => 'A96'];
$loadError = $filters['message'];
try {
    $paymentOptions = aptd_vh_payment_options($mysqli);
    if ($filters['valid']) {
        $report = aptd_vh_fetch_report($mysqli, $filters);
    }
} catch (Throwable $exception) {
    error_log('Data Vaksinasi Haji: ' . $exception->getMessage());
    $loadError = 'Data vaksinasi haji belum dapat dimuat. Silakan coba kembali.';
}
$exportUrl = 'main_app.php?' . http_build_query([
    'page' => 'export_vaksinasi_haji_pdf',
    'tanggal_awal' => $filters['tanggal_awal'],
    'tanggal_akhir' => $filters['tanggal_akhir'],
    'jenis_bayar' => $filters['jenis_bayar'],
]);
?>
<style>
.vh-page{padding:20px 0 64px;color:#26333e;letter-spacing:0;min-width:0}
.vh-heading{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px}
.vh-heading h1{font-size:24px;font-weight:700;margin:0;overflow-wrap:anywhere}
.vh-filters{display:flex;align-items:flex-end;flex-wrap:wrap;gap:12px;border-top:1px solid #dce1e5;border-bottom:1px solid #dce1e5;padding:16px 0}
.vh-field{flex:1 1 170px;max-width:260px;min-width:0}.vh-field label{display:block;font-size:13px;font-weight:600;margin-bottom:5px}
.vh-filters .form-control,.vh-filters .btn{height:38px;font-size:13px}.vh-actions{display:flex;gap:8px;align-items:center}
.vh-reset{width:38px;display:inline-flex;justify-content:center;align-items:center}
.vh-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));margin:18px 0;border-bottom:1px solid #dce1e5;padding-bottom:16px;gap:16px}
.vh-stat{border-left:3px solid #368ab5;padding-left:12px;min-width:0}.vh-stat:nth-child(2){border-color:#269579}.vh-stat:nth-child(3){border-color:#c59127}.vh-stat:nth-child(4){border-color:#ba5a69}
.vh-stat span{display:block;font-size:12px;overflow-wrap:anywhere;color:#55636e}.vh-stat strong{display:block;font-size:23px;margin-top:4px}
.vh-table-wrap{overflow-x:auto}.vh-table{width:100%;min-width:1070px;font-size:12px}.vh-table th{white-space:nowrap;background:#f0f3f5}.vh-table td{vertical-align:middle}.vh-table .vh-nowrap{white-space:nowrap}.vh-table tfoot{background:#f0f3f5;font-weight:700}
.vh-page .dataTables_wrapper .row{margin-left:0;margin-right:0}.vh-page .dataTables_filter input{max-width:190px}
@media(max-width:600px){.vh-heading h1{font-size:21px}.vh-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.vh-field{max-width:none;flex-basis:100%}.vh-actions{width:100%}}
</style>
<div class="vh-page">
    <div class="vh-heading">
        <h1>Data Vaksinasi Haji</h1>
        <?php if ($loadError === ''): ?>
            <a href="<?php echo aptd_vh_h($exportUrl); ?>" class="btn btn-danger btn-sm"><span class="glyphicon glyphicon-file" aria-hidden="true"></span> Export PDF</a>
        <?php endif; ?>
    </div>
    <form method="get" action="main_app.php" class="vh-filters">
        <input type="hidden" name="page" value="data_vaksinasi_haji">
        <div class="vh-field">
            <label for="vh-start">Tanggal Awal</label>
            <input class="form-control" type="date" id="vh-start" name="tanggal_awal" value="<?php echo aptd_vh_h($filters['tanggal_awal']); ?>" required>
        </div>
        <div class="vh-field">
            <label for="vh-end">Tanggal Akhir</label>
            <input class="form-control" type="date" id="vh-end" name="tanggal_akhir" value="<?php echo aptd_vh_h($filters['tanggal_akhir']); ?>" required>
        </div>
        <div class="vh-field">
            <label for="vh-payment">Jenis Bayar</label>
            <select class="form-control" id="vh-payment" name="jenis_bayar">
                <option value="">Semua Jenis Bayar</option>
                <?php foreach ($paymentOptions as $code => $label): ?>
                    <option value="<?php echo aptd_vh_h($code); ?>" <?php echo $filters['jenis_bayar'] === $code ? 'selected' : ''; ?>><?php echo aptd_vh_h($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="vh-actions">
            <button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-filter" aria-hidden="true"></span> Tampilkan Data</button>
            <a href="main_app.php?page=data_vaksinasi_haji" class="btn btn-light vh-reset" title="Reset filter" aria-label="Reset filter"><span class="glyphicon glyphicon-refresh" aria-hidden="true"></span></a>
        </div>
    </form>
    <?php if ($loadError !== ''): ?>
        <div class="alert alert-danger mt-3" role="alert"><?php echo aptd_vh_h($loadError); ?></div>
    <?php else: ?>
        <div class="vh-summary" aria-label="Ringkasan laporan">
            <div class="vh-stat"><span>Total Kunjungan</span><strong><?php echo number_format($report['total_kunjungan'], 0, ',', '.'); ?></strong></div>
            <div class="vh-stat"><span>Pasien Unik</span><strong><?php echo number_format($report['total_pasien'], 0, ',', '.'); ?></strong></div>
            <?php foreach ($paymentOptions as $code => $label): ?>
                <div class="vh-stat"><span><?php echo aptd_vh_h($label); ?></span><strong><?php echo number_format($report['per_bayar'][$code], 0, ',', '.'); ?></strong></div>
            <?php endforeach; ?>
        </div>
        <div class="vh-table-wrap">
            <table class="table table-bordered table-hover vh-table" id="vh-table">
                <thead><tr><th>No.</th><th>No. Rawat</th><th>Tanggal Registrasi</th><th>Jam</th><th>No. RM</th><th>Nama Pasien</th><th>Poliklinik</th><th>Jenis Bayar</th><th>Status Bayar</th></tr></thead>
                <tbody>
                    <?php foreach ($report['rows'] as $index => $row): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td class="vh-nowrap"><?php echo aptd_vh_h($row['no_rawat']); ?></td>
                            <td class="vh-nowrap"><?php echo aptd_vh_h($row['tgl_registrasi']); ?></td>
                            <td><?php echo aptd_vh_h($row['jam_reg']); ?></td>
                            <td><?php echo aptd_vh_h($row['no_rkm_medis']); ?></td>
                            <td><?php echo aptd_vh_h($row['nm_pasien']); ?></td>
                            <td><?php echo aptd_vh_h($row['nm_poli'] . ' (' . $row['kd_poli'] . ')'); ?></td>
                            <td><?php echo aptd_vh_h($row['jenis_bayar'] . ' (' . $row['kd_pj'] . ')'); ?></td>
                            <td class="vh-nowrap"><?php echo aptd_vh_h($row['status_bayar']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="8">Total Kunjungan</td><td><?php echo number_format($report['total_kunjungan'], 0, ',', '.'); ?></td></tr></tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn.DataTable && document.getElementById('vh-table')) {
        jQuery('#vh-table').DataTable({
            order: [], pageLength: 25, lengthMenu: [25, 50, 100],
            language: {search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ baris', info: '_START_ - _END_ dari _TOTAL_ kunjungan', infoEmpty: '0 kunjungan', infoFiltered: '(dari _MAX_ kunjungan)', emptyTable: 'Tidak ada data vaksinasi haji pada filter ini.', zeroRecords: 'Tidak ada data yang cocok.', paginate: {previous: 'Sebelumnya', next: 'Berikutnya'}}
        });
    }
});
</script>
