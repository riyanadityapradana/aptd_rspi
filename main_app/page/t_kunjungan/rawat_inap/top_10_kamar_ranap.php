<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
$conn = $mysqli;

$filter_month = isset($_POST['month']) ? (int) $_POST['month'] : (int) date('n');
$filter_year = isset($_POST['year']) ? (int) $_POST['year'] : (int) date('Y');
if ($filter_month < 1 || $filter_month > 12) $filter_month = (int) date('n');
if ($filter_year < 2020 || $filter_year > ((int) date('Y') + 1)) $filter_year = (int) date('Y');

$start_date = sprintf('%04d-%02d-01', $filter_year, $filter_month);
$end_date = date('Y-m-t', strtotime($start_date));
$month_labels = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

$sql = "SELECT 
    k.kd_kamar,
    IFNULL(b.nm_bangsal, '-') AS nama_bangsal,
    COUNT(DISTINCT rp.no_rawat) AS jumlah_pasien,
    SUM(CASE WHEN rp.kd_pj='A09' THEN 1 ELSE 0 END) AS jumlah_umum,
    SUM(CASE WHEN rp.kd_pj='BPJ' THEN 1 ELSE 0 END) AS jumlah_bpjs,
    SUM(CASE WHEN rp.kd_pj='A92' THEN 1 ELSE 0 END) AS jumlah_asuransi,
    SUM(COALESCE(bill.total_tagihan, ki.ttl_biaya, 0)) AS total_biaya,
    AVG(COALESCE(bill.total_tagihan, ki.ttl_biaya, 0)) AS rata_biaya
FROM kamar_inap ki
INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
LEFT JOIN (
    SELECT no_rawat, MAX(totalbiaya) AS total_tagihan
    FROM billing
    WHERE status='Tagihan'
    GROUP BY no_rawat
) bill ON bill.no_rawat = rp.no_rawat
WHERE ki.tgl_masuk BETWEEN ? AND ?
  AND rp.status_lanjut='Ranap'
  AND rp.status_bayar='Sudah Bayar'
  AND (ki.stts_pulang IS NULL OR ki.stts_pulang<>'Pindah Kamar')
GROUP BY k.kd_kamar, b.nm_bangsal
ORDER BY jumlah_pasien DESC, total_biaya DESC
LIMIT 10";

$stmt = $conn->prepare($sql);
if (!$stmt) die('Query prepare gagal: ' . $conn->error);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$data_rows = [];
$total_pasien = 0;
$total_biaya = 0;
$total_umum = 0;
$total_bpjs = 0;
$total_asuransi = 0;
$top_room_name = '-';
$top_room_patients = 0;
$chart_labels = [];
$chart_pasien = [];
$chart_biaya = [];
$payment_distribution = ['Umum' => 0, 'BPJS' => 0, 'Asuransi' => 0];

