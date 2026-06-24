<?php
require_once dirname(__DIR__) . '/report_helper.php';
list($month, $year, $startDate, $endDate) = aptd_filter_month_year();
$service = aptd_selected_service();
$monthLabels = aptd_month_labels_local();
$where = "WHERE rp.tgl_registrasi BETWEEN ? AND ? AND rp.stts <> 'Batal'";
$types = 'ss';
$params = [$startDate, $endDate];
if ($service !== 'all') {
    $where .= " AND rp.status_lanjut = ?";
    $types .= 's';
    $params[] = $service;
}
$sql = "SELECT d.kd_dokter,
               IFNULL(d.nm_dokter, rp.kd_dokter) AS nama_dokter,
               COUNT(DISTINCT rp.no_rawat) AS jumlah_pasien,
               SUM(CASE WHEN rp.kd_pj = 'A09' THEN 1 ELSE 0 END) AS umum,
               SUM(CASE WHEN rp.kd_pj = 'BPJ' THEN 1 ELSE 0 END) AS bpjs,
               SUM(CASE WHEN rp.kd_pj = 'A92' THEN 1 ELSE 0 END) AS asuransi
        FROM reg_periksa rp
        LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
        $where
        GROUP BY d.kd_dokter, d.nm_dokter, rp.kd_dokter
        ORDER BY jumlah_pasien DESC, nama_dokter ASC
        LIMIT 10";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
