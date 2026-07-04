<?php
require_once __DIR__ . '/indikator_rawat_inap_helper.php';

list($month, $year, $startDate, $endDate) = aptd_filter_month_year();
$monthLabels = aptd_month_labels_local();
$bangsalOptions = aptd_ranap_indicator_bangsal_options($mysqli);
$allowedBangsal = array_column($bangsalOptions, 'kd_bangsal');
$bangsalFilter = isset($_POST['bangsal']) ? trim((string) $_POST['bangsal']) : '';
if ($bangsalFilter !== '' && !in_array($bangsalFilter, $allowedBangsal, true)) {
    $bangsalFilter = '';
}

$indicatorData = aptd_ranap_indicator_calculate($mysqli, $startDate, $endDate, $bangsalFilter);
$totals = $indicatorData['totals'];
$wardRows = $indicatorData['wards'];
$classRows = $indicatorData['classes'];
$validations = $indicatorData['validations'];
$hasBeds = $totals['jumlah_tt'] > 0;
$hasExits = $totals['pasien_keluar'] > 0;

$metrics = [
    [
        'key' => 'bor',
        'label' => 'BOR',
        'name' => 'Bed Occupancy Rate',
        'value' => $totals['bor'],
        'suffix' => '%',
        'ideal' => '60–85%',
        'has_denominator' => $totals['hari_tersedia'] > 0,
    ],
    [
        'key' => 'los',
        'label' => 'LOS',
        'name' => 'Length of Stay',
        'value' => $totals['los'],
        'suffix' => ' hari',
        'ideal' => '6–9 hari',
        'has_denominator' => $hasExits,
    ],
    [
        'key' => 'toi',
        'label' => 'TOI',
        'name' => 'Turn Over Interval',
        'value' => $totals['toi'],
        'suffix' => ' hari',
        'ideal' => '1–3 hari',
        'has_denominator' => $hasExits,
    ],
    [
        'key' => 'bto',
        'label' => 'BTO',
        'name' => 'Bed Turn Over',
        'value' => $totals['bto'],
        'suffix' => ' kali',
        'ideal' => '2–4 kali/bulan',
        'has_denominator' => $hasBeds,
    ],
];

foreach ($metrics as &$metric) {
    $metric['is_ideal'] = aptd_ranap_indicator_is_ideal(
        $metric['key'],
        $metric['value'],
        $metric['has_denominator']
    );
}
unset($metric);

$chartRows = array_slice($wardRows, 0, 15);
$chartLabels = array_column($chartRows, 'nm_bangsal');
$chartBor = array_map(function ($row) {
    return round((float) $row['bor'], 2);
}, $chartRows);
$chartLos = array_map(function ($row) {
    return round((float) $row['los'], 2);
}, $chartRows);
$chartToi = array_map(function ($row) {
    return round((float) $row['toi'], 2);
}, $chartRows);
$chartBto = array_map(function ($row) {
    return round((float) $row['bto'], 2);
}, $chartRows);

ob_start();
?>
<form method="post" class="analytics-filter">
    <div class="form-group mb-0">
        <label for="month"><strong>Bulan</strong></label>
        <select name="month" id="month" class="form-control form-control-sm">
            <?php foreach ($monthLabels as $number => $label): ?>
                <option value="<?php echo $number; ?>" <?php echo $month === $number ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group mb-0">
        <label for="year"><strong>Tahun</strong></label>
        <select name="year" id="year" class="form-control form-control-sm">
            <?php for ($selectedYear = 2020; $selectedYear <= ((int) date('Y') + 1); $selectedYear++): ?>
                <option value="<?php echo $selectedYear; ?>" <?php echo $year === $selectedYear ? 'selected' : ''; ?>>
                    <?php echo $selectedYear; ?>
                </option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="form-group mb-0">
        <label for="bangsal"><strong>Bangsal</strong></label>
        <select name="bangsal" id="bangsal" class="form-control form-control-sm">
            <option value="">Semua Bangsal</option>
            <?php foreach ($bangsalOptions as $option): ?>
                <option value="<?php echo htmlspecialchars($option['kd_bangsal'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $bangsalFilter === $option['kd_bangsal'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($option['nm_bangsal'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button>
