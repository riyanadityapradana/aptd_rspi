<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
$conn = $mysqli;

$filter_month = isset($_POST['month']) ? (int) $_POST['month'] : (int) date('n');
$filter_year = isset($_POST['year']) ? (int) $_POST['year'] : (int) date('Y');

if ($filter_month < 1 || $filter_month > 12) {
    $filter_month = (int) date('n');
}

if ($filter_year < 2020 || $filter_year > ((int) date('Y') + 1)) {
    $filter_year = (int) date('Y');
}

$start_date = sprintf('%04d-%02d-01', $filter_year, $filter_month);
$end_date = date('Y-m-t', strtotime($start_date));
$month_labels = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$sql = "SELECT 
    rp.kd_poli,
    IFNULL(pl.nm_poli, rp.kd_poli) AS nama_poli,
    COUNT(DISTINCT rp.no_rawat) AS jumlah_pasien,
    SUM(CASE WHEN rp.kd_pj='A09' THEN 1 ELSE 0 END) AS jumlah_umum,
    SUM(CASE WHEN rp.kd_pj='BPJ' THEN 1 ELSE 0 END) AS jumlah_bpjs,
    SUM(CASE WHEN rp.kd_pj='A92' THEN 1 ELSE 0 END) AS jumlah_asuransi
FROM reg_periksa rp
LEFT JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli
WHERE rp.tgl_registrasi BETWEEN ? AND ?
  AND rp.status_lanjut='Ralan'
  AND rp.status_bayar='Sudah Bayar'
  AND rp.stts='Sudah'
  AND rp.no_rkm_medis NOT IN (
      SELECT no_rkm_medis FROM pasien WHERE LOWER(nm_pasien) LIKE '%test%'
  )
GROUP BY rp.kd_poli, pl.nm_poli
ORDER BY jumlah_pasien DESC, nama_poli ASC
LIMIT 10";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Query prepare gagal: ' . $conn->error);
}

$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$data_rows = [];
$total_pasien = 0;
$total_umum = 0;
$total_bpjs = 0;
$total_asuransi = 0;
$top_poli_name = '-';
$top_poli_code = '-';
$top_poli_patients = 0;
$chart_labels = [];
$chart_pasien = [];
$payment_distribution = ['Umum' => 0, 'BPJS' => 0, 'Asuransi' => 0];

while ($row = $result->fetch_assoc()) {
    $data_rows[] = $row;
    $total_pasien += (int) $row['jumlah_pasien'];
    $total_umum += (int) $row['jumlah_umum'];
    $total_bpjs += (int) $row['jumlah_bpjs'];
    $total_asuransi += (int) $row['jumlah_asuransi'];
    $chart_labels[] = $row['nama_poli'];
    $chart_pasien[] = (int) $row['jumlah_pasien'];
    $payment_distribution['Umum'] += (int) $row['jumlah_umum'];
    $payment_distribution['BPJS'] += (int) $row['jumlah_bpjs'];
    $payment_distribution['Asuransi'] += (int) $row['jumlah_asuransi'];

    if ((int) $row['jumlah_pasien'] > $top_poli_patients) {
        $top_poli_patients = (int) $row['jumlah_pasien'];
        $top_poli_name = $row['nama_poli'];
        $top_poli_code = $row['kd_poli'];
    }
}
$stmt->close();

