<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';

$conn = $mysqli;
$pageTitle = 'Rekap Kode Penyakit A/B Rawat Inap';
$pageSubtitle = 'Ringkasan diagnosa utama kode penyakit A dan B pada pasien rawat inap dengan pemisahan kategori anak dan dewasa.';

$tgl_awal = isset($_POST['tgl_awal']) && $_POST['tgl_awal'] !== '' ? trim((string) $_POST['tgl_awal']) : date('Y-m-01');
$tgl_akhir = isset($_POST['tgl_akhir']) && $_POST['tgl_akhir'] !== '' ? trim((string) $_POST['tgl_akhir']) : date('Y-m-d');
$exportAction = 'page/t_kode_penyakit/rawat_inap/export_kode_penyakit_ab_ranap.php';

if ($tgl_awal > $tgl_akhir) {
    $tmp = $tgl_awal;
    $tgl_awal = $tgl_akhir;
    $tgl_akhir = $tmp;
}

$anak_rows = array();
$dewasa_rows = array();
$error_message = '';
$total_anak = 0;
$total_dewasa = 0;
$top_anak = array('nama' => '-', 'jumlah' => 0);
$top_dewasa = array('nama' => '-', 'jumlah' => 0);
$chart_anak_labels = array();
$chart_anak_values = array();
$chart_dewasa_labels = array();
$chart_dewasa_values = array();

$sql = "SELECT
            CASE
                WHEN TIMESTAMPDIFF(YEAR, ps.tgl_lahir, rp.tgl_registrasi) < 18 THEN 'ANAK'
                ELSE 'DEWASA'
            END AS kategori_umur,
            p.kd_penyakit,
            p.nm_penyakit,
            COUNT(DISTINCT dp.no_rawat) AS jumlah_kasus
        FROM diagnosa_pasien dp
        INNER JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit
        INNER JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat
        INNER JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
        INNER JOIN pasien ps ON rp.no_rkm_medis = ps.no_rkm_medis
        WHERE dp.prioritas = '1'
          AND (p.kd_penyakit LIKE 'A%' OR p.kd_penyakit LIKE 'B%')
          AND DATE(rp.tgl_registrasi) BETWEEN ? AND ?
          AND rp.status_lanjut = 'Ranap'
          AND LOWER(ps.nm_pasien) NOT LIKE '%test%'
          AND LOWER(ps.nm_pasien) NOT LIKE '%tes%'
          AND LOWER(ps.nm_pasien) NOT LIKE '%coba%'
        GROUP BY kategori_umur, p.kd_penyakit, p.nm_penyakit
        ORDER BY kategori_umur ASC, jumlah_kasus DESC, p.kd_penyakit ASC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('ss', $tgl_awal, $tgl_akhir);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['jumlah_kasus'] = (int) $row['jumlah_kasus'];
        if ($row['kategori_umur'] === 'ANAK') {
            $anak_rows[] = $row;
            $total_anak += $row['jumlah_kasus'];
        } else {
            $dewasa_rows[] = $row;
            $total_dewasa += $row['jumlah_kasus'];
        }
    }
    $stmt->close();
} else {
    $error_message = 'Query prepare gagal: ' . $conn->error;
}

if (!empty($anak_rows)) {
    $top_anak['nama'] = $anak_rows[0]['nm_penyakit'];
    $top_anak['jumlah'] = $anak_rows[0]['jumlah_kasus'];
}
if (!empty($dewasa_rows)) {
    $top_dewasa['nama'] = $dewasa_rows[0]['nm_penyakit'];
    $top_dewasa['jumlah'] = $dewasa_rows[0]['jumlah_kasus'];
}