</form>
<?php
$filters = ob_get_clean();

ob_start();
?>
<style>
.indicator-metrics{display:grid;grid-template-columns:repeat(4,minmax(190px,1fr));gap:16px}
.indicator-metric{position:relative;overflow:hidden;padding:20px;border-radius:22px;background:#fff;border:1px solid rgba(120,155,220,.18);box-shadow:0 18px 36px rgba(74,101,145,.10)}
.indicator-metric::before{content:"";position:absolute;inset:0 auto 0 0;width:6px;background:#92a8c7}
.indicator-metric.is-ideal::before{background:#27ae60}.indicator-metric.is-outside::before{background:#e74c3c}
.indicator-top{display:flex;align-items:start;justify-content:space-between;gap:10px}.indicator-code{font-size:14px;font-weight:900;letter-spacing:1px;color:#294c79}.indicator-name{font-size:12px;color:#7186a5;margin-top:3px}
.indicator-value{font-size:32px;font-weight:900;color:#183b68;line-height:1.1;margin-top:16px}.indicator-ideal{font-size:12px;color:#627b9e;margin-top:8px}
.indicator-status{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;background:#edf2f8;color:#60789a}
.indicator-status.is-ideal{background:#e7f8ee;color:#178447}.indicator-status.is-outside{background:#ffeded;color:#c0392b}
.indicator-base{display:grid;grid-template-columns:repeat(5,minmax(140px,1fr));gap:12px;margin-bottom:18px}.indicator-base-item{padding:14px;border-radius:16px;background:#f6f9fd;border:1px solid #e4edf8}.indicator-base-k{font-size:11px;text-transform:uppercase;letter-spacing:.7px;color:#7186a5}.indicator-base-v{font-size:22px;font-weight:850;color:#244b78;margin-top:5px}
.indicator-chart-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.indicator-validation{display:grid;gap:10px}.indicator-check{padding:12px 14px;border-radius:14px;font-size:13px}.indicator-check.ok{background:#eaf8ef;color:#177b43;border:1px solid #bfe8cf}.indicator-check.warn{background:#fff0ee;color:#b43a2f;border:1px solid #f2c3bd}
.indicator-formulas{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px}.indicator-formula{padding:12px;border-radius:14px;background:#f5f8fc;color:#486582;font-size:12px}
@media(max-width:1100px){.indicator-metrics{grid-template-columns:repeat(2,1fr)}.indicator-base{grid-template-columns:repeat(3,1fr)}}@media(max-width:767px){.indicator-metrics,.indicator-chart-grid,.indicator-formulas{grid-template-columns:1fr}.indicator-base{grid-template-columns:repeat(2,1fr)}}
</style>
<section class="indicator-metrics">
    <?php foreach ($metrics as $metric): ?>
        <?php
        $statusClass = $metric['is_ideal'] === true ? 'is-ideal' : ($metric['is_ideal'] === false ? 'is-outside' : '');
        $statusLabel = $metric['is_ideal'] === true ? 'Ideal' : ($metric['is_ideal'] === false ? 'Di luar ideal' : 'Belum cukup data');
        ?>
        <article class="indicator-metric <?php echo $statusClass; ?>">
            <div class="indicator-top">
                <div>
                    <div class="indicator-code"><?php echo htmlspecialchars($metric['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="indicator-name"><?php echo htmlspecialchars($metric['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <span class="indicator-status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="indicator-value"><?php echo number_format((float) $metric['value'], 2, ',', '.'); ?><?php echo htmlspecialchars($metric['suffix'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="indicator-ideal">Nilai ideal: <?php echo htmlspecialchars($metric['ideal'], ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
    <?php endforeach; ?>
</section>
<?php
$cards = ob_get_clean();

ob_start();
?>
<section class="analytics-panel">
    <div class="analytics-head">
        <div>
            <h2 class="analytics-h">Variabel Dasar Perhitungan</h2>
            <p class="analytics-d">Seluruh indikator memakai satu kontrak data yang sama untuk periode terpilih.</p>
        </div>
        <span class="analytics-pill"><?php echo htmlspecialchars($startDate . ' s.d. ' . $endDate, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <div class="indicator-base">
        <div class="indicator-base-item"><div class="indicator-base-k">Tempat Tidur Aktif</div><div class="indicator-base-v"><?php echo aptd_number($totals['jumlah_tt']); ?></div></div>
        <div class="indicator-base-item"><div class="indicator-base-k">Hari Periode</div><div class="indicator-base-v"><?php echo aptd_number($totals['hari_periode']); ?></div></div>
        <div class="indicator-base-item"><div class="indicator-base-k">Hari Perawatan</div><div class="indicator-base-v"><?php echo aptd_number($totals['hari_perawatan']); ?></div></div>
        <div class="indicator-base-item"><div class="indicator-base-k">Lama Dirawat</div><div class="indicator-base-v"><?php echo aptd_number($totals['lama_dirawat']); ?></div></div>
        <div class="indicator-base-item"><div class="indicator-base-k">Pasien Keluar</div><div class="indicator-base-v"><?php echo aptd_number($totals['pasien_keluar']); ?></div></div>
        <div class="indicator-base-item"><div class="indicator-base-k">Pasien Awal Bulan</div><div class="indicator-base-v"><?php echo aptd_number($totals['pasien_awal']); ?></div></div>
        <div class="indicator-base-item"><div class="indicator-base-k">Pasien Masuk</div><div class="indicator-base-v"><?php echo aptd_number($totals['pasien_masuk']); ?></div></div>
        <div class="indicator-base-item"><div class="indicator-base-k">Pasien Pindahan</div><div class="indicator-base-v"><?php echo aptd_number($totals['pasien_pindahan']); ?></div></div>
    </div>
    <div class="indicator-chart-grid">
        <div>
            <h3 class="analytics-h" style="font-size:16px;">BOR dan BTO per Bangsal</h3>
            <div class="analytics-chart"><canvas id="chartBorBtoRanap"></canvas></div>
        </div>
        <div>
            <h3 class="analytics-h" style="font-size:16px;">LOS dan TOI per Bangsal</h3>
            <div class="analytics-chart"><canvas id="chartLosToiRanap"></canvas></div>
        </div>
    </div>
</section>

<section class="analytics-grid">
    <div class="analytics-panel">
        <div class="analytics-head">
            <div>
                <h2 class="analytics-h">Validasi dan Nilai Ideal</h2>
                <p class="analytics-d">Pemeriksaan otomatis atas konsistensi variabel dasar.</p>
            </div>
        </div>
        <div class="indicator-validation">
            <div class="indicator-check <?php echo $validations['hari_perawatan_gte_lama_dirawat'] ? 'ok' : 'warn'; ?>">
                <?php echo $validations['hari_perawatan_gte_lama_dirawat'] ? '✓' : '⚠'; ?>
                Hari Perawatan (<?php echo aptd_number($totals['hari_perawatan']); ?>)
                <?php echo $validations['hari_perawatan_gte_lama_dirawat'] ? 'tidak kurang dari' : 'lebih kecil dari'; ?>
                Lama Dirawat (<?php echo aptd_number($totals['lama_dirawat']); ?>).
            </div>
            <div class="indicator-check <?php echo $validations['hari_perawatan_lte_kapasitas'] ? 'ok' : 'warn'; ?>">
                <?php echo $validations['hari_perawatan_lte_kapasitas'] ? '✓' : '⚠'; ?>
                Hari Perawatan <?php echo $validations['hari_perawatan_lte_kapasitas'] ? 'berada dalam' : 'melebihi'; ?>
                kapasitas <?php echo aptd_number($totals['hari_tersedia']); ?> bed-days.
            </div>
            <div class="indicator-check <?php echo $validations['lama_dirawat_gte_alur_pasien'] ? 'ok' : 'warn'; ?>">
                <?php echo $validations['lama_dirawat_gte_alur_pasien'] ? '✓' : '⚠'; ?>
                Lama Dirawat (<?php echo aptd_number($totals['lama_dirawat']); ?>)
                <?php echo $validations['lama_dirawat_gte_alur_pasien'] ? 'tidak kurang dari' : 'lebih kecil dari'; ?>
                Pasien Awal + Masuk + Pindahan
                (<?php echo aptd_number($totals['pasien_awal'] + $totals['pasien_masuk'] + $totals['pasien_pindahan']); ?>).
            </div>
        </div>
        <div class="indicator-formulas">
            <div class="indicator-formula"><strong>BOR</strong><br>Hari Perawatan ÷ (TT × Hari Periode) × 100%</div>
            <div class="indicator-formula"><strong>LOS</strong><br>Lama Dirawat ÷ Pasien Keluar</div>
            <div class="indicator-formula"><strong>TOI</strong><br>((TT × Hari Periode) − Hari Perawatan) ÷ Pasien Keluar</div>
            <div class="indicator-formula"><strong>BTO</strong><br>Pasien Keluar ÷ TT</div>
        </div>
    </div>
    <div class="analytics-panel">
        <div class="analytics-head">
            <div>
                <h2 class="analytics-h">Aturan Data</h2>
                <p class="analytics-d">Implementasi Juknis SIRS Revisi 6.3.</p>
            </div>
        </div>
        <div class="analytics-note">
            Tempat tidur berasal dari <code>kamar.statusdata = '1'</code>; bangsal
            <code>test</code>, <code>KB</code>, dan <code>OK</code> dikecualikan.
            Pasien keluar dihitung satu kali per <code>no_rawat</code> berdasarkan tanggal keluar dalam bulan laporan,
            dengan status <code>-</code> dan <code>Pindah Kamar</code> tidak dihitung sebagai keluar rumah sakit.
            Lama dirawat dihitung dari tanggal masuk pertama sampai tanggal keluar akhir.
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    const labels = <?php echo json_encode($chartLabels); ?>;
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 10, color: '#496280' } } }
    };

    const borBtoCanvas = document.getElementById('chartBorBtoRanap');
    if (borBtoCanvas) {
        new Chart(borBtoCanvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'BOR %', data: <?php echo json_encode($chartBor); ?>, backgroundColor: 'rgba(39,174,96,.76)', borderRadius: 7, yAxisID: 'y' },
                    { label: 'BTO kali', data: <?php echo json_encode($chartBto); ?>, backgroundColor: 'rgba(111,66,193,.72)', borderRadius: 7, yAxisID: 'y1' }
                ]
            },
            options: Object.assign({}, commonOptions, {
                scales: {
                    x: { ticks: { color: '#587192' }, grid: { display: false } },
                    y: { beginAtZero: true, position: 'left', title: { display: true, text: 'BOR %' } },
                    y1: { beginAtZero: true, position: 'right', title: { display: true, text: 'BTO' }, grid: { drawOnChartArea: false } }
                }
            })
        });
    }

    const losToiCanvas = document.getElementById('chartLosToiRanap');
    if (losToiCanvas) {
        new Chart(losToiCanvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'LOS hari', data: <?php echo json_encode($chartLos); ?>, backgroundColor: 'rgba(46,134,222,.76)', borderRadius: 7 },
                    { label: 'TOI hari', data: <?php echo json_encode($chartToi); ?>, backgroundColor: 'rgba(255,159,67,.76)', borderRadius: 7 }
                ]
            },
            options: Object.assign({}, commonOptions, {
                scales: {
                    x: { ticks: { color: '#587192' }, grid: { display: false } },
                    y: { beginAtZero: true, title: { display: true, text: 'Hari' } }
                }
            })
        });
    }
})();
</script>
<?php
$panels = ob_get_clean();

