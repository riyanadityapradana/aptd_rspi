<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
$conn = $mysqli;

// Default date range: current month
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-t');

// Validasi input
if (!strtotime($start_date)) $start_date = date('Y-m-01');
if (!strtotime($end_date)) $end_date = date('Y-m-t');

// Query: aggregate per kabupaten for specific three kabupaten
$sql = "SELECT kab.nm_kab AS kabupaten,
        SUM(CASE WHEN rp.kd_pj = 'A09' THEN 1 ELSE 0 END) AS Umum,
        SUM(CASE WHEN rp.kd_pj = 'BPJ' THEN 1 ELSE 0 END) AS BPJS,
        SUM(CASE WHEN rp.kd_pj = 'A92' THEN 1 ELSE 0 END) AS Asuransi,
        COUNT(*) AS Total
FROM reg_periksa rp
JOIN pasien p      ON p.no_rkm_medis = rp.no_rkm_medis
JOIN penjab pj     ON pj.kd_pj = rp.kd_pj
JOIN poliklinik pl ON pl.kd_poli = rp.kd_poli
JOIN kabupaten kab ON kab.kd_kab = p.kd_kab
WHERE rp.tgl_registrasi BETWEEN ? AND ?
  AND rp.status_lanjut = 'Ralan'
  AND rp.status_bayar = 'Sudah Bayar'
  AND rp.stts <> 'Batal'
  AND pl.nm_poli NOT IN ('Tinggal Rawat Inap', 'OBGYN', 'PONEK RANAP', 'TEST', 'Unit Gizi', 'Poli Vaksin', 'UGD', 'UNIT PACHO')
  AND kab.nm_kab IN ('BANJARMASIN', 'BANJARBARU', 'BANJAR')
  AND rp.kd_pj IN ('A09','A92','BPJ')
GROUP BY kab.nm_kab
ORDER BY FIELD(kab.nm_kab, 'BANJARMASIN', 'BANJARBARU', 'BANJAR')";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$total_umum = 0;
$total_bpjs = 0;
$total_asuransi = 0;
$total_pasien = 0; // placeholder to avoid undefined later
$labels = [];
$umum = [];
$bpjs = [];
$asuransi = [];
$total_data = [];

// Fetch results
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['kabupaten'];
        $umum[] = (int)$row['Umum'];
        $bpjs[] = (int)$row['BPJS'];
        $asuransi[] = (int)$row['Asuransi'];
        $total_data[] = (int)$row['Total'];

        $total_umum += (int)$row['Umum'];
        $total_bpjs += (int)$row['BPJS'];
        $total_asuransi += (int)$row['Asuransi'];
    }
}

?>
<br>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Pasien per Kabupaten</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css"/>
    <style>
        .card { border-radius: 4px; margin-bottom: 12px; }
        .card-header { padding: 10px; }
        .card-body { padding: 12px; }
    </style>
</head>
<body>
<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h2 style="font-size: 18px; color: black;">REKAP PASIEN RAWAT JALAN PER KABUPATEN</h2>
      </div>
    </div>
  </div>
