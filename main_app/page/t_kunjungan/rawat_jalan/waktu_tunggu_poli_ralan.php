<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
$conn = $mysqli;

$filter_month = isset($_POST['month']) ? (int) $_POST['month'] : (int) date('n');
$filter_year = isset($_POST['year']) ? (int) $_POST['year'] : (int) date('Y');

if ($filter_month < 1 || $filter_month > 12) {
    $filter_month = (int) date('n');
}

$currentYear = (int) date('Y');
if ($filter_year < 2020 || $filter_year > ($currentYear + 1)) {
    $filter_year = $currentYear;
}

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];

$rows = [];
$totalRows = 0;
$sumMinutes = 0;
$avgWait = '00:00:00';
$maxWait = '00:00:00';

$sql = "SELECT
            hasil.no_rawat,
            hasil.nm_poli,
            hasil.nm_dokter,
            hasil.task_id_4,
            hasil.task_id_5,
            TIMEDIFF(hasil.task_id_5, hasil.task_id_4) AS waktu_tunggu_poli
        FROM (
            SELECT
                pr.no_rawat,
                pl.nm_poli,
                d_reg.nm_dokter,
                MIN(CASE WHEN d_soap.kd_dokter IS NULL THEN pr.jam_rawat END) AS task_id_4,
                MIN(CASE WHEN d_soap.kd_dokter IS NOT NULL THEN pr.jam_rawat END) AS task_id_5
            FROM pemeriksaan_ralan pr
            INNER JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
            INNER JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli
            INNER JOIN dokter d_reg ON rp.kd_dokter = d_reg.kd_dokter
            LEFT JOIN dokter d_soap ON pr.nip = d_soap.kd_dokter
            WHERE MONTH(pr.tgl_perawatan) = ?
              AND YEAR(pr.tgl_perawatan) = ?
              AND rp.kd_pj = 'BPJ'
            GROUP BY pr.no_rawat, pl.nm_poli, d_reg.nm_dokter
        ) AS hasil
        WHERE hasil.task_id_4 IS NOT NULL
          AND hasil.task_id_5 IS NOT NULL
        ORDER BY hasil.nm_poli ASC, hasil.task_id_4 ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Query prepare gagal: ' . $conn->error);
}

$stmt->bind_param('ii', $filter_month, $filter_year);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
    $totalRows++;

    $waitTime = isset($row['waktu_tunggu_poli']) ? $row['waktu_tunggu_poli'] : '00:00:00';
    if ($waitTime > $maxWait) {
        $maxWait = $waitTime;
    }

    $timeParts = explode(':', $waitTime);
    if (count($timeParts) === 3) {
        $sumMinutes += (((int) $timeParts[0]) * 60) + (int) $timeParts[1];
    }
}

$stmt->close();

