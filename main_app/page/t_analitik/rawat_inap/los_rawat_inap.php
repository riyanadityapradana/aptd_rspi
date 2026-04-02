<?php
require_once dirname(__DIR__) . '/report_helper.php';
list($month, $year, $startDate, $endDate) = aptd_filter_month_year();
$monthLabels = aptd_month_labels_local();
$bangsalFilter = isset($_POST['bangsal']) ? trim($_POST['bangsal']) : '';
$bangsalOptions = [];
$resBangsal = $mysqli->query("SELECT kd_bangsal, nm_bangsal FROM bangsal WHERE status = '1' ORDER BY nm_bangsal ASC");
while ($row = $resBangsal->fetch_assoc()) { $bangsalOptions[] = $row; }
$whereExtra = '';
$types = 'ss';
$params = [$startDate, $endDate];
if ($bangsalFilter !== '') { $whereExtra = " AND b.kd_bangsal = ?"; $types .= 's'; $params[] = $bangsalFilter; }
$sql = "SELECT b.kd_bangsal, b.nm_bangsal,
               COUNT(DISTINCT ki.no_rawat) AS jumlah_pasien,
               SUM(IFNULL(ki.lama, 0)) AS total_hari,
               AVG(IFNULL(ki.lama, 0)) AS avg_los,
               MAX(IFNULL(ki.lama, 0)) AS max_los
        FROM kamar_inap ki
        INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
        INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
        INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
        WHERE ki.tgl_masuk BETWEEN ? AND ?
          AND rp.status_lanjut = 'Ranap'
          AND (
                ki.stts_pulang IS NULL 
                OR ki.stts_pulang = '-' 
                OR ki.stts_pulang <> 'Pindah Kamar'
            )
          $whereExtra
        GROUP BY b.kd_bangsal, b.nm_bangsal
        ORDER BY avg_los DESC, jumlah_pasien DESC";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = []; $totalPasien = 0; $totalHari = 0; $globalAvg = 0; $topBangsal = '-'; $topAvg = 0; $chartLabels = []; $chartValues = [];
