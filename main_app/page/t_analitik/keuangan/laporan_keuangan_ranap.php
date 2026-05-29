<?php
require_once __DIR__ . '/laporan_keuangan_ranap_helper.php';

list($month, $year, $startDate, $endDate) = aptd_keu_ranap_date_filter();
$monthLabels = aptd_month_labels_local();
$saveMessage = null;
if (isset($_POST['save_keu_manual']) && $_POST['save_keu_manual'] === '1') {
    $saveMessage = aptd_keu_ranap_save_manual(
        $mysqli,
        isset($_POST['manual_no_rawat']) ? $_POST['manual_no_rawat'] : '',
        isset($_POST['manual_claim']) ? $_POST['manual_claim'] : 0,
        isset($_POST['manual_jd_operator']) ? $_POST['manual_jd_operator'] : 0
    );
    $_SESSION['keu_ranap_flash'] = $saveMessage;
    $redirectUrl = 'main_app.php?page=laporan_keuangan_ranap&month=' . rawurlencode($month) . '&year=' . rawurlencode($year);
    echo '<script>window.location.href=' . json_encode($redirectUrl) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    return;
}
if (isset($_SESSION['keu_ranap_flash'])) {
    $saveMessage = $_SESSION['keu_ranap_flash'];
    unset($_SESSION['keu_ranap_flash']);
}
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
<section class="analytics-panel keu-ranap-panel">
    <?php if ($saveMessage): ?>
        <div class="alert <?php echo $saveMessage['success'] ? 'alert-success' : 'alert-danger'; ?> mb-3" id="info">
            <?php echo htmlspecialchars($saveMessage['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>
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
        .keu-ranap-panel{overflow:hidden}
        .keu-ranap-scroll{width:100%;max-width:100%;overflow-x:auto;overflow-y:hidden;padding-bottom:8px;background:#fff}
        .keu-ranap-scroll::-webkit-scrollbar{height:12px}
        .keu-ranap-scroll::-webkit-scrollbar-thumb{background:#8b97aa;border-radius:999px;border:2px solid #eef2f7}
        .keu-ranap-scroll .dataTables_wrapper{width:100%;min-width:0}
        .keu-ranap-scroll .dataTables_wrapper>.row{margin-left:0;margin-right:0}
        .keu-ranap-scroll .dataTables_wrapper>.row>[class^="col-"],.keu-ranap-scroll .dataTables_wrapper>.row>[class*=" col-"]{padding-left:0;padding-right:0}
        .keu-ranap-scroll .dataTables_filter{padding-bottom:8px}
        .keu-ranap-scroll .dataTables_info{padding-top:8px}
        .keu-ranap-scroll .dataTables_paginate{padding-top:6px}
        .keu-ranap-table{min-width:4780px;width:max-content!important;margin:0!important;white-space:nowrap;border-collapse:collapse!important;background:#fff;color:#000}
        .keu-ranap-table th,.keu-ranap-table td{vertical-align:middle!important;border:1px solid #111!important;padding:2px 5px!important;line-height:1.15}
        .keu-ranap-table thead th{position:sticky;top:0;z-index:2;background:#d9e2f3!important;color:#000!important;text-align:center;font-weight:700}
        .keu-ranap-table thead tr:first-child th{background:#cfd8e8!important}
        .keu-ranap-table tbody td{font-size:11px}
        .keu-ranap-table .col-rawat{width:118px}
        .keu-ranap-table .col-rm{width:70px}
        .keu-ranap-table .col-name{width:180px}
        .keu-ranap-table .col-diagnosa{width:170px;max-width:170px;overflow:hidden;text-overflow:ellipsis}
        .keu-ranap-table .col-date{width:82px;text-align:center}
        .keu-ranap-table .col-status{width:110px}
        .keu-ranap-table .col-dpjp{width:170px}
        .keu-ranap-table .col-ket-dpjp{width:190px;max-width:190px;overflow:hidden;text-overflow:ellipsis}
        .keu-ranap-table .col-ket-anestesi{width:210px;max-width:210px;overflow:hidden;text-overflow:ellipsis}
        .keu-ranap-table .col-ket-anak{width:210px;max-width:210px;overflow:hidden;text-overflow:ellipsis}
        .keu-ranap-table .col-ket-visite{width:240px;max-width:240px;overflow:hidden;text-overflow:ellipsis}
        .keu-ranap-table .col-kamar{width:105px}
        .keu-ranap-table .num{width:76px;text-align:right}
        .keu-ranap-table .flag{width:58px;text-align:center}
        .keu-rawat-btn{display:inline-flex;align-items:center;gap:4px;border:0;border-radius:4px;background:#256ec7;color:#fff;font-size:10px;font-weight:700;padding:2px 6px;cursor:pointer}
        .keu-rawat-btn:hover{background:#174f94;color:#fff}
    </style>
    <div class="keu-ranap-scroll">
        <table class="table table-sm table-bordered table-hover analytics-table keu-ranap-table" id="table4" style="width:100%;font-size:11px;">
            <thead>
                <tr>
                    <th rowspan="2" class="col-rawat">No Rawat</th>
                    <th rowspan="2" class="col-rm">No RM</th>
                    <th rowspan="2" class="col-name">Nama Pasien</th>
                    <th rowspan="2" class="col-diagnosa">Diagnosa Awal</th>
                    <th rowspan="2" class="col-diagnosa">Diagnosa Akhir</th>
                    <th rowspan="2" class="col-date">Tanggal Masuk</th>
                    <th rowspan="2" class="col-date">Tanggal Keluar</th>
                    <th rowspan="2" class="col-status">Status Pulang</th>
                    <th rowspan="2" class="col-dpjp">DPJP</th>
                    <th rowspan="2" class="col-kamar">Kamar</th>
                    <th rowspan="2" class="num">CLAIM</th>
                    <th colspan="18">Jasa Dokter</th>
                    <th rowspan="2" class="num">JK</th>
                    <th rowspan="2" class="num">BHP</th>
                    <th rowspan="2" class="num">OBAT</th>
                    <th colspan="7">Penunjang</th>
                    <th colspan="3">MAKAN</th>
                    <th rowspan="2" class="num">Phototherapy</th>
                    <th rowspan="2" class="num">Oksigen</th>
                    <th rowspan="2" class="num">Spirometri</th>
                    <th rowspan="2" class="num">TOTAL</th>
                    <th rowspan="2" class="num">MARGIN</th>
                    <th colspan="4">Keterangan</th>
                </tr>
                <tr>
                    <th class="num">Dokter UGD</th>
                    <th class="num">JD DPJP</th>
                    <th class="col-ket-dpjp">Ket. JD DPJP</th>
                    <th class="num">JD Operator</th>
                    <th class="num">JD Anestesi</th>
                    <th class="col-ket-anestesi">Ket. JD Anestesi</th>
                    <th class="num">JD Anak</th>
                    <th class="col-ket-anak">Ket. JD Anak</th>
                    <th class="num">JD Visite</th>
                    <th class="num">JD Visite Umum</th>
                    <th class="num">JD Visite Spesialis</th>
                    <th class="col-ket-visite">Ket. JD Visite</th>
                    <th class="num">JD Telp</th>
                    <th class="num">JD USG</th>
                    <th class="num">JD Rontgen</th>
                    <th class="num">JD Lab</th>
                    <th class="num">JD PA</th>
                    <th class="num">HD</th>
                    <th class="num">LAB PK</th>
                    <th class="num">LAB PA</th>
                    <th class="num">Rad USG</th>
                    <th class="num">Rontgen</th>
                    <th class="num">Fisio</th>
                    <th class="num">EKG</th>
                    <th class="num">Darah</th>
                    <th class="num">Jumlah</th>
                    <th class="num">Harga</th>
                    <th class="flag">Kali</th>
                    <th class="flag">DARAH</th>
                    <th class="flag">ALBU</th>
                    <th class="flag">TINDA</th>
                    <th class="col-status">SEP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="51" class="analytics-empty">Tidak ada data pada periode ini.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td class="col-rawat">
                            <button type="button"
                                    class="keu-rawat-btn"
                                    data-toggle="modal"
                                    data-target="#modalKeuManual"
                                    data-no-rawat="<?php echo htmlspecialchars($row['no_rawat'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-claim="<?php echo htmlspecialchars((string) $row['claim'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-jd-operator="<?php echo htmlspecialchars((string) $row['jd_operator'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($row['no_rawat'], ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                        </td>
                        <td class="col-rm"><?php echo htmlspecialchars($row['no_rkm_medis'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-name"><?php echo htmlspecialchars($row['nama_pasien_umur'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-diagnosa"><?php echo htmlspecialchars($row['diagnosa_awal'] ?: $row['diagnosa_sep'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-diagnosa"><?php echo htmlspecialchars($row['diagnosa_akhir'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-date"><?php echo htmlspecialchars($row['tanggal_masuk'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-date"><?php echo htmlspecialchars($row['tanggal_keluar'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['status_pulang'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['dpjp'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['kamar'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num"><?php echo aptd_currency($row['claim']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['dokter_ugd']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_dpjp']); ?></td>
                        <td class="col-ket-dpjp" title="<?php echo htmlspecialchars($row['ket_dpjp'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['ket_dpjp'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_operator']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_anestesi']); ?></td>
                        <td class="col-ket-anestesi" title="<?php echo htmlspecialchars($row['ket_anestesi'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['ket_anestesi'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_anak']); ?></td>
                        <td class="col-ket-anak" title="<?php echo htmlspecialchars($row['ket_anak'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['ket_anak'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_visit']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_visit_umum']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_visit_spesialis']); ?></td>
                        <td class="col-ket-visite" title="<?php echo htmlspecialchars($row['ket_visit'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['ket_visit'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_telpon']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_usg']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_rontgen']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_lab']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jd_pa']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['hd']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['jk']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['bhp']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['obat']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['lab_pk']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['lab_pa']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['rad_usg']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['rontgen']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['fisio']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['ekg']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['darah']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['makan_jumlah']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['makan_harga']); ?></td>
                        <td class="flag"><?php echo aptd_number($row['makan_kali']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['phototherapy']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['oksigen']); ?></td>
                        <td class="num"><?php echo aptd_currency($row['spirometri']); ?></td>
                        <td class="num" style="font-weight:bold;"><?php echo aptd_currency($row['total_biaya_laporan']); ?></td>
                        <td class="num" style="font-weight:bold;"><?php echo aptd_currency($row['margin']); ?></td>
                        <td class="flag"><?php echo htmlspecialchars($row['ket_darah'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="flag"><?php echo htmlspecialchars($row['ket_albumin'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="flag"><?php echo htmlspecialchars($row['ket_tindakan'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['no_sep'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="modal fade" id="modalKeuManual" tabindex="-1" role="dialog" aria-labelledby="modalKeuManualLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" class="modal-content">
                <input type="hidden" name="save_keu_manual" value="1">
                <input type="hidden" name="month" value="<?php echo (int) $month; ?>">
                <input type="hidden" name="year" value="<?php echo (int) $year; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalKeuManualLabel">Input Data Keuangan BPJS</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="manual_no_rawat"><strong>No Rawat</strong></label>
                        <input type="text" class="form-control" name="manual_no_rawat" id="manual_no_rawat" readonly>
                    </div>
                    <div class="form-group">
                        <label for="manual_claim"><strong>Nilai / Jumlah Claim</strong></label>
                        <input type="number" class="form-control" name="manual_claim" id="manual_claim" min="0" step="0.01" required>
                    </div>
                    <div class="form-group mb-0">
                        <label for="manual_jd_operator"><strong>Nilai / Jumlah JD Operator</strong></label>
                        <input type="number" class="form-control" name="manual_jd_operator" id="manual_jd_operator" min="0" step="0.01" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        (function(){
            document.addEventListener('DOMContentLoaded', function() {
                if (!window.jQuery) { return; }
                $('#modalKeuManual').on('show.bs.modal', function(event) {
                    var button = $(event.relatedTarget);
                    $('#manual_no_rawat').val(button.data('no-rawat') || '');
                    $('#manual_claim').val(button.data('claim') || 0);
                    $('#manual_jd_operator').val(button.data('jd-operator') || 0);
                });
            });
        })();
    </script>
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
