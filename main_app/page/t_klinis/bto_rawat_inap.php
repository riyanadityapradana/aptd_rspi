<?php
require_once dirname(__DIR__) . '/t_analitik/report_helper.php';

list($month, $year, $startDate, $endDate) = aptd_filter_month_year();
$monthLabels = aptd_month_labels_local();
$bangsalFilter = isset($_POST['bangsal']) ? trim((string) $_POST['bangsal']) : '';
$bangsalOptions = [];
$resBangsal = $mysqli->query("SELECT kd_bangsal, nm_bangsal FROM bangsal WHERE status = '1' ORDER BY nm_bangsal ASC");
while ($row = $resBangsal->fetch_assoc()) { $bangsalOptions[] = $row; }
$whereBangsal = '';
$params = [$startDate, $endDate];
if ($bangsalFilter !== '') { $whereBangsal = 'WHERE b.kd_bangsal = ?'; $params[] = $bangsalFilter; }
$types = str_repeat('s', count($params));
$sql = "SELECT b.kd_bangsal, b.nm_bangsal, COUNT(DISTINCT k.kd_kamar) AS jumlah_tt,
               SUM(CASE WHEN ki.tgl_keluar BETWEEN ? AND ? AND ki.tgl_keluar IS NOT NULL AND ki.tgl_keluar <> '0000-00-00' AND ki.stts_pulang IS NOT NULL AND ki.stts_pulang NOT IN ('Pindah Kamar', '-', '') THEN 1 ELSE 0 END) AS pasien_keluar
        FROM bangsal b
        INNER JOIN kamar k ON b.kd_bangsal = k.kd_bangsal
        LEFT JOIN kamar_inap ki ON k.kd_kamar = ki.kd_kamar
        $whereBangsal
        GROUP BY b.kd_bangsal, b.nm_bangsal
        ORDER BY b.nm_bangsal ASC";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = []; $totalTt = 0; $totalPasienKeluar = 0; $chartLabels = []; $chartValues = [];
