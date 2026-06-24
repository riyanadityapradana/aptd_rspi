<?php
require_once dirname(__DIR__) . '/report_helper.php';
list($startDate, $endDate) = aptd_filter_date_range();

$sqlMasuk = "SELECT DATE(rp.tgl_registrasi) AS tanggal,
                    rm.perujuk,
                    rm.no_rujuk,
                    rm.kd_penyakit,
                    rm.kategori_rujuk AS kategori,
                    COUNT(*) AS total
             FROM rujuk_masuk rm
             INNER JOIN reg_periksa rp ON rm.no_rawat = rp.no_rawat
             WHERE rp.tgl_registrasi BETWEEN ? AND ?
             GROUP BY DATE(rp.tgl_registrasi), rm.perujuk, rm.no_rujuk, rm.kd_penyakit, rm.kategori_rujuk
             ORDER BY tanggal ASC, rm.perujuk ASC";
$stmtMasuk = $mysqli->prepare($sqlMasuk);
$stmtMasuk->bind_param('ss', $startDate, $endDate);
$stmtMasuk->execute();
$resMasuk = $stmtMasuk->get_result();

$sqlKeluar = "SELECT r.tgl_rujuk AS tanggal,
                     r.rujuk_ke AS perujuk,
                     r.no_rujuk,
                     COALESCE(dp.kd_penyakit, '-') AS kd_penyakit,
                     r.kat_rujuk AS kategori,
                     COUNT(*) AS total
              FROM rujuk r
              LEFT JOIN diagnosa_pasien dp ON r.no_rawat = dp.no_rawat AND dp.prioritas = '1'
              WHERE r.tgl_rujuk BETWEEN ? AND ?
              GROUP BY r.tgl_rujuk, r.rujuk_ke, r.no_rujuk, COALESCE(dp.kd_penyakit, '-'), r.kat_rujuk
              ORDER BY tanggal ASC, r.rujuk_ke ASC";
$stmtKeluar = $mysqli->prepare($sqlKeluar);
$stmtKeluar->bind_param('ss', $startDate, $endDate);
$stmtKeluar->execute();
$resKeluar = $stmtKeluar->get_result();

$masukRows = [];
$keluarRows = [];
$totalMasuk = 0;
$totalKeluar = 0;
$catMasuk = [];
$catKeluar = [];

while ($row = $resMasuk->fetch_assoc()) {
    $masukRows[] = $row;
    $totalMasuk += (int) $row['total'];
    $current = isset($catMasuk[$row['kategori']]) ? $catMasuk[$row['kategori']] : 0;
    $catMasuk[$row['kategori']] = $current + (int) $row['total'];
}

while ($row = $resKeluar->fetch_assoc()) {
    $keluarRows[] = $row;
    $totalKeluar += (int) $row['total'];
    $current = isset($catKeluar[$row['kategori']]) ? $catKeluar[$row['kategori']] : 0;
    $catKeluar[$row['kategori']] = $current + (int) $row['total'];
}

$stmtMasuk->close();
$stmtKeluar->close();

$labels = array_values(array_unique(array_merge(array_keys($catMasuk), array_keys($catKeluar))));
$masukVals = [];
$keluarVals = [];
foreach ($labels as $label) {
    $masukVals[] = isset($catMasuk[$label]) ? $catMasuk[$label] : 0;
    $keluarVals[] = isset($catKeluar[$label]) ? $catKeluar[$label] : 0;
}

ob_start();
?>
<form method="post" class="analytics-filter">
    <div class="form-group mb-0">
        <label for="start_date"><strong>Tanggal Awal</strong></label>
        <input type="date" class="form-control form-control-sm" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="form-group mb-0">
        <label for="end_date"><strong>Tanggal Akhir</strong></label>
        <input type="date" class="form-control form-control-sm" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button>
</form>
<?php
$filters = ob_get_clean();

ob_start();
?>
<section class="analytics-cards">
    <div class="analytics-card"><div class="analytics-k">Rujukan Masuk</div><div class="analytics-v"><?php echo aptd_number($totalMasuk); ?></div><div class="analytics-s">Dari tabel <code>rujuk_masuk</code></div></div>
    <div class="analytics-card"><div class="analytics-k">Rujukan Keluar</div><div class="analytics-v"><?php echo aptd_number($totalKeluar); ?></div><div class="analytics-s">Dari tabel <code>rujuk</code></div></div>
    <div class="analytics-card"><div class="analytics-k">Selisih</div><div class="analytics-v"><?php echo aptd_number($totalMasuk - $totalKeluar); ?></div><div class="analytics-s">Masuk dikurangi keluar</div></div>
    <div class="analytics-card"><div class="analytics-k">Kategori Terbaca</div><div class="analytics-v"><?php echo aptd_number(count($labels)); ?></div><div class="analytics-s">Gabungan masuk dan keluar</div></div>
</section>
<?php
$cards = ob_get_clean();

