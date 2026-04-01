<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
$conn = $mysqli;

$defaultYear = (int) date('Y');
$filter_start = isset($_POST['tanggal_awal']) ? trim((string) $_POST['tanggal_awal']) : sprintf('%04d-01-01', $defaultYear);
$filter_end = isset($_POST['tanggal_akhir']) ? trim((string) $_POST['tanggal_akhir']) : sprintf('%04d-12-31', $defaultYear);

$start_dt = DateTime::createFromFormat('Y-m-d', $filter_start);
$end_dt = DateTime::createFromFormat('Y-m-d', $filter_end);

if (!$start_dt) {
    $start_dt = new DateTime(sprintf('%04d-01-01', $defaultYear));
}
if (!$end_dt) {
    $end_dt = new DateTime(sprintf('%04d-12-31', $defaultYear));
}
if ($start_dt > $end_dt) {
    $temp = $start_dt;
    $start_dt = $end_dt;
    $end_dt = $temp;
}

$filter_start = $start_dt->format('Y-m-d');
$filter_end = $end_dt->format('Y-m-d');

$sql = "SELECT 
    base.kategori_bayar,
    base.jenis_kelas,
    COUNT(DISTINCT base.no_rawat) AS total_pasien
FROM (
    SELECT
        ki.no_rawat,
        CASE
            WHEN rp.kd_pj = 'BPJ' THEN 'BPJS'
            WHEN rp.kd_pj = 'A09' THEN 'UMUM'
            WHEN rp.kd_pj = 'A92' THEN 'ASURANSI'
            ELSE 'LAIN-LAIN'
        END AS kategori_bayar,
        CASE
            WHEN UPPER(IFNULL(k.kd_bangsal, '')) LIKE '%ICU%' OR UPPER(IFNULL(k.kd_bangsal, '')) LIKE '%NICU%' OR UPPER(IFNULL(b.nm_bangsal, '')) LIKE '%ICU%' OR UPPER(IFNULL(b.nm_bangsal, '')) LIKE '%NICU%' THEN 'ICU NICU'
            WHEN UPPER(IFNULL(k.kd_bangsal, '')) LIKE '%ISO%' OR UPPER(IFNULL(b.nm_bangsal, '')) LIKE '%ISOLASI%' THEN 'ISOLASI'
            WHEN k.kelas = 'Kelas 1' THEN 'KELAS 1'
            WHEN k.kelas = 'Kelas 2' THEN 'KELAS 2'
            WHEN k.kelas = 'Kelas 3' THEN 'KELAS 3'
            WHEN k.kelas = 'Kelas VIP' OR k.kelas = 'Kelas Utama' THEN 'VIP'
            WHEN k.kelas = 'Kelas VVIP' THEN 'VVIP'
            ELSE UPPER(IFNULL(k.kelas, '-'))
        END AS jenis_kelas
    FROM kamar_inap ki
    INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
    LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
    INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
    WHERE ki.tgl_keluar BETWEEN ? AND ?
      AND ki.stts_pulang NOT IN ('Pindah Kamar', '-')
      AND rp.kd_pj IN ('BPJ', 'A09', 'A92')
) base
GROUP BY base.kategori_bayar, base.jenis_kelas
ORDER BY base.kategori_bayar ASC, base.jenis_kelas ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Query prepare gagal: ' . $conn->error);
}
$stmt->bind_param('ss', $filter_start, $filter_end);
$stmt->execute();
$result = $stmt->get_result();

$class_order = ['KELAS 1', 'KELAS 2', 'KELAS 3', 'VIP', 'VVIP', 'ICU NICU', 'ISOLASI'];
$payment_order = ['BPJS', 'UMUM', 'ASURANSI'];
$payment_colors = [
    'BPJS' => ['panel' => '#dcefcf', 'accent' => '#71b742', 'chart' => ['#4472c4', '#ed7d31', '#a5a5a5', '#ffc000', '#5b9bd5', '#70ad47', '#264478']],
    'UMUM' => ['panel' => '#fff0c9', 'accent' => '#ffc000', 'chart' => ['#4472c4', '#ed7d31', '#a5a5a5', '#ffc000', '#5b9bd5', '#70ad47', '#264478']],
    'ASURANSI' => ['panel' => '#ececec', 'accent' => '#a5a5a5', 'chart' => ['#4472c4', '#ed7d31', '#a5a5a5', '#ffc000', '#5b9bd5', '#70ad47', '#264478']],
];

$grouped = [];
$grand_total = 0;
foreach ($payment_order as $payment) {
    $grouped[$payment] = [
        'total' => 0,
        'classes' => array_fill_keys($class_order, 0),
    ];
}