ob_start();
?>
<section class="analytics-panel">
    <div class="analytics-head">
        <div>
            <h2 class="analytics-h">Hari Perawatan per Kelas</h2>
            <p class="analytics-d">Agregasi VVIP, VIP, I, II, III, dan Kelas Khusus.</p>
        </div>
    </div>
    <div class="table-responsive-sm">
        <table class="table table-sm table-bordered table-hover analytics-table" style="width:100%;font-size:12px;">
            <thead class="thead-dark"><tr><th>Kelas</th><th>TT Aktif</th><th>Hari Perawatan</th></tr></thead>
            <tbody>
                <?php foreach ($classRows as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['kelas'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="text-align:center;"><?php echo aptd_number($row['jumlah_tt']); ?></td>
                        <td style="text-align:center;"><?php echo aptd_number($row['hari_perawatan']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold;background:#f4f7fb;">
                    <td>Total</td>
                    <td style="text-align:center;"><?php echo aptd_number($totals['jumlah_tt']); ?></td>
                    <td style="text-align:center;"><?php echo aptd_number($totals['hari_perawatan']); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</section>

<section class="analytics-panel">
    <div class="analytics-head">
        <div>
            <h2 class="analytics-h">Rincian Indikator per Bangsal</h2>
            <p class="analytics-d">Dasar audit angka dashboard pada periode yang sama.</p>
        </div>
    </div>
    <div class="table-responsive-sm">
        <table class="table table-sm table-bordered table-hover analytics-table" id="table4" style="width:100%;font-size:12px;">
            <thead class="thead-dark">
                <tr><th>No</th><th>Bangsal</th><th>TT</th><th>Hari Perawatan</th><th>Lama Dirawat</th><th>Pasien Keluar</th><th>BOR</th><th>LOS</th><th>TOI</th><th>BTO</th></tr>
            </thead>
            <tbody>
                <?php if (empty($wardRows)): ?>
                    <tr><td colspan="10" style="text-align:center;">Tidak ada data indikator untuk filter ini.</td></tr>
                <?php else: ?>
                    <?php $number = 1; foreach ($wardRows as $row): ?>
                        <tr>
                            <td style="text-align:center;"><?php echo $number++; ?></td>
                            <td><?php echo htmlspecialchars($row['nm_bangsal'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="text-align:center;"><?php echo aptd_number($row['jumlah_tt']); ?></td>
                            <td style="text-align:center;"><?php echo aptd_number($row['hari_perawatan']); ?></td>
                            <td style="text-align:center;"><?php echo aptd_number($row['lama_dirawat']); ?></td>
                            <td style="text-align:center;"><?php echo aptd_number($row['pasien_keluar']); ?></td>
                            <td style="text-align:center;font-weight:bold;"><?php echo number_format((float) $row['bor'], 2, ',', '.'); ?>%</td>
                            <td style="text-align:center;"><?php echo number_format((float) $row['los'], 2, ',', '.'); ?></td>
                            <td style="text-align:center;"><?php echo number_format((float) $row['toi'], 2, ',', '.'); ?></td>
                            <td style="text-align:center;"><?php echo number_format((float) $row['bto'], 2, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
$table = ob_get_clean();

aptd_render_shell([
    'title' => 'Dashboard Indikator Rawat Inap',
    'subtitle' => 'Kalkulasi otomatis BOR, LOS, TOI, dan BTO berdasarkan standar Juknis SIRS Revisi 6.3.',
    'filters' => $filters,
    'cards' => $cards,
    'panels' => $panels,
    'table' => $table,
]);
