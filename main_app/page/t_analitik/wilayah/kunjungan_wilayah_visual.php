<?php
require_once dirname(__DIR__) . '/report_helper.php';
list($startDate, $endDate) = aptd_filter_date_range();
$level = isset($_POST['wilayah']) && in_array($_POST['wilayah'], ['kabupaten','kecamatan'], true) ? $_POST['wilayah'] : 'kabupaten';
if ($level === 'kecamatan') {
    $sql = "SELECT IFNULL(kec.nm_kec, 'Tidak Diketahui') AS wilayah,
                   SUM(CASE WHEN rp.kd_pj = 'A09' THEN 1 ELSE 0 END) AS umum,
                   SUM(CASE WHEN rp.kd_pj = 'BPJ' THEN 1 ELSE 0 END) AS bpjs,
                   SUM(CASE WHEN rp.kd_pj = 'A92' THEN 1 ELSE 0 END) AS asuransi,
                   COUNT(*) AS total
            FROM reg_periksa rp
            INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN kecamatan kec ON p.kd_kec = kec.kd_kec
            WHERE rp.tgl_registrasi BETWEEN ? AND ?
              AND rp.status_lanjut = 'Ralan'
              AND rp.status_bayar = 'Sudah Bayar'
              AND rp.stts <> 'Batal'
            GROUP BY kec.nm_kec
            ORDER BY total DESC
            LIMIT 12";
} else {
    $sql = "SELECT IFNULL(kab.nm_kab, 'Tidak Diketahui') AS wilayah,
                   SUM(CASE WHEN rp.kd_pj = 'A09' THEN 1 ELSE 0 END) AS umum,
                   SUM(CASE WHEN rp.kd_pj = 'BPJ' THEN 1 ELSE 0 END) AS bpjs,
                   SUM(CASE WHEN rp.kd_pj = 'A92' THEN 1 ELSE 0 END) AS asuransi,
                   COUNT(*) AS total
            FROM reg_periksa rp
            INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN kabupaten kab ON p.kd_kab = kab.kd_kab
            WHERE rp.tgl_registrasi BETWEEN ? AND ?
              AND rp.status_lanjut = 'Ralan'
              AND rp.status_bayar = 'Sudah Bayar'
              AND rp.stts <> 'Batal'
            GROUP BY kab.nm_kab
            ORDER BY total DESC
            LIMIT 12";
}
$stmt = $mysqli->prepare($sql); $stmt->bind_param('ss', $startDate, $endDate); $stmt->execute(); $result = $stmt->get_result();
$rows = []; $labels = []; $totals = []; $umum = []; $bpjs = []; $asuransi = []; $sumTotal = 0; $topWilayah = '-'; $topJumlah = 0;
while ($row = $result->fetch_assoc()) { $rows[] = $row; $labels[] = $row['wilayah']; $totals[] = (int) $row['total']; $umum[] = (int) $row['umum']; $bpjs[] = (int) $row['bpjs']; $asuransi[] = (int) $row['asuransi']; $sumTotal += (int) $row['total']; if ((int) $row['total'] > $topJumlah) { $topJumlah = (int) $row['total']; $topWilayah = $row['wilayah']; } }
$stmt->close();
ob_start(); ?>
<form method="post" class="analytics-filter"><div class="form-group mb-0"><label for="start_date"><strong>Tanggal Awal</strong></label><input type="date" class="form-control form-control-sm" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>"></div><div class="form-group mb-0"><label for="end_date"><strong>Tanggal Akhir</strong></label><input type="date" class="form-control form-control-sm" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>"></div><div class="form-group mb-0"><label for="wilayah"><strong>Visualisasi</strong></label><select name="wilayah" id="wilayah" class="form-control form-control-sm"><option value="kabupaten" <?php echo $level === 'kabupaten' ? 'selected' : ''; ?>>Kabupaten</option><option value="kecamatan" <?php echo $level === 'kecamatan' ? 'selected' : ''; ?>>Kecamatan</option></select></div><button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button></form>
<?php $filters = ob_get_clean(); ob_start(); ?>
<section class="analytics-cards"><div class="analytics-card"><div class="analytics-k">Total Kunjungan</div><div class="analytics-v"><?php echo aptd_number($sumTotal); ?></div><div class="analytics-s">Rawat jalan sudah bayar</div></div><div class="analytics-card"><div class="analytics-k">Wilayah Teratas</div><div class="analytics-v"><?php echo aptd_number($topJumlah); ?></div><div class="analytics-s"><?php echo htmlspecialchars($topWilayah, ENT_QUOTES, 'UTF-8'); ?></div></div><div class="analytics-card"><div class="analytics-k">Jumlah Wilayah</div><div class="analytics-v"><?php echo aptd_number(count($rows)); ?></div><div class="analytics-s">Top visual <?php echo htmlspecialchars($level, ENT_QUOTES, 'UTF-8'); ?></div></div><div class="analytics-card"><div class="analytics-k">Periode</div><div class="analytics-v"><?php echo htmlspecialchars($level === 'kabupaten' ? 'Kabupaten' : 'Kecamatan', ENT_QUOTES, 'UTF-8'); ?></div><div class="analytics-s"><?php echo htmlspecialchars($startDate . ' s.d. ' . $endDate, ENT_QUOTES, 'UTF-8'); ?></div></div></section>
<?php $cards = ob_get_clean(); ob_start(); ?>
<section class="analytics-grid"><div class="analytics-panel"><div class="analytics-head"><div><h2 class="analytics-h">Kunjungan Berdasarkan <?php echo $level === 'kabupaten' ? 'Kabupaten' : 'Kecamatan'; ?></h2><p class="analytics-d">Grafik batang menampilkan total kunjungan rawat jalan per wilayah.</p></div><span class="analytics-pill"><?php echo htmlspecialchars($level === 'kabupaten' ? 'Per Kabupaten' : 'Per Kecamatan', ENT_QUOTES, 'UTF-8'); ?></span></div><div class="analytics-chart"><canvas id="chartWilayah"></canvas></div></div><div class="analytics-panel"><div class="analytics-head"><div><h2 class="analytics-h">Komposisi Pembayaran</h2><p class="analytics-d">Stacked bar membandingkan Umum, BPJS, dan Asuransi.</p></div></div><div class="analytics-chart"><canvas id="chartPembayaranWilayah"></canvas></div></div></section><script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>(function(){const c=document.getElementById('chartWilayah');if(c&&typeof Chart!=='undefined'){const ctx=c.getContext('2d');const g=ctx.createLinearGradient(0,0,420,0);g.addColorStop(0,'rgba(46,134,222,.92)');g.addColorStop(1,'rgba(46,134,222,.22)');new Chart(ctx,{type:'bar',data:{labels:<?php echo json_encode($labels); ?>,datasets:[{label:'Total Kunjungan',data:<?php echo json_encode($totals); ?>,backgroundColor:g,borderRadius:12,borderSkipped:false,maxBarThickness:24}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:10,color:'#496280'}}},scales:{y:{grid:{display:false},ticks:{color:'#4d6c95'}},x:{beginAtZero:true,ticks:{color:'#2e86de'},grid:{color:'rgba(46,134,222,.10)'}}}}});}const d=document.getElementById('chartPembayaranWilayah');if(d&&typeof Chart!=='undefined'){new Chart(d,{type:'bar',data:{labels:<?php echo json_encode($labels); ?>,datasets:[{label:'Umum',data:<?php echo json_encode($umum); ?>,backgroundColor:'rgba(255,193,7,.78)',borderRadius:10},{label:'BPJS',data:<?php echo json_encode($bpjs); ?>,backgroundColor:'rgba(46,134,222,.78)',borderRadius:10},{label:'Asuransi',data:<?php echo json_encode($asuransi); ?>,backgroundColor:'rgba(39,174,96,.72)',borderRadius:10}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:10,color:'#496280'}}},scales:{x:{stacked:true,ticks:{color:'#6f84a4'},grid:{display:false}},y:{stacked:true,beginAtZero:true,ticks:{color:'#4d6c95'},grid:{color:'rgba(113,138,180,.12)'}}}}});}})();</script>
<?php $panels = ob_get_clean(); ob_start(); ?>
<section class="analytics-panel"><div class="analytics-head"><div><h2 class="analytics-h">Tabel Kunjungan Wilayah</h2><p class="analytics-d">Top wilayah dengan total kunjungan dan komposisi pembayaran.</p></div></div><div class="table-responsive-sm"><table class="table table-sm table-bordered table-hover analytics-table" id="table4" style="width:100%;font-size:12px;"><thead class="thead-dark"><tr><th>No</th><th><?php echo $level === 'kabupaten' ? 'Kabupaten' : 'Kecamatan'; ?></th><th>Umum</th><th>BPJS</th><th>Asuransi</th><th>Total</th></tr></thead><tbody><?php if (empty($rows)): ?><tr><td colspan="6" style="text-align:center;">Tidak ada data wilayah pada periode ini.</td></tr><?php else: $no = 1; foreach ($rows as $row): ?><tr><td style="text-align:center;"><?php echo $no++; ?></td><td><?php echo htmlspecialchars($row['wilayah'], ENT_QUOTES, 'UTF-8'); ?></td><td style="text-align:center;"><?php echo aptd_number($row['umum']); ?></td><td style="text-align:center;"><?php echo aptd_number($row['bpjs']); ?></td><td style="text-align:center;"><?php echo aptd_number($row['asuransi']); ?></td><td style="text-align:center;font-weight:bold;"><?php echo aptd_number($row['total']); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
<?php $table = ob_get_clean(); aptd_render_shell(['title' => 'Kunjungan Berdasarkan Kecamatan / Kabupaten', 'subtitle' => 'Visualisasi wilayah yang lebih hidup untuk membaca konsentrasi kunjungan rawat jalan dan komposisi pembayarannya.', 'filters' => $filters, 'cards' => $cards, 'panels' => $panels, 'table' => $table]); ?>
