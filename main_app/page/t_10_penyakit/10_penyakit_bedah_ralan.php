<?php
// Koneksi ke database sik9
require_once('../config/koneksi.php');
$conn = $mysqli;

// Ambil input tanggal awal dan akhir dari POST, default tanggal hari ini
$tgl_awal = isset($_POST['tgl_awal']) ? $_POST['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_POST['tgl_akhir']) ? $_POST['tgl_akhir'] : date('Y-m-d');

// Validasi input tanggal
$tgl_awal = !empty($tgl_awal) ? $tgl_awal : date('Y-m-01');
$tgl_akhir = !empty($tgl_akhir) ? $tgl_akhir : date('Y-m-d');

// Query untuk mengambil 10 besar penyakit bedah rawat jalan berdasarkan filter
$query_bedah_ralan = "
SELECT
    d.kd_penyakit,
    p.nm_penyakit,
    COUNT(*) AS jumlah_kasus
FROM diagnosa_pasien d
JOIN reg_periksa r ON d.no_rawat = r.no_rawat
JOIN pasien p2 ON r.no_rkm_medis = p2.no_rkm_medis
JOIN penyakit p ON d.kd_penyakit = p.kd_penyakit
JOIN poliklinik pl ON r.kd_poli = pl.kd_poli
WHERE r.status_lanjut = 'Ralan'
  AND pl.nm_poli LIKE '%Bedah%'
  AND p2.nm_pasien NOT LIKE '%TEST%'
  AND p2.nm_pasien NOT LIKE '%Tes%'
  AND p2.nm_pasien NOT LIKE '%Coba%'
  AND DATE(r.tgl_registrasi) BETWEEN '$tgl_awal' AND '$tgl_akhir'
GROUP BY d.kd_penyakit, p.nm_penyakit
ORDER BY jumlah_kasus DESC
LIMIT 10";

$result_bedah_ralan = $conn->query($query_bedah_ralan);

// Error handling untuk query
if (!$result_bedah_ralan) {
    die('<div class="alert alert-danger">Query error: ' . $conn->error . '</div>');
}

// Ambil data untuk grafik dan tabel
$data_grafik = [];
$labels_grafik = [];
$total_kasus = 0;

if ($result_bedah_ralan->num_rows > 0) {
    while($row = $result_bedah_ralan->fetch_assoc()) {
        $labels_grafik[] = strlen($row['nm_penyakit']) > 25 ?
                          substr($row['nm_penyakit'], 0, 25) . '...' :
                          $row['nm_penyakit'];
        $data_grafik[] = (int)$row['jumlah_kasus'];
        $total_kasus += (int)$row['jumlah_kasus'];
    }
}

// Jika tidak ada data, set array kosong
if (empty($labels_grafik)) {
    $labels_grafik = ['Tidak ada data untuk periode ini'];
    $data_grafik = [0];
}
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>10 BESAR PENYAKIT BEDAH RAWAT JALAN</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="main_app.php?page=beranda">Home</a></li>
                    <li class="breadcrumb-item active">10 Besar Penyakit Bedah Rawat Jalan</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="card-tools" style="float: left; text-align: left;">
                            <form method="post" class="mb-3" style="display:flex;align-items:center;gap:10px;">
                                <label for="tgl_awal" style="margin-bottom:0;">Tanggal Awal:</label>
                                <input type="date" name="tgl_awal" id="tgl_awal" class="form-control" value="<?php echo htmlspecialchars($tgl_awal); ?>" style="width:auto;display:inline-block;">
                                <label for="tgl_akhir" style="margin-bottom:0;">Tanggal Akhir:</label>
                                <input type="date" name="tgl_akhir" id="tgl_akhir" class="form-control" value="<?php echo htmlspecialchars($tgl_akhir); ?>" style="width:auto;display:inline-block;">
                                <button type="submit" class="btn btn-primary">Tampilkan Data</button>
                            </form>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row">
                            <!-- Panel Kiri - Tabel -->
                            <div class="col-md-6">
                                <div style="overflow-x: auto; white-space: nowrap;">
                                    <table class="table table-bordered table-striped text-center align-middle" style="width: 100%; table-layout: auto;">
                                        <thead style="background:#81a1c1;color:#fff;">
                                            <tr>
                                                <th>No</th>
                                                <th>Kode</th>
                                                <th>Nama Penyakit</th>
                                                <th>Jumlah</th>
                                                <th>%</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if (empty($data_grafik) || $total_kasus == 0) {
                                                echo '<tr><td colspan="5" class="text-center">Tidak ada data untuk periode yang dipilih.</td></tr>';
                                            } else {
                                                $result_bedah_ralan->data_seek(0); // Reset pointer hasil query
                                                $no = 1;
                                                while($row = $result_bedah_ralan->fetch_assoc()):
                                                    $persentase = $total_kasus > 0 ? round(($row['jumlah_kasus'] / $total_kasus) * 100, 1) : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo htmlspecialchars($row['kd_penyakit']); ?></td>
                                                <td><?php echo htmlspecialchars($row['nm_penyakit']); ?></td>
                                                <td><?php echo number_format($row['jumlah_kasus']); ?></td>
                                                <td><?php echo $persentase; ?>%</td>
                                            </tr>
                                            <?php endwhile; } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Panel Kanan - Grafik -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h4 class="card-title">Grafik 10 Besar Penyakit Bedah Rawat Jalan</h4>
                                        <div class="card-tools">
                                            <small class="text-muted"><?php echo date('d M Y', strtotime($tgl_awal)) . ' s/d ' . date('d M Y', strtotime($tgl_akhir)); ?></small>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="chartPenyakitBedahRalan" width="400" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize chart dengan data dari PHP - Line Chart
    const ctxBedahRalan = document.getElementById('chartPenyakitBedahRalan').getContext('2d');
    new Chart(ctxBedahRalan, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels_grafik); ?>,
            datasets: [{
                label: 'Jumlah Kasus',
                data: <?php echo json_encode($data_grafik); ?>,
                borderColor: 'rgba(255, 107, 107, 1)',
                backgroundColor: 'rgba(255, 107, 107, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: 'rgba(255, 107, 107, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverRadius: 7,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
});
</script>

