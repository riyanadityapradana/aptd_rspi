<?php
require_once dirname(dirname(dirname(__DIR__))) . '/config/koneksi.php';
require_once __DIR__ . '/adime_gizi_helper.php';

$conn = $mysqli;
list($startDate, $endDate, $selectedStatus) = aptd_adime_filter_from_request();
$report = aptd_adime_fetch($conn, $startDate, $endDate, $selectedStatus);
$rows = $report['rows'];
$summary = $report['summary'];
$statusOptions = aptd_adime_status_options();
$exportAction = 'page/t_gizi/export_adime_gizi.php';
?>
<br>
<style>
.adime-wrap{display:grid;gap:16px}.adime-hero,.adime-panel,.adime-card{background:#fff;border:1px solid rgba(82,122,168,.18);box-shadow:0 14px 30px rgba(58,81,115,.10);border-radius:14px}.adime-hero{padding:20px;background:linear-gradient(135deg,#f8fbff,#edf8f2 62%,#fff8ec)}.adime-title{margin:0 0 6px;font-size:30px;font-weight:800;color:#24466f}.adime-sub{margin:0;color:#657b96;font-size:13px}.adime-filter{display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin-top:16px}.adime-filter .form-control,.adime-filter .btn{border-radius:10px}.adime-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px}.adime-card{padding:15px}.adime-k{font-size:12px;text-transform:uppercase;color:#6c819d;font-weight:700}.adime-v{font-size:26px;font-weight:800;color:#203f68;margin-top:5px}.adime-panel{padding:18px}.adime-head{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:10px}.adime-h{margin:0;font-size:19px;font-weight:800;color:#24466f}.adime-pill{display:inline-flex;padding:7px 11px;border-radius:999px;background:#eef6ff;color:#3368a8;font-size:12px;font-weight:700}.adime-status{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:800}.adime-done{background:#e9f8ef;color:#1f7a3b}.adime-pending{background:#fde8e8;color:#b42318}@media(max-width:576px){.adime-title{font-size:24px}.adime-filter{flex-direction:column;align-items:stretch}.adime-head{display:block}.adime-pill{margin-top:8px}}
</style>
<div class="adime-wrap">
    <section class="adime-hero">
        <h1 class="adime-title">Monitoring ADIME Gizi Rawat Inap</h1>
        <p class="adime-sub">Tarikan pasien rawat inap berdasarkan tanggal masuk untuk melihat status catatan ADIME gizi.</p>
        <form method="post" class="adime-filter" id="filterAdimeForm">
            <div class="form-group mb-0">
                <label for="tgl_awal"><strong>Tanggal Awal</strong></label>
                <input type="date" name="tgl_awal" id="tgl_awal" class="form-control form-control-sm" value="<?php echo aptd_adime_h($startDate); ?>">
            </div>
            <div class="form-group mb-0">
                <label for="tgl_akhir"><strong>Tanggal Akhir</strong></label>
                <input type="date" name="tgl_akhir" id="tgl_akhir" class="form-control form-control-sm" value="<?php echo aptd_adime_h($endDate); ?>">
            </div>
            <div class="form-group mb-0">
                <label for="status_adime"><strong>Status ADIME</strong></label>
                <select name="status_adime" id="status_adime" class="form-control form-control-sm">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?php echo aptd_adime_h($value); ?>" <?php echo ($selectedStatus === $value) ? 'selected' : ''; ?>><?php echo aptd_adime_h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button>
            <button type="button" class="btn btn-success btn-sm px-4" id="btnExportAdime">Export Excel</button>
        </form>
    </section>

    <section class="adime-cards">
        <div class="adime-card"><div class="adime-k">Total Pasien</div><div class="adime-v"><?php echo number_format($summary['total'], 0, ',', '.'); ?></div></div>
        <div class="adime-card"><div class="adime-k">Sudah ADIME</div><div class="adime-v"><?php echo number_format($summary['SUDAH ADIME'], 0, ',', '.'); ?></div></div>
        <div class="adime-card"><div class="adime-k">Belum ADIME</div><div class="adime-v"><?php echo number_format($summary['BELUM ADIME'], 0, ',', '.'); ?></div></div>
    </section>

    <section class="adime-panel">
        <div class="adime-head">
            <h2 class="adime-h">Detail Pasien</h2>
            <span class="adime-pill"><?php echo aptd_adime_h($startDate . ' s/d ' . $endDate . ' - ' . $statusOptions[$selectedStatus]); ?></span>
        </div>
        <div class="table-responsive-sm">
            <table class="table table-sm table-bordered table-hover" id="table4" style="width:100%;font-size:12px;">
                <thead class="thead-dark">
                    <tr>
                        <th style="text-align:center;width:45px;">No.</th>
                        <th>No Rawat</th>
                        <th>No RM</th>
                        <th>Nama Pasien</th>
                        <th>Kamar</th>
                        <th>Tgl Masuk</th>
                        <th>Jam Masuk</th>
                        <th>Status ADIME</th>
                        <th>Status Pulang</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9" style="text-align:center;">Tidak ada data.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($rows as $row): ?>
                            <?php $statusClass = $row['status_adime'] === 'SUDAH ADIME' ? 'adime-done' : 'adime-pending'; ?>
                            <tr>
                                <td style="text-align:center;"><?php echo $no++; ?></td>
                                <td><?php echo aptd_adime_h($row['no_rawat']); ?></td>
                                <td><?php echo aptd_adime_h($row['no_rkm_medis']); ?></td>
                                <td><?php echo aptd_adime_h($row['nm_pasien']); ?></td>
                                <td><?php echo aptd_adime_h($row['kd_kamar']); ?></td>
                                <td><?php echo aptd_adime_h($row['tgl_masuk']); ?></td>
                                <td><?php echo aptd_adime_h($row['jam_masuk']); ?></td>
                                <td><span class="adime-status <?php echo $statusClass; ?>"><?php echo aptd_adime_h($row['status_adime']); ?></span></td>
                                <td><?php echo aptd_adime_h($row['stts_pulang']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<script>
(function(){
    var button = document.getElementById('btnExportAdime');
    var form = document.getElementById('filterAdimeForm');
    if (!button || !form) return;
    button.addEventListener('click', function(){
        var exportForm = form.cloneNode(true);
        exportForm.id = '';
        exportForm.method = 'post';
        exportForm.action = '<?php echo $exportAction; ?>';
        exportForm.style.display = 'none';
        var buttons = exportForm.querySelectorAll('button');
        buttons.forEach(function(item){ item.parentNode.removeChild(item); });
        document.body.appendChild(exportForm);
        exportForm.submit();
        document.body.removeChild(exportForm);
    });
})();
</script>
