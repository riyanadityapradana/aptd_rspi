<?php
require_once dirname(dirname(dirname(__DIR__))) . '/config/koneksi.php';

function rl34_valid_month($value)
{
    $month = (int) $value;
    return ($month >= 1 && $month <= 12) ? $month : (int) date('n');
}

function rl34_valid_year($value)
{
    $year = (int) $value;
    return ($year >= 2000 && $year <= 2100) ? $year : (int) date('Y');
}

function rl34_month_names()
{
    return [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];
}

$bulan = rl34_valid_month(isset($_GET['bulan']) ? $_GET['bulan'] : date('n'));
$tahun = rl34_valid_year(isset($_GET['tahun']) ? $_GET['tahun'] : date('Y'));
$monthNames = rl34_month_names();

$startDate = sprintf('%04d-%02d-01', $tahun, $bulan);
$nextMonthDate = date('Y-m-d', strtotime($startDate . ' +1 month'));
$endDate = date('Y-m-d', strtotime($nextMonthDate . ' -1 day'));

$sql = "SELECT
            SUM(CASE WHEN p.tgl_daftar >= ? AND p.tgl_daftar < ? THEN 1 ELSE 0 END) AS pengunjung_baru,
            SUM(CASE WHEN p.tgl_daftar < ? THEN 1 ELSE 0 END) AS pengunjung_lama
        FROM (
            SELECT DISTINCT no_rkm_medis
            FROM reg_periksa
            WHERE tgl_registrasi >= ?
              AND tgl_registrasi < ?
        ) pengunjung
        INNER JOIN pasien p ON p.no_rkm_medis = pengunjung.no_rkm_medis";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('sssss', $startDate, $nextMonthDate, $startDate, $startDate, $nextMonthDate);
try {
    $stmt->execute();
} catch (mysqli_sql_exception $exception) {
    if ((int) $exception->getCode() !== 1615) {
        throw $exception;
    }

    $stmt->close();
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('sssss', $startDate, $nextMonthDate, $startDate, $startDate, $nextMonthDate);
    $stmt->execute();
}

$result = $stmt->get_result();
$summary = $result ? $result->fetch_assoc() : [];
$stmt->close();

