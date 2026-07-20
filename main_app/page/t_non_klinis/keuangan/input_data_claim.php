<?php
require_once __DIR__ . '/laporan_keuangan_ranap_helper.php';

list($month, $year, $startDate, $endDate) = aptd_keu_ranap_date_filter(false);
$monthLabels = aptd_month_labels_local();
$saveMessage = null;
$levelLogin = isset($_SESSION['level']) ? $_SESSION['level'] : '';
$canEditClaim = in_array($levelLogin, ['admin', 'rekammedis'], true);
if (isset($_POST['save_claim']) && $_POST['save_claim'] === '1') {
    $claimPage = isset($_POST['claim_page']) ? max(0, (int) $_POST['claim_page']) : 0;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claimPage = 0;
} else {
    $claimPage = isset($_GET['claim_page']) ? max(0, (int) $_GET['claim_page']) : 0;
}

if (isset($_POST['save_claim']) && $_POST['save_claim'] === '1') {
    $saveMessage = aptd_keu_ranap_save_claim(
        $mysqli,
        isset($_POST['manual_no_rawat']) ? $_POST['manual_no_rawat'] : '',
        isset($_POST['manual_claim']) ? $_POST['manual_claim'] : 0,
        $canEditClaim
    );
    $_SESSION['claim_ranap_flash'] = $saveMessage;
    $redirectUrl = 'main_app.php?page=input_data_claim&month=' . rawurlencode($month) . '&year=' . rawurlencode($year) . '&claim_page=' . rawurlencode($claimPage);
    echo '<script>window.location.href=' . json_encode($redirectUrl) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    return;
}

if (isset($_SESSION['claim_ranap_flash'])) {
    $saveMessage = $_SESSION['claim_ranap_flash'];
    unset($_SESSION['claim_ranap_flash']);
}

$rows = aptd_keu_ranap_fetch_claim_rows($mysqli, $startDate, $endDate);
?>