ob_start();
?>
<section class="analytics-grid">
    <div class="analytics-panel">
        <div class="analytics-head"><div><h2 class="analytics-h">Rujukan Masuk vs Keluar</h2><p class="analytics-d">Perbandingan jumlah rujukan berdasarkan kategori pada periode terpilih.</p></div><span class="analytics-pill"><?php echo htmlspecialchars($startDate . ' s.d. ' . $endDate, ENT_QUOTES, 'UTF-8'); ?></span></div>
        <div class="analytics-chart"><canvas id="chartRujukan"></canvas></div>
    </div>
    <div class="analytics-panel">
        <div class="analytics-head"><div><h2 class="analytics-h">Catatan</h2><p class="analytics-d">Masuk diambil dari <code>rujuk_masuk</code>, keluar dari <code>rujuk</code>.</p></div></div>
        <div class="analytics-note">Detail keluar sekarang juga menampilkan tujuan rujuk, nomor rujukan, dan kode penyakit utama dari <code>diagnosa_pasien</code> agar audit data lebih mudah.</div>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
    const chart = document.getElementById('chartRujukan');
    if (!chart || typeof Chart === 'undefined') {
        return;
    }
    new Chart(chart, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [
                { label: 'Masuk', data: <?php echo json_encode($masukVals); ?>, backgroundColor: 'rgba(46,134,222,.78)', borderRadius: 10, borderSkipped: false, maxBarThickness: 24 },
                { label: 'Keluar', data: <?php echo json_encode($keluarVals); ?>, backgroundColor: 'rgba(255,159,67,.72)', borderRadius: 10, borderSkipped: false, maxBarThickness: 24 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 10, color: '#496280' } } },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#6f84a4' } },
                y: { beginAtZero: true, ticks: { color: '#4d6c95' }, grid: { color: 'rgba(113,138,180,.12)' } }
            }
        }
    });
})();
</script>
<?php
$panels = ob_get_clean();

ob_start();
?>
<section class="analytics-grid">
    <div class="analytics-panel">
        <div class="analytics-head"><div><h2 class="analytics-h">Detail Rujukan Masuk</h2><p class="analytics-d">Jumlah rujukan masuk per tanggal dan kategori beserta identitas rujukan.</p></div></div>
        <div class="table-responsive-sm">
            <table class="table table-sm table-bordered table-hover analytics-table" id="table4" style="width:100%;font-size:12px;">
                <thead class="thead-dark"><tr><th>No</th><th>Tanggal</th><th>Perujuk</th><th>No Rujuk</th><th>KD Penyakit</th><th>Kategori</th><th>Total</th></tr></thead>
                <tbody>
                <?php if (empty($masukRows)): ?>
                    <tr><td colspan="7" style="text-align:center;">Tidak ada data rujukan masuk.</td></tr>
                <?php else: $no = 1; foreach ($masukRows as $row): ?>
                    <tr><td style="text-align:center;"><?php echo $no++; ?></td><td><?php echo htmlspecialchars($row['tanggal'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($row['perujuk'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($row['no_rujuk'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($row['kd_penyakit'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($row['kategori'], ENT_QUOTES, 'UTF-8'); ?></td><td style="text-align:center;font-weight:bold;"><?php echo aptd_number($row['total']); ?></td></tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="analytics-panel">
        <div class="analytics-head"><div><h2 class="analytics-h">Detail Rujukan Keluar</h2><p class="analytics-d">Jumlah rujukan keluar per tanggal dan kategori beserta tujuan rujuk.</p></div></div>
        <div class="table-responsive-sm">
            <table class="table table-sm table-bordered table-hover analytics-table" id="table4" style="width:100%;font-size:12px;">
                <thead class="thead-dark"><tr><th>No</th><th>Tanggal</th><th>Perujuk</th><th>No Rujuk</th><th>KD Penyakit</th><th>Kategori</th><th>Total</th></tr></thead>
                <tbody>
                <?php if (empty($keluarRows)): ?>
                    <tr><td colspan="7" style="text-align:center;">Tidak ada data rujukan keluar.</td></tr>
                <?php else: $no = 1; foreach ($keluarRows as $row): ?>
                    <tr><td style="text-align:center;"><?php echo $no++; ?></td><td><?php echo htmlspecialchars($row['tanggal'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($row['perujuk'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($row['no_rujuk'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($row['kd_penyakit'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($row['kategori'], ENT_QUOTES, 'UTF-8'); ?></td><td style="text-align:center;font-weight:bold;"><?php echo aptd_number($row['total']); ?></td></tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php
$table = ob_get_clean();

aptd_render_shell([
    'title' => 'Pasien Rujukan Masuk / Keluar',
    'subtitle' => 'Pantau arus rujukan masuk dan keluar dengan query terpisah yang lebih jelas dan konsisten.',
    'filters' => $filters,
    'cards' => $cards,
    'panels' => $panels,
    'table' => $table,
]);
?>