while ($row = $result->fetch_assoc()) { $row['bto'] = (int) $row['jumlah_tt'] > 0 ? (int) $row['pasien_keluar'] / (int) $row['jumlah_tt'] : 0; $rows[] = $row; $totalTt += (int) $row['jumlah_tt']; $totalPasienKeluar += (int) $row['pasien_keluar']; }
$stmt->close();
$summaryBto = $totalTt > 0 ? $totalPasienKeluar / $totalTt : 0;
foreach (array_slice($rows, 0, 12) as $row) { $chartLabels[] = $row['nm_bangsal']; $chartValues[] = round((float) $row['bto'], 2); }
ob_start(); ?>
<form method="post" class="analytics-filter"><div class="form-group mb-0"><label for="month"><strong>Bulan</strong></label><select name="month" id="month" class="form-control form-control-sm"><?php foreach ($monthLabels as $n => $label): ?><option value="<?php echo $n; ?>" <?php echo $month === $n ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div><div class="form-group mb-0"><label for="year"><strong>Tahun</strong></label><select name="year" id="year" class="form-control form-control-sm"><?php for ($y = 2020; $y <= ((int) date('Y') + 1); $y++): ?><option value="<?php echo $y; ?>" <?php echo $year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option><?php endfor; ?></select></div><div class="form-group mb-0"><label for="bangsal"><strong>Bangsal</strong></label><select name="bangsal" id="bangsal" class="form-control form-control-sm"><option value="">Semua Bangsal</option><?php foreach ($bangsalOptions as $option): ?><option value="<?php echo htmlspecialchars($option['kd_bangsal'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $bangsalFilter === $option['kd_bangsal'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($option['nm_bangsal'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div><button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button></form>
<?php $filters = ob_get_clean(); ob_start(); ?>
<section class="analytics-cards"><div class="analytics-card"><div class="analytics-k">BTO</div><div class="analytics-v"><?php echo number_format($summaryBto, 2, ',', '.'); ?></div><div class="analytics-s">Pasien keluar / tempat tidur</div></div><div class="analytics-card"><div class="analytics-k">Pasien Keluar</div><div class="analytics-v"><?php echo aptd_number($totalPasienKeluar); ?></div><div class="analytics-s">Hidup + meninggal</div></div><div class="analytics-card"><div class="analytics-k">Tempat Tidur</div><div class="analytics-v"><?php echo aptd_number($totalTt); ?></div><div class="analytics-s">Jumlah TT pada filter</div></div><div class="analytics-card"><div class="analytics-k">Periode</div><div class="analytics-v"><?php echo htmlspecialchars($monthLabels[$month], ENT_QUOTES, 'UTF-8'); ?></div><div class="analytics-s"><?php echo (int) $year; ?></div></div></section>
<?php $cards = ob_get_clean(); ob_start(); ?>
<section class="analytics-grid"><div class="analytics-panel"><div class="analytics-head"><div><h2 class="analytics-h">BTO per Bangsal</h2><p class="analytics-d">Frekuensi penggunaan tempat tidur oleh pasien keluar.</p></div><span class="analytics-pill"><?php echo htmlspecialchars($startDate . ' s.d. ' . $endDate, ENT_QUOTES, 'UTF-8'); ?></span></div><div class="analytics-chart"><canvas id="chartBtoKlinis"></canvas></div></div><div class="analytics-panel"><div class="analytics-head"><div><h2 class="analytics-h">Rumus BTO</h2><p class="analytics-d">Bed Turn Over.</p></div></div><div class="analytics-note">BTO = jumlah pasien keluar (hidup + meninggal) / jumlah tempat tidur.</div></div></section><script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>(function(){const c=document.getElementById('chartBtoKlinis');if(!c||typeof Chart==='undefined')return;new Chart(c,{type:'bar',data:{labels:<?php echo json_encode($chartLabels); ?>,datasets:[{label:'BTO',data:<?php echo json_encode($chartValues); ?>,backgroundColor:'rgba(111,66,193,.76)',borderRadius:8}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:10,color:'#496280'}}},scales:{x:{ticks:{color:'#587192'},grid:{display:false}},y:{beginAtZero:true,ticks:{color:'#456384'},grid:{color:'rgba(113,138,180,.12)'}}}}});})();</script>
<?php $panels = ob_get_clean(); ob_start(); ?>
<section class="analytics-panel"><div class="analytics-head"><div><h2 class="analytics-h">Tabel BTO Rawat Inap</h2><p class="analytics-d">Pasien keluar dibandingkan jumlah tempat tidur per bangsal.</p></div></div><div class="table-responsive-sm"><table class="table table-sm table-bordered table-hover analytics-table" id="table4" style="width:100%;font-size:12px;"><thead class="thead-dark"><tr><th>No</th><th>Kode</th><th>Bangsal</th><th>TT</th><th>Pasien Keluar</th><th>BTO</th></tr></thead><tbody><?php if (empty($rows)): ?><tr><td colspan="6" style="text-align:center;">Tidak ada data BTO pada periode ini.</td></tr><?php else: $no = 1; foreach ($rows as $row): ?><tr><td style="text-align:center;"><?php echo $no++; ?></td><td><?php echo htmlspecialchars($row['kd_bangsal'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($row['nm_bangsal'], ENT_QUOTES, 'UTF-8'); ?></td><td style="text-align:center;"><?php echo aptd_number($row['jumlah_tt']); ?></td><td style="text-align:center;"><?php echo aptd_number($row['pasien_keluar']); ?></td><td style="text-align:center;font-weight:bold;"><?php echo number_format((float) $row['bto'], 2, ',', '.'); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
<?php $table = ob_get_clean(); aptd_render_shell(['title' => 'BTO Rawat Inap', 'subtitle' => 'Bed Turn Over berdasarkan pasien keluar dan jumlah tempat tidur.', 'filters' => $filters, 'cards' => $cards, 'panels' => $panels, 'table' => $table]); ?>
