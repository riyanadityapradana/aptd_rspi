<?php
$projectRoot = dirname(dirname(dirname(dirname(__DIR__))));
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once $projectRoot . '/config/akses.php';

$currentLevel = isset($_SESSION['level']) ? (string) $_SESSION['level'] : '';
if (!isset($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true
    || !aptd_can_access($currentLevel, 'rekap_resep_fornas_non_fornas')) {
    http_response_code(403);
    exit('Akses ditolak.');
}

require_once $projectRoot . '/config/koneksi.php';
require_once __DIR__ . '/rekap_resep_fornas_non_fornas_helper.php';

$period = aptd_fornas_period_from_request();
$report = aptd_fornas_empty_report();
$loadError = '';

if ($period['valid']) {
    try {
        $report = aptd_fornas_fetch_report($mysqli, $period['tanggal_awal'], $period['tanggal_akhir']);
    } catch (Throwable $exception) {
        error_log('AR-157 Rekap Resep: ' . $exception->getMessage());
        $loadError = 'Data rekap resep belum dapat dimuat.';
    }
} else {
    $loadError = $period['message'];
}

$dimensions = aptd_fornas_dimensions();
$percentageDenominator = (int) $report['total_terklasifikasi'];
$formulariumPercentages = [];
foreach ($dimensions['formularium'] as $formularium) {
    $formulariumPercentages[$formularium] = $percentageDenominator > 0
        ? ((int) $report['formularium_totals'][$formularium] / $percentageDenominator) * 100
        : 0;
}
$totalClassifiedPercentage = $percentageDenominator > 0 ? 100 : 0;
$exportPdfAction = 'page/t_non_klinis/farmasi/export_rekap_resep_fornas_pdf.php';

$cardData = [
    ['label' => 'Total Resep', 'value' => $report['total_resep'], 'note' => 'Total resep yang ada item obat', 'class' => 'total'],
    ['label' => 'Fornas', 'value' => $report['formularium_totals']['Fornas'], 'note' => 'Item evaluasi seluruhnya Fornas', 'class' => 'fornas'],
    ['label' => 'Non-Fornas', 'value' => $report['formularium_totals']['Non-Fornas'], 'note' => 'Memiliki minimal satu item Non-Fornas', 'class' => 'non-fornas'],
    ['label' => 'Non For RSPI', 'value' => $report['formularium_totals']['Non For RSPI'], 'note' => 'Non For RSPI murni atau gabungan Fornas', 'class' => 'non-rspi'],
    ['label' => 'Belum Terklasifikasi', 'value' => $report['belum_terklasifikasi'], 'note' => 'Tidak memiliki item kategori formularium', 'class' => 'unclassified'],
];
?>
<br>
<style>
.fornas-page{display:grid;gap:14px;padding-bottom:42px;color:#25384c}.fornas-panel,.fornas-card{background:#fff;border:1px solid #d8e2ec;border-radius:8px;box-shadow:0 8px 22px rgba(35,58,88,.08)}.fornas-panel{padding:18px}.fornas-title-row{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.fornas-title{margin:0;font-size:26px;font-weight:800;color:#183b63}.fornas-subtitle{margin:5px 0 0;color:#60748a;font-size:13px}.fornas-period{white-space:nowrap;border-left:3px solid #2d7dd2;padding:4px 0 4px 10px;color:#536a82;font-size:12px}.fornas-filter{display:flex;flex-wrap:wrap;align-items:flex-end;gap:10px;margin-top:16px}.fornas-filter-group{min-width:180px}.fornas-filter label{display:block;margin-bottom:5px;font-size:12px;font-weight:700;color:#334d69}.fornas-filter .form-control,.fornas-filter .btn{height:35px;font-size:12px}.fornas-filter .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px}.fornas-cards{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.fornas-card{padding:16px;border-top:4px solid var(--accent)}.fornas-card.total{--accent:#2d7dd2}.fornas-card.fornas{--accent:#24966d}.fornas-card.non-fornas{--accent:#d1495b}.fornas-card.non-rspi{--accent:#e0a126}.fornas-card.unclassified{--accent:#738496}.fornas-card-label{font-size:11px;font-weight:800;color:#657a90;text-transform:uppercase}.fornas-card-value{margin-top:6px;font-size:28px;line-height:1.1;font-weight:800;color:#1b3552}.fornas-card-note{margin-top:7px;font-size:11px;color:#7b8da0}.fornas-chart-grid{display:grid;grid-template-columns:minmax(280px,.8fr) minmax(0,1.4fr);gap:14px}.fornas-heading{margin:0;font-size:17px;font-weight:800;color:#244565}.fornas-heading-note{margin:4px 0 0;color:#708399;font-size:12px}.fornas-chart{position:relative;height:300px;margin-top:12px}.fornas-table-wrap{margin-top:14px;overflow:auto;border:1px solid #d8e1eb}.fornas-table{width:100%;min-width:850px;margin:0;font-size:12px}.fornas-table th,.fornas-table td{padding:8px;text-align:center;vertical-align:middle}.fornas-table thead th{background:#263746;color:#fff;border-color:#4b5966}.fornas-table thead tr:last-child th{background:#315b78}.fornas-table td.label{text-align:left}.fornas-table tbody tr:nth-child(even){background:#f7f9fb}.fornas-table .subtotal td{background:#eaf1f7;font-weight:800}.fornas-table tfoot td{background:#ff8a43;color:#fff;font-weight:800}.fornas-table tfoot .percentage td{background:#fff3e9;color:#244565;border-top-color:#f2b184}.fornas-alert{margin:0;font-size:12px}.fornas-meta{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;color:#708399;font-size:11px}.fornas-progress{height:6px;overflow:hidden;background:#e4eaf0;border-radius:3px}.fornas-progress-bar{height:100%;background:#24966d}.fornas-empty-chart{display:flex;align-items:center;justify-content:center;height:100%;color:#71859a;font-size:12px}.fornas-card,.fornas-panel{min-width:0}@media(max-width:1180px){.fornas-cards{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:850px){.fornas-chart-grid{grid-template-columns:1fr}.fornas-cards{grid-template-columns:repeat(2,minmax(0,1fr))}.fornas-title-row{flex-direction:column}.fornas-period{white-space:normal}}@media(max-width:575px){.fornas-panel{padding:14px}.fornas-title{font-size:22px}.fornas-filter{align-items:stretch;flex-direction:column}.fornas-filter-group,.fornas-filter .btn{width:100%}.fornas-cards{grid-template-columns:1fr}.fornas-card-value{font-size:24px}}
</style>

<div class="fornas-page">
    <section class="fornas-panel">
        <div class="fornas-title-row">
            <div>
                <h1 class="fornas-title">Rekap Resep Fornas, Non-Fornas dan Non For RSPI</h1>
                <p class="fornas-subtitle">Rekap nomor resep berdasarkan layanan, penjamin, jenis resep, dan formularium.</p>
            </div>
            <div class="fornas-period"><?php echo aptd_fornas_h($period['tanggal_awal'] . ' s.d. ' . $period['tanggal_akhir']); ?></div>
        </div>

        <?php if ($loadError !== ''): ?>
            <div class="alert alert-danger fornas-alert mt-3" role="alert"><?php echo aptd_fornas_h($loadError); ?></div>
        <?php endif; ?>

        <form method="post" class="fornas-filter" id="fornasFilterForm" novalidate>
            <div class="fornas-filter-group">
                <label for="fornasTanggalAwal">Tanggal Awal</label>
                <input type="date" class="form-control" id="fornasTanggalAwal" name="tanggal_awal" value="<?php echo aptd_fornas_h($period['tanggal_awal']); ?>" required>
            </div>
            <div class="fornas-filter-group">
                <label for="fornasTanggalAkhir">Tanggal Akhir</label>
                <input type="date" class="form-control" id="fornasTanggalAkhir" name="tanggal_akhir" value="<?php echo aptd_fornas_h($period['tanggal_akhir']); ?>" required>
            </div>
            <button type="submit" class="btn btn-primary px-4"><span class="glyphicon glyphicon-filter"></span> Tampilkan Data</button>
            <button type="button" class="btn btn-danger px-4" id="fornasExportPdf"><span class="glyphicon glyphicon-file"></span> Export PDF</button>
        </form>
    </section>

    <section class="fornas-cards" aria-label="Ringkasan rekap resep">
        <?php foreach ($cardData as $card): ?>
            <article class="fornas-card <?php echo aptd_fornas_h($card['class']); ?>">
                <div class="fornas-card-label"><?php echo aptd_fornas_h($card['label']); ?></div>
                <div class="fornas-card-value"><?php echo number_format($card['value'], 0, ',', '.'); ?></div>
                <div class="fornas-card-note"><?php echo aptd_fornas_h($card['note']); ?></div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="fornas-chart-grid">
        <div class="fornas-panel">
            <h2 class="fornas-heading">Komposisi Formularium</h2>
            <p class="fornas-heading-note">Resep yang memenuhi tiga aturan formularium.</p>
            <div class="fornas-chart">
                <?php if ($report['total_terklasifikasi'] > 0): ?>
                    <canvas id="fornasCategoryChart"></canvas>
                <?php else: ?>
                    <div class="fornas-empty-chart">Belum ada resep terklasifikasi.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="fornas-panel">
            <h2 class="fornas-heading">Formularium per Jenis Rawat</h2>
            <p class="fornas-heading-note">Perbandingan resep Ralan dan Ranap.</p>
            <div class="fornas-chart">
                <?php if ($report['total_terklasifikasi'] > 0): ?>
                    <canvas id="fornasRawatChart"></canvas>
                <?php else: ?>
                    <div class="fornas-empty-chart">Belum ada data pada periode ini.</div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="fornas-panel">
        <h2 class="fornas-heading">Matriks Rekap Resep</h2>
        <p class="fornas-heading-note">Satuan hitung: nomor resep.</p>
        <div class="fornas-table-wrap">
            <table class="table table-bordered fornas-table">
                <thead>
                    <tr>
                        <th rowspan="2">Jenis Rawat</th>
                        <th rowspan="2">Kategori Resep</th>
                        <th rowspan="2">Jenis Bayar</th>
                        <th colspan="3">Kategori Formularium</th>
                        <th rowspan="2">Total Terklasifikasi</th>
                    </tr>
                    <tr>
                        <?php foreach ($dimensions['formularium'] as $formularium): ?>
                            <th><?php echo aptd_fornas_h($formularium); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dimensions['rawat'] as $rawat): ?>
                        <?php $rawatRow = 0; ?>
                        <?php foreach ($dimensions['racikan'] as $racikan): ?>
                            <?php foreach ($dimensions['bayar'] as $bayar): ?>
                                <?php
                                $counts = $report['matrix'][$rawat][$racikan][$bayar];
                                $rowTotal = array_sum($counts);
                                ?>
                                <tr>
                                    <?php if ($rawatRow === 0): ?><td rowspan="6" class="label"><strong><?php echo aptd_fornas_h($rawat); ?></strong></td><?php endif; ?>
                                    <?php if ($rawatRow % 3 === 0): ?><td rowspan="3" class="label"><?php echo aptd_fornas_h($racikan); ?></td><?php endif; ?>
                                    <td class="label"><?php echo aptd_fornas_h($bayar); ?></td>
                                    <?php foreach ($dimensions['formularium'] as $formularium): ?>
                                        <td><?php echo number_format($counts[$formularium], 0, ',', '.'); ?></td>
                                    <?php endforeach; ?>
                                    <td><strong><?php echo number_format($rowTotal, 0, ',', '.'); ?></strong></td>
                                </tr>
                                <?php $rawatRow++; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <tr class="subtotal">
                            <td colspan="3" class="label">Subtotal <?php echo aptd_fornas_h($rawat); ?></td>
                            <?php foreach ($dimensions['formularium'] as $formularium): ?>
                                <td><?php echo number_format($report['rawat_totals'][$rawat][$formularium], 0, ',', '.'); ?></td>
                            <?php endforeach; ?>
                            <td><?php echo number_format(array_sum($report['rawat_totals'][$rawat]), 0, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="label">Grand Total</td>
                        <?php foreach ($dimensions['formularium'] as $formularium): ?>
                            <td><?php echo number_format($report['formularium_totals'][$formularium], 0, ',', '.'); ?></td>
                        <?php endforeach; ?>
                        <td><?php echo number_format($report['total_terklasifikasi'], 0, ',', '.'); ?></td>
                    </tr>
                    <tr class="percentage">
                        <td colspan="3" class="label">Persentase</td>
                        <?php foreach ($dimensions['formularium'] as $formularium): ?>
                            <td><?php echo number_format($formulariumPercentages[$formularium], 2, '.', ','); ?>%</td>
                        <?php endforeach; ?>
                        <td><?php echo number_format($totalClassifiedPercentage, 2, '.', ','); ?>%</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php $classifiedPercentage = $report['total_resep'] > 0 ? ($report['total_terklasifikasi'] / $report['total_resep']) * 100 : 0; ?>
        <div class="fornas-meta">
            <span><?php echo number_format($report['belum_terklasifikasi'], 0, ',', '.'); ?> resep tidak memiliki item kategori formularium.</span>
            <span>Waktu query: <?php echo number_format($report['query_seconds'], 3, ',', '.'); ?> detik</span>
        </div>
        <div class="fornas-progress mt-2" title="<?php echo aptd_fornas_h(number_format($classifiedPercentage, 2, ',', '.') . '% terklasifikasi'); ?>">
            <div class="fornas-progress-bar" style="width:<?php echo aptd_fornas_h(number_format($classifiedPercentage, 2, '.', '')); ?>%"></div>
        </div>
    </section>
</div>

<?php if ($report['total_terklasifikasi'] > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    const labels = <?php echo json_encode($dimensions['formularium']); ?>;
    const colors = ['#24966d', '#d1495b', '#e0a126'];
    const categoryCanvas = document.getElementById('fornasCategoryChart');
    if (categoryCanvas) {
        new Chart(categoryCanvas, {
            type: 'doughnut',
            data: {labels: labels, datasets: [{data: <?php echo json_encode(array_values($report['formularium_totals'])); ?>, backgroundColor: colors, borderWidth: 0, hoverOffset: 7}]},
            options: {responsive: true, maintainAspectRatio: false, cutout: '64%', animation: {duration: 850}, plugins: {legend: {position: 'bottom', labels: {usePointStyle: true, boxWidth: 10}}}}
        });
    }

    const rawatCanvas = document.getElementById('fornasRawatChart');
    if (rawatCanvas) {
        new Chart(rawatCanvas, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($dimensions['rawat']); ?>,
                datasets: labels.map(function (label, index) {
                    const rawatData = <?php echo json_encode($report['rawat_totals']); ?>;
                    return {label: label, data: [rawatData.Ralan[label], rawatData.Ranap[label]], backgroundColor: colors[index], borderRadius: 5, borderSkipped: false};
                })
            },
            options: {responsive: true, maintainAspectRatio: false, animation: {duration: 850}, plugins: {legend: {position: 'bottom', labels: {usePointStyle: true, boxWidth: 10}}}, scales: {x: {stacked: true, grid: {display: false}}, y: {stacked: true, beginAtZero: true, ticks: {precision: 0}}}}
        });
    }
});
</script>
<?php endif; ?>

<script>
(function () {
    const form = document.getElementById('fornasFilterForm');
    if (!form) return;

    function hasValidPeriod() {
        const start = document.getElementById('fornasTanggalAwal').value;
        const end = document.getElementById('fornasTanggalAkhir').value;
        if (!start || !end || end < start) {
            alert('Rentang tanggal tidak valid.');
            return false;
        }
        return true;
    }

    const exportPdfButton = document.getElementById('fornasExportPdf');
    if (exportPdfButton) {
        exportPdfButton.addEventListener('click', function () {
            if (!hasValidPeriod()) return;

            const exportForm = document.createElement('form');
            exportForm.method = 'post';
            exportForm.action = <?php echo json_encode($exportPdfAction); ?>;
            exportForm.style.display = 'none';

            ['tanggal_awal', 'tanggal_akhir'].forEach(function (name) {
                const source = form.querySelector('[name="' + name + '"]');
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = source ? source.value : '';
                exportForm.appendChild(input);
            });

            document.body.appendChild(exportForm);
            exportForm.submit();
            document.body.removeChild(exportForm);
        });
    }

    form.addEventListener('submit', function (event) {
        if (!hasValidPeriod()) {
            event.preventDefault();
        }
    });
})();
</script>