while ($row = $result->fetch_assoc()) {
    $payment = strtoupper((string) $row['kategori_bayar']);
    $className = strtoupper(trim((string) $row['jenis_kelas']));
    $total = (int) $row['total_pasien'];

    if (!isset($grouped[$payment])) {
        $grouped[$payment] = [
            'total' => 0,
            'classes' => [],
        ];
        $payment_order[] = $payment;
        if (!isset($payment_colors[$payment])) {
            $payment_colors[$payment] = ['panel' => '#eef5ff', 'accent' => '#5b9bd5', 'chart' => ['#4472c4', '#ed7d31', '#a5a5a5', '#ffc000', '#5b9bd5', '#70ad47', '#264478']];
        }
    }

    if (!array_key_exists($className, $grouped[$payment]['classes'])) {
        $grouped[$payment]['classes'][$className] = 0;
        if (!in_array($className, $class_order, true)) {
            $class_order[] = $className;
        }
    }

    $grouped[$payment]['classes'][$className] += $total;
    $grouped[$payment]['total'] += $total;
    $grand_total += $total;
}
$stmt->close();

foreach ($payment_order as $payment) {
    foreach ($class_order as $className) {
        if (!array_key_exists($className, $grouped[$payment]['classes'])) {
            $grouped[$payment]['classes'][$className] = 0;
        }
    }
}

$top_payment = '-';
$top_payment_total = 0;
$top_class = '-';
$top_class_total = 0;
$class_totals = array_fill_keys($class_order, 0);

foreach ($payment_order as $payment) {
    if ($grouped[$payment]['total'] > $top_payment_total) {
        $top_payment_total = $grouped[$payment]['total'];
        $top_payment = $payment;
    }

    foreach ($grouped[$payment]['classes'] as $className => $total) {
        if (!isset($class_totals[$className])) {
            $class_totals[$className] = 0;
        }
        $class_totals[$className] += $total;
    }
}

foreach ($class_totals as $className => $total) {
    if ($total > $top_class_total) {
        $top_class_total = $total;
        $top_class = $className;
    }
}