$totalPasien = 0;
$totalBpjs = 0;
$totalUmum = 0;
$totalAsuransi = 0;
$topDokter = '-';
$topJumlah = 0;
$chartLabels = [];
$chartValues = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
    $totalPasien += (int) $row['jumlah_pasien'];
    $totalUmum += (int) $row['umum'];
    $totalBpjs += (int) $row['bpjs'];
    $totalAsuransi += (int) $row['asuransi'];
    $chartLabels[] = $row['nama_dokter'];
    $chartValues[] = (int) $row['jumlah_pasien'];
    if ((int) $row['jumlah_pasien'] > $topJumlah) {
        $topJumlah = (int) $row['jumlah_pasien'];
        $topDokter = $row['nama_dokter'];
    }
}
$stmt->close();
ob_start(); ?>
<form method="post" class="analytics-filter"><div class="form-group mb-0"><label for="month"><strong>Bulan</strong></label><select name="month" id="month" class="form-control form-control-sm"><?php foreach ($monthLabels as $n => $label): ?><option value="<?php echo $n; ?>" <?php echo $month === $n ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div><div class="form-group mb-0"><label for="year"><strong>Tahun</strong></label><select name="year" id="year" class="form-control form-control-sm"><?php for ($y = 2020; $y <= ((int) date('Y') + 1); $y++): ?><option value="<?php echo $y; ?>" <?php echo $year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option><?php endfor; ?></select></div><div class="form-group mb-0"><label for="service"><strong>Layanan</strong></label><select name="service" id="service" class="form-control form-control-sm"><?php foreach (aptd_service_options() as $key => $label): ?><option value="<?php echo $key; ?>" <?php echo $service === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div><button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button></form>
<?php $filters = ob_get_clean(); ob_start(); ?>
<section class="analytics-cards"><div class="analytics-card"><div class="analytics-k">Total Pasien Top 10</div><div class="analytics-v"><?php echo aptd_number($totalPasien); ?></div><div class="analytics-s"><?php echo htmlspecialchars($monthLabels[$month] . ' ' . $year, ENT_QUOTES, 'UTF-8'); ?></div></div><div class="analytics-card"><div class="analytics-k">Dokter Teratas</div><div class="analytics-v"><?php echo aptd_number($topJumlah); ?> px</div><div class="analytics-s"><?php echo htmlspecialchars($topDokter, ENT_QUOTES, 'UTF-8'); ?></div></div><div class="analytics-card"><div class="analytics-k">Komposisi BPJS</div><div class="analytics-v"><?php echo aptd_number($totalBpjs); ?></div><div class="analytics-s"><?php echo aptd_number($totalUmum); ?> Umum / <?php echo aptd_number($totalAsuransi); ?> Asuransi</div></div><div class="analytics-card"><div class="analytics-k">Layanan</div><div class="analytics-v"><?php echo htmlspecialchars(aptd_service_options()[$service], ENT_QUOTES, 'UTF-8'); ?></div><div class="analytics-s">Ranking 10 dokter terbanyak</div></div></section>
<?php $cards = ob_get_clean(); ob_start(); ?>
<section class="analytics-grid"><div class="analytics-panel"><div class="analytics-head"><div><h2 class="analytics-h">Top 10 Dokter</h2><p class="analytics-d">Ranking dokter berdasarkan jumlah pasien unik (<code>COUNT DISTINCT no_rawat</code>).</p></div><span class="analytics-pill"><?php echo htmlspecialchars($startDate . ' s.d. ' . $endDate, ENT_QUOTES, 'UTF-8'); ?></span></div><div class="analytics-chart"><canvas id="chartDokterTop"></canvas></div></div><div class="analytics-panel"><div class="analytics-head"><div><h2 class="analytics-h">Catatan</h2><p class="analytics-d">Query dioptimalkan di level agregasi dokter.</p></div></div><div class="analytics-note">Gunakan filter layanan untuk memisahkan ranking dokter rawat jalan dan rawat inap, atau pilih semua layanan untuk ranking gabungan.</div></div></section><script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>(function(){const c=document.getElementById('chartDokterTop');if(!c||typeof Chart==='undefined')return;const ctx=c.getContext('2d');const g=ctx.createLinearGradient(0,0,420,0);g.addColorStop(0,'rgba(46,134,222,.92)');g.addColorStop(1,'rgba(46,134,222,.22)');new Chart(ctx,{type:'bar',data:{labels:<?php echo json_encode($chartLabels); ?>,datasets:[{label:'Jumlah Pasien',data:<?php echo json_encode($chartValues); ?>,backgroundColor:g,borderRadius:12,borderSkipped:false,maxBarThickness:24}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:10,color:'#496280'}}},scales:{y:{grid:{display:false},ticks:{color:'#4d6c95'}},x:{beginAtZero:true,ticks:{color:'#2e86de'},grid:{color:'rgba(46,134,222,.10)'}}}}});})();</script>
<?php $panels = ob_get_clean(); ob_start(); ?>
<section class="analytics-panel"><div class="analytics-head"><div><h2 class="analytics-h">Tabel Top 10 Dokter</h2><p class="analytics-d">Rekap dokter dengan pasien terbanyak pada periode terpilih.</p></div></div><div class="table-responsive-sm"><table class="table table-sm table-bordered table-hover analytics-table" id="table4" style="width:100%;font-size:12px;"><thead class="thead-dark"><tr><th>No</th><th>Kode Dokter</th><th>Nama Dokter</th><th>Jumlah Pasien</th><th>Umum</th><th>BPJS</th><th>Asuransi</th></tr></thead><tbody><?php if (empty($rows)): ?><tr><td colspan="7" style="text-align:center;">Tidak ada data dokter pada periode ini.</td></tr><?php else: $no = 1; foreach ($rows as $row): ?><tr><td style="text-align:center;"><?php echo $no++; ?></td><td><?php echo htmlspecialchars($row['kd_dokter'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($row['nama_dokter'], ENT_QUOTES, 'UTF-8'); ?></td><td style="text-align:center;font-weight:bold;"><?php echo aptd_number($row['jumlah_pasien']); ?></td><td style="text-align:center;"><?php echo aptd_number($row['umum']); ?></td><td style="text-align:center;"><?php echo aptd_number($row['bpjs']); ?></td><td style="text-align:center;"><?php echo aptd_number($row['asuransi']); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
<?php $table = ob_get_clean(); aptd_render_shell(['title' => 'Top 10 Dokter Paling Banyak Pasien', 'subtitle' => 'Ranking dokter berdasarkan jumlah pasien dengan query agregasi yang lebih cepat dan konsisten.', 'filters' => $filters, 'cards' => $cards, 'panels' => $panels, 'table' => $table]); ?>
