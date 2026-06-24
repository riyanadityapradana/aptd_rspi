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

$sql = "SELECT rp.tgl_registrasi,
               SUM(CASE WHEN rp.stts_daftar = 'Baru' THEN 1 ELSE 0 END) AS pasien_baru,
               SUM(CASE WHEN rp.stts_daftar = 'Lama' THEN 1 ELSE 0 END) AS pasien_lama,
               COUNT(*) AS total_pasien
        FROM reg_periksa rp
        $where
        GROUP BY rp.tgl_registrasi
        ORDER BY rp.tgl_registrasi ASC";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
$totalBaru = 0;
$totalLama = 0;
$totalPasien = 0;
$chartLabels = [];
$chartBaru = [];
$chartLama = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
    $totalBaru += (int) $row['pasien_baru'];
    $totalLama += (int) $row['pasien_lama'];
    $totalPasien += (int) $row['total_pasien'];
    $chartLabels[] = date('d M', strtotime($row['tgl_registrasi']));
    $chartBaru[] = (int) $row['pasien_baru'];
    $chartLama[] = (int) $row['pasien_lama'];
}
$stmt->close();
$dominant = $totalBaru >= $totalLama ? 'Pasien Baru' : 'Pasien Lama';
ob_start();
?>
<form method="post" class="analytics-filter">
    <div class="form-group mb-0">
        <label for="month"><strong>Bulan</strong></label>
        <select name="month" id="month" class="form-control form-control-sm">
            <?php foreach ($monthLabels as $n => $label): ?>
                <option value="<?php echo $n; ?>" <?php echo $month === $n ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group mb-0">
        <label for="year"><strong>Tahun</strong></label>
        <select name="year" id="year" class="form-control form-control-sm">
            <?php for ($y = 2020; $y <= ((int) date('Y') + 1); $y++): ?>
                <option value="<?php echo $y; ?>" <?php echo $year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="form-group mb-0">
        <label for="service"><strong>Layanan</strong></label>
        <select name="service" id="service" class="form-control form-control-sm">
            <?php foreach (aptd_service_options() as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo $service === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button>
</form>
<?php
$filters = ob_get_clean();
ob_start();
?>
<section class="analytics-cards">
    <div class="analytics-card"><div class="analytics-k">Total Pasien</div><div class="analytics-v"><?php echo aptd_number($totalPasien); ?></div><div class="analytics-s"><?php echo htmlspecialchars($monthLabels[$month] . ' ' . $year, ENT_QUOTES, 'UTF-8'); ?></div></div>
    <div class="analytics-card"><div class="analytics-k">Pasien Baru</div><div class="analytics-v"><?php echo aptd_number($totalBaru); ?></div><div class="analytics-s">Persentase <?php echo $totalPasien > 0 ? number_format(($totalBaru / $totalPasien) * 100, 1, ',', '.') : '0'; ?>%</div></div>
    <div class="analytics-card"><div class="analytics-k">Pasien Lama</div><div class="analytics-v"><?php echo aptd_number($totalLama); ?></div><div class="analytics-s">Persentase <?php echo $totalPasien > 0 ? number_format(($totalLama / $totalPasien) * 100, 1, ',', '.') : '0'; ?>%</div></div>
    <div class="analytics-card"><div class="analytics-k">Komposisi Dominan</div><div class="analytics-v"><?php echo htmlspecialchars($dominant, ENT_QUOTES, 'UTF-8'); ?></div><div class="analytics-s"><?php echo htmlspecialchars(aptd_service_options()[$service], ENT_QUOTES, 'UTF-8'); ?></div></div>
</section>
<?php $cards = ob_get_clean(); ob_start(); ?>
<section class="analytics-grid">
    <div class="analytics-panel">
        <div class="analytics-head"><div><h2 class="analytics-h">Tren Pasien Baru vs Lama</h2><p class="analytics-d">Jumlah kunjungan harian berdasarkan status pendaftaran pasien.</p></div><span class="analytics-pill"><?php echo htmlspecialchars($startDate . ' s.d. ' . $endDate, ENT_QUOTES, 'UTF-8'); ?></span></div>
        <div class="analytics-chart"><canvas id="chartBaruLama"></canvas></div>
    </div>
    <div class="analytics-panel">
        <div class="analytics-head"><div><h2 class="analytics-h">Catatan</h2><p class="analytics-d">Sumber data dari <code>reg_periksa.stts_daftar</code>.</p></div></div>
        <div class="analytics-note">Gunakan filter layanan untuk membandingkan pola pasien baru dan lama antara rawat jalan dan rawat inap. Query sudah memakai agregasi harian agar lebih ringan saat dibuka.</div>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>(function(){const ctx=document.getElementById('chartBaruLama');if(!ctx||typeof Chart==='undefined')return;new Chart(ctx,{type:'bar',data:{labels:<?php echo json_encode($chartLabels); ?>,datasets:[{label:'Pasien Baru',data:<?php echo json_encode($chartBaru); ?>,backgroundColor:'rgba(46,134,222,0.78)',borderRadius:10,borderSkipped:false,maxBarThickness:22},{label:'Pasien Lama',data:<?php echo json_encode($chartLama); ?>,backgroundColor:'rgba(39,174,96,0.72)',borderRadius:10,borderSkipped:false,maxBarThickness:22}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:10,color:'#496280'}}},scales:{x:{grid:{display:false},ticks:{color:'#6f84a4'}},y:{beginAtZero:true,ticks:{color:'#4d6c95'},grid:{color:'rgba(113,138,180,.12)'}}}}});})();</script>
<?php $panels = ob_get_clean(); ob_start(); ?>
<section class="analytics-panel">
    <div class="analytics-head"><div><h2 class="analytics-h">Detail Harian</h2><p class="analytics-d">Rekap jumlah pasien baru dan lama per tanggal registrasi.</p></div></div>
    <div class="table-responsive-sm"><table class="table table-sm table-bordered table-hover analytics-table" id="table4" style="width:100%;font-size:12px;"><thead class="thead-dark"><tr><th>No</th><th>Tanggal</th><th>Pasien Baru</th><th>Pasien Lama</th><th>Total</th></tr></thead><tbody><?php if (empty($rows)): ?><tr><td colspan="5" style="text-align:center;">Tidak ada data pada periode ini.</td></tr><?php else: $no = 1; foreach ($rows as $row): ?><tr><td style="text-align:center;"><?php echo $no++; ?></td><td><?php echo htmlspecialchars($row['tgl_registrasi'], ENT_QUOTES, 'UTF-8'); ?></td><td style="text-align:center;"><?php echo aptd_number($row['pasien_baru']); ?></td><td style="text-align:center;"><?php echo aptd_number($row['pasien_lama']); ?></td><td style="text-align:center;font-weight:bold;"><?php echo aptd_number($row['total_pasien']); ?></td></tr><?php endforeach; endif; ?></tbody></table></div>
</section>
<?php $table = ob_get_clean(); aptd_render_shell(['title' => 'Rekap Pasien Baru vs Lama', 'subtitle' => 'Bandingkan komposisi pasien baru dan lama per periode dengan query agregasi yang lebih cepat dan konsisten.', 'filters' => $filters, 'cards' => $cards, 'panels' => $panels, 'table' => $table]); ?>
