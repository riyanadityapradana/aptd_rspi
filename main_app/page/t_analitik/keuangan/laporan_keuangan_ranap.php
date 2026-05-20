<?php
require_once __DIR__ . '/laporan_keuangan_ranap_helper.php';

list($month, $year, $startDate, $endDate) = aptd_keu_ranap_date_filter();
$monthLabels = aptd_month_labels_local();
$rows = aptd_keu_ranap_fetch_rows($mysqli, $startDate, $endDate);
$summary = aptd_keu_ranap_summary($rows);

ob_start(); ?>
<form method="post" class="analytics-filter">
    <div class="form-group mb-0">
        <label for="month"><strong>Bulan</strong></label>
        <select name="month" id="month" class="form-control form-control-sm">
            <?php foreach ($monthLabels as $n => $label): ?>
                <option value="<?php echo $n; ?>" <?php echo $month === $n ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group mb-0">
        <label for="year"><strong>Tahun</strong></label>
        <select name="year" id="year" class="form-control form-control-sm">
            <?php for ($y = 2020; $y <= ((int) date('Y') + 1); $y++): ?>
                <option value="<?php echo $y; ?>" <?php echo $year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button>
    <a class="btn btn-success btn-sm px-4" href="<?php echo htmlspecialchars(aptd_keu_ranap_export_url($month, $year), ENT_QUOTES, 'UTF-8'); ?>">Export Excel</a>
</form>
<?php $filters = ob_get_clean();

ob_start(); ?>
<section class="analytics-cards">
    <div class="analytics-card"><div class="analytics-k">Pasien BPJS Ranap</div><div class="analytics-v"><?php echo aptd_number($summary['jumlah_pasien']); ?></div><div class="analytics-s"><?php echo htmlspecialchars($monthLabels[$month] . ' ' . $year, ENT_QUOTES, 'UTF-8'); ?></div></div>
    <div class="analytics-card"><div class="analytics-k">Total Claim/Tagihan</div><div class="analytics-v"><?php echo aptd_currency($summary['total_claim']); ?></div><div class="analytics-s">Sumber sementara: billing Tagihan</div></div>
    <div class="analytics-card"><div class="analytics-k">Total Jasa Dokter</div><div class="analytics-v"><?php echo aptd_currency($summary['total_jasa_dokter']); ?></div><div class="analytics-s">Akumulasi kolom jasa dokter</div></div>
    <div class="analytics-card"><div class="analytics-k">Lab + Radiologi</div><div class="analytics-v"><?php echo aptd_currency($summary['total_lab'] + $summary['total_radiologi']); ?></div><div class="analytics-s">Dokter lab, USG, dan rontgen</div></div>
</section>
<?php $cards = ob_get_clean();

