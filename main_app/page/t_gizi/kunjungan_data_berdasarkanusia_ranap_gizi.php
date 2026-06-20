<?php
require_once dirname(dirname(dirname(__DIR__))) . '/config/koneksi.php';
require_once __DIR__ . '/kunjungan_usia_ranap_gizi_helper.php';

$conn = $mysqli;
$filters = aptd_gizi_usia_filter_from_request();
$usiaCategories = aptd_gizi_usia_categories();
$penjabList = aptd_gizi_usia_penjab_list();
$rows = aptd_gizi_usia_fetch($conn, $filters);
$totalRows = count($rows);
$exportAction = 'page/t_gizi/export_kunjungan_usia_ranap_gizi.php';
?>
<br>
<style>
.gizi-usia-wrap{display:grid;gap:14px}.gizi-usia-panel{background:#fff;border:1px solid rgba(80,114,160,.18);box-shadow:0 10px 24px rgba(48,73,107,.08);border-radius:8px;padding:16px}.gizi-usia-title{margin:0 0 4px;font-size:24px;font-weight:800;color:#27496d}.gizi-usia-sub{margin:0;color:#62758d;font-size:13px}.gizi-usia-filter{display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin-top:14px}.gizi-usia-filter .form-control{min-width:145px}.gizi-usia-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px}.gizi-usia-badge{display:inline-flex;padding:6px 10px;border-radius:8px;background:#edf6ee;color:#287244;font-weight:700;font-size:12px}@media(max-width:576px){.gizi-usia-title{font-size:20px}.gizi-usia-filter{display:block}.gizi-usia-filter .form-group,.gizi-usia-filter .btn{width:100%;margin-top:8px}.gizi-usia-head{display:block}.gizi-usia-badge{margin-top:8px}}
</style>
<div class="gizi-usia-wrap">
    <section class="gizi-usia-panel">
        <h1 class="gizi-usia-title">Kunjungan Pasien Rawat Inap Berdasarkan Usia</h1>
        <p class="gizi-usia-sub">Laporan rawat inap untuk kebutuhan gizi, termasuk filter khusus usia 0-5 tahun.</p>
        <form id="filterGiziUsiaForm" method="post" class="gizi-usia-filter">
            <div class="form-group mb-0">
                <label for="tgl_awal"><strong>Tanggal Awal</strong></label>
                <input type="date" name="tgl_awal" id="tgl_awal" class="form-control form-control-sm" value="<?php echo aptd_gizi_usia_h($filters['tgl_awal']); ?>">
            </div>
            <div class="form-group mb-0">
                <label for="tgl_akhir"><strong>Tanggal Akhir</strong></label>
                <input type="date" name="tgl_akhir" id="tgl_akhir" class="form-control form-control-sm" value="<?php echo aptd_gizi_usia_h($filters['tgl_akhir']); ?>">
            </div>
            <div class="form-group mb-0">
                <label for="stts"><strong>Status Pulang</strong></label>
                <select name="stts" id="stts" class="form-control form-control-sm">
                    <?php foreach (['semua' => 'Semua', 'Pulang' => 'Pulang', 'Membaik' => 'Membaik', 'Meninggal' => 'Meninggal', 'Atas Persetujuan Dokter' => 'Atas Persetujuan Dokter'] as $value => $label): ?>
                        <option value="<?php echo aptd_gizi_usia_h($value); ?>" <?php echo $filters['stts'] === $value ? 'selected' : ''; ?>><?php echo aptd_gizi_usia_h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <label for="usia"><strong>Kategori Usia</strong></label>
                <select name="usia" id="usia" class="form-control form-control-sm">
                    <?php foreach ($usiaCategories as $value => $label): ?>
                        <option value="<?php echo aptd_gizi_usia_h($value); ?>" <?php echo $filters['usia'] === $value ? 'selected' : ''; ?>><?php echo aptd_gizi_usia_h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <label for="jenis_bayar"><strong>Jenis Bayar</strong></label>
                <select name="jenis_bayar" id="jenis_bayar" class="form-control form-control-sm">
                    <option value="semua" <?php echo $filters['jenis_bayar'] === 'semua' ? 'selected' : ''; ?>>Semua</option>
                    <?php foreach ($penjabList as $value => $label): ?>
                        <option value="<?php echo aptd_gizi_usia_h($value); ?>" <?php echo $filters['jenis_bayar'] === $value ? 'selected' : ''; ?>><?php echo aptd_gizi_usia_h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan</button>
            <button type="button" class="btn btn-success btn-sm px-4" id="btnExportGiziUsia">Export Excel</button>
        </form>
    </section>

    <section class="gizi-usia-panel">
        <div class="gizi-usia-head">
            <div>
                <h2 class="gizi-usia-title" style="font-size:18px;">Detail Data Kunjungan</h2>
                <p class="gizi-usia-sub">Total data sesuai filter: <?php echo number_format($totalRows, 0, ',', '.'); ?> pasien.</p>
            </div>
            <span class="gizi-usia-badge"><?php echo aptd_gizi_usia_h($usiaCategories[$filters['usia']]); ?></span>
        </div>
        <div class="table-responsive-sm">
            <table class="table table-sm table-bordered table-hover" id="table4" style="width:100%;font-size:12px;">
                <thead class="thead-dark">
                    <tr>
                        <th style="text-align:center;width:45px;">No.</th>
                        <th>No. RM</th>
                        <th>Nama Pasien</th>
                        <th>No. Rawat</th>
                        <th>Tgl Lahir</th>
                        <th>Tgl Registrasi</th>
                        <th>Umur Daftar</th>
                        <th>Usia Tahun</th>
                        <th>Kode Kamar</th>
                        <th>Nama Bangsal</th>
                        <th>Tgl Masuk</th>
                        <th>Tgl Keluar</th>
                        <th>Jenis Bayar</th>
                        <th>Status Pulang</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="14" style="text-align:center;color:#777;">Tidak ada data.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($rows as $row): ?>
                            <tr>
                                <td style="text-align:center;"><?php echo $no++; ?></td>
                                <td><?php echo aptd_gizi_usia_h($row['no_rm']); ?></td>
                                <td><?php echo aptd_gizi_usia_h($row['nama_pasien']); ?></td>
                                <td><?php echo aptd_gizi_usia_h($row['no_rawat']); ?></td>
                                <td><?php echo aptd_gizi_usia_h($row['tgl_lahir']); ?></td>
                                <td><?php echo aptd_gizi_usia_h($row['tgl_registrasi']); ?></td>
                                <td><?php echo aptd_gizi_usia_h($row['umur_daftar'] . ' ' . $row['status_umur']); ?></td>
                                <td style="text-align:center;"><?php echo aptd_gizi_usia_h($row['usia_tahun']); ?></td>
                                <td><?php echo aptd_gizi_usia_h($row['kode_kamar']); ?></td>
                                <td><?php echo aptd_gizi_usia_h($row['nama_bangsal']); ?></td>
                                <td><?php echo aptd_gizi_usia_h($row['tgl_masuk']); ?></td>
                                <td><?php echo aptd_gizi_usia_h($row['tgl_keluar']); ?></td>
                                <td><?php echo aptd_gizi_usia_h($row['jenis_bayar']); ?></td>
                                <td><?php echo aptd_gizi_usia_h($row['status_pulang']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f8f9fa;font-weight:bold;">
                        <td colspan="14" style="text-align:right;">Total Data: <?php echo number_format($totalRows, 0, ',', '.'); ?> pasien</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</div>
<script>
(function(){
    var form = document.getElementById('filterGiziUsiaForm');
    var exportButton = document.getElementById('btnExportGiziUsia');
    if (!form) return;

    form.querySelectorAll('select,input[type="date"]').forEach(function(item){
        item.addEventListener('change', function(){
            form.submit();
        });
    });

    if (exportButton) {
        exportButton.addEventListener('click', function(){
            var exportForm = form.cloneNode(true);
            exportForm.id = '';
            exportForm.method = 'post';
            exportForm.action = '<?php echo $exportAction; ?>';
            exportForm.style.display = 'none';
            exportForm.querySelectorAll('button').forEach(function(button){
                button.parentNode.removeChild(button);
            });
            document.body.appendChild(exportForm);
            exportForm.submit();
            document.body.removeChild(exportForm);
        });
    }
})();
</script>