while ($row = $result->fetch_assoc()) {
    $data_rows[] = $row;
    $total_pasien += (int) $row['jumlah_pasien'];
    $total_biaya += (float) $row['total_biaya'];
    $total_umum += (int) $row['jumlah_umum'];
    $total_bpjs += (int) $row['jumlah_bpjs'];
    $total_asuransi += (int) $row['jumlah_asuransi'];
    $chart_labels[] = $row['kd_kamar'];
    $chart_pasien[] = (int) $row['jumlah_pasien'];
    $chart_biaya[] = round((float) $row['total_biaya']);
    $payment_distribution['Umum'] += (int) $row['jumlah_umum'];
    $payment_distribution['BPJS'] += (int) $row['jumlah_bpjs'];
    $payment_distribution['Asuransi'] += (int) $row['jumlah_asuransi'];

    if ((int) $row['jumlah_pasien'] > $top_room_patients) {
        $top_room_patients = (int) $row['jumlah_pasien'];
        $top_room_name = $row['kd_kamar'] . ' / ' . $row['nama_bangsal'];
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
.top-wrap{display:grid;gap:18px}.top-hero,.top-panel,.top-card{background:#fff;border:1px solid rgba(120,155,220,.16);box-shadow:0 18px 36px rgba(74,101,145,.10);border-radius:22px}.top-hero{padding:24px;background:linear-gradient(135deg,#fff7ee,#fffdfa 45%,#e8f2ff)}.top-title{margin:0 0 8px;font-size:34px;font-weight:800;color:#21406c}.top-sub{margin:0;color:#587192;font-size:14px;max-width:760px}.top-filter{display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin-top:18px}.top-filter .form-control,.top-filter .btn{border-radius:12px}.top-filter .btn-primary{background:linear-gradient(135deg,#ff9f43,#e17000);border:none}.top-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px}.top-card{padding:18px}.top-card:nth-child(1){background:linear-gradient(135deg,#fff5ea,#fff)}.top-card:nth-child(2){background:linear-gradient(135deg,#edf6ff,#fff)}.top-card:nth-child(3){background:linear-gradient(135deg,#eefcf5,#fff)}.top-card:nth-child(4){background:linear-gradient(135deg,#f7f2ff,#fff)}.top-k{font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#6f84a4}.top-v{font-size:28px;font-weight:800;color:#1f3f6d;line-height:1.1}.top-s{margin-top:8px;font-size:12px;color:#60789d}.top-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:18px}.top-panel{padding:20px}.top-head{display:flex;justify-content:space-between;gap:12px;align-items:start;margin-bottom:14px}.top-h{margin:0;font-size:20px;font-weight:800;color:#1e3d6a}.top-d{margin:4px 0 0;color:#6f84a4;font-size:13px}.top-pill{display:inline-flex;padding:8px 12px;border-radius:999px;background:#fff1e4;color:#d67909;font-size:12px;font-weight:700}.top-chart{position:relative;min-height:340px}@media(max-width:991px){.top-grid{grid-template-columns:1fr}}@media(max-width:576px){.top-title{font-size:28px}.top-filter{flex-direction:column;align-items:stretch}}
</style>

<div class="top-wrap">
<section class="top-hero">
    <h1 class="top-title">Top 10 Kamar Pasien Tertinggi</h1>
    <p class="top-sub">Lihat kamar paling padat, beban biaya tertinggi, dan komposisi pembayaran pasien rawat inap yang sudah bayar dalam satu dashboard yang lebih visual.</p>
    <form method="post" class="top-filter">
        <div class="form-group mb-0">
            <label for="month"><strong>Bulan</strong></label>
            <select name="month" id="month" class="form-control form-control-sm">
                <?php foreach($month_labels as $n => $name): ?>
                    <option value="<?php echo $n; ?>" <?php echo ($filter_month === $n) ? 'selected' : ''; ?>><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0">
            <label for="year"><strong>Tahun</strong></label>
            <select name="year" id="year" class="form-control form-control-sm">
                <?php for($year = 2020; $year <= ((int) date('Y') + 1); $year++): ?>
                    <option value="<?php echo $year; ?>" <?php echo ($filter_year === $year) ? 'selected' : ''; ?>><?php echo $year; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button>
    </form>
</section>

<section class="top-cards">
    <div class="top-card"><div class="top-k">Total Pasien Top 10</div><div class="top-v"><?php echo number_format($total_pasien,0,',','.'); ?></div><div class="top-s">Akumulasi seluruh kamar dalam ranking</div></div>
    <div class="top-card"><div class="top-k">Total Biaya</div><div class="top-v"><?php echo number_format($total_biaya,0,',','.'); ?></div><div class="top-s">Sumber utama dari billing tagihan</div></div>
    <div class="top-card"><div class="top-k">Kamar Terpadat</div><div class="top-v"><?php echo $top_room_patients > 0 ? number_format($top_room_patients,0,',','.').' px' : '-'; ?></div><div class="top-s"><?php echo htmlspecialchars($top_room_name, ENT_QUOTES, 'UTF-8'); ?></div></div>
    <div class="top-card"><div class="top-k">Komposisi Pasien</div><div class="top-v"><?php echo number_format($total_bpjs,0,',','.'); ?> BPJS</div><div class="top-s"><?php echo number_format($total_umum,0,',','.').' Umum / '.number_format($total_asuransi,0,',','.').' Asuransi'; ?></div></div>
</section>

<section class="top-grid">
    <div class="top-panel">
        <div class="top-head">
            <div>
                <h2 class="top-h">Peringkat Kamar</h2>
                <p class="top-d">Batang horizontal menampilkan jumlah pasien, garis menunjukkan total biaya per kamar.</p>
            </div>
            <span class="top-pill"><?php echo htmlspecialchars($month_labels[$filter_month], ENT_QUOTES, 'UTF-8'); ?> <?php echo (int) $filter_year; ?></span>
        </div>
        <div class="top-chart"><canvas id="chartTopKamar"></canvas></div>
    </div>
    <div class="top-panel">
        <div class="top-head">
            <div>
                <h2 class="top-h">Distribusi Pembayaran</h2>
                <p class="top-d">Proporsi jenis pembiayaan dari akumulasi pasien top 10 kamar.</p>
            </div>
        </div>
        <div class="top-chart"><canvas id="chartPembayaranKamar"></canvas></div>
    </div>
</section>

<section class="top-panel">
    <div class="top-head">
        <div>
            <h2 class="top-h">Tabel Ranking Kamar</h2>
            <p class="top-d">Ranking kamar berdasarkan jumlah pasien rawat inap dengan status bayar <strong>Sudah Bayar</strong> untuk periode <?php echo htmlspecialchars($month_labels[$filter_month], ENT_QUOTES, 'UTF-8'); ?> <?php echo (int) $filter_year; ?>.</p>
        </div>
    </div>
    <div class="table-responsive-sm">
        <table class="table table-sm table-bordered table-hover" id="table4" style="width:100%;margin-top:10px;font-size:12px;">
            <thead class="thead-dark">
                <tr>
                    <th style="text-align:center;">Ranking</th>
                    <th>Kode Kamar</th>
                    <th>Bangsal</th>
                    <th>Jumlah Pasien</th>
                    <th>Umum</th>
                    <th>BPJS</th>
                    <th>Asuransi</th>
                    <th>Total Biaya</th>
                    <th>Rata-rata Biaya</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data_rows)): ?>
                    <tr><td colspan="9" style="text-align:center;">Tidak ada data untuk periode ini.</td></tr>
                <?php else: ?>
                    <?php $rank = 1; foreach($data_rows as $row): ?>
                        <tr>
                            <td style="text-align:center;"><?php echo $rank++; ?></td>
                            <td><?php echo htmlspecialchars($row['kd_kamar'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_bangsal'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="text-align:center;font-weight:bold;"><?php echo (int) $row['jumlah_pasien']; ?></td>
                            <td style="text-align:center;"><?php echo (int) $row['jumlah_umum']; ?></td>
                            <td style="text-align:center;"><?php echo (int) $row['jumlah_bpjs']; ?></td>
                            <td style="text-align:center;"><?php echo (int) $row['jumlah_asuransi']; ?></td>
                            <td style="text-align:right;"><?php echo number_format((float) $row['total_biaya'], 0, ',', '.'); ?></td>
                            <td style="text-align:right;"><?php echo number_format((float) $row['rata_biaya'], 0, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($data_rows)): ?>
                <tfoot>
                    <tr style="background-color:#f8f9fa;font-weight:bold;">
                        <td colspan="3" style="text-align:right;">Total Top 10</td>
                        <td style="text-align:center;"><?php echo $total_pasien; ?></td>
                        <td colspan="3"></td>
                        <td style="text-align:right;"><?php echo number_format($total_biaya, 0, ',', '.'); ?></td>
                        <td></td>
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
    const roomLabels = <?php echo json_encode($chart_labels); ?>;
    const roomPatients = <?php echo json_encode($chart_pasien); ?>;
    const roomCosts = <?php echo json_encode($chart_biaya); ?>;
    const paymentLabels = <?php echo json_encode($payment_labels); ?>;
    const paymentValues = <?php echo json_encode($payment_values); ?>;
    const nf = new Intl.NumberFormat('id-ID');

    const roomCanvas = document.getElementById('chartTopKamar');
    if (roomCanvas && typeof Chart !== 'undefined') {
        const ctx = roomCanvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 480, 0);
        gradient.addColorStop(0, 'rgba(255,159,67,0.95)');
        gradient.addColorStop(1, 'rgba(255,159,67,0.25)');

        new Chart(ctx, {
            data: {
                labels: roomLabels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Jumlah Pasien',
                        data: roomPatients,
                        backgroundColor: gradient,
                        borderRadius: 12,
                        borderSkipped: false,
                        xAxisID: 'x',
                        maxBarThickness: 24
                    },
                    {
                        type: 'line',
                        label: 'Total Biaya',
                        data: roomCosts,
                        borderColor: '#2e86de',
                        backgroundColor: 'rgba(46,134,222,0.20)',
                        pointBackgroundColor: '#2e86de',
                        pointBorderColor: '#ffffff',
                        pointRadius: 4,
                        borderWidth: 3,
                        tension: 0.35,
                        fill: false,
                        xAxisID: 'x1'
                    }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 10,
                            color: '#496280'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                if (context.dataset.label === 'Total Biaya') {
                                    return context.dataset.label + ': Rp ' + nf.format(context.raw || 0);
                                }
                                return context.dataset.label + ': ' + nf.format(context.raw || 0) + ' pasien';
                            }
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
                        position: 'bottom',
                        ticks: { color: '#d4790a' },
                        grid: { color: 'rgba(222,145,33,0.10)' }
                    },
                    x1: {
                        beginAtZero: true,
                        position: 'top',
                        grid: { drawOnChartArea: false },
                        ticks: {
                            color: '#2e86de',
                            callback: function (value) {
                                return 'Rp ' + nf.format(value);
                            }
                        }
                    }
                }
            }
        });
    }

    const paymentCanvas = document.getElementById('chartPembayaranKamar');
    if (paymentCanvas && typeof Chart !== 'undefined') {
        new Chart(paymentCanvas.getContext('2d'), {
            type: 'polarArea',
            data: {
                labels: paymentLabels.length ? paymentLabels : ['Belum Ada Data'],
                datasets: [{
                    data: paymentValues.length ? paymentValues : [1],
                    backgroundColor: paymentValues.length
                        ? ['rgba(255,159,67,0.86)', 'rgba(46,134,222,0.82)', 'rgba(39,174,96,0.82)']
                        : ['#dbe7f7'],
                    borderWidth: 0
                }]
            },
            options: {
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
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.label + ': ' + nf.format(context.raw || 0) + ' pasien';
                            }
                        }
                    }
                },
                scales: {
                    r: {
                        ticks: {
                            backdropColor: 'transparent',
                            color: '#6f84a4'
                        },
                        grid: {
                            color: 'rgba(113,138,180,0.12)'
                        }
                    }
                }
            }
        });
    }
})();
</script>