ob_start(); ?>
<section class="analytics-panel">
    <div class="analytics-head">
        <div>
            <h2 class="analytics-h">Catatan Query</h2>
            <p class="analytics-d">Kolom biaya dipisah di helper agar aturan perhitungan bisa ditambah bertahap seperti file Excel.</p>
        </div>
        <span class="analytics-pill"><?php echo htmlspecialchars($startDate . ' s.d. ' . $endDate, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <div class="analytics-note">Filter awal mengikuti arahan gambar: pasien rawat inap BPJS, ada di <code>kamar_inap</code>, dan status pulang bukan <code>Pindah Kamar</code>. Nominal claim saat ini memakai total tagihan dari <code>billing</code> karena tabel klaim final belum ditentukan.</div>
</section>
<?php $panels = ob_get_clean();

ob_start(); ?>
<section class="analytics-panel">
    <div class="analytics-head">
        <div>
            <h2 class="analytics-h">Laporan Keuangan Rawat Inap</h2>
            <p class="analytics-d">Format detail mengikuti kebutuhan rekap Excel per pasien dan per nomor rawat.</p>
        </div>
    </div>
    <style>
        .keu-ranap-scroll{width:100%;overflow-x:auto;overflow-y:hidden;padding-bottom:8px}
        .keu-ranap-table{min-width:3600px;white-space:nowrap}
        .keu-ranap-table th,.keu-ranap-table td{vertical-align:middle}
    </style>
    <div class="keu-ranap-scroll">
        <table class="table table-sm table-bordered table-hover analytics-table keu-ranap-table" id="table4" style="width:100%;font-size:11px;">
            <thead class="thead-dark">
                <tr>
                    <th>No</th>
                    <th>No Rawat</th>
                    <th>No RM</th>
                    <th>Nama Pasien</th>
                    <th>Diagnosa Awal</th>
                    <th>Diagnosa Akhir</th>
                    <th>Tanggal Masuk</th>
                    <th>Tanggal Keluar</th>
                    <th>Status Pulang</th>
                    <th>DPJP</th>
                    <th>Kamar</th>
                    <th>Claim</th>
                    <th>Dokter UGD</th>
                    <th>JD DPJP</th>
                    <th>JD Operator</th>
                    <th>JD Anestesi</th>
                    <th>JD Anak</th>
                    <th>JD Visit</th>
                    <th>JD Telpon</th>
                    <th>JD USG</th>
                    <th>JD Rontgen</th>
                    <th>JD Lab</th>
                    <th>JD PA</th>
                    <th>HD</th>
                    <th>JK</th>
                    <th>BHP</th>
                    <th>OBAT</th>
                    <th>LAB PK</th>
                    <th>LAB PA</th>
                    <th>RAD USG</th>
                    <th>Rontgen</th>
                    <th>Fisio</th>
                    <th>EKG</th>
                    <th>Darah</th>
                    <th>Makan Jumlah</th>
                    <th>Makan Harga</th>
                    <th>Makan Kali</th>
                    <th>Phototherapy</th>
                    <th>Oksigen</th>
                    <th>Spirometri</th>
                    <th>Total</th>
                    <th>Margin</th>
                    <th>Darah</th>
                    <th>Albumin</th>
                    <th>Tindakan</th>
                    <th>SEP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="46" class="analytics-empty">Tidak ada data pada periode ini.</td></tr>
                <?php else: $no = 1; foreach ($rows as $row): ?>
                    <tr>
                        <td style="text-align:center;"><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($row['no_rawat'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['no_rkm_medis'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_pasien_umur'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['diagnosa_awal'] ?: $row['diagnosa_sep'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['diagnosa_akhir'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['tanggal_masuk'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['tanggal_keluar'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['status_pulang'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['dpjp'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['kamar'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['claim']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['dokter_ugd']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['jd_dpjp']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['jd_operator']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['jd_anestesi']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['jd_anak']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['jd_visit']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['jd_telpon']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['jd_usg']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['jd_rontgen']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['jd_lab']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['jd_pa']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['hd']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['jk']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['bhp']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['obat']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['lab_pk']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['lab_pa']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['rad_usg']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['rontgen']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['fisio']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['ekg']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['darah']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['makan_jumlah']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['makan_harga']); ?></td>
                        <td style="text-align:center;"><?php echo aptd_number($row['makan_kali']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['phototherapy']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['oksigen']); ?></td>
                        <td style="text-align:right;"><?php echo aptd_currency($row['spirometri']); ?></td>
                        <td style="text-align:right;font-weight:bold;"><?php echo aptd_currency($row['total_biaya_laporan']); ?></td>
                        <td style="text-align:right;font-weight:bold;"><?php echo aptd_currency($row['margin']); ?></td>
                        <td style="text-align:center;"><?php echo htmlspecialchars($row['ket_darah'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="text-align:center;"><?php echo htmlspecialchars($row['ket_albumin'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="text-align:center;"><?php echo htmlspecialchars($row['ket_tindakan'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['no_sep'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php $table = ob_get_clean();

aptd_render_shell([
    'title' => 'Laporan Keuangan Rawat Inap',
    'subtitle' => 'Tarikan detail pasien BPJS rawat inap untuk rekap jasa dokter dan komponen keuangan per nomor rawat.',
    'filters' => $filters,
    'cards' => $cards,
    'panels' => $panels,
    'table' => $table,
]);
?>