$payment_labels = [];
$payment_values = [];
foreach ($payment_distribution as $label => $value) {
    if ($value > 0) {
        $payment_labels[] = $label;
        $payment_values[] = $value;
    }
}
?>
<br>
<style>
.ralan-top-wrap{display:grid;gap:18px}.ralan-top-hero,.ralan-top-panel,.ralan-top-card{background:#fff;border:1px solid rgba(120,155,220,.16);box-shadow:0 18px 36px rgba(74,101,145,.10);border-radius:22px}.ralan-top-hero{padding:24px;background:linear-gradient(135deg,#edf7ff,#ffffff 45%,#eefcf5)}.ralan-top-title{margin:0 0 8px;font-size:34px;font-weight:800;color:#21406c}.ralan-top-sub{margin:0;color:#587192;font-size:14px;max-width:760px}.ralan-top-filter{display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin-top:18px}.ralan-top-filter .form-control,.ralan-top-filter .btn{border-radius:12px}.ralan-top-filter .btn-primary{background:linear-gradient(135deg,#2e86de,#1f5fae);border:none}.ralan-top-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px}.ralan-top-card{padding:18px}.ralan-top-card:nth-child(1){background:linear-gradient(135deg,#edf6ff,#fff)}.ralan-top-card:nth-child(2){background:linear-gradient(135deg,#eefcf5,#fff)}.ralan-top-card:nth-child(3){background:linear-gradient(135deg,#fff6ea,#fff)}.ralan-top-card:nth-child(4){background:linear-gradient(135deg,#f5f1ff,#fff)}.ralan-top-k{font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#6f84a4}.ralan-top-v{font-size:28px;font-weight:800;color:#1f3f6d;line-height:1.1}.ralan-top-s{margin-top:8px;font-size:12px;color:#60789d}.ralan-top-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:18px}.ralan-top-panel{padding:20px}.ralan-top-head{display:flex;justify-content:space-between;gap:12px;align-items:start;margin-bottom:14px}.ralan-top-h{margin:0;font-size:20px;font-weight:800;color:#1e3d6a}.ralan-top-d{margin:4px 0 0;color:#6f84a4;font-size:13px}.ralan-top-pill{display:inline-flex;padding:8px 12px;border-radius:999px;background:#eaf4ff;color:#2d6ab0;font-size:12px;font-weight:700}.ralan-top-chart{position:relative;min-height:340px}@media(max-width:991px){.ralan-top-grid{grid-template-columns:1fr}}@media(max-width:576px){.ralan-top-title{font-size:28px}.ralan-top-filter{flex-direction:column;align-items:stretch}}
</style>
<div class="ralan-top-wrap">
<section class="ralan-top-hero">
    <h1 class="ralan-top-title">Top 10 Poliklinik Pasien Tertinggi</h1>
    <p class="ralan-top-sub">Lihat poliklinik rawat jalan paling padat dan komposisi jenis pembiayaan pasien dalam satu dashboard yang lebih visual.</p>
    <form method="post" class="ralan-top-filter">
        <div class="form-group mb-0">
            <label for="month"><strong>Bulan</strong></label>
            <select name="month" id="month" class="form-control form-control-sm">
                <?php foreach ($month_labels as $n => $name): ?>
                    <option value="<?php echo $n; ?>" <?php echo ($filter_month === $n) ? 'selected' : ''; ?>><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0">
            <label for="year"><strong>Tahun</strong></label>
            <select name="year" id="year" class="form-control form-control-sm">
                <?php for ($year = 2020; $year <= ((int) date('Y') + 1); $year++): ?>
                    <option value="<?php echo $year; ?>" <?php echo ($filter_year === $year) ? 'selected' : ''; ?>><?php echo $year; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button>
    </form>
</section>
<section class="ralan-top-cards">
    <div class="ralan-top-card"><div class="ralan-top-k">Total Pasien Top 10</div><div class="ralan-top-v"><?php echo number_format($total_pasien, 0, ',', '.'); ?></div><div class="ralan-top-s">Akumulasi seluruh pasien dari 10 poliklinik teratas</div></div>
    <div class="ralan-top-card"><div class="ralan-top-k">Total Poliklinik</div><div class="ralan-top-v"><?php echo number_format(count($data_rows), 0, ',', '.'); ?></div><div class="ralan-top-s">Jumlah poliklinik yang masuk ranking pada periode ini</div></div>
    <div class="ralan-top-card"><div class="ralan-top-k">Poliklinik Terpadat</div><div class="ralan-top-v"><?php echo $top_poli_patients > 0 ? number_format($top_poli_patients, 0, ',', '.') . ' px' : '-'; ?></div><div class="ralan-top-s"><?php echo htmlspecialchars($top_poli_code, ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars($top_poli_name, ENT_QUOTES, 'UTF-8'); ?></div></div>
    <div class="ralan-top-card"><div class="ralan-top-k">Komposisi Pasien</div><div class="ralan-top-v"><?php echo number_format($total_bpjs, 0, ',', '.'); ?> BPJS</div><div class="ralan-top-s"><?php echo number_format($total_umum, 0, ',', '.') . ' Umum / ' . number_format($total_asuransi, 0, ',', '.') . ' Asuransi'; ?></div></div>
</section>
<section class="ralan-top-grid">
    <div class="ralan-top-panel">
        <div class="ralan-top-head"><div><h2 class="ralan-top-h">Peringkat Poliklinik</h2><p class="ralan-top-d">Batang horizontal menampilkan jumlah pasien untuk setiap poliklinik yang masuk ranking.</p></div><span class="ralan-top-pill"><?php echo htmlspecialchars($month_labels[$filter_month], ENT_QUOTES, 'UTF-8'); ?> <?php echo (int) $filter_year; ?></span></div>
        <div class="ralan-top-chart"><canvas id="chartTopPoli"></canvas></div>
    </div>
    <div class="ralan-top-panel">
        <div class="ralan-top-head"><div><h2 class="ralan-top-h">Distribusi Pembayaran</h2><p class="ralan-top-d">Proporsi jenis pembiayaan dari akumulasi pasien top 10 poliklinik.</p></div></div>
        <div class="ralan-top-chart"><canvas id="chartPembayaranPoli"></canvas></div>
    </div>
</section>
<section class="ralan-top-panel">
    <div class="ralan-top-head"><div><h2 class="ralan-top-h">Tabel Ranking Poliklinik</h2><p class="ralan-top-d">Ranking poliklinik berdasarkan jumlah pasien rawat jalan dengan status bayar <strong>Sudah Bayar</strong> untuk periode <?php echo htmlspecialchars($month_labels[$filter_month], ENT_QUOTES, 'UTF-8'); ?> <?php echo (int) $filter_year; ?>.</p></div></div>
    <div class="table-responsive-sm">
        <table class="table table-sm table-bordered table-hover" id="table4" style="width:100%;margin-top:10px;font-size:12px;">
            <thead class="thead-dark">
                <tr>
                    <th style="text-align:center;">Ranking</th>
                    <th>Kode Poli</th>
                    <th>Nama Poliklinik</th>
                    <th>Jumlah Pasien</th>
                    <th>Umum</th>
                    <th>BPJS</th>
                    <th>Asuransi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data_rows)): ?>
                    <tr><td colspan="7" style="text-align:center;">Tidak ada data untuk periode ini.</td></tr>
                <?php else: ?>
                    <?php $rank = 1; foreach ($data_rows as $row): ?>
                        <tr>
                            <td style="text-align:center;"><?php echo $rank++; ?></td>
                            <td><?php echo htmlspecialchars($row['kd_poli'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_poli'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="text-align:center;font-weight:bold;"><?php echo (int) $row['jumlah_pasien']; ?></td>
                            <td style="text-align:center;"><?php echo (int) $row['jumlah_umum']; ?></td>
                            <td style="text-align:center;"><?php echo (int) $row['jumlah_bpjs']; ?></td>
                            <td style="text-align:center;"><?php echo (int) $row['jumlah_asuransi']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($data_rows)): ?>
                <tfoot>
                    <tr style="background-color:#f8f9fa;font-weight:bold;">
                        <td colspan="3" style="text-align:right;">Total Top 10</td>
                        <td style="text-align:center;"><?php echo $total_pasien; ?></td>
                        <td style="text-align:center;"><?php echo $total_umum; ?></td>
                        <td style="text-align:center;"><?php echo $total_bpjs; ?></td>
                        <td style="text-align:center;"><?php echo $total_asuransi; ?></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</section>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    const poliLabels = <?php echo json_encode($chart_labels); ?>;
    const poliPatients = <?php echo json_encode($chart_pasien); ?>;
    const paymentLabels = <?php echo json_encode($payment_labels); ?>;
    const paymentValues = <?php echo json_encode($payment_values); ?>;
    const nf = new Intl.NumberFormat('id-ID');
    const poliCanvas = document.getElementById('chartTopPoli');

    if (poliCanvas && typeof Chart !== 'undefined') {
        const ctx = poliCanvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 480, 0);
        gradient.addColorStop(0, 'rgba(46,134,222,0.92)');
        gradient.addColorStop(1, 'rgba(46,134,222,0.24)');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: poliLabels,
                datasets: [{
                    label: 'Jumlah Pasien',
                    data: poliPatients,
                    backgroundColor: gradient,
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
                        labels: { usePointStyle: true, boxWidth: 10, color: '#496280' }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.dataset.label + ': ' + nf.format(context.raw || 0) + ' pasien';
                            }
                        }
                    }
                },
                scales: {
                    y: { grid: { display: false }, ticks: { color: '#4d6c95' } },
                    x: { beginAtZero: true, ticks: { color: '#2e86de' }, grid: { color: 'rgba(46,134,222,0.10)' } }
                }
            }
        });
    }

    const paymentCanvas = document.getElementById('chartPembayaranPoli');
    if (paymentCanvas && typeof Chart !== 'undefined') {
        new Chart(paymentCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: paymentLabels.length ? paymentLabels : ['Belum Ada Data'],
                datasets: [{
                    data: paymentValues.length ? paymentValues : [1],
                    backgroundColor: paymentValues.length ? ['#2e86de', '#27ae60', '#ff9f43'] : ['#dbe7f7'],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, boxWidth: 10, color: '#496280' }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.label + ': ' + nf.format(context.raw || 0) + ' pasien';
                            }
                        }
                    }
                }
            }
        });
    }
})();
</script>
