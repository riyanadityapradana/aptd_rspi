<?php
require_once dirname(__DIR__) . '/report_helper.php';
list($month, $year, $startDate, $endDate) = aptd_filter_month_year();
$monthLabels = aptd_month_labels_local();
$viewMode = isset($_POST['view_mode']) && in_array($_POST['view_mode'], ['bangsal','kamar'], true) ? $_POST['view_mode'] : 'bangsal';
$periodDays = (int) date('t', strtotime($startDate));

if ($viewMode === 'bangsal') {
    $sql = "SELECT b.kd_bangsal AS kode_unit,
                   b.nm_bangsal AS nama_unit,
                   COUNT(DISTINCT k.kd_kamar) AS jumlah_kamar,
                   SUM(CASE
                           WHEN ki.no_rawat IS NULL THEN 0
                           WHEN COALESCE(ki.tgl_keluar, ?) < ? OR ki.tgl_masuk > ? THEN 0
                           ELSE DATEDIFF(LEAST(COALESCE(ki.tgl_keluar, ?), ?), GREATEST(ki.tgl_masuk, ?)) + 1
                       END) AS hari_terpakai
            FROM bangsal b
            INNER JOIN kamar k ON b.kd_bangsal = k.kd_bangsal
            LEFT JOIN kamar_inap ki ON k.kd_kamar = ki.kd_kamar
                AND (ki.stts_pulang IS NULL OR ki.stts_pulang <> 'Pindah Kamar')
            GROUP BY b.kd_bangsal, b.nm_bangsal
            ORDER BY b.nm_bangsal ASC";
} else {
    $sql = "SELECT k.kd_kamar AS kode_unit,
                   CONCAT(k.kd_kamar, ' / ', b.nm_bangsal) AS nama_unit,
                   1 AS jumlah_kamar,
                   SUM(CASE
                           WHEN ki.no_rawat IS NULL THEN 0
                           WHEN COALESCE(ki.tgl_keluar, ?) < ? OR ki.tgl_masuk > ? THEN 0
                           ELSE DATEDIFF(LEAST(COALESCE(ki.tgl_keluar, ?), ?), GREATEST(ki.tgl_masuk, ?)) + 1
                       END) AS hari_terpakai
            FROM kamar k
            INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
            LEFT JOIN kamar_inap ki ON k.kd_kamar = ki.kd_kamar
                AND (ki.stts_pulang IS NULL OR ki.stts_pulang <> 'Pindah Kamar')
            GROUP BY k.kd_kamar, b.nm_bangsal
            ORDER BY b.nm_bangsal ASC, k.kd_kamar ASC";
}

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ssssss', $endDate, $startDate, $endDate, $endDate, $endDate, $startDate);
$stmt->execute();
$result = $stmt->get_result();

$rows = array();
$topUnit = '-';
$topBor = 0;
$avgBor = 0;
$countUnits = 0;
$chartLabels = array();
$chartValues = array();

while ($row = $result->fetch_assoc()) {
    $availableDays = max(1, ((int) $row['jumlah_kamar']) * $periodDays);
    $bor = $availableDays > 0 ? ((float) $row['hari_terpakai'] / $availableDays) * 100 : 0;
    $row['available_days'] = $availableDays;
    $row['bor'] = $bor;
    $rows[] = $row;
    $avgBor += $bor;
    $countUnits++;

    if ($bor > $topBor) {
        $topBor = $bor;
        $topUnit = $row['nama_unit'];
    }
}
$stmt->close();

usort($rows, function ($a, $b) {
    if ($a['bor'] == $b['bor']) {
        return 0;
    }
    return ($a['bor'] < $b['bor']) ? 1 : -1;
});

foreach (array_slice($rows, 0, 10) as $row) {
    $chartLabels[] = $row['nama_unit'];
    $chartValues[] = round((float) $row['bor'], 2);
}

$avgBor = $countUnits > 0 ? $avgBor / $countUnits : 0;

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
        <label for="view_mode"><strong>Tampilan</strong></label>
        <select name="view_mode" id="view_mode" class="form-control form-control-sm">
            <option value="bangsal" <?php echo $viewMode === 'bangsal' ? 'selected' : ''; ?>>Per Bangsal</option>
            <option value="kamar" <?php echo $viewMode === 'kamar' ? 'selected' : ''; ?>>Per Kamar</option>
        </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button>
</form>
<?php
$filters = ob_get_clean();

