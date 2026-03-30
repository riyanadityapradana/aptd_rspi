<?php
require_once dirname(dirname(dirname(__DIR__))) . '/config/koneksi.php';
$conn = $mysqli;

$mode = isset($aptd_mode) ? $aptd_mode : 'ralan_non_bedah';
$pageTitle = isset($aptd_title) ? $aptd_title : 'Data Pasien Berdasarkan Kode Penyakit';
$pageSubtitle = isset($aptd_subtitle) ? $aptd_subtitle : 'Tarikan data pasien berdasarkan kode penyakit.';

$tgl_awal = isset($_POST['tgl_awal']) && $_POST['tgl_awal'] !== '' ? $_POST['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_POST['tgl_akhir']) && $_POST['tgl_akhir'] !== '' ? $_POST['tgl_akhir'] : date('Y-m-d');
$kode_penyakit = isset($_POST['kode_penyakit']) ? strtoupper(trim($_POST['kode_penyakit'])) : '';

$isRanap = strpos($mode, 'ranap') === 0;
$isBedah = in_array($mode, ['ralan_bedah', 'ranap_bedah'], true);

$serviceLabel = $isRanap ? 'Rawat Inap' : 'Rawat Jalan';
$categoryLabel = $isBedah ? 'Bedah' : 'Non Bedah';

$rows = [];
$total_pasien = 0;
$total_laki = 0;
$total_perempuan = 0;
$unit_summary = [];
$chart_labels = [];
$chart_values = [];
$error_message = '';
$selectedDiseaseName = '-';

if ($kode_penyakit !== '') {
    if ($isRanap) {
        $sql = "SELECT DISTINCT
                    rp.no_rawat,
                    rp.no_rkm_medis,
                    ps.nm_pasien,
                    ps.jk,
                    dp.kd_penyakit,
                    py.nm_penyakit,
                    IFNULL(pl.nm_poli, '-') AS unit_layanan,
                    IFNULL(ki.daftar_kamar, '-') AS lokasi,
                    ki.tgl_masuk_awal AS tgl_layanan,
                    ki.tgl_keluar_akhir AS tgl_keluar,
                    ki.lama_dirawat
                FROM diagnosa_pasien dp
                INNER JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat
                INNER JOIN pasien ps ON rp.no_rkm_medis = ps.no_rkm_medis
                INNER JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit
                LEFT JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli
                INNER JOIN (
                    SELECT 
                        no_rawat,
                        GROUP_CONCAT(DISTINCT kd_kamar ORDER BY kd_kamar SEPARATOR ', ') AS daftar_kamar,
                        MIN(tgl_masuk) AS tgl_masuk_awal,
                        MAX(tgl_keluar) AS tgl_keluar_akhir,
                        MAX(IFNULL(lama, 0)) AS lama_dirawat
                    FROM kamar_inap
                    GROUP BY no_rawat
                ) ki ON rp.no_rawat = ki.no_rawat
                WHERE rp.status_lanjut = 'Ranap'
                  AND dp.kd_penyakit LIKE CONCAT(?, '%')
                  AND dp.prioritas = '1'
                  AND ki.tgl_masuk_awal BETWEEN ? AND ?
                  AND LOWER(ps.nm_pasien) NOT LIKE '%test%'
                  AND LOWER(ps.nm_pasien) NOT LIKE '%tes%'
                  AND LOWER(ps.nm_pasien) NOT LIKE '%coba%'";

        if ($isBedah) {
            $sql .= " AND LOWER(IFNULL(pl.nm_poli, '')) LIKE '%bedah%'";
        } else {
            $sql .= " AND LOWER(IFNULL(pl.nm_poli, '')) NOT LIKE '%bedah%'";
        }

        $sql .= " ORDER BY ki.tgl_masuk_awal DESC, ps.nm_pasien ASC";
    } else {
        $sql = "SELECT 
                    rp.no_rawat,
                    rp.no_rkm_medis,
                    ps.nm_pasien,
                    ps.jk,
                    dp.kd_penyakit,
                    py.nm_penyakit,
                    IFNULL(pl.nm_poli, '-') AS unit_layanan,
                    DATE(rp.tgl_registrasi) AS tgl_layanan
                FROM diagnosa_pasien dp
                INNER JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat
                INNER JOIN pasien ps ON rp.no_rkm_medis = ps.no_rkm_medis
                INNER JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit
                LEFT JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli
                WHERE rp.status_lanjut = 'Ralan'
                  AND dp.kd_penyakit LIKE CONCAT(?, '%')
                  AND DATE(rp.tgl_registrasi) BETWEEN ? AND ?
                  AND LOWER(ps.nm_pasien) NOT LIKE '%test%'
                  AND LOWER(ps.nm_pasien) NOT LIKE '%tes%'
                  AND LOWER(ps.nm_pasien) NOT LIKE '%coba%'";

        if ($isBedah) {
            $sql .= " AND LOWER(IFNULL(pl.nm_poli, '')) LIKE '%bedah%'";
        } else {
            $sql .= " AND LOWER(IFNULL(pl.nm_poli, '')) NOT LIKE '%bedah%'";
        }

        $sql .= " ORDER BY rp.tgl_registrasi DESC, ps.nm_pasien ASC";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('sss', $kode_penyakit, $tgl_awal, $tgl_akhir);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
            $selectedDiseaseName = $row['nm_penyakit'];
            $total_pasien++;

            if ($row['jk'] === 'L') {
                $total_laki++;
            } else {
                $total_perempuan++;
            }

            $unitKey = $isRanap ? $row['lokasi'] : $row['unit_layanan'];
            if (!isset($unit_summary[$unitKey])) {
                $unit_summary[$unitKey] = 0;
            }
            $unit_summary[$unitKey]++;
        }
        $stmt->close();
    } else {
        $error_message = 'Query prepare gagal: ' . $conn->error;
    }
}

