<?php
$roRoot = dirname(__DIR__, 3);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once $roRoot . '/config/akses.php';
if (empty($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true
    || !aptd_can_access(isset($_SESSION['level']) ? $_SESSION['level'] : '', 'rekap_ranap_operasi')) {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses ke laporan ini.');
}
require_once $roRoot . '/config/koneksi.php';
require_once __DIR__ . '/rekap_ranap_operasi_helper.php';
$roError = '';
$roReport = null;
$roWards = [];
$roFilters = aptd_ranap_operasi_filters([]);
try {
    $roFilters = aptd_ranap_operasi_filters($_GET);
    $roWards = aptd_ranap_operasi_bangsal($mysqli);
    $roReport = aptd_ranap_operasi_report($mysqli, $roFilters);
} catch (InvalidArgumentException $exception) {
    $roError = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('APTD AR-186: laporan operasi gagal dimuat; kode ' . $exception->getCode());
    $roError = 'Laporan belum dapat dimuat. Silakan coba kembali atau hubungi tim IT.';
}
$roExport = 'page/t_klinis/export_rekap_ranap_operasi.php?' . http_build_query($roFilters);
?>
<style>
.ranap-operasi{padding:20px 0 60px;color:#233c55;font-size:1rem}.ranap-operasi .ro-panel{padding:20px;margin-bottom:18px;background:#fff;border:1px solid #d8e3ee;border-radius:8px}.ranap-operasi h1{font-size:1.6rem;font-weight:700;color:#173d67}.ranap-operasi h2{font-size:1.2rem;font-weight:700}.ranap-operasi .ro-filter{display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;margin-top:20px}.ranap-operasi .ro-field{flex:1;min-width:160px}.ranap-operasi label{display:block;font-size:.9rem;font-weight:600}.ranap-operasi .form-control{font-size:.95rem;height:40px}.ranap-operasi .ro-actions{display:flex;gap:8px;flex-wrap:wrap}.ranap-operasi .btn{font-size:.9rem;min-height:40px}.ranap-operasi .ro-note{font-size:.9rem;color:#536b84;margin:12px 0 0}.ranap-operasi table{width:100%!important;font-size:.9rem}.ranap-operasi th{background:#eaf2fa;color:#173d67}.ranap-operasi td,.ranap-operasi th{vertical-align:middle;padding:10px}.ranap-operasi .ro-total{font-weight:700;background:#eaf2fa}.ranap-operasi .ro-count{font-weight:700;color:#173d67}.ranap-operasi .ro-table-wrap{overflow-x:auto}.ranap-operasi .ro-procedures{min-width:230px;white-space:normal;overflow-wrap:anywhere}.ranap-operasi .ro-rm{white-space:nowrap}@media(max-width:600px){.ranap-operasi .ro-panel{padding:14px}.ranap-operasi h1{font-size:1.3rem}.ranap-operasi .ro-actions{width:100%}}
/* Keep DataTables controls separate from the report form's block labels. */
.ranap-operasi #ro-detail_wrapper > .row:first-child{align-items:center}
.ranap-operasi #ro-detail_length label{display:inline-flex;align-items:center;gap:.35rem;margin:0}
.ranap-operasi #ro-detail_filter label{display:inline-flex;align-items:center;gap:.5rem;margin:0}
.ranap-operasi #ro-detail_filter input{width:230px;max-width:100%;margin-left:0}
@media(max-width:767px){
    .ranap-operasi #ro-detail_filter{margin-top:12px;text-align:left}
    .ranap-operasi #ro-detail_filter label{display:flex;width:100%}
    .ranap-operasi #ro-detail_filter input{flex:1;min-width:0;width:auto}
}
</style>
<div class="ranap-operasi">
    <section class="ro-panel">
        <h1>Rekap Pasien Rawat Inap dengan Tindakan Operasi</h1>
        <p class="ro-note">Jumlah pasien per bangsal dan jenis bayar, berdasarkan episode perawatan.</p>
        <form method="get" action="main_app.php" class="ro-filter">
            <input type="hidden" name="page" value="rekap_ranap_operasi">
            <div class="ro-field"><label for="ro-start">Tanggal Awal</label><input class="form-control" type="date" id="ro-start" name="start_date" value="<?= aptd_ranap_operasi_escape($roFilters['start_date']) ?>" required></div>
            <div class="ro-field"><label for="ro-end">Tanggal Akhir</label><input class="form-control" type="date" id="ro-end" name="end_date" value="<?= aptd_ranap_operasi_escape($roFilters['end_date']) ?>" required></div>
            <div class="ro-field"><label for="ro-basis">Basis Tanggal</label><select class="form-control" id="ro-basis" name="date_type"><option value="masuk" <?= $roFilters['date_type'] === 'masuk' ? 'selected' : '' ?>>Tanggal Masuk Ranap</option><option value="keluar" <?= $roFilters['date_type'] === 'keluar' ? 'selected' : '' ?>>Tanggal Keluar Ranap</option></select></div>
            <div class="ro-field"><label for="ro-ward">Bangsal</label><select class="form-control" id="ro-ward" name="kd_bangsal"><option value="">Semua Bangsal</option>
                <?php if ($roFilters['kd_bangsal'] !== '' && !in_array($roFilters['kd_bangsal'], array_column($roWards, 'kd_bangsal'), true)): ?>
                    <option value="<?= aptd_ranap_operasi_escape($roFilters['kd_bangsal']) ?>" selected><?= aptd_ranap_operasi_escape($roFilters['kd_bangsal']) ?> (di luar daftar aktif)</option>
                <?php endif; ?>
                <?php foreach ($roWards as $roWard): ?><option value="<?= aptd_ranap_operasi_escape($roWard['kd_bangsal']) ?>" <?= $roFilters['kd_bangsal'] === $roWard['kd_bangsal'] ? 'selected' : '' ?>><?= aptd_ranap_operasi_escape($roWard['nm_bangsal']) ?></option><?php endforeach; ?>
            </select></div>
            <div class="ro-actions"><button type="submit" class="btn btn-primary">Tampilkan</button>
                <?php if ($roReport !== null && aptd_can_access($_SESSION['level'], 'export_rekap_ranap_operasi')): ?><a class="btn btn-success" href="<?= aptd_ranap_operasi_escape($roExport) ?>">Export Excel</a><?php endif; ?>
            </div>
        </form>
        <p class="ro-note">Pasien dihitung sekali per nomor rawat pada kamar akhir dengan status keluar yang valid. Pasien yang masih dirawat tidak termasuk. Tanggal masuk mengacu pada tanggal registrasi.</p>
    </section>
    <?php if ($roError !== ''): ?><div class="alert alert-danger" role="alert"><?= aptd_ranap_operasi_escape($roError) ?></div><?php endif; ?>
    <?php if ($roReport !== null): ?>
    <section class="ro-panel">
        <h2>Rekapitulasi per Bangsal dan Jenis Bayar</h2>
        <p class="ro-note mb-3"><?= aptd_ranap_operasi_escape(aptd_ranap_operasi_subtitle($roFilters, $roWards)) ?></p>
        <div class="ro-table-wrap"><table class="table table-bordered table-striped" id="ro-summary"><thead><tr><th>Bangsal</th><th>BPJS</th><th>Umum</th><th>Asuransi</th><th>Total</th></tr></thead><tbody>
            <?php foreach ($roReport['summary'] as $roRow): ?><tr><td><?= aptd_ranap_operasi_escape($roRow['nm_bangsal']) ?></td><?php foreach (['BPJS', 'Umum', 'Asuransi', 'Total'] as $roCategory): ?><td class="text-right"><?= $roRow[$roCategory] ?></td><?php endforeach; ?></tr><?php endforeach; ?>
            <?php if (!$roReport['summary']): ?><tr><td colspan="5" class="text-center">Tidak ada pasien operasi pada filter ini.</td></tr><?php endif; ?>
        </tbody><tfoot><tr class="ro-total"><td>Total</td><?php foreach ($roReport['totals'] as $roTotal): ?><td class="text-right"><?= $roTotal ?></td><?php endforeach; ?></tr></tfoot></table></div>
    </section>
    <section class="ro-panel">
        <h2>Rincian Pasien Operasi <span class="ro-count">(<?= count($roReport['rows']) ?>)</span></h2>
        <div class="ro-table-wrap mt-3"><table class="table table-bordered table-striped" id="ro-detail"><thead><tr><th>No. RM</th><th>Nama Pasien</th><th>Nama Ruangan</th><th>Lama Rawat (Hari)</th><th>Nama Tindakan Operasi</th></tr></thead><tbody>
            <?php foreach ($roReport['rows'] as $roRow): ?><tr><td class="ro-rm"><?= aptd_ranap_operasi_escape($roRow['no_rkm_medis']) ?></td><td><?= aptd_ranap_operasi_escape($roRow['nm_pasien']) ?></td><td><?= aptd_ranap_operasi_escape($roRow['nama_ruangan']) ?></td><td class="text-right"><?= $roRow['lama_rawat'] ?></td><td class="ro-procedures"><?= aptd_ranap_operasi_escape($roRow['nama_tindakan']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <noscript><p class="ro-note">Seluruh rincian ditampilkan. Aktifkan JavaScript untuk pencarian dan pembagian halaman.</p></noscript>
    </section>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.jQuery || !jQuery.fn.DataTable || !document.getElementById('ro-detail')) { return; }
    jQuery('#ro-detail').DataTable({pageLength: 25, order: [], language: {
        emptyTable: 'Tidak ada pasien operasi pada filter ini.', zeroRecords: 'Tidak ada rincian yang sesuai.',
        search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ rincian', info: 'Menampilkan _START_–_END_ dari _TOTAL_ rincian',
        infoEmpty: 'Tidak ada rincian', infoFiltered: '(dari _MAX_ rincian)', paginate: {first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya'}
    }});
});
</script>