if ($totalRows > 0) {
    $averageMinutes = (int) floor($sumMinutes / $totalRows);
    $avgHours = (int) floor($averageMinutes / 60);
    $avgMinutesOnly = $averageMinutes % 60;
    $avgWait = sprintf('%02d:%02d:00', $avgHours, $avgMinutesOnly);
}
?>
<br>
<style>
.wtp-wrap{display:grid;gap:18px}.wtp-hero,.wtp-card,.wtp-panel{background:#fff;border:1px solid rgba(120,155,220,.16);box-shadow:0 18px 36px rgba(74,101,145,.10);border-radius:22px}.wtp-hero{padding:24px;background:linear-gradient(135deg,#eef7ff,#ffffff 46%,#eefcf5)}.wtp-title{margin:0 0 8px;font-size:32px;font-weight:800;color:#21406c}.wtp-sub{margin:0;color:#587192;font-size:14px;max-width:860px}.wtp-filter{display:flex;flex-wrap:wrap;gap:12px;align-items:end;margin-top:18px}.wtp-filter .form-control,.wtp-filter .btn{border-radius:12px}.wtp-filter .btn-primary{background:linear-gradient(135deg,#2e86de,#1f5fae);border:none}.wtp-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px}.wtp-card{padding:18px}.wtp-card:nth-child(1){background:linear-gradient(135deg,#edf6ff,#fff)}.wtp-card:nth-child(2){background:linear-gradient(135deg,#eefcf5,#fff)}.wtp-card:nth-child(3){background:linear-gradient(135deg,#fff6ea,#fff)}.wtp-card:nth-child(4){background:linear-gradient(135deg,#f5f1ff,#fff)}.wtp-k{font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#6f84a4}.wtp-v{font-size:28px;font-weight:800;color:#1f3f6d;line-height:1.1}.wtp-s{margin-top:8px;font-size:12px;color:#60789d}.wtp-panel{padding:20px}.wtp-head{display:flex;justify-content:space-between;gap:12px;align-items:start;margin-bottom:14px}.wtp-h{margin:0;font-size:20px;font-weight:800;color:#1e3d6a}.wtp-d{margin:4px 0 0;color:#6f84a4;font-size:13px}.wtp-pill{display:inline-flex;padding:8px 12px;border-radius:999px;background:#eaf4ff;color:#2d6ab0;font-size:12px;font-weight:700}.wtp-empty{padding:24px;text-align:center;color:#6c84a8}@media(max-width:576px){.wtp-title{font-size:28px}.wtp-filter{flex-direction:column;align-items:stretch}}
</style>

<div class="wtp-wrap">
    <section class="wtp-hero">
        <h1 class="wtp-title">Waktu Tunggu Poli BPJS Rawat Jalan</h1>
        <p class="wtp-sub">Laporan ini menampilkan selisih waktu antara task perawat (task_id_4) dan task dokter (task_id_5) pada pemeriksaan rawat jalan pasien BPJS.</p>

        <form method="post" class="wtp-filter">
            <div class="form-group mb-0">
                <label for="month"><strong>Bulan</strong></label>
                <select name="month" id="month" class="form-control form-control-sm">
                    <?php foreach ($months as $monthNumber => $monthLabel): ?>
                        <option value="<?php echo $monthNumber; ?>" <?php echo $filter_month === $monthNumber ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <label for="year"><strong>Tahun</strong></label>
                <select name="year" id="year" class="form-control form-control-sm">
                    <?php for ($year = 2020; $year <= ($currentYear + 1); $year++): ?>
                        <option value="<?php echo $year; ?>" <?php echo $filter_year === $year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <button type="submit" class="btn btn-primary btn-sm">Tampilkan Data</button>
            </div>
        </form>
    </section>

    <section class="wtp-cards">
        <div class="wtp-card">
            <div class="wtp-k">Total Kunjungan</div>
            <div class="wtp-v"><?php echo number_format($totalRows, 0, ',', '.'); ?></div>
            <div class="wtp-s">Data dengan task 4 dan task 5 terisi.</div>
        </div>
        <div class="wtp-card">
            <div class="wtp-k">Rata-rata Tunggu</div>
            <div class="wtp-v"><?php echo htmlspecialchars($avgWait, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="wtp-s">Rata-rata selisih antara perawat dan dokter.</div>
        </div>
        <div class="wtp-card">
            <div class="wtp-k">Waktu Tunggu Terlama</div>
            <div class="wtp-v"><?php echo htmlspecialchars($maxWait, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="wtp-s">Selisih tertinggi pada periode terpilih.</div>
        </div>
        <div class="wtp-card">
            <div class="wtp-k">Periode</div>
            <div class="wtp-v"><?php echo htmlspecialchars($months[$filter_month], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="wtp-s">Tahun <?php echo htmlspecialchars((string) $filter_year, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
    </section>

    <section class="wtp-panel">
        <div class="wtp-head">
            <div>
                <h2 class="wtp-h">Detail Waktu Tunggu Poli</h2>
                <p class="wtp-d">Urutan data ditampilkan berdasarkan poliklinik dan jam task perawat paling awal.</p>
            </div>
            <span class="wtp-pill">Penjamin BPJS</span>
        </div>

        <?php if (empty($rows)): ?>
            <div class="wtp-empty">Belum ada data yang memenuhi filter bulan dan tahun ini.</div>
        <?php else: ?>
            <div class="table-responsive-sm">
                <table class="table table-sm table-bordered table-hover" id="table4" style="width:100%;font-size:12px;">
                    <thead class="thead-dark">
                        <tr>
                            <th style="text-align:center;">No.</th>
                            <th>No. Rawat</th>
                            <th>Poliklinik</th>
                            <th>Dokter Registrasi</th>
                            <th style="text-align:center;">Task ID 4</th>
                            <th style="text-align:center;">Task ID 5</th>
                            <th style="text-align:center;">Waktu Tunggu Poli</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($rows as $row): ?>
                            <tr>
                                <td style="text-align:center;"><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($row['no_rawat'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($row['nm_poli'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($row['nm_dokter'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="text-align:center;"><?php echo htmlspecialchars($row['task_id_4'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="text-align:center;"><?php echo htmlspecialchars($row['task_id_5'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="text-align:center;font-weight:700;background:#fff8de;"><?php echo htmlspecialchars($row['waktu_tunggu_poli'], ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
