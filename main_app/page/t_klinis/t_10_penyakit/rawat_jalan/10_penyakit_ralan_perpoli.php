<?php
require_once dirname(dirname(dirname(dirname(dirname(__DIR__))))) . '/config/koneksi.php';
require_once dirname(dirname(dirname(__DIR__))) . '/t_kunjungan/poli_specialty_helper.php';
$conn = $mysqli;

function penyakitRalanPerpoliValidDate($value, $fallback)
{
    $value = trim((string) $value);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)) {
        return $fallback;
    }

    return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : $fallback;
}

$specialtyGroups = aptd_poli_specialty_mapping($mysqli);

$tglAwal = penyakitRalanPerpoliValidDate(isset($_POST['tgl_awal']) ? $_POST['tgl_awal'] : '', date('Y-m-01'));
$tglAkhir = penyakitRalanPerpoliValidDate(isset($_POST['tgl_akhir']) ? $_POST['tgl_akhir'] : '', date('Y-m-d'));
$requestedPoli = isset($_POST['poli']) ? trim((string) $_POST['poli']) : '';
$filterPoli = aptd_poli_specialty_selected_group($specialtyGroups, $requestedPoli);
if ($tglAwal > $tglAkhir) { $tmp = $tglAwal; $tglAwal = $tglAkhir; $tglAkhir = $tmp; }

$codes = $specialtyGroups[$filterPoli];
$placeholders = implode(',', array_fill(0, count($codes), '?'));
$types = str_repeat('s', count($codes)) . 'ss';
$params = array_merge($codes, [$tglAwal, $tglAkhir]);
$sql = "SELECT selected.kd_penyakit, p.nm_penyakit, COUNT(*) AS jumlah_kasus
        FROM (
            SELECT
                r.no_rawat,
                SUBSTRING_INDEX(
                    GROUP_CONCAT(
                        d.kd_penyakit
                        ORDER BY
                            CASE WHEN d.prioritas > 0 THEN d.prioritas ELSE 999 END ASC,
                            d.kd_penyakit ASC
                    ),
                    ',',
                    1
                ) AS kd_penyakit
            FROM reg_periksa r
            INNER JOIN diagnosa_pasien d ON d.no_rawat = r.no_rawat
            INNER JOIN pasien ps ON ps.no_rkm_medis = r.no_rkm_medis
            WHERE r.status_lanjut = 'Ralan'
              AND d.status = 'Ralan'
              AND UPPER(d.kd_penyakit) NOT LIKE 'Z%'
              AND r.kd_poli IN ($placeholders)
              AND EXISTS (
                  SELECT 1
                  FROM poliklinik pl
                  WHERE pl.kd_poli = r.kd_poli
                    AND pl.status = '1'
              )
              AND LOWER(ps.nm_pasien) NOT LIKE '%test%'
              AND LOWER(ps.nm_pasien) NOT LIKE '%tes%'
              AND LOWER(ps.nm_pasien) NOT LIKE '%coba%'
              AND r.tgl_registrasi >= ?
              AND r.tgl_registrasi < DATE_ADD(?, INTERVAL 1 DAY)
            GROUP BY r.no_rawat
        ) selected
        INNER JOIN penyakit p ON p.kd_penyakit = selected.kd_penyakit
        GROUP BY selected.kd_penyakit, p.nm_penyakit
        ORDER BY jumlah_kasus DESC, selected.kd_penyakit ASC
        LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
try {
    $stmt->execute();
} catch (mysqli_sql_exception $exception) {
    if ((int) $exception->getCode() !== 1615) {
        throw $exception;
    }

    // MariaDB 10.1 kadang meminta prepared statement dibuat ulang setelah cache metadata berubah.
    $stmt->close();
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
}
$result = $stmt->get_result();
$rows = [];
$totalKasus = 0;
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
    $totalKasus += (int) $row['jumlah_kasus'];
}
$stmt->close();
$labels = array_map(function ($row) { return strlen($row['nm_penyakit']) > 28 ? substr($row['nm_penyakit'], 0, 28) . '...' : $row['nm_penyakit']; }, $rows);
$values = array_map(function ($row) { return (int) $row['jumlah_kasus']; }, $rows);
?>
<br>
<div class="row text-left"><div class="col"><h3 style="color:#666;margin-bottom:5px;">10 BESAR PENYAKIT RAWAT JALAN PER POLI</h3><hr style="height:1px;background-image:linear-gradient(to right,rgba(0,0,0,0),rgba(102,102,102,1),rgba(0,0,0,0));margin-top:0;margin-bottom:10px;"></div></div>
<form method="post" class="form-inline mb-3">
    <label for="poli" class="mr-2">Poli</label>
    <select name="poli" id="poli" class="form-control form-control-sm mr-2"><?php foreach ($specialtyGroups as $name => $codes): ?><option value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filterPoli === $name ? 'selected' : ''; ?>><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select>
    <label for="tgl_awal" class="mr-2">Tanggal Awal</label><input type="date" name="tgl_awal" id="tgl_awal" class="form-control form-control-sm mr-2" value="<?php echo htmlspecialchars($tglAwal, ENT_QUOTES, 'UTF-8'); ?>">
    <label for="tgl_akhir" class="mr-2">Tanggal Akhir</label><input type="date" name="tgl_akhir" id="tgl_akhir" class="form-control form-control-sm mr-2" value="<?php echo htmlspecialchars($tglAkhir, ENT_QUOTES, 'UTF-8'); ?>">
    <button type="submit" class="btn btn-primary btn-sm">Tampilkan Data</button>
</form>
<div class="row">
    <div class="col-md-6">
        <div class="table-responsive-sm"><table class="table table-sm table-bordered table-hover" id="table4" style="width:100%;font-size:12px;"><thead class="thead-dark"><tr><th>No</th><th>Kode ICD-10</th><th>Nama Penyakit</th><th>Jumlah</th><th>%</th></tr></thead><tbody><?php if (empty($rows)): ?><tr><td colspan="5" style="text-align:center;">Tidak ada data penyakit pada periode ini.</td></tr><?php else: $no = 1; foreach ($rows as $row): $persen = $totalKasus > 0 ? ((int) $row['jumlah_kasus'] / $totalKasus) * 100 : 0; ?><tr><td style="text-align:center;"><?php echo $no++; ?></td><td><?php echo htmlspecialchars($row['kd_penyakit'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($row['nm_penyakit'], ENT_QUOTES, 'UTF-8'); ?></td><td style="text-align:center;font-weight:bold;"><?php echo number_format((int) $row['jumlah_kasus'], 0, ',', '.'); ?></td><td style="text-align:center;"><?php echo number_format($persen, 1, ',', '.'); ?>%</td></tr><?php endforeach; endif; ?></tbody></table></div>
    </div>
    <div class="col-md-6"><div class="card"><div class="card-header"><strong>Grafik 10 Penyakit - <?php echo htmlspecialchars($filterPoli, ENT_QUOTES, 'UTF-8'); ?></strong></div><div class="card-body"><canvas id="chartPenyakitPerpoli" height="280"></canvas></div></div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const c = document.getElementById('chartPenyakitPerpoli');
    if (!c || typeof Chart === 'undefined') return;
    new Chart(c, { type: 'bar', data: { labels: <?php echo json_encode($labels); ?>, datasets: [{ label: 'Jumlah Kasus', data: <?php echo json_encode($values); ?>, backgroundColor: 'rgba(46,134,222,.72)', borderRadius: 6 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } } });
});
</script>