while ($row = $result->fetch_assoc()) { $rows[] = $row; $totalPasien += (int) $row['jumlah_pasien']; $totalHari += (float) $row['total_hari']; $chartLabels[] = $row['nm_bangsal']; $chartValues[] = round((float) $row['avg_los'], 2); if ((float) $row['avg_los'] > $topAvg) { $topAvg = (float) $row['avg_los']; $topBangsal = $row['nm_bangsal']; } }
$stmt->close(); $globalAvg = $totalPasien > 0 ? $totalHari / $totalPasien : 0;
ob_start(); ?>
<form method="post" class="analytics-filter"><div class="form-group mb-0"><label for="month"><strong>Bulan</strong></label><select name="month" id="month" class="form-control form-control-sm"><?php foreach ($monthLabels as $n => $label): ?><option value="<?php echo $n; ?>" <?php echo $month === $n ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div><div class="form-group mb-0"><label for="year"><strong>Tahun</strong></label><select name="year" id="year" class="form-control form-control-sm"><?php for ($y = 2020; $y <= ((int) date('Y') + 1); $y++): ?><option value="<?php echo $y; ?>" <?php echo $year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option><?php endfor; ?></select></div><div class="form-group mb-0"><label for="bangsal"><strong>Bangsal</strong></label><select name="bangsal" id="bangsal" class="form-control form-control-sm"><option value="">Semua Bangsal</option><?php foreach ($bangsalOptions as $option): ?><option value="<?php echo htmlspecialchars($option['kd_bangsal'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $bangsalFilter === $option['kd_bangsal'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($option['nm_bangsal'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div><button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button></form>
<?php $filters = ob_get_clean(); ob_start(); ?>
<section class="analytics-cards"><div class="analytics-card"><div class="analytics-k">Rata-rata LOS</div><div class="analytics-v"><?php echo number_format($globalAvg, 2, ',', '.'); ?> hari</div><div class="analytics-s">Rata-rata seluruh pasien rawat inap</div></div><div class="analytics-card"><div class="analytics-k">Total Pasien</div><div class="analytics-v"><?php echo aptd_number($totalPasien); ?></div><div class="analytics-s">Periode <?php echo htmlspecialchars($monthLabels[$month] . ' ' . $year, ENT_QUOTES, 'UTF-8'); ?></div></div><div class="analytics-card"><div class="analytics-k">Total Hari Rawat</div><div class="analytics-v"><?php echo aptd_number($totalHari); ?></div><div class="analytics-s">Akumulasi lama dirawat</div></div><div class="analytics-card"><div class="analytics-k">Bangsal LOS Tertinggi</div><div class="analytics-v"><?php echo number_format($topAvg, 2, ',', '.'); ?></div><div class="analytics-s"><?php echo htmlspecialchars($topBangsal, ENT_QUOTES, 'UTF-8'); ?></div></div></section>
<?php $cards = ob_get_clean(); ob_start(); ?>
<section class="analytics-grid"><div class="analytics-panel"><div class="analytics-head"><div><h2 class="analytics-h">LOS per Bangsal</h2><p class="analytics-d">Rata-rata lama dirawat per bangsal pada periode terpilih.</p></div><span class="analytics-pill"><?php echo htmlspecialchars($startDate . ' s.d. ' . $endDate, ENT_QUOTES, 'UTF-8'); ?></span></div><div class="analytics-chart"><canvas id="chartLos"></canvas></div></div><div class="analytics-panel"><div class="analytics-head"><div><h2 class="analytics-h">Catatan</h2><p class="analytics-d">Menggunakan data <code>kamar_inap.lama</code>.</p></div></div><div class="analytics-note">Halaman ini cocok untuk memantau rata-rata LOS, total hari rawat, dan bangsal dengan lama perawatan tertinggi.</div></div></section><script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>(function(){const c=document.getElementById('chartLos');if(!c||typeof Chart==='undefined')return;const ctx=c.getContext('2d');const g=ctx.createLinearGradient(0,0,420,0);g.addColorStop(0,'rgba(255,159,67,.92)');g.addColorStop(1,'rgba(255,159,67,.22)');new Chart(ctx,{type:'bar',data:{labels:<?php echo json_encode($chartLabels); ?>,datasets:[{label:'AVG LOS (hari)',data:<?php echo json_encode($chartValues); ?>,backgroundColor:g,borderRadius:12,borderSkipped:false,maxBarThickness:24}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:10,color:'#496280'}}},scales:{y:{grid:{display:false},ticks:{color:'#4d6c95'}},x:{beginAtZero:true,ticks:{color:'#ff8c00'},grid:{color:'rgba(255,159,67,.12)'}}}}});})();</script>
<?php $panels = ob_get_clean(); ob_start(); ?>
<section class="analytics-panel"><div class="analytics-head"><div><h2 class="analytics-h">Tabel LOS Rawat Inap</h2><p class="analytics-d">Rekap jumlah pasien, total hari rawat, rata-rata LOS, dan maksimum LOS per bangsal.</p></div></div><div class="table-responsive-sm"><table class="table table-sm table-bordered table-hover analytics-table" id="table4" style="width:100%;font-size:12px;"><thead class="thead-dark"><tr><th>No</th><th>Kode Bangsal</th><th>Bangsal</th><th>Jumlah Pasien</th><th>Total Hari</th><th>AVG LOS</th><th>LOS Maksimum</th></tr></thead><tbody><?php if (empty($rows)): ?><tr><td colspan="7" style="text-align:center;">Tidak ada data rawat inap pada periode ini.</td></tr><?php else: $no = 1; foreach ($rows as $row): ?><tr><td style="text-align:center;"><?php echo $no++; ?></td><td><?php echo htmlspecialchars($row['kd_bangsal'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($row['nm_bangsal'], ENT_QUOTES, 'UTF-8'); ?></td><td style="text-align:center;"><?php echo aptd_number($row['jumlah_pasien']); ?></td><td style="text-align:center;"><?php echo aptd_number($row['total_hari']); ?></td><td style="text-align:center;font-weight:bold;"><?php echo number_format((float) $row['avg_los'], 2, ',', '.'); ?></td><td style="text-align:center;"><?php echo number_format((float) $row['max_los'], 0, ',', '.'); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
<?php $table = ob_get_clean(); aptd_render_shell(['title' => 'LOS Rawat Inap', 'subtitle' => 'Pantau lama dirawat rata-rata per bangsal dengan query agregasi yang lebih cepat dan konsisten.', 'filters' => $filters, 'cards' => $cards, 'panels' => $panels, 'table' => $table]); ?>
