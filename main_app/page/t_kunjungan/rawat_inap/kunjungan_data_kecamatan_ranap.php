<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/kunjungan_kecamatan_helper.php';
$conn = $mysqli;

$monthLabels = aptd_kecamatan_month_labels();
$kategoriList = aptd_kecamatan_payment_labels();
$wilayahList = aptd_kecamatan_wilayah_list();
$selectedWilayah = aptd_kecamatan_selected_wilayah(isset($_POST['wilayah']) ? $_POST['wilayah'] : '');
list($filterMonth, $filterYear, $startDate, $endDate) = aptd_kecamatan_period_from_request();
$report = aptd_kecamatan_fetch($conn, 'ranap', $startDate, $endDate, $selectedWilayah);
$data = $report['data'];
$displayWilayahList = $report['wilayah_list'];
$totalWilayah = $report['total_wilayah'];
$totalKategori = $report['total_kategori'];
$grandTotal = $report['grand_total'];
$exportAction = 'page/t_kunjungan/rawat_inap/export_kunjungan_kecamatan_ranap.php';
function h($value) { return aptd_kecamatan_h($value); }
?>
<br>
<style>
.kec-wrap{display:grid;gap:16px}.kec-hero,.kec-panel,.kec-card{background:#fff;border:1px solid rgba(96,132,174,.18);box-shadow:0 14px 30px rgba(58,81,115,.10);border-radius:14px}.kec-hero{padding:20px;background:linear-gradient(135deg,#f8fbff,#e8f3ff 62%,#fff8ec)}.kec-title{margin:0 0 6px;font-size:30px;font-weight:800;color:#24466f}.kec-sub{margin:0;color:#657b96;font-size:13px}.kec-filter{display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin-top:16px}.kec-filter .form-control,.kec-filter .btn{border-radius:10px}.kec-filter .btn-success{background:#198754;border-color:#198754}.kec-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px}.kec-card{padding:15px}.kec-k{font-size:12px;text-transform:uppercase;color:#6c819d;font-weight:700}.kec-v{font-size:26px;font-weight:800;color:#203f68;margin-top:5px}.kec-panel{padding:18px}.kec-head{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:10px}.kec-h{margin:0;font-size:19px;font-weight:800;color:#24466f}.kec-pill{display:inline-flex;padding:7px 11px;border-radius:999px;background:#eef6ff;color:#3368a8;font-size:12px;font-weight:700}.kec-section-row td{background:#dfefff!important;color:#24466f;font-weight:800}.kec-total-row td{background:#fff4d9!important;font-weight:800}.kec-grand-row td{background:#ff8c42!important;color:#fff;font-weight:800}@media(max-width:576px){.kec-title{font-size:24px}.kec-filter{flex-direction:column;align-items:stretch}.kec-head{display:block}.kec-pill{margin-top:8px}}
</style>
<div class="kec-wrap">
    <section class="kec-hero">
        <h1 class="kec-title">Kunjungan Rawat Inap Berdasarkan Kecamatan</h1>
        <p class="kec-sub">Data memakai query per wilayah yang sama dengan SQL Yog. Periode: <?php echo h($startDate); ?> sampai sebelum <?php echo h($endDate); ?>.</p>
        <form method="post" class="kec-filter" id="filterKecamatanForm">
            <div class="form-group mb-0"><label for="month"><strong>Bulan</strong></label><select name="month" id="month" class="form-control form-control-sm"><?php foreach ($monthLabels as $num => $label): ?><option value="<?php echo $num; ?>" <?php echo ($filterMonth === $num) ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select></div>
            <div class="form-group mb-0"><label for="year"><strong>Tahun</strong></label><select name="year" id="year" class="form-control form-control-sm"><?php for ($year = 2020; $year <= ((int) date('Y') + 1); $year++): ?><option value="<?php echo $year; ?>" <?php echo ($filterYear === $year) ? 'selected' : ''; ?>><?php echo $year; ?></option><?php endfor; ?></select></div>
            <div class="form-group mb-0"><label for="wilayah"><strong>Wilayah</strong></label><select name="wilayah" id="wilayah" class="form-control form-control-sm"><option value="" <?php echo ($selectedWilayah === '') ? 'selected' : ''; ?>>Semua Wilayah</option><?php foreach ($wilayahList as $wilayahOption): ?><option value="<?php echo h($wilayahOption); ?>" <?php echo ($selectedWilayah === $wilayahOption) ? 'selected' : ''; ?>><?php echo h($wilayahOption); ?></option><?php endforeach; ?></select></div>
            <button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button>
            <button type="button" class="btn btn-success btn-sm px-4" id="btnExportKecamatan">Export Excel</button>
        </form>
    </section>
    <section class="kec-cards">
        <div class="kec-card"><div class="kec-k">Total Kunjungan</div><div class="kec-v"><?php echo number_format($grandTotal,0,',','.'); ?></div></div>
        <?php foreach ($kategoriList as $kategori): ?><div class="kec-card"><div class="kec-k"><?php echo h($kategori); ?></div><div class="kec-v"><?php echo number_format($totalKategori[$kategori],0,',','.'); ?></div></div><?php endforeach; ?>
    </section>
    <section class="kec-panel">
        <div class="kec-head"><h2 class="kec-h">Detail Kecamatan</h2><span class="kec-pill"><?php echo h(($selectedWilayah !== '' ? $selectedWilayah . ' - ' : '') . $monthLabels[$filterMonth] . ' ' . $filterYear); ?></span></div>
        <div class="table-responsive-sm"><table class="table table-sm table-bordered table-hover" id="table4" style="width:100%;font-size:12px;"><thead class="thead-dark"><tr><th style="text-align:center;width:45px;">No.</th><th>Wilayah</th><th>Kecamatan</th><th style="text-align:center;">Umum</th><th style="text-align:center;">Asuransi</th><th style="text-align:center;">BPJS</th><th style="text-align:center;">Total</th></tr></thead><tbody><?php $no=1; foreach ($displayWilayahList as $wilayah): ?><tr class="kec-section-row"><td colspan="7"><?php echo h($wilayah); ?></td></tr><?php if (empty($data[$wilayah])): ?><tr><td colspan="7" style="text-align:center;">Tidak ada data.</td></tr><?php else: ?><?php foreach ($data[$wilayah] as $kecamatan => $row): $rowTotal = array_sum($row); ?><tr><td style="text-align:center;"><?php echo $no++; ?></td><td><?php echo h($wilayah); ?></td><td><?php echo h($kecamatan); ?></td><?php foreach ($kategoriList as $kategori): ?><td style="text-align:center;"><?php echo number_format($row[$kategori],0,',','.'); ?></td><?php endforeach; ?><td style="text-align:center;font-weight:700;"><?php echo number_format($rowTotal,0,',','.'); ?></td></tr><?php endforeach; ?><?php endif; ?><tr class="kec-total-row"><td colspan="3">Total <?php echo h($wilayah); ?></td><?php foreach ($kategoriList as $kategori): ?><td style="text-align:center;"><?php echo number_format($totalWilayah[$wilayah][$kategori],0,',','.'); ?></td><?php endforeach; ?><td style="text-align:center;"><?php echo number_format(array_sum($totalWilayah[$wilayah]),0,',','.'); ?></td></tr><?php endforeach; ?><tr class="kec-grand-row"><td colspan="3">Grand Total</td><?php foreach ($kategoriList as $kategori): ?><td style="text-align:center;"><?php echo number_format($totalKategori[$kategori],0,',','.'); ?></td><?php endforeach; ?><td style="text-align:center;"><?php echo number_format($grandTotal,0,',','.'); ?></td></tr></tbody></table></div>
    </section>
</div>
<script>
(function(){
    var button = document.getElementById('btnExportKecamatan');
    var form = document.getElementById('filterKecamatanForm');
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