<section class="claim-panel">
    <?php if ($saveMessage): ?>
        <div class="alert <?php echo $saveMessage['success'] ? 'alert-success' : 'alert-danger'; ?> mb-3" id="info">
            <?php echo htmlspecialchars($saveMessage['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="claim-head">
        <div>
            <h2>Input Data Claim</h2>
            <p>Input nilai claim pasien rawat inap BPJS. Tarif INA-CBG ditampilkan sebagai fallback sampai claim manual disimpan.</p>
        </div>
        <form method="post" class="claim-filter">
            <select name="month" class="form-control form-control-sm">
                <?php foreach ($monthLabels as $n => $label): ?>
                    <option value="<?php echo $n; ?>" <?php echo $month === $n ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="year" class="form-control form-control-sm">
                <?php for ($y = 2020; $y <= ((int) date('Y') + 1); $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo $year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
        </form>
    </div>

    <style>
        .claim-panel{background:#fff;border:1px solid rgba(120,155,220,.18);border-radius:16px;padding:18px;margin-top:16px;box-shadow:0 12px 28px rgba(74,101,145,.10)}
        .claim-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:18px}
        .claim-head h2{margin:0;color:#123d73;font-size:22px;font-weight:800}
        .claim-head p{margin:4px 0 0;color:#55729d;font-size:13px}
        .claim-filter{display:flex;gap:8px;align-items:center}
        .claim-filter .form-control{width:auto;min-width:110px}
        .claim-table-wrap{overflow-x:auto;padding-bottom:10px}
        .claim-table{min-width:1424px;width:1424px!important;margin:0!important;table-layout:fixed;background:#fff;color:#000;border-collapse:separate!important;border-spacing:0}
        .claim-table th,.claim-table td{border:1px solid #111!important;padding:6px 8px!important;vertical-align:middle!important;font-size:13px;line-height:1.25;white-space:nowrap}
        .claim-table thead th{background:#d9e2f3!important;text-align:center;font-weight:800;white-space:normal}
        .claim-table .col-rawat{width:155px}
        .claim-table .col-rm{width:88px}
        .claim-table .col-name{width:260px;overflow:hidden;text-overflow:ellipsis}
        .claim-table .col-diagnosa{width:200px;overflow:hidden;text-overflow:ellipsis}
        .claim-table .col-date{width:104px;text-align:center}
        .claim-table .col-status{width:140px;overflow:hidden;text-overflow:ellipsis}
        .claim-table .col-dpjp{width:220px;overflow:hidden;text-overflow:ellipsis}
        .claim-table .num{width:104px;text-align:right}
        .claim-rawat-btn{display:inline-flex;align-items:center;border:0;border-radius:4px;background:#256ec7;color:#fff;font-size:12px;font-weight:800;padding:4px 8px;cursor:pointer}
        .claim-rawat-btn:hover{background:#174f94;color:#fff}
        @media(max-width:768px){.claim-panel{padding:12px}.claim-head{display:block}.claim-filter{margin-top:12px;flex-wrap:wrap}.claim-table{min-width:1284px;width:1284px!important}.claim-table th,.claim-table td{font-size:12px;padding:5px 7px!important}}
    </style>

    <div class="claim-table-wrap">
        <table class="table table-sm table-bordered table-hover claim-table" id="table4">
            <thead>
                <tr>
                    <th class="col-rawat">No Rawat</th>
                    <th class="col-rm">No RM</th>
                    <th class="col-name">Nama Pasien</th>
                    <th class="col-diagnosa">Diagnosa Awal</th>
                    <th class="col-diagnosa">Diagnosa Akhir</th>
                    <th class="col-date">Tanggal Masuk</th>
                    <th class="col-date">Tanggal Keluar</th>
                    <th class="col-status">Status Pulang</th>
                    <th class="col-dpjp">DPJP</th>
                    <th class="num">Claim</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="10" style="text-align:center;padding:22px!important;">Tidak ada data pada periode ini.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td class="col-rawat">
                            <button type="button"
                                    class="claim-rawat-btn"
                                    data-toggle="modal"
                                    data-target="#modalClaim"
                                    data-no-rawat="<?php echo htmlspecialchars($row['no_rawat'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-claim="<?php echo htmlspecialchars((string) $row['claim'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($row['no_rawat'], ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                        </td>
                        <td class="col-rm"><?php echo htmlspecialchars($row['no_rkm_medis'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-name" title="<?php echo htmlspecialchars($row['nama_pasien_umur'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['nama_pasien_umur'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-diagnosa" title="<?php echo htmlspecialchars($row['diagnosa_awal'] ?: $row['diagnosa_sep'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['diagnosa_awal'] ?: $row['diagnosa_sep'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-diagnosa" title="<?php echo htmlspecialchars($row['diagnosa_akhir'] ?: '-', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['diagnosa_akhir'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-date"><?php echo htmlspecialchars($row['tanggal_masuk'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-date"><?php echo htmlspecialchars($row['tanggal_keluar'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-status" title="<?php echo htmlspecialchars($row['status_pulang'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['status_pulang'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-dpjp" title="<?php echo htmlspecialchars($row['dpjp'] ?: '-', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['dpjp'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num"><?php echo aptd_currency($row['claim']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal fade" id="modalClaim" tabindex="-1" role="dialog" aria-labelledby="modalClaimLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="post" class="modal-content">
            <input type="hidden" name="save_claim" value="1">
            <input type="hidden" name="month" value="<?php echo (int) $month; ?>">
            <input type="hidden" name="year" value="<?php echo (int) $year; ?>">
            <input type="hidden" name="claim_page" id="claim_page" value="<?php echo (int) $claimPage; ?>">
            <div class="modal-header">
                <h5 class="modal-title" id="modalClaimLabel">Input Data Claim</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="manual_no_rawat"><strong>No Rawat</strong></label>
                    <input type="text" class="form-control" name="manual_no_rawat" id="manual_no_rawat" readonly>
                </div>
                <div class="form-group mb-0">
                    <label for="manual_claim"><strong>Jumlah Claim</strong></label>
                    <input type="number" class="form-control" name="manual_claim" id="manual_claim" min="0" step="0.01" <?php echo $canEditClaim ? '' : 'readonly'; ?> required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                <?php if ($canEditClaim): ?>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
    (function(){
        document.addEventListener('DOMContentLoaded', function() {
            if (!window.jQuery) { return; }
            var targetPage = <?php echo (int) $claimPage; ?>;
            var pageLength = 10;
            var storageKey = 'aptd_input_claim_page_<?php echo (int) $year; ?>_<?php echo (int) $month; ?>';
            var storedPage = parseInt(window.sessionStorage ? (sessionStorage.getItem(storageKey) || '0') : '0', 10);
            if (targetPage <= 0 && storedPage > 0) {
                targetPage = storedPage;
            }

            var language = {
                decimal: '',
                sEmptyTable: 'Tidak ada data yang tersedia pada tabel ini',
                sProcessing: 'Sedang memproses...',
                sLengthMenu: 'Tampilkan _MENU_ entri',
                sZeroRecords: 'Tidak ditemukan data yang sesuai',
                sInfo: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ entri',
                sInfoEmpty: 'Menampilkan 0 sampai 0 dari 0 entri',
                sInfoFiltered: '(disaring dari _MAX_ entri keseluruhan)',
                sInfoPostFix: '',
                sSearch: '',
                searchPlaceholder: 'Cari Data..',
                sUrl: '',
                oPaginate: {
                    sFirst: 'Pertama',
                    sPrevious: 'Sebelumnya',
                    sNext: 'Selanjutnya',
                    sLast: 'Terakhir'
                }
            };

            var initClaimTable = function() {
                if (!$.fn.DataTable || !$('#table4').length) { return false; }
                if (!$.fn.DataTable.isDataTable('#table4')) {
                    $('#table4').DataTable({
                        lengthChange: false,
                        paging: true,
                        pagingType: 'numbers',
                        scrollCollapse: true,
                        ordering: true,
                        info: true,
                        displayStart: Math.max(0, targetPage) * pageLength,
                        language: language
                    });
                }
                return true;
            };

            var restoreClaimPage = function() {
                if (!$.fn.DataTable || !$.fn.DataTable.isDataTable('#table4')) { return false; }
                var table = $('#table4').DataTable();
                var pageInfo = table.page.info();
                if (targetPage > 0 && targetPage < pageInfo.pages) {
                    table.page(targetPage).draw(false);
                }
                return true;
            };

            if (!initClaimTable() || !restoreClaimPage()) {
                setTimeout(function() { initClaimTable(); restoreClaimPage(); }, 50);
                setTimeout(restoreClaimPage, 150);
                setTimeout(restoreClaimPage, 400);
            }

            var syncClaimPage = function() {
                if ($.fn.DataTable && $.fn.DataTable.isDataTable('#table4')) {
                    var currentPage = $('#table4').DataTable().page();
                    $('#claim_page').val(currentPage);
                    if (window.sessionStorage) {
                        sessionStorage.setItem(storageKey, currentPage);
                    }
                }
            };

            $('#table4').on('page.dt draw.dt', syncClaimPage);

            $('#modalClaim').on('show.bs.modal', function(event) {
                syncClaimPage();
                var button = $(event.relatedTarget);
                $('#manual_no_rawat').val(button.data('no-rawat') || '');
                $('#manual_claim').val(button.data('claim') || 0);
            });

            $('#modalClaim form').on('submit', function() {
                syncClaimPage();
            });
        });
    })();
</script>