$chart_payload = [];
foreach ($payment_order as $payment) {
    $labels = [];
    $values = [];
    foreach ($class_order as $className) {
        $value = isset($grouped[$payment]['classes'][$className]) ? (int) $grouped[$payment]['classes'][$className] : 0;
        if ($value > 0) {
            $labels[] = $className;
            $values[] = $value;
        }
    }

    $chart_payload[$payment] = [
        'labels' => $labels,
        'values' => $values,
        'colors' => isset($payment_colors[$payment]['chart']) ? $payment_colors[$payment]['chart'] : ['#4472c4', '#ed7d31', '#a5a5a5', '#ffc000', '#5b9bd5', '#70ad47', '#264478'],
    ];
}
?>
<br>
<style>
.perkelas-wrap{display:grid;gap:18px}.perkelas-hero,.perkelas-card,.perkelas-panel,.perkelas-cat{background:#fff;border:1px solid rgba(111,138,180,.16);box-shadow:0 18px 36px rgba(74,101,145,.10);border-radius:22px}.perkelas-hero{padding:24px;background:linear-gradient(135deg,#fefbf1,#edf5ff 54%,#f9fff7)}.perkelas-title{margin:0 0 8px;font-size:34px;font-weight:800;color:#1f3558}.perkelas-sub{margin:0;color:#60789d;font-size:14px;max-width:860px}.perkelas-filter{display:flex;flex-wrap:wrap;gap:12px;align-items:end;margin-top:18px}.perkelas-filter .form-control,.perkelas-filter .btn{border-radius:12px}.perkelas-filter .btn-primary{background:linear-gradient(135deg,#3487e3,#1f5fae);border:none}.perkelas-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}.perkelas-card{padding:18px}.perkelas-card:nth-child(1){background:linear-gradient(135deg,#edf6ff,#fff)}.perkelas-card:nth-child(2){background:linear-gradient(135deg,#eefcf5,#fff)}.perkelas-card:nth-child(3){background:linear-gradient(135deg,#fff6ea,#fff)}.perkelas-card:nth-child(4){background:linear-gradient(135deg,#f5f1ff,#fff)}.perkelas-k{font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#6f84a4}.perkelas-v{font-size:28px;font-weight:800;color:#1f3f6d;line-height:1.1;margin-top:8px}.perkelas-s{margin-top:8px;font-size:12px;color:#60789d}.perkelas-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px}.perkelas-cat{padding:18px 18px 14px}.perkelas-cat-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:14px}.perkelas-cat-title{margin:0;font-size:20px;font-weight:800;color:#263849}.perkelas-badge{display:inline-flex;align-items:center;justify-content:center;padding:8px 14px;border-radius:999px;font-size:12px;font-weight:700;color:#263849}.perkelas-chart{position:relative;min-height:260px;margin-bottom:14px}.perkelas-mini{width:100%;font-size:13px;margin-bottom:0}.perkelas-mini th,.perkelas-mini td{padding:6px 10px;border-top:1px solid rgba(255,255,255,.45)}.perkelas-mini thead th{text-align:center;background:rgba(255,255,255,.4)}.perkelas-mini tbody td:first-child{font-weight:600}.perkelas-panel{padding:20px}.perkelas-head{display:flex;justify-content:space-between;gap:12px;align-items:start;margin-bottom:14px}.perkelas-h{margin:0;font-size:20px;font-weight:800;color:#1e3d6a}.perkelas-d{margin:4px 0 0;color:#6f84a4;font-size:13px}.perkelas-pill{display:inline-flex;padding:8px 12px;border-radius:999px;background:#eef5ff;color:#3468b0;font-size:12px;font-weight:700}@media(max-width:576px){.perkelas-title{font-size:28px}.perkelas-filter{flex-direction:column;align-items:stretch}}
</style>
<div class="perkelas-wrap">
    <section class="perkelas-hero">
        <h1 class="perkelas-title">Tarikan Rawat Inap Per Jenis Kelas</h1>
        <p class="perkelas-sub">Dashboard ini menampilkan distribusi pasien rawat inap per kategori bayar dan jenis kelas sesuai periode tanggal keluar yang dipilih. Grafik dipisah per penjamin agar pola BPJS, Umum, dan Asuransi lebih mudah dibaca.</p>
        <form method="post" class="perkelas-filter">
            <div class="form-group mb-0">
                <label for="tanggal_awal"><strong>Tanggal Awal</strong></label>
                <input type="date" name="tanggal_awal" id="tanggal_awal" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_start, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group mb-0">
                <label for="tanggal_akhir"><strong>Tanggal Akhir</strong></label>
                <input type="date" name="tanggal_akhir" id="tanggal_akhir" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_end, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button>
        </form>
    </section>

    <section class="perkelas-cards">
        <div class="perkelas-card"><div class="perkelas-k">Total Pasien</div><div class="perkelas-v"><?php echo number_format($grand_total, 0, ',', '.'); ?></div><div class="perkelas-s">Akumulasi semua kategori bayar pada periode terpilih</div></div>
        <div class="perkelas-card"><div class="perkelas-k">Kategori Terbanyak</div><div class="perkelas-v"><?php echo htmlspecialchars($top_payment, ENT_QUOTES, 'UTF-8'); ?></div><div class="perkelas-s"><?php echo $top_payment_total > 0 ? number_format($top_payment_total, 0, ',', '.') . ' pasien' : 'Belum ada data'; ?></div></div>
        <div class="perkelas-card"><div class="perkelas-k">Kelas Terbanyak</div><div class="perkelas-v"><?php echo htmlspecialchars($top_class, ENT_QUOTES, 'UTF-8'); ?></div><div class="perkelas-s"><?php echo $top_class_total > 0 ? number_format($top_class_total, 0, ',', '.') . ' pasien' : 'Belum ada data'; ?></div></div>
        <div class="perkelas-card"><div class="perkelas-k">Rentang Data</div><div class="perkelas-v"><?php echo (int) $start_dt->diff($end_dt)->days + 1; ?> hari</div><div class="perkelas-s"><?php echo htmlspecialchars($filter_start, ENT_QUOTES, 'UTF-8'); ?> s.d. <?php echo htmlspecialchars($filter_end, ENT_QUOTES, 'UTF-8'); ?></div></div>
    </section>

    <section class="perkelas-grid">
        <?php foreach ($payment_order as $payment): ?>
            <?php
            $panelColor = isset($payment_colors[$payment]['panel']) ? $payment_colors[$payment]['panel'] : '#eef5ff';
            $accentColor = isset($payment_colors[$payment]['accent']) ? $payment_colors[$payment]['accent'] : '#5b9bd5';
            $paymentTotal = isset($grouped[$payment]['total']) ? (int) $grouped[$payment]['total'] : 0;
            ?>
            <article class="perkelas-cat" style="background:linear-gradient(180deg, <?php echo htmlspecialchars($panelColor, ENT_QUOTES, 'UTF-8'); ?> 0%, #ffffff 100%);">
                <div class="perkelas-cat-head">
                    <h2 class="perkelas-cat-title"><?php echo htmlspecialchars($payment, ENT_QUOTES, 'UTF-8'); ?></h2>
                    <span class="perkelas-badge" style="background:<?php echo htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8'); ?>;color:#ffffff;">
                        <?php echo number_format($paymentTotal, 0, ',', '.'); ?> pasien
                    </span>
                </div>
                <div class="perkelas-chart"><canvas id="chart_<?php echo strtolower(preg_replace('/[^a-z0-9]+/i', '_', $payment)); ?>"></canvas></div>
                <div class="table-responsive-sm">
                    <table class="table table-sm perkelas-mini">
                        <thead>
                            <tr>
                                <th><?php echo htmlspecialchars($payment, ENT_QUOTES, 'UTF-8'); ?></th>
                                <th>Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($class_order as $className): ?>
                                <?php $value = isset($grouped[$payment]['classes'][$className]) ? (int) $grouped[$payment]['classes'][$className] : 0; ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td style="text-align:right;">
                                        <?php
                                        $percent = $paymentTotal > 0 ? round(($value / $paymentTotal) * 100) : 0;
                                        echo number_format($value, 0, ',', '.');
                                        echo $value > 0 ? ' (' . $percent . '%)' : '';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="perkelas-panel">
        <div class="perkelas-head">
            <div>
                <h2 class="perkelas-h">Rekap Seluruh Kategori</h2>
                <p class="perkelas-d">Tabel ini memudahkan pengecekan silang jumlah pasien per jenis kelas antar kategori bayar dalam satu tampilan.</p>
            </div>
            <span class="perkelas-pill"><?php echo htmlspecialchars($filter_start, ENT_QUOTES, 'UTF-8'); ?> s.d. <?php echo htmlspecialchars($filter_end, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="table-responsive-sm">
            <table class="table table-sm table-bordered table-hover" id="table4" style="width:100%;margin-top:10px;font-size:12px;">
                <thead class="thead-dark">
                    <tr>
                        <th style="text-align:center;">No.</th>
                        <th>Jenis Kelas</th>
                        <?php foreach ($payment_order as $payment): ?>
                            <th><?php echo htmlspecialchars($payment, ENT_QUOTES, 'UTF-8'); ?></th>
                        <?php endforeach; ?>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($grand_total <= 0): ?>
                        <tr><td colspan="<?php echo count($payment_order) + 3; ?>" style="text-align:center;">Tidak ada data pada periode ini.</td></tr>
                    <?php else: ?>
                        <?php $rowNo = 1; ?>
                        <?php foreach ($class_order as $className): ?>
                            <?php
                            $rowTotal = 0;
                            foreach ($payment_order as $payment) {
                                $rowTotal += isset($grouped[$payment]['classes'][$className]) ? (int) $grouped[$payment]['classes'][$className] : 0;
                            }
                            ?>
                            <tr>
                                <td style="text-align:center;"><?php echo $rowNo++; ?></td>
                                <td><?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?></td>
                                <?php foreach ($payment_order as $payment): ?>
                                    <td style="text-align:center;"><?php echo number_format((int) $grouped[$payment]['classes'][$className], 0, ',', '.'); ?></td>
                                <?php endforeach; ?>
                                <td style="text-align:center;font-weight:bold;"><?php echo number_format($rowTotal, 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if ($grand_total > 0): ?>
                    <tfoot>
                        <tr style="background-color:#f8f9fa;font-weight:bold;">
                            <td colspan="2" style="text-align:right;">Total</td>
                            <?php foreach ($payment_order as $payment): ?>
                                <td style="text-align:center;"><?php echo number_format((int) $grouped[$payment]['total'], 0, ',', '.'); ?></td>
                            <?php endforeach; ?>
                            <td style="text-align:center;"><?php echo number_format($grand_total, 0, ',', '.'); ?></td>
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
    const chartData = <?php echo json_encode($chart_payload); ?>;
    const nf = new Intl.NumberFormat('id-ID');

    Object.keys(chartData).forEach(function (payment) {
        const safeId = 'chart_' + payment.toLowerCase().replace(/[^a-z0-9]+/g, '_');
        const canvas = document.getElementById(safeId);
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        const payload = chartData[payment];
        const labels = payload.labels && payload.labels.length ? payload.labels : ['Belum Ada Data'];
        const values = payload.values && payload.values.length ? payload.values : [1];
        const colors = payload.values && payload.values.length ? payload.colors.slice(0, values.length) : ['#dbe7f7'];

        new Chart(canvas.getContext('2d'), {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 10,
                            usePointStyle: true,
                            color: '#496280'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const total = values.reduce(function (sum, value) { return sum + value; }, 0);
                                const raw = context.raw || 0;
                                const percent = total > 0 ? Math.round((raw / total) * 100) : 0;
                                return context.label + ': ' + nf.format(raw) + ' pasien (' + percent + '%)';
                            }
                        }
                    }
                }
            }
        });
    });
})();
</script>