arsort($unit_summary);
$chart_labels = array_slice(array_keys($unit_summary), 0, 8);
$chart_values = array_slice(array_values($unit_summary), 0, 8);
?>
<br>
<style>
.kode-wrap{display:grid;gap:18px}.kode-hero,.kode-card,.kode-panel{background:#fff;border:1px solid rgba(120,155,220,.16);box-shadow:0 18px 36px rgba(74,101,145,.10);border-radius:22px}.kode-hero{padding:24px;background:linear-gradient(135deg,#eef7ff,#ffffff 46%,#f3fbf4)}.kode-title{margin:0 0 8px;font-size:34px;font-weight:800;color:#21406c}.kode-sub{margin:0;color:#587192;font-size:14px;max-width:780px}.kode-filter{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;align-items:end;margin-top:18px}.kode-filter .form-control,.kode-filter .btn{border-radius:12px}.kode-filter .btn-primary{background:linear-gradient(135deg,#2e86de,#1f5fae);border:none;height:38px}.kode-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px}.kode-card{padding:18px}.kode-card:nth-child(1){background:linear-gradient(135deg,#edf6ff,#fff)}.kode-card:nth-child(2){background:linear-gradient(135deg,#eefcf5,#fff)}.kode-card:nth-child(3){background:linear-gradient(135deg,#fff6ea,#fff)}.kode-card:nth-child(4){background:linear-gradient(135deg,#f5f1ff,#fff)}.kode-k{font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#6f84a4}.kode-v{font-size:28px;font-weight:800;color:#1f3f6d;line-height:1.1}.kode-s{margin-top:8px;font-size:12px;color:#60789d}.kode-grid{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(280px,1fr);gap:18px}.kode-panel{padding:20px}.kode-head{display:flex;justify-content:space-between;gap:12px;align-items:start;margin-bottom:14px}.kode-h{margin:0;font-size:20px;font-weight:800;color:#1e3d6a}.kode-d{margin:4px 0 0;color:#6f84a4;font-size:13px}.kode-pill{display:inline-flex;padding:8px 12px;border-radius:999px;background:#eaf4ff;color:#2d6ab0;font-size:12px;font-weight:700}.kode-chart{position:relative;min-height:320px}.kode-note{padding:14px 16px;border-radius:16px;background:#fff8e8;border:1px solid #f5db9a;color:#8a6816}.kode-empty{padding:26px;text-align:center;color:#6682a7}@media(max-width:991px){.kode-grid{grid-template-columns:1fr}.kode-filter{grid-template-columns:1fr 1fr}}@media(max-width:576px){.kode-title{font-size:28px}.kode-filter{grid-template-columns:1fr}}
</style>
<div class="kode-wrap">
    <section class="kode-hero">
        <h1 class="kode-title"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="kode-sub"><?php echo htmlspecialchars($pageSubtitle, ENT_QUOTES, 'UTF-8'); ?></p>
        <form method="post" class="kode-filter">
            <div class="form-group mb-0">
                <label for="tgl_awal"><strong>Tanggal Awal</strong></label>
                <input type="date" class="form-control form-control-sm" id="tgl_awal" name="tgl_awal" value="<?php echo htmlspecialchars($tgl_awal, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group mb-0">
                <label for="tgl_akhir"><strong>Tanggal Akhir</strong></label>
                <input type="date" class="form-control form-control-sm" id="tgl_akhir" name="tgl_akhir" value="<?php echo htmlspecialchars($tgl_akhir, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group mb-0">
                <label for="kode_penyakit"><strong>Kode Penyakit</strong></label>
                <input type="text" class="form-control form-control-sm" id="kode_penyakit" name="kode_penyakit" value="<?php echo htmlspecialchars($kode_penyakit, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Contoh: A09 atau I10">
            </div>
            <div class="form-group mb-0">
                <button type="submit" class="btn btn-primary btn-sm btn-block">Tampilkan Data</button>
            </div>
        </form>
    </section>

    <?php if ($error_message !== ''): ?>
        <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="kode-cards">
        <div class="kode-card"><div class="kode-k">Kode Penyakit</div><div class="kode-v"><?php echo $kode_penyakit !== '' ? htmlspecialchars($kode_penyakit, ENT_QUOTES, 'UTF-8') : '-'; ?></div><div class="kode-s"><?php echo htmlspecialchars($selectedDiseaseName, ENT_QUOTES, 'UTF-8'); ?></div></div>
        <div class="kode-card"><div class="kode-k">Total Pasien</div><div class="kode-v"><?php echo number_format($total_pasien, 0, ',', '.'); ?></div><div class="kode-s"><?php echo htmlspecialchars($serviceLabel . ' / ' . $categoryLabel, ENT_QUOTES, 'UTF-8'); ?></div></div>
        <div class="kode-card"><div class="kode-k">Laki-laki</div><div class="kode-v"><?php echo number_format($total_laki, 0, ',', '.'); ?></div><div class="kode-s">Perempuan: <?php echo number_format($total_perempuan, 0, ',', '.'); ?></div></div>
        <div class="kode-card"><div class="kode-k">Unit Dominan</div><div class="kode-v"><?php echo !empty($chart_values) ? number_format($chart_values[0], 0, ',', '.') . ' px' : '-'; ?></div><div class="kode-s"><?php echo !empty($chart_labels) ? htmlspecialchars($chart_labels[0], ENT_QUOTES, 'UTF-8') : 'Belum ada data'; ?></div></div>
    </section>

    <section class="kode-grid">
        <div class="kode-panel">
            <div class="kode-head"><div><h2 class="kode-h">Sebaran <?php echo $isRanap ? 'Kamar' : 'Poliklinik'; ?></h2><p class="kode-d">Ringkasan pasien untuk kode penyakit yang dipilih berdasarkan <?php echo $isRanap ? 'kamar rawat inap' : 'poliklinik rawat jalan'; ?>.</p></div><span class="kode-pill"><?php echo htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="kode-chart"><canvas id="chartKodePenyakit"></canvas></div>
        </div>
        <div class="kode-panel">
            <div class="kode-head"><div><h2 class="kode-h">Petunjuk</h2><p class="kode-d">Gunakan kode ICD yang sesuai untuk memanggil data pasien.</p></div></div>
            <div class="kode-note">
                <?php if ($kode_penyakit === ''): ?>
                    Masukkan kode penyakit terlebih dahulu, lalu klik <strong>Tampilkan Data</strong> untuk menampilkan daftar pasien.
                <?php elseif (empty($rows)): ?>
                    Tidak ada pasien yang cocok dengan filter tanggal dan kode penyakit ini.
                <?php else: ?>
                    Menampilkan <strong><?php echo number_format($total_pasien, 0, ',', '.'); ?></strong> pasien untuk kode penyakit <strong><?php echo htmlspecialchars($kode_penyakit, ENT_QUOTES, 'UTF-8'); ?></strong> pada layanan <strong><?php echo htmlspecialchars($serviceLabel, ENT_QUOTES, 'UTF-8'); ?></strong> kategori <strong><?php echo htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8'); ?></strong>.
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="kode-panel">
        <div class="kode-head"><div><h2 class="kode-h">Daftar Pasien</h2><p class="kode-d">Hasil tarikan pasien berdasarkan kode penyakit untuk periode <?php echo date('d M Y', strtotime($tgl_awal)); ?> s.d. <?php echo date('d M Y', strtotime($tgl_akhir)); ?>.</p></div></div>
        <?php if ($kode_penyakit === ''): ?>
            <div class="kode-empty">Masukkan kode penyakit untuk mulai menampilkan data pasien.</div>
        <?php else: ?>
            <div class="table-responsive-sm">
                <table class="table table-sm table-bordered table-hover" id="table4" style="width:100%;font-size:12px;">
                    <thead class="thead-dark">
                        <tr>
                            <th style="text-align:center;">No</th>
                            <th>No Rawat</th>
                            <th>No RM</th>
                            <th>Nama Pasien</th>
                            <th>JK</th>
                            <th><?php echo $isRanap ? 'Tanggal Masuk' : 'Tanggal Registrasi'; ?></th>
                            <th><?php echo $isRanap ? 'Kamar' : 'Poliklinik'; ?></th>
                            <?php if ($isRanap): ?>
                                <th>Tanggal Keluar</th>
                                <th>Lama Dirawat</th>
                            <?php endif; ?>
                            <th>Kode Penyakit</th>
                            <th>Nama Penyakit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="<?php echo $isRanap ? '10' : '8'; ?>" style="text-align:center;">Tidak ada data pasien untuk filter ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($rows as $row): ?>
                                <tr>
                                    <td style="text-align:center;"><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($row['no_rawat'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($row['no_rkm_medis'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($row['nm_pasien'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td style="text-align:center;"><?php echo htmlspecialchars($row['jk'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($row['tgl_layanan'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($isRanap ? $row['lokasi'] : $row['unit_layanan'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <?php if ($isRanap): ?>
                                        <td><?php echo !empty($row['tgl_keluar']) ? htmlspecialchars($row['tgl_keluar'], ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                        <td style="text-align:center;"><?php echo number_format((float) $row['lama_dirawat'], 0, ',', '.'); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($row['kd_penyakit'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($row['nm_penyakit'], ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    const labels = <?php echo json_encode($chart_labels); ?>;
    const values = <?php echo json_encode($chart_values); ?>;
    const target = document.getElementById('chartKodePenyakit');
    if (!target || typeof Chart === 'undefined') {
        return;
    }

    const ctx = target.getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 480, 0);
    gradient.addColorStop(0, 'rgba(46,134,222,0.95)');
    gradient.addColorStop(1, 'rgba(46,134,222,0.22)');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels.length ? labels : ['Belum Ada Data'],
            datasets: [{
                label: 'Jumlah Pasien',
                data: values.length ? values : [0],
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
                }
            },
            scales: {
                y: { grid: { display: false }, ticks: { color: '#4d6c95' } },
                x: { beginAtZero: true, ticks: { color: '#2e86de' }, grid: { color: 'rgba(46,134,222,0.10)' } }
            }
        }
    });
})();
</script>