foreach (array_slice($anak_rows, 0, 10) as $row) {
    $chart_anak_labels[] = $row['kd_penyakit'] . ' - ' . (strlen($row['nm_penyakit']) > 34 ? substr($row['nm_penyakit'], 0, 34) . '...' : $row['nm_penyakit']);
    $chart_anak_values[] = $row['jumlah_kasus'];
}
foreach (array_slice($dewasa_rows, 0, 10) as $row) {
    $chart_dewasa_labels[] = $row['kd_penyakit'] . ' - ' . (strlen($row['nm_penyakit']) > 34 ? substr($row['nm_penyakit'], 0, 34) . '...' : $row['nm_penyakit']);
    $chart_dewasa_values[] = $row['jumlah_kasus'];
}
?>
<br>
<style>
.ab-wrap{display:grid;gap:18px}.ab-hero,.ab-card,.ab-panel{background:#fff;border:1px solid rgba(120,155,220,.16);box-shadow:0 18px 36px rgba(74,101,145,.10);border-radius:22px}.ab-hero{padding:24px;background:linear-gradient(135deg,#eef7ff,#ffffff 46%,#f3fbf4)}.ab-title{margin:0 0 8px;font-size:34px;font-weight:800;color:#21406c}.ab-sub{margin:0;color:#587192;font-size:14px;max-width:860px}.ab-filter{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;align-items:end;margin-top:18px}.ab-filter .form-control,.ab-filter .btn{border-radius:12px}.ab-filter .btn-primary{background:linear-gradient(135deg,#2e86de,#1f5fae);border:none;height:38px}.ab-filter .btn-success{background:linear-gradient(135deg,#23a36d,#1f7d57);border:none;height:38px}.ab-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px}.ab-card{padding:18px}.ab-card:nth-child(1){background:linear-gradient(135deg,#edf6ff,#fff)}.ab-card:nth-child(2){background:linear-gradient(135deg,#eefcf5,#fff)}.ab-card:nth-child(3){background:linear-gradient(135deg,#fff6ea,#fff)}.ab-card:nth-child(4){background:linear-gradient(135deg,#f5f1ff,#fff)}.ab-k{font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#6f84a4}.ab-v{font-size:28px;font-weight:800;color:#1f3f6d;line-height:1.1}.ab-s{margin-top:8px;font-size:12px;color:#60789d}.ab-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.ab-panel{padding:20px}.ab-head{display:flex;justify-content:space-between;gap:12px;align-items:start;margin-bottom:14px}.ab-h{margin:0;font-size:20px;font-weight:800;color:#1e3d6a}.ab-d{margin:4px 0 0;color:#6f84a4;font-size:13px}.ab-pill{display:inline-flex;padding:8px 12px;border-radius:999px;background:#eaf4ff;color:#2d6ab0;font-size:12px;font-weight:700}.ab-pill-green{background:#ebf8f0;color:#218a5e}.ab-chart{position:relative;min-height:340px}.ab-note{padding:14px 16px;border-radius:16px;background:#fff8e8;border:1px solid #f5db9a;color:#8a6816}.ab-table th,.ab-table td{vertical-align:middle}.ab-table td:nth-child(1),.ab-table td:nth-child(4){text-align:center}.ab-table td:nth-child(4){font-weight:700}.ab-empty{padding:24px;text-align:center;color:#6682a7}.ab-link{display:inline-flex;min-width:34px;justify-content:center;padding:3px 8px;border-radius:10px;background:#eef6ff;color:#1f5fae;font-weight:700;text-decoration:none;cursor:pointer}.ab-link:hover{background:#dbeeff;color:#184b88;text-decoration:none}.ab-modal-head{background:#eef7ff;border-bottom:1px solid rgba(120,155,220,.22)}.ab-modal-table th,.ab-modal-table td{font-size:12px;vertical-align:middle}.ab-modal-table th{text-align:center}.ab-modal-table td:nth-child(1),.ab-modal-table td:nth-child(2),.ab-modal-table td:nth-child(4),.ab-modal-table td:nth-child(5){white-space:nowrap}@media(max-width:991px){.ab-grid-2{grid-template-columns:1fr}.ab-filter{grid-template-columns:1fr 1fr}}@media(max-width:576px){.ab-title{font-size:28px}.ab-filter{grid-template-columns:1fr}}
</style>
<div class="ab-wrap">
    <section class="ab-hero">
        <h1 class="ab-title"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="ab-sub"><?php echo htmlspecialchars($pageSubtitle, ENT_QUOTES, 'UTF-8'); ?></p>
        <form method="post" class="ab-filter" id="formKodePenyakitAbExport">
            <div class="form-group mb-0">
                <label for="tgl_awal"><strong>Tanggal Awal</strong></label>
                <input type="date" class="form-control form-control-sm" id="tgl_awal" name="tgl_awal" value="<?php echo htmlspecialchars($tgl_awal, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group mb-0">
                <label for="tgl_akhir"><strong>Tanggal Akhir</strong></label>
                <input type="date" class="form-control form-control-sm" id="tgl_akhir" name="tgl_akhir" value="<?php echo htmlspecialchars($tgl_akhir, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group mb-0">
                <button type="submit" class="btn btn-primary btn-sm btn-block">Tampilkan Data</button>
            </div>
            <div class="form-group mb-0">
                <button type="button" class="btn btn-success btn-sm btn-block" id="btnExportKodePenyakitAb">Export Excel</button>
            </div>
        </form>
    </section>

    <?php if ($error_message !== ''): ?>
        <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="ab-cards">
        <div class="ab-card"><div class="ab-k">Kategori Anak</div><div class="ab-v"><?php echo number_format($total_anak, 0, ',', '.'); ?></div><div class="ab-s">Usia pasien kurang dari 18 tahun saat tanggal registrasi.</div></div>
        <div class="ab-card"><div class="ab-k">Kategori Dewasa</div><div class="ab-v"><?php echo number_format($total_dewasa, 0, ',', '.'); ?></div><div class="ab-s">Usia pasien 18 tahun atau lebih saat tanggal registrasi.</div></div>
        <div class="ab-card"><div class="ab-k">Top Anak</div><div class="ab-v"><?php echo $top_anak['jumlah'] > 0 ? number_format($top_anak['jumlah'], 0, ',', '.') . ' kasus' : '-'; ?></div><div class="ab-s"><?php echo htmlspecialchars($top_anak['nama'], ENT_QUOTES, 'UTF-8'); ?></div></div>
        <div class="ab-card"><div class="ab-k">Top Dewasa</div><div class="ab-v"><?php echo $top_dewasa['jumlah'] > 0 ? number_format($top_dewasa['jumlah'], 0, ',', '.') . ' kasus' : '-'; ?></div><div class="ab-s"><?php echo htmlspecialchars($top_dewasa['nama'], ENT_QUOTES, 'UTF-8'); ?></div></div>
    </section>

    <section class="ab-grid-2">
        <div class="ab-panel">
            <div class="ab-head"><div><h2 class="ab-h">Grafik Kategori Anak</h2><p class="ab-d">Anak: pasien dengan usia kurang dari 18 tahun saat registrasi rawat inap.</p></div><span class="ab-pill"><?php echo htmlspecialchars(date('d M Y', strtotime($tgl_awal)) . ' s.d. ' . date('d M Y', strtotime($tgl_akhir)), ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ab-chart"><canvas id="chartAnakRanap"></canvas></div>
        </div>
        <div class="ab-panel">
            <div class="ab-head"><div><h2 class="ab-h">Grafik Kategori Dewasa</h2><p class="ab-d">Dewasa: pasien dengan usia 18 tahun atau lebih saat registrasi rawat inap.</p></div><span class="ab-pill ab-pill-green"><?php echo htmlspecialchars(date('d M Y', strtotime($tgl_awal)) . ' s.d. ' . date('d M Y', strtotime($tgl_akhir)), ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ab-chart"><canvas id="chartDewasaRanap"></canvas></div>
        </div>
    </section>

    <section class="ab-grid-2">
        <div class="ab-panel">
            <div class="ab-head"><div><h2 class="ab-h">Tabel Kategori Anak</h2><p class="ab-d">Daftar kode penyakit A/B untuk pasien usia kurang dari 18 tahun.</p></div></div>
            <div class="table-responsive-sm">
                <table class="table table-sm table-bordered table-hover ab-table" id="table4" style="width:100%;font-size:12px;">
                    <thead class="thead-dark">
                        <tr>
                            <th>No</th>
                            <th>Kode Penyakit</th>
                            <th>Nama Penyakit</th>
                            <th>Jumlah Kasus</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($anak_rows)): ?>
                            <tr><td colspan="4" style="text-align:center;">Tidak ada data kategori anak untuk filter tanggal ini.</td></tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($anak_rows as $row): ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($row['kd_penyakit'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($row['nm_penyakit'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><a href="#" class="ab-link detail-trigger" data-kategori="ANAK" data-kd="<?php echo htmlspecialchars($row['kd_penyakit'], ENT_QUOTES, 'UTF-8'); ?>" data-penyakit="<?php echo htmlspecialchars($row['nm_penyakit'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo number_format($row['jumlah_kasus'], 0, ',', '.'); ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="ab-panel">
            <div class="ab-head"><div><h2 class="ab-h">Tabel Kategori Dewasa</h2><p class="ab-d">Daftar kode penyakit A/B untuk pasien usia 18 tahun atau lebih.</p></div></div>
            <div class="table-responsive-sm">
                <table class="table table-sm table-bordered table-hover ab-table" id="table22" style="width:100%;font-size:12px;">
                    <thead class="thead-dark">
                        <tr>
                            <th>No</th>
                            <th>Kode Penyakit</th>
                            <th>Nama Penyakit</th>
                            <th>Jumlah Kasus</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dewasa_rows)): ?>
                            <tr><td colspan="4" style="text-align:center;">Tidak ada data kategori dewasa untuk filter tanggal ini.</td></tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($dewasa_rows as $row): ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($row['kd_penyakit'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($row['nm_penyakit'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><a href="#" class="ab-link detail-trigger" data-kategori="DEWASA" data-kd="<?php echo htmlspecialchars($row['kd_penyakit'], ENT_QUOTES, 'UTF-8'); ?>" data-penyakit="<?php echo htmlspecialchars($row['nm_penyakit'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo number_format($row['jumlah_kasus'], 0, ',', '.'); ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <div class="ab-note">
        Keterangan kategori umur: <strong>Anak</strong> = usia pasien <strong>kurang dari 18 tahun</strong>, <strong>Dewasa</strong> = usia pasien <strong>18 tahun atau lebih</strong>, dihitung berdasarkan <code>TIMESTAMPDIFF(YEAR, tgl_lahir, tgl_registrasi)</code>.
    </div>
</div>

<div class="modal fade" id="detailModalPenyakitAb" tabindex="-1" role="dialog" aria-labelledby="detailModalPenyakitAbLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header ab-modal-head">
                <h5 class="modal-title" id="detailModalPenyakitAbLabel">Rincian Data Pasien</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="detailModalPenyakitAbBody">
                Loading...
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    function renderCategoryChart(targetId, labels, values, color) {
        const target = document.getElementById(targetId);
        if (!target || typeof Chart === 'undefined') {
            return;
        }

        new Chart(target, {
            type: 'bar',
            data: {
                labels: labels.length ? labels : ['Belum Ada Data'],
                datasets: [{
                    label: 'Jumlah Kasus',
                    data: values.length ? values : [0],
                    backgroundColor: color,
                    borderRadius: 10,
                    borderSkipped: false,
                    maxBarThickness: 26
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 10, color: '#496280' } } },
                scales: {
                    y: { ticks: { color: '#4d6c95' }, grid: { display: false } },
                    x: { beginAtZero: true, ticks: { color: '#2e86de' }, grid: { color: 'rgba(46,134,222,0.10)' } }
                }
            }
        });
    }

    renderCategoryChart('chartAnakRanap', <?php echo json_encode($chart_anak_labels); ?>, <?php echo json_encode($chart_anak_values); ?>, 'rgba(46,134,222,0.82)');
    renderCategoryChart('chartDewasaRanap', <?php echo json_encode($chart_dewasa_labels); ?>, <?php echo json_encode($chart_dewasa_values); ?>, 'rgba(39,174,96,0.76)');

    var exportButton = document.getElementById('btnExportKodePenyakitAb');
    var exportForm = document.getElementById('formKodePenyakitAbExport');
    if (exportButton && exportForm) {
        exportButton.addEventListener('click', function(){
            var clonedForm = exportForm.cloneNode(true);
            clonedForm.id = '';
            clonedForm.method = 'post';
            clonedForm.action = '<?php echo $exportAction; ?>';
            clonedForm.style.display = 'none';
            var buttons = clonedForm.querySelectorAll('button');
            buttons.forEach(function(item){ item.parentNode.removeChild(item); });
            document.body.appendChild(clonedForm);
            clonedForm.submit();
            document.body.removeChild(clonedForm);
        });
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);
        return div.innerHTML;
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('.detail-trigger');
        if (!trigger) {
            return;
        }

        event.preventDefault();

        const kategori = trigger.getAttribute('data-kategori');
        const kd = trigger.getAttribute('data-kd');
        const penyakit = trigger.getAttribute('data-penyakit');
        const tglAwal = document.getElementById('tgl_awal') ? document.getElementById('tgl_awal').value : '';
        const tglAkhir = document.getElementById('tgl_akhir') ? document.getElementById('tgl_akhir').value : '';
        const kategoriLabel = kategori === 'ANAK' ? 'Anak' : 'Dewasa';
        const modalLabel = document.getElementById('detailModalPenyakitAbLabel');
        const modalBody = document.getElementById('detailModalPenyakitAbBody');

        if (modalLabel) {
            modalLabel.textContent = 'Rincian ' + penyakit + ' - Kategori ' + kategoriLabel;
        }
        if (modalBody) {
            modalBody.innerHTML = '<div class="text-center p-5">Loading...</div>';
        }

        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery('#detailModalPenyakitAb').modal('show');
        }

        const params = new URLSearchParams({
            kategori: kategori,
            kd_penyakit: kd,
            tgl_awal: tglAwal,
            tgl_akhir: tglAkhir
        });

        fetch('page/t_kode_penyakit/rawat_inap/kode_penyakit_ab_ranap_details.php?' + params.toString(), {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(function (response) {
            return response.json().catch(function () {
                throw new Error('Respons detail tidak valid.');
            });
        })
        .then(function (response) {
            if (!response || response.status !== 'success') {
                const message = response && response.message ? response.message : 'Gagal mengambil data rincian.';
                if (modalBody) {
                    modalBody.innerHTML = '<div class="alert alert-danger">' + escapeHtml(message) + '</div>';
                }
                return;
            }

            if (!response.data || !response.data.length) {
                if (modalBody) {
                    modalBody.innerHTML = '<div class="ab-empty">Tidak ada rincian pasien untuk penyakit ini.</div>';
                }
                return;
            }

            let html = '<p class="mb-2"><strong>Total Data Ditemukan: ' + response.data.length + '</strong></p>';
            html += '<div class="table-responsive"><table class="table table-sm table-bordered table-striped ab-modal-table"><thead><tr>';
            Object.keys(response.data[0]).forEach(function (key) {
                html += '<th>' + escapeHtml(key) + '</th>';
            });
            html += '</tr></thead><tbody>';
            response.data.forEach(function (row) {
                html += '<tr>';
                Object.keys(row).forEach(function (key) {
                    html += '<td>' + escapeHtml(row[key]) + '</td>';
                });
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            if (modalBody) {
                modalBody.innerHTML = html;
            }
        })
        .catch(function (error) {
            if (modalBody) {
                modalBody.innerHTML = '<div class="alert alert-danger">Gagal mengambil data rincian. ' + escapeHtml(error.message || '') + '</div>';
            }
        });
    });
})();
</script>
