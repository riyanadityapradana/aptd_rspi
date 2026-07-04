<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
require_once __DIR__ . '/10_penyakit_ugd_ponek_helper.php';

list($tanggalAwal, $tanggalAkhir) = aptd_igd_ponek_period_from_post();
$categories = aptd_igd_ponek_categories();
$diseaseRankings = aptd_igd_ponek_top_diseases($mysqli, $tanggalAwal, $tanggalAkhir, 10);
?>
<br>
<style>
.clinical-disease-wrap{display:grid;gap:18px;margin-bottom:55px}.clinical-disease-hero,.clinical-disease-panel{background:#fff;border:1px solid rgba(120,155,220,.17);box-shadow:0 18px 36px rgba(74,101,145,.10);border-radius:22px}
.clinical-disease-hero{padding:24px;background:linear-gradient(135deg,#eef7ff,#fff 48%,#f5efff)}.clinical-disease-title{margin:0;color:#1e3d6a;font-size:32px;font-weight:850}.clinical-disease-subtitle{margin:7px 0 0;color:#627b9d;font-size:14px}
.clinical-disease-filter{display:flex;flex-wrap:wrap;align-items:end;gap:12px;margin-top:18px}.clinical-disease-filter .form-control,.clinical-disease-filter .btn{border-radius:11px}
.clinical-disease-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.clinical-disease-panel{padding:20px;border-top:5px solid var(--category-color)}
.clinical-disease-head{display:flex;justify-content:space-between;gap:12px;align-items:start;margin-bottom:14px}.clinical-disease-h{margin:0;font-size:19px;font-weight:800;color:#214570}.clinical-disease-d{margin:4px 0 0;font-size:12px;color:#7388a5}.clinical-disease-pill{padding:7px 11px;border-radius:999px;background:#eaf4ff;color:#2d6ab0;font-size:11px;font-weight:700}
.clinical-disease-table thead th{text-align:center;vertical-align:middle}.clinical-disease-table td{vertical-align:middle}.clinical-disease-rank{display:inline-flex;width:25px;height:25px;align-items:center;justify-content:center;border-radius:8px;background:#eef4fb;color:#315f91;font-weight:800}
.clinical-disease-note{padding:13px 15px;border-radius:14px;background:#f5f8fc;border:1px solid #dce8f5;color:#536d8c;font-size:12px}
@media(max-width:850px){.clinical-disease-grid{grid-template-columns:1fr}}@media(max-width:575px){.clinical-disease-filter{flex-direction:column;align-items:stretch}.clinical-disease-title{font-size:27px}}
</style>

<div class="clinical-disease-wrap">
    <section class="clinical-disease-hero">
        <h1 class="clinical-disease-title">10 Penyakit Terbanyak UGD &amp; Ponek</h1>
        <p class="clinical-disease-subtitle">Rekap diagnosis utama berdasarkan kategori UGD Ralan, UGD Ranap, Ponek Ralan, dan Ponek Ranap.</p>
        <form method="post" class="clinical-disease-filter">
            <div class="form-group mb-0">
                <label for="tanggal_awal"><strong>Tanggal Awal</strong></label>
                <input type="date" name="tanggal_awal" id="tanggal_awal" class="form-control form-control-sm" value="<?php echo htmlspecialchars($tanggalAwal, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group mb-0">
                <label for="tanggal_akhir"><strong>Tanggal Akhir</strong></label>
                <input type="date" name="tanggal_akhir" id="tanggal_akhir" class="form-control form-control-sm" value="<?php echo htmlspecialchars($tanggalAkhir, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button>
            <button type="button" class="btn btn-success btn-sm px-4" id="btnExportDiseaseUgd">Export Excel</button>
        </form>
    </section>

    <div class="clinical-disease-note">
        Setiap <code>no_rawat</code> mengambil tepat satu diagnosis utama dengan
        <code>diagnosa_pasien.prioritas = 1</code>. Aturan kategori mengikuti dashboard kunjungan UGD &amp; Ponek.
    </div>

    <section class="clinical-disease-grid">
        <?php foreach ($categories as $categoryKey => $category): ?>
            <?php $diseaseRows = $diseaseRankings[$categoryKey]; ?>
            <article class="clinical-disease-panel" style="--category-color:<?php echo htmlspecialchars($category['color'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="clinical-disease-head">
                    <div>
                        <h2 class="clinical-disease-h"><?php echo htmlspecialchars($category['label'], ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="clinical-disease-d"><?php echo htmlspecialchars($category['criteria'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <span class="clinical-disease-pill"><?php echo htmlspecialchars($category['code'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="table-responsive-sm">
                    <table class="table table-sm table-bordered table-hover clinical-disease-table" style="width:100%;font-size:12px;">
                        <thead class="thead-dark"><tr><th>Peringkat</th><th>Kode ICD-10</th><th>Nama Penyakit</th><th>Jumlah</th></tr></thead>
                        <tbody>
                            <?php if (empty($diseaseRows)): ?>
                                <tr><td colspan="4" style="text-align:center;">Tidak ada diagnosis utama pada periode ini.</td></tr>
                            <?php else: ?>
                                <?php $rank = 1; foreach ($diseaseRows as $diseaseRow): ?>
                                    <tr>
                                        <td style="text-align:center;"><span class="clinical-disease-rank"><?php echo $rank++; ?></span></td>
                                        <td><?php echo htmlspecialchars($diseaseRow['kd_penyakit'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($diseaseRow['nm_penyakit'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td style="text-align:center;font-weight:bold;"><?php echo number_format($diseaseRow['jumlah_kasus'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const exportButton = document.getElementById('btnExportDiseaseUgd');
    if (!exportButton) return;
    exportButton.addEventListener('click', function () {
        const formData = new FormData();
        formData.append('tanggal_awal', document.getElementById('tanggal_awal').value);
        formData.append('tanggal_akhir', document.getElementById('tanggal_akhir').value);
        exportButton.disabled = true;

        fetch('main_app.php?page=export_10_penyakit_ugd_ponek', { method: 'POST', body: formData })
            .then(function (response) {
                if (!response.ok) throw new Error('Export gagal.');
                return response.blob();
            })
            .then(function (blob) {
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.href = url;
                link.download = '10_Penyakit_UGD_Ponek_' + new Date().toISOString().split('T')[0] + '.xlsx';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            })
            .catch(function () {
                alert('Gagal export 10 penyakit UGD dan Ponek.');
            })
            .finally(function () {
                exportButton.disabled = false;
            });
    });
});
</script>