ob_start();
?>
<section class="analytics-cards">
    <div class="analytics-card"><div class="analytics-k">AVG BOR</div><div class="analytics-v"><?php echo number_format($avgBor, 2, ',', '.'); ?>%</div><div class="analytics-s">Rata-rata seluruh unit</div></div>
    <div class="analytics-card"><div class="analytics-k">BOR Tertinggi</div><div class="analytics-v"><?php echo number_format($topBor, 2, ',', '.'); ?>%</div><div class="analytics-s"><?php echo htmlspecialchars($topUnit, ENT_QUOTES, 'UTF-8'); ?></div></div>
    <div class="analytics-card"><div class="analytics-k">Jumlah Unit</div><div class="analytics-v"><?php echo aptd_number(count($rows)); ?></div><div class="analytics-s"><?php echo $viewMode === 'bangsal' ? 'Bangsal aktif' : 'Kamar aktif'; ?></div></div>
    <div class="analytics-card"><div class="analytics-k">Periode Hari</div><div class="analytics-v"><?php echo aptd_number($periodDays); ?></div><div class="analytics-s"><?php echo htmlspecialchars($monthLabels[$month] . ' ' . $year, ENT_QUOTES, 'UTF-8'); ?></div></div>
</section>
<?php
$cards = ob_get_clean();

ob_start();
?>
<section class="analytics-grid">
    <div class="analytics-panel">
        <div class="analytics-head"><div><h2 class="analytics-h">BOR <?php echo $viewMode === 'bangsal' ? 'Bangsal' : 'Kamar'; ?></h2><p class="analytics-d">Perhitungan sederhana berdasarkan hari terpakai dibanding hari tersedia.</p></div><span class="analytics-pill"><?php echo $viewMode === 'bangsal' ? 'Per Bangsal' : 'Per Kamar'; ?></span></div>
        <div class="analytics-chart"><canvas id="chartBor"></canvas></div>
    </div>
    <div class="analytics-panel">
        <div class="analytics-head"><div><h2 class="analytics-h">Formula</h2><p class="analytics-d">BOR sederhana = hari terpakai / hari tersedia x 100%.</p></div></div>
        <div class="analytics-note">Hari tersedia dihitung dari jumlah kamar dikali jumlah hari pada bulan berjalan. Ini versi sederhana yang cocok untuk pemantauan internal cepat.</div>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
    const c = document.getElementById('chartBor');
    if (!c || typeof Chart === 'undefined') {
        return;
    }
    const ctx = c.getContext('2d');
    const g = ctx.createLinearGradient(0, 0, 420, 0);
    g.addColorStop(0, 'rgba(39,174,96,.92)');
    g.addColorStop(1, 'rgba(39,174,96,.22)');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'BOR %',
                data: <?php echo json_encode($chartValues); ?>,
                backgroundColor: g,
                borderRadius: 12,
                borderSkipped: false,
                maxBarThickness: 24
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 10,
                        color: '#496280'
                    }
                }
            },
            scales: {
                y: {
                    grid: { display: false },
                    ticks: { color: '#4d6c95' }
                },
                x: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { color: '#27ae60' },
                    grid: { color: 'rgba(39,174,96,.12)' }
                }
            }
        }
    });
})();
</script>
<?php
$panels = ob_get_clean();

ob_start();
?>
<section class="analytics-panel">
    <div class="analytics-head"><div><h2 class="analytics-h">Tabel BOR Sederhana</h2><p class="analytics-d">Hari terpakai, hari tersedia, dan persentase BOR per unit.</p></div></div>
    <div class="table-responsive-sm">
        <table class="table table-sm table-bordered table-hover analytics-table" id="table4" style="width:100%;font-size:12px;">
            <thead class="thead-dark">
                <tr><th>No</th><th>Kode</th><th>Unit</th><th>Jumlah Kamar</th><th>Hari Terpakai</th><th>Hari Tersedia</th><th>BOR %</th></tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7" style="text-align:center;">Tidak ada data BOR pada periode ini.</td></tr>
            <?php else: $no = 1; foreach ($rows as $row): ?>
                <tr>
                    <td style="text-align:center;"><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($row['kode_unit'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($row['nama_unit'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td style="text-align:center;"><?php echo aptd_number($row['jumlah_kamar']); ?></td>
                    <td style="text-align:center;"><?php echo number_format((float) $row['hari_terpakai'], 0, ',', '.'); ?></td>
                    <td style="text-align:center;"><?php echo aptd_number($row['available_days']); ?></td>
                    <td style="text-align:center;font-weight:bold;"><?php echo number_format((float) $row['bor'], 2, ',', '.'); ?>%</td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
$table = ob_get_clean();

aptd_render_shell(array(
    'title' => 'BOR Sederhana Per Bangsal/Kamar',
    'subtitle' => 'Pantau keterisian bangsal atau kamar dengan perhitungan sederhana yang cepat dibuka dan mudah dibaca.',
    'filters' => $filters,
    'cards' => $cards,
    'panels' => $panels,
    'table' => $table,
));
?>