$pengunjungBaru = isset($summary['pengunjung_baru']) ? (int) $summary['pengunjung_baru'] : 0;
$pengunjungLama = isset($summary['pengunjung_lama']) ? (int) $summary['pengunjung_lama'] : 0;
$rows = [
    ['jenis' => 'Pengunjung Baru', 'jumlah' => $pengunjungBaru],
    ['jenis' => 'Pengunjung Lama', 'jumlah' => $pengunjungLama],
];
$totalPengunjung = $pengunjungBaru + $pengunjungLama;
$dominant = $pengunjungBaru >= $pengunjungLama ? 'Pengunjung Baru' : 'Pengunjung Lama';
$chartLabels = array_map(function ($row) {
    return $row['jenis'];
}, $rows);
$chartValues = array_map(function ($row) {
    return $row['jumlah'];
}, $rows);
?>
<br>
<style>
.rl34-wrap{display:grid;gap:16px;margin-bottom:55px}.rl34-card,.rl34-panel{background:#fff;border:1px solid rgba(130,155,190,.22);box-shadow:0 16px 34px rgba(70,96,130,.10);border-radius:18px;padding:20px}.rl34-title{margin:0;color:#1e3d6a;font-size:28px;font-weight:850}.rl34-subtitle{margin:6px 0 0;color:#637b96;font-size:13px}.rl34-filter{display:flex;flex-wrap:wrap;align-items:end;gap:10px;margin-top:16px}.rl34-filter .form-control,.rl34-filter .btn{border-radius:10px}.rl34-note{font-size:12px;color:#5d7188;background:#f5f8fc;border:1px solid #dce8f5;border-radius:12px;padding:10px 12px}.rl34-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px}.rl34-mini{background:#fff;border:1px solid rgba(130,155,190,.22);box-shadow:0 12px 24px rgba(70,96,130,.08);border-radius:16px;padding:16px}.rl34-mini:nth-child(1){background:linear-gradient(135deg,#edf6ff,#fff)}.rl34-mini:nth-child(2){background:linear-gradient(135deg,#eefcf5,#fff)}.rl34-mini:nth-child(3){background:linear-gradient(135deg,#fff6ea,#fff)}.rl34-mini-k{font-size:12px;letter-spacing:.7px;text-transform:uppercase;color:#6f84a4}.rl34-mini-v{font-size:28px;font-weight:850;color:#1f3f6d;line-height:1.1;margin-top:6px}.rl34-mini-s{font-size:12px;color:#60789d;margin-top:6px}.rl34-grid{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr);gap:16px}.rl34-head{display:flex;justify-content:space-between;gap:12px;align-items:start;margin-bottom:12px}.rl34-h{margin:0;color:#1e3d6a;font-size:19px;font-weight:800}.rl34-d{margin:4px 0 0;color:#6f84a4;font-size:12px}.rl34-pill{display:inline-flex;padding:7px 11px;border-radius:999px;background:#eef5ff;color:#3468b0;font-size:12px;font-weight:700}.rl34-chart{position:relative;min-height:310px}.rl34-table{font-size:13px}.rl34-table th,.rl34-table td{vertical-align:middle}.rl34-table thead th{text-align:center}.rl34-table td:first-child,.rl34-table td:last-child{text-align:center}
@media(max-width:768px){.rl34-title{font-size:23px}.rl34-filter{align-items:stretch;flex-direction:column}}
@media(max-width:991px){.rl34-grid{grid-template-columns:1fr}}
</style>

<div class="rl34-wrap">
    <section class="rl34-card">
        <h3 class="rl34-title">RL 3.4 Rekapitulasi Pengunjung</h3>
        <p class="rl34-subtitle">Rekap bulanan pengunjung unik berdasarkan standar Juknis SIRS Revisi 6.3.</p>

        <form method="get" class="rl34-filter">
            <input type="hidden" name="page" value="rl34_pengunjung">
            <div class="form-group mb-0">
                <label for="bulan"><strong>Bulan</strong></label>
                <select name="bulan" id="bulan" class="form-control form-control-sm">
                    <?php foreach ($monthNames as $monthNumber => $monthName): ?>
                        <option value="<?php echo $monthNumber; ?>" <?php echo $bulan === $monthNumber ? 'selected' : ''; ?>><?php echo htmlspecialchars($monthName, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <label for="tahun"><strong>Tahun</strong></label>
                <select name="tahun" id="tahun" class="form-control form-control-sm">
                    <?php $currentYear = (int) date('Y'); for ($year = $currentYear - 6; $year <= $currentYear + 1; $year++): ?>
                        <option value="<?php echo $year; ?>" <?php echo $tahun === $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button>
        </form>
    </section>

    <div class="rl34-note">
        Periode <strong><?php echo htmlspecialchars($startDate . ' s.d. ' . $endDate, ENT_QUOTES, 'UTF-8'); ?></strong>.
        Perhitungan memakai <code>DISTINCT reg_periksa.no_rkm_medis</code>, sehingga pasien yang datang lebih dari satu kali dalam bulan yang sama tetap dihitung satu pengunjung.
    </div>

    <section class="rl34-cards">
        <div class="rl34-mini">
            <div class="rl34-mini-k">Total Pengunjung</div>
            <div class="rl34-mini-v"><?php echo number_format($totalPengunjung, 0, ',', '.'); ?></div>
            <div class="rl34-mini-s"><?php echo htmlspecialchars($monthNames[$bulan] . ' ' . $tahun, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="rl34-mini">
            <div class="rl34-mini-k">Pengunjung Baru</div>
            <div class="rl34-mini-v"><?php echo number_format($pengunjungBaru, 0, ',', '.'); ?></div>
            <div class="rl34-mini-s">Persentase <?php echo $totalPengunjung > 0 ? number_format(($pengunjungBaru / $totalPengunjung) * 100, 1, ',', '.') : '0'; ?>%</div>
        </div>
        <div class="rl34-mini">
            <div class="rl34-mini-k">Pengunjung Lama</div>
            <div class="rl34-mini-v"><?php echo number_format($pengunjungLama, 0, ',', '.'); ?></div>
            <div class="rl34-mini-s">Dominan: <?php echo htmlspecialchars($dominant, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
    </section>

    <section class="rl34-grid">
        <div class="rl34-panel">
            <div class="rl34-head">
                <div>
                    <h2 class="rl34-h">Grafik Pengunjung Baru vs Lama</h2>
                    <p class="rl34-d">Visualisasi komposisi pengunjung unik pada periode terpilih.</p>
                </div>
                <span class="rl34-pill"><?php echo htmlspecialchars($monthNames[$bulan] . ' ' . $tahun, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="rl34-chart"><canvas id="chartRl34Pengunjung"></canvas></div>
        </div>
        <div class="rl34-panel">
        <div class="table-responsive-sm">
            <table class="table table-sm table-bordered table-hover rl34-table" id="table4" style="width:100%;">
                <thead class="thead-dark">
                    <tr>
                        <th style="width:70px;">No</th>
                        <th>Jenis Pengunjung</th>
                        <th style="width:180px;">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['jenis'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="font-weight:bold;"><?php echo number_format($row['jumlah'], 0, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </div>
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    const canvas = document.getElementById('chartRl34Pengunjung');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }
    const ctx = canvas.getContext('2d');
    const gradientBlue = ctx.createLinearGradient(0, 0, 0, 320);
    gradientBlue.addColorStop(0, 'rgba(46,134,222,.90)');
    gradientBlue.addColorStop(1, 'rgba(46,134,222,.25)');
    const gradientGreen = ctx.createLinearGradient(0, 0, 0, 320);
    gradientGreen.addColorStop(0, 'rgba(39,174,96,.86)');
    gradientGreen.addColorStop(1, 'rgba(39,174,96,.25)');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Jumlah Pengunjung',
                data: <?php echo json_encode($chartValues); ?>,
                backgroundColor: [gradientBlue, gradientGreen],
                borderRadius: 12,
                borderSkipped: false,
                maxBarThickness: 72
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return 'Jumlah: ' + new Intl.NumberFormat('id-ID').format(context.raw || 0);
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#60789d' } },
                y: { beginAtZero: true, ticks: { color: '#4d6c95' }, grid: { color: 'rgba(113,138,180,.12)' } }
            }
        }
    });
})();
</script>