</section>
<section class="content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header" style="background:#007bff; color:white;">
                        <h3 class="card-title">Filter Data</h3>
                    </div>
                    <div class="card-body">
                        <form method="post" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <label for="start_date" style="margin-bottom:0;">Tanggal Mulai:</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" value="<?= $start_date ?>" style="width:auto;display:inline-block;">
                            <label for="end_date" style="margin-bottom:0;">Tanggal Akhir:</label>
                            <input type="date" name="end_date" id="end_date" class="form-control" value="<?= $end_date ?>" style="width:auto;display:inline-block;">
                            <button type="submit" class="btn btn-primary">Tampilkan Data</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card card-success">
                    <div class="card-header" style="background:#28a745; color:white;">
                        <h3 class="card-title">Grafik Jumlah Pasien per Kabupaten</h3>
                    </div>
                    <div class="card-body" style="background:rgb(203, 212, 212); min-height: 400px; display: flex; align-items: center; justify-content: center;">
                        <canvas id="grafikLine" style="min-height: 350px; height: 350px; max-height: 400px; max-width: 100%;"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-info">
                    <div class="card-header" style="background:#17a2b8; color:white;">
                        <h3 class="card-title">RINGKASAN DATA</h3>
                    </div>
                    <div class="card-body" style="background:rgb(203, 212, 212); min-height: 400px;">
                        <table class="table table-bordered">
                            <tr style="background:#007bff; color:white;">
                                <th>Kategori</th>
                                <th>Jumlah</th>
                            </tr>
                            <tr>
                                <td>Umum</td>
                                <td align="center"><strong><?php echo $total_umum; ?></strong></td>
                            </tr>
                            <tr>
                                <td>BPJS</td>
                                <td align="center"><strong><?php echo $total_bpjs; ?></strong></td>
                            </tr>
                            <tr>
                                <td>Asuransi</td>
                                <td align="center"><strong><?php echo $total_asuransi; ?></strong></td>
                            </tr>
                            <tr style="background:#ffc107; color:#212529;">
                                <td><strong>TOTAL PASIEN</strong></td>
                                <td align="center"><strong><?php echo $total_umum + $total_bpjs + $total_asuransi; ?></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card card-danger">
                    <div class="card-header" style="background:#dc3545; color:white;">
                        <h3 class="card-title">DATA PASIEN RAWAT JALAN</h3>
                    </div>
                    <div class="card-body" style="background:rgb(203, 212, 212);">
                        <table class="table table-bordered table-striped" style="width:100%;">
                            <thead style="background:#007bff; color:white;">
                                <tr>
                                    <th style="text-align:center;">No</th>
                                    <th style="text-align:center;">Kabupaten</th>
                                    <th style="text-align:center;">Umum</th>
                                    <th style="text-align:center;">BPJS</th>
                                    <th style="text-align:center;">Asuransi</th>
                                    <th style="text-align:center;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                if ($result && $result->num_rows > 0) {
                                    $result->data_seek(0);
                                    while ($row = $result->fetch_assoc()) {
                                        echo '<tr>';
                                        echo '<td align="center">' . $no++ . '</td>';
                                        echo '<td>' . htmlspecialchars($row['kabupaten']) . '</td>';
                                        echo '<td align="center">' . $row['Umum'] . '</td>';
                                        echo '<td align="center">' . $row['BPJS'] . '</td>';
                                        echo '<td align="center">' . $row['Asuransi'] . '</td>';
                                        echo '<td align="center">' . $row['Total'] . '</td>';
                                        echo '</tr>';
                                    }
                                    // Tambah baris JUMLAH TOTAL
                                    echo '<tr style="background:#ffc107; color:#212529; font-weight:bold;">';
                                    echo '<td colspan="2" align="center">JUMLAH TOTAL</td>';
                                    echo '<td align="center">' . $total_umum . '</td>';
                                    echo '<td align="center">' . $total_bpjs . '</td>';
                                    echo '<td align="center">' . $total_asuransi . '</td>';
                                    echo '<td align="center">' . ($total_umum + $total_bpjs + $total_asuransi) . '</td>';
                                    echo '</tr>';
                                } else {
                                    echo '<tr><td colspan="6" align="center">Tidak ada data untuk periode ' . $start_date . ' sampai ' . $end_date . '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- DataTables JS & CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css"/>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    $('#tabelPasien').DataTable({
        paging: true,
        searching: true,
        ordering: true,
        info: true,
        lengthChange: false,
        pageLength: 10,
        language: {
            search: 'Cari:',
            emptyTable: 'Tidak ada data',
            paginate: { previous: 'Sebelumnya', next: 'Selanjutnya' }
        }
    });
});
</script>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('grafikLine').getContext('2d');
const chart = new Chart(ctx, {
        type: 'line',
        data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [
                        {
                                label: 'Umum',
                                backgroundColor: 'rgba(255, 193, 7, 0.2)',
                                borderColor: 'rgba(255, 193, 7, 1)',
                                borderWidth: 2,
                                fill: false,
                                data: <?php echo json_encode($umum); ?>,
                                tension: 0.4
                        },
                        {
                                label: 'BPJS',
                                backgroundColor: 'rgba(33, 150, 243, 0.2)',
                                borderColor: 'rgba(33, 150, 243, 1)',
                                borderWidth: 2,
                                fill: false,
                                data: <?php echo json_encode($bpjs); ?>,
                                tension: 0.4
                        },
                        {
                                label: 'Asuransi',
                                backgroundColor: 'rgba(76, 175, 80, 0.2)',
                                borderColor: 'rgba(76, 175, 80, 1)',
                                borderWidth: 2,
                                fill: false,
                                data: <?php echo json_encode($asuransi); ?>,
                                tension: 0.4
                        }
                ]
        },
        options: {
                responsive: true,
                plugins: {
                        legend: { position: 'top' },
                        title: { display: true, text: 'Grafik Jumlah Pasien Rawat Jalan per Kabupaten' }
                },
                scales: {
                        y: {
                                beginAtZero: true,
                                title: {
                                        display: true,
                                        text: 'Jumlah Pasien'
                                }
                        },
                        x: {
                                title: {
                                        display: true,
                                        text: 'Kabupaten'
                                }
                        }
                }
        }
});
</script>

