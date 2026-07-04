<?php
require_once dirname(dirname(dirname(__DIR__))) . '/config/koneksi.php';
require_once __DIR__ . '/kunjungan_igd_ponek_helper.php';

list($tanggalAwal, $tanggalAkhir) = aptd_igd_ponek_period_from_post();
$summary = aptd_igd_ponek_summary($mysqli, $tanggalAwal, $tanggalAkhir);
$excludedSpecialists = aptd_igd_ponek_excluded_specialists($mysqli, $tanggalAwal, $tanggalAkhir);

$grandTotal = 0;
$paymentTotals = ['umum' => 0, 'bpjs' => 0, 'asuransi' => 0, 'lainnya' => 0];
foreach ($summary as $row) {
    $grandTotal += $row['total'];
    foreach (array_keys($paymentTotals) as $field) {
        $paymentTotals[$field] += $row[$field];
    }
}

$chartLabels = array_column($summary, 'label');
$chartValues = array_column($summary, 'total');
$chartColors = array_column($summary, 'color');
?>
<br>
<style>
.emergency-wrap{display:grid;gap:18px;margin-bottom:55px}
.emergency-hero,.emergency-panel,.emergency-card{background:#fff;border:1px solid rgba(120,155,220,.17);box-shadow:0 18px 36px rgba(74,101,145,.10);border-radius:22px}
.emergency-hero{padding:24px;background:linear-gradient(135deg,#eef7ff,#fff 48%,#fff4f2)}
.emergency-title{margin:0;color:#1e3d6a;font-size:32px;font-weight:850}.emergency-subtitle{margin:7px 0 0;color:#627b9d;font-size:14px}
.emergency-filter{display:flex;flex-wrap:wrap;align-items:end;gap:12px;margin-top:18px}.emergency-filter .form-control,.emergency-filter .btn{border-radius:11px}
.emergency-cards{display:grid;grid-template-columns:repeat(4,minmax(190px,1fr));gap:15px}.emergency-card{padding:18px;position:relative;overflow:hidden}.emergency-card::before{content:"";position:absolute;inset:0 auto 0 0;width:6px;background:var(--card-color)}
.emergency-card-code{font-size:12px;font-weight:800;letter-spacing:.8px;color:#66809f}.emergency-card-value{font-size:31px;font-weight:900;color:#1f416d;line-height:1.1;margin:10px 0 7px}.emergency-card-note{font-size:11px;color:#7489a6}
.emergency-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(300px,1fr);gap:18px}.emergency-panel{padding:20px}.emergency-head{display:flex;justify-content:space-between;gap:12px;align-items:start;margin-bottom:14px}.emergency-h{margin:0;font-size:19px;font-weight:800;color:#214570}.emergency-d{margin:4px 0 0;font-size:12px;color:#7388a5}.emergency-pill{padding:7px 11px;border-radius:999px;background:#eaf4ff;color:#2d6ab0;font-size:11px;font-weight:700}
.emergency-chart{position:relative;min-height:310px}.emergency-note{padding:13px 15px;border-radius:14px;background:#fff6e8;border:1px solid #f3d99e;color:#816215;font-size:12px;line-height:1.6}
.emergency-table thead th{text-align:center;vertical-align:middle}.emergency-table td{vertical-align:middle}
@media(max-width:1100px){.emergency-cards{grid-template-columns:repeat(2,1fr)}}@media(max-width:850px){.emergency-grid{grid-template-columns:1fr}}@media(max-width:575px){.emergency-cards{grid-template-columns:1fr}.emergency-filter{flex-direction:column;align-items:stretch}.emergency-title{font-size:27px}}
</style>

<div class="emergency-wrap">
    <section class="emergency-hero">
        <h1 class="emergency-title">Dashboard Kunjungan UGD &amp; Ponek</h1>
        <p class="emergency-subtitle">Pemisahan kunjungan UGD Ralan, UGD Ranap, Ponek Ralan, dan Ponek Ranap berdasarkan alur registrasi pasien.</p>
        <form id="filterFormIgd" method="post" class="emergency-filter">
            <div class="form-group mb-0">
                <label for="tanggal_awal"><strong>Tanggal Awal</strong></label>
                <input type="date" name="tanggal_awal" id="tanggal_awal" class="form-control form-control-sm" value="<?php echo htmlspecialchars($tanggalAwal, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group mb-0">
                <label for="tanggal_akhir"><strong>Tanggal Akhir</strong></label>
                <input type="date" name="tanggal_akhir" id="tanggal_akhir" class="form-control form-control-sm" value="<?php echo htmlspecialchars($tanggalAkhir, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button>
            <button type="button" class="btn btn-success btn-sm px-4" id="btnExportIgd">Export Excel</button>
        </form>
    </section>

    <section class="emergency-cards">
        <?php foreach ($summary as $row): ?>
            <article class="emergency-card" style="--card-color:<?php echo htmlspecialchars($row['color'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="emergency-card-code"><?php echo htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($row['code'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="emergency-card-value"><?php echo number_format($row['total'], 0, ',', '.'); ?></div>
                <div class="emergency-card-note"><?php echo htmlspecialchars($row['criteria'], ENT_QUOTES, 'UTF-8'); ?></div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="emergency-grid">
        <div class="emergency-panel">
            <div class="emergency-head">
                <div>
                    <h2 class="emergency-h">Perbandingan Empat Kategori</h2>
                    <p class="emergency-d">Total <?php echo number_format($grandTotal, 0, ',', '.'); ?> kunjungan pada periode terpilih.</p>
                </div>
                <span class="emergency-pill"><?php echo htmlspecialchars($tanggalAwal . ' s.d. ' . $tanggalAkhir, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="emergency-chart"><canvas id="chartIgdPonekCategory"></canvas></div>
        </div>
        <div class="emergency-panel">
            <div class="emergency-head">
                <div>
                    <h2 class="emergency-h">Komposisi Penjamin</h2>
                    <p class="emergency-d">Distribusi penjamin dari seluruh kategori.</p>
                </div>
            </div>
            <div class="emergency-chart"><canvas id="chartIgdPonekPayment"></canvas></div>
        </div>
    </section>

    <section class="emergency-panel">
        <div class="emergency-head">
            <div>
                <h2 class="emergency-h">Rincian Kunjungan</h2>
                <p class="emergency-d">Setiap nomor rawat berasal dari satu baris registrasi; relasi kamar menggunakan EXISTS sehingga perpindahan kamar tidak menggandakan hitungan.</p>
            </div>
        </div>
        <div class="table-responsive-sm">
            <table class="table table-sm table-bordered table-hover emergency-table" id="table4" style="width:100%;font-size:12px;">
                <thead class="thead-dark">
                    <tr><th>No</th><th>Kategori</th><th>Kode Poli</th><th>Kriteria</th><th>Umum</th><th>BPJS</th><th>Asuransi</th><th>Lainnya</th><th>Total</th></tr>
                </thead>
                <tbody>
                    <?php $number = 1; foreach ($summary as $row): ?>
                        <tr>
                            <td style="text-align:center;"><?php echo $number++; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td style="text-align:center;"><?php echo htmlspecialchars($row['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['criteria'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="text-align:center;"><?php echo number_format($row['umum'], 0, ',', '.'); ?></td>
                            <td style="text-align:center;"><?php echo number_format($row['bpjs'], 0, ',', '.'); ?></td>
                            <td style="text-align:center;"><?php echo number_format($row['asuransi'], 0, ',', '.'); ?></td>
                            <td style="text-align:center;"><?php echo number_format($row['lainnya'], 0, ',', '.'); ?></td>
                            <td style="text-align:center;font-weight:bold;"><?php echo number_format($row['total'], 0, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight:bold;background:#f4f7fb;">
                        <td colspan="4" style="text-align:right;">Total</td>
                        <td style="text-align:center;"><?php echo number_format($paymentTotals['umum'], 0, ',', '.'); ?></td>
                        <td style="text-align:center;"><?php echo number_format($paymentTotals['bpjs'], 0, ',', '.'); ?></td>
                        <td style="text-align:center;"><?php echo number_format($paymentTotals['asuransi'], 0, ',', '.'); ?></td>
                        <td style="text-align:center;"><?php echo number_format($paymentTotals['lainnya'], 0, ',', '.'); ?></td>
                        <td style="text-align:center;"><?php echo number_format($grandTotal, 0, ',', '.'); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="emergency-note mt-3">
            <strong>Pengecualian UGD Ranap:</strong>
            <?php echo number_format($excludedSpecialists, 0, ',', '.'); ?> kunjungan IGDK yang memiliki data kamar inap tidak dihitung karena dokter registrasinya bukan dokter umum dengan <code>kd_sps = S0016</code>.
        </div>
    </section>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart !== 'undefined') {
        const categoryCanvas = document.getElementById('chartIgdPonekCategory');
        if (categoryCanvas) {
            new Chart(categoryCanvas, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                        label: 'Jumlah Kunjungan',
                        data: <?php echo json_encode($chartValues); ?>,
                        backgroundColor: <?php echo json_encode($chartColors); ?>,
                        borderRadius: 9
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true }, x: { grid: { display: false } } }
                }
            });
        }

        const paymentCanvas = document.getElementById('chartIgdPonekPayment');
        if (paymentCanvas) {
            new Chart(paymentCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Umum', 'BPJS', 'Asuransi', 'Lainnya'],
                    datasets: [{
                        data: <?php echo json_encode(array_values($paymentTotals)); ?>,
                        backgroundColor: ['#2e86de', '#27ae60', '#f39c12', '#95a5a6'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 10 } } }
                }
            });
        }
    }

    const exportButton = document.getElementById('btnExportIgd');
    if (exportButton) {
        exportButton.addEventListener('click', function () {
            const formData = new FormData();
            formData.append('tanggal_awal', document.getElementById('tanggal_awal').value);
            formData.append('tanggal_akhir', document.getElementById('tanggal_akhir').value);
            formData.append('export', '1');
            exportButton.disabled = true;

            fetch('main_app.php?page=export_kunjungan_igd', {
                method: 'POST',
                body: formData
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('Export gagal.');
                    return response.blob();
                })
                .then(function (blob) {
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);
                    link.href = url;
                    link.download = 'Data_Kunjungan_UGD_Ponek_' + new Date().toISOString().split('T')[0] + '.xlsx';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                })
                .catch(function () {
                    alert('Gagal export data UGD dan Ponek.');
                })
                .finally(function () {
                    exportButton.disabled = false;
                });
        });
    }
});
</script>
