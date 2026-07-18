<?php
require_once __DIR__ . '/diagnosa_awal_sementara_ranap_helper.php';

list($month, $year, $startDate, $endDate) = aptd_diag_awal_ranap_date_filter();
$monthLabels = aptd_month_labels_local();
$levelLogin = isset($_SESSION['level']) ? $_SESSION['level'] : '';
$namaLogin = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : '';
$canInputDiagnosa = $levelLogin === 'perawat' || $levelLogin === 'admin';
$saveMessage = null;

if (isset($_POST['save_diagnosa_awal_sementara']) && $_POST['save_diagnosa_awal_sementara'] === '1') {
    $diagnosaPage = isset($_POST['diagnosa_page']) ? max(0, (int) $_POST['diagnosa_page']) : 0;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $diagnosaPage = 0;
} else {
    $diagnosaPage = isset($_GET['diagnosa_page']) ? max(0, (int) $_GET['diagnosa_page']) : 0;
}

if (isset($_POST['save_diagnosa_awal_sementara']) && $_POST['save_diagnosa_awal_sementara'] === '1') {
    if (!$canInputDiagnosa) {
        $saveMessage = ['success' => false, 'message' => 'Level Anda tidak memiliki akses untuk menyimpan diagnosa awal sementara.'];
    } else {
        $saveMessage = aptd_diag_awal_ranap_save(
            $mysqli,
            isset($_POST['manual_no_rawat']) ? $_POST['manual_no_rawat'] : '',
            isset($_POST['kode_icd']) ? $_POST['kode_icd'] : '',
            $namaLogin
        );
    }

    $_SESSION['diagnosa_awal_ranap_flash'] = $saveMessage;
    $redirectUrl = 'main_app.php?page=diagnosa_awal_sementara_ranap&month=' . rawurlencode($month) . '&year=' . rawurlencode($year) . '&diagnosa_page=' . rawurlencode($diagnosaPage);
    echo '<script>window.location.href=' . json_encode($redirectUrl) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    return;
}

if (isset($_SESSION['diagnosa_awal_ranap_flash'])) {
    $saveMessage = $_SESSION['diagnosa_awal_ranap_flash'];
    unset($_SESSION['diagnosa_awal_ranap_flash']);
}

$rows = aptd_diag_awal_ranap_fetch_rows($mysqli, $startDate, $endDate);
?>

<section class="diag-panel">
    <?php if ($saveMessage): ?>
        <div class="alert <?php echo $saveMessage['success'] ? 'alert-success' : 'alert-danger'; ?> mb-3" id="info">
            <?php echo htmlspecialchars($saveMessage['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="diag-head">
        <div>
            <h2>Input Diagnosa Awal Sementara Ranap</h2>
            <p>Form khusus perawat untuk memilih satu kode ICD diagnosa awal sementara pada pasien rawat inap BPJS.</p>
        </div>
        <form method="post" class="diag-filter">
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

    <div class="diag-note">
        Halaman ini hanya menampilkan identitas pasien rawat inap dan data SEP. Tidak ada nominal claim atau kalkulasi biaya yang ditarik pada fitur ini.
    </div>

    <style>
        .diag-panel{background:#fff;border:1px solid rgba(120,155,220,.18);border-radius:16px;padding:18px;margin-top:16px;box-shadow:0 12px 28px rgba(74,101,145,.10)}
        .diag-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:14px}
        .diag-head h2{margin:0;color:#123d73;font-size:22px;font-weight:800}
        .diag-head p{margin:4px 0 0;color:#55729d;font-size:13px}
        .diag-filter{display:flex;gap:8px;align-items:center}
        .diag-filter .form-control{width:auto;min-width:110px}
        .diag-note{border:1px solid #ffd580;background:#fff8e7;color:#8a5b00;border-radius:12px;padding:12px 14px;margin-bottom:16px;font-size:13px}
        .diag-table-wrap{overflow-x:auto;padding-bottom:10px}
        .diag-table{min-width:1540px;width:1540px!important;margin:0!important;table-layout:fixed;background:#fff;color:#000;border-collapse:separate!important;border-spacing:0}
        .diag-table th,.diag-table td{border:1px solid #111!important;padding:7px 8px!important;vertical-align:middle!important;font-size:13px;line-height:1.3;white-space:nowrap}
        .diag-table thead th{background:#d9e2f3!important;text-align:center;font-weight:800;white-space:normal}
        .diag-table .col-rawat{width:150px}
        .diag-table .col-rm{width:86px}
        .diag-table .col-name{width:245px;overflow:hidden;text-overflow:ellipsis}
        .diag-table .col-sep{width:180px;overflow:hidden;text-overflow:ellipsis}
        .diag-table .col-diagnosa{width:230px;overflow:hidden;text-overflow:ellipsis}
        .diag-table .col-date{width:105px;text-align:center}
        .diag-table .col-status{width:130px;overflow:hidden;text-overflow:ellipsis}
        .diag-table .col-kamar{width:130px;overflow:hidden;text-overflow:ellipsis}
        .diag-table .col-dpjp{width:210px;overflow:hidden;text-overflow:ellipsis}
        .diag-table .col-updated{width:150px;overflow:hidden;text-overflow:ellipsis}
        .diag-rawat-btn{display:inline-flex;align-items:center;border:0;border-radius:4px;background:#256ec7;color:#fff;font-size:12px;font-weight:800;padding:4px 8px;cursor:pointer}
        .diag-rawat-btn:hover{background:#174f94;color:#fff}
        .diag-badge{display:inline-flex;align-items:center;border-radius:999px;padding:4px 8px;font-weight:800;font-size:11px}
        .diag-badge-filled{background:#dcf5ea;color:#176747}
        .diag-badge-empty{background:#eef2f7;color:#60758f}
        .diag-text{white-space:normal;max-height:46px;overflow:hidden}
        .diag-icd-search{position:relative}
        .diag-icd-results{display:none;position:absolute;left:0;right:0;top:100%;z-index:1065;max-height:190px;overflow-y:auto;background:#fff;border:1px solid #b8c5d6;border-top:0;box-shadow:0 8px 18px rgba(28,48,78,.16)}
        .diag-icd-item{display:block;width:100%;border:0;border-bottom:1px solid #eef2f7;background:#fff;text-align:left;padding:8px 10px;font-size:13px;color:#12263f;cursor:pointer}
        .diag-icd-item:hover{background:#edf5ff}
        .diag-icd-empty{padding:8px 10px;font-size:13px;color:#6b7c90}
        @media(max-width:768px){.diag-panel{padding:12px}.diag-head{display:block}.diag-filter{margin-top:12px;flex-wrap:wrap}.diag-table{min-width:1320px;width:1320px!important}.diag-table th,.diag-table td{font-size:12px;padding:5px 7px!important}}
    </style>

    <div class="diag-table-wrap">
        <table class="table table-sm table-bordered table-hover diag-table" id="tableDiagnosaAwalRanap">
            <thead>
                <tr>
                    <th class="col-rawat">No Rawat</th>
                    <th class="col-rm">No RM</th>
                    <th class="col-name">Nama Pasien</th>
                    <th class="col-sep">SEP</th>
                    <th class="col-diagnosa">ICD Sementara</th>
                    <th class="col-diagnosa">Diagnosa Awal Kamar</th>
                    <th class="col-diagnosa">Diagnosa SEP</th>
                    <th class="col-date">Tanggal Masuk</th>
                    <th class="col-date">Tanggal Keluar</th>
                    <th class="col-status">Status Pulang</th>
                    <th class="col-kamar">Kamar</th>
                    <th class="col-dpjp">DPJP</th>
                    <th class="col-updated">Update</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="13" style="text-align:center;padding:22px!important;">Tidak ada data rawat inap BPJS pada periode ini.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <?php
                    $kodeIcd = trim((string) $row['kode_icd']);
                    $namaPenyakit = trim((string) $row['nama_penyakit']);
                    $icdText = $kodeIcd !== '' ? $kodeIcd . ($namaPenyakit !== '' ? ' - ' . $namaPenyakit : '') : '';
                    $diagnosaAwalKamar = trim((string) $row['diagnosa_awal']);
                    $diagnosaReferensi = trim((string) ($row['diagnosa_sep'] ?: $row['diagnosa_awal']));
                    ?>
                    <tr>
                        <td class="col-rawat">
                            <button type="button"
                                    class="diag-rawat-btn"
                                    data-toggle="modal"
                                    data-target="#modalDiagnosaAwal"
                                    data-no-rawat="<?php echo htmlspecialchars($row['no_rawat'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-no-sep="<?php echo htmlspecialchars($row['no_sep'] ?: '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-nama="<?php echo htmlspecialchars($row['nama_pasien_umur'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-diagnosa-awal="<?php echo htmlspecialchars($diagnosaAwalKamar ?: '-', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-kode-icd="<?php echo htmlspecialchars($kodeIcd, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-icd-text="<?php echo htmlspecialchars($icdText, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($row['no_rawat'], ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                        </td>
                        <td class="col-rm"><?php echo htmlspecialchars($row['no_rkm_medis'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-name" title="<?php echo htmlspecialchars($row['nama_pasien_umur'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['nama_pasien_umur'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-sep" title="<?php echo htmlspecialchars($row['no_sep'] ?: '-', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['no_sep'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-diagnosa" title="<?php echo htmlspecialchars($icdText ?: 'Belum diisi', ENT_QUOTES, 'UTF-8'); ?>">
                            <?php if ($kodeIcd !== ''): ?>
                                <div class="diag-text"><?php echo htmlspecialchars($icdText, ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php else: ?>
                                <span class="diag-badge diag-badge-empty">Belum diisi</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-diagnosa" title="<?php echo htmlspecialchars($diagnosaAwalKamar ?: '-', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($diagnosaAwalKamar ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-diagnosa" title="<?php echo htmlspecialchars($diagnosaReferensi ?: '-', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($diagnosaReferensi ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-date"><?php echo htmlspecialchars($row['tanggal_masuk'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-date"><?php echo htmlspecialchars($row['tanggal_keluar'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-status" title="<?php echo htmlspecialchars($row['status_pulang'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['status_pulang'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-kamar" title="<?php echo htmlspecialchars($row['kamar'] ?: '-', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['kamar'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-dpjp" title="<?php echo htmlspecialchars($row['dpjp'] ?: '-', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['dpjp'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="col-updated">
                            <?php if ($kodeIcd !== ''): ?>
                                <span class="diag-badge diag-badge-filled">Tersimpan</span>
                                <div title="<?php echo htmlspecialchars($row['diagnosa_updated_by'] ?: $row['diagnosa_created_by'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['diagnosa_updated_at'] ?: $row['diagnosa_created_at'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal fade" id="modalDiagnosaAwal" tabindex="-1" role="dialog" aria-labelledby="modalDiagnosaAwalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="post" class="modal-content">
            <input type="hidden" name="save_diagnosa_awal_sementara" value="1">
            <input type="hidden" name="month" value="<?php echo (int) $month; ?>">
            <input type="hidden" name="year" value="<?php echo (int) $year; ?>">
            <input type="hidden" name="diagnosa_page" id="diagnosa_page" value="<?php echo (int) $diagnosaPage; ?>">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDiagnosaAwalLabel">Input Diagnosa Awal Sementara</h5>
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
                    <label for="manual_no_sep"><strong>No SEP</strong></label>
                    <input type="text" class="form-control" id="manual_no_sep" readonly>
                </div>
                <div class="form-group">
                    <label for="manual_nama_pasien"><strong>Nama Pasien</strong></label>
                    <input type="text" class="form-control" id="manual_nama_pasien" readonly>
                </div>
                <div class="form-group">
                    <label for="manual_diagnosa_awal"><strong>Diagnosa Awal dari Kamar Inap</strong></label>
                    <textarea class="form-control" id="manual_diagnosa_awal" rows="3" readonly></textarea>
                    <small class="form-text text-muted">Gunakan teks ini sebagai panduan saat mencari kode ICD.</small>
                </div>
                <div class="form-group mb-0">
                    <label for="kode_icd_search"><strong>Cari Kode ICD / Penyakit</strong></label>
                    <div class="diag-icd-search">
                        <input type="text" class="form-control" id="kode_icd_search" autocomplete="off" placeholder="Ketik kode ICD atau nama penyakit" required <?php echo $canInputDiagnosa ? '' : 'readonly'; ?>>
                        <input type="hidden" name="kode_icd" id="kode_icd" required>
                        <div class="diag-icd-results" id="kode_icd_results"></div>
                    </div>
                    <small class="form-text text-muted">Format pilihan: kode ICD - nama penyakit. Maksimal satu kode ICD per nomor rawat.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                <?php if ($canInputDiagnosa): ?>
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

            var tableSelector = '#tableDiagnosaAwalRanap';
            var targetPage = <?php echo (int) $diagnosaPage; ?>;
            var pageLength = 10;
            var storageKey = 'aptd_diag_awal_ranap_page_<?php echo (int) $year; ?>_<?php echo (int) $month; ?>';
            var storedPage = parseInt(window.sessionStorage ? (sessionStorage.getItem(storageKey) || '0') : '0', 10);
            if (targetPage <= 0 && storedPage > 0) {
                targetPage = storedPage;
            }

            var language = {
                sEmptyTable: 'Tidak ada data yang tersedia pada tabel ini',
                sProcessing: 'Sedang memproses...',
                sLengthMenu: 'Tampilkan _MENU_ entri',
                sZeroRecords: 'Tidak ditemukan data yang sesuai',
                sInfo: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ entri',
                sInfoEmpty: 'Menampilkan 0 sampai 0 dari 0 entri',
                sInfoFiltered: '(disaring dari _MAX_ entri keseluruhan)',
                sSearch: '',
                searchPlaceholder: 'Cari Data..',
                oPaginate: {
                    sFirst: 'Pertama',
                    sPrevious: 'Sebelumnya',
                    sNext: 'Selanjutnya',
                    sLast: 'Terakhir'
                }
            };

            var initTable = function() {
                if (!$.fn.DataTable || !$(tableSelector).length) { return false; }
                if (!$.fn.DataTable.isDataTable(tableSelector)) {
                    $(tableSelector).DataTable({
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

            var syncPage = function() {
                if ($.fn.DataTable && $.fn.DataTable.isDataTable(tableSelector)) {
                    var currentPage = $(tableSelector).DataTable().page();
                    $('#diagnosa_page').val(currentPage);
                    if (window.sessionStorage) {
                        sessionStorage.setItem(storageKey, currentPage);
                    }
                }
            };

            var restorePage = function() {
                if (!$.fn.DataTable || !$.fn.DataTable.isDataTable(tableSelector)) { return false; }

                var table = $(tableSelector).DataTable();
                var info = table.page.info();
                var pageToOpen = Math.max(0, parseInt(targetPage || 0, 10));
                if (info.pages > 0 && pageToOpen >= info.pages) {
                    pageToOpen = info.pages - 1;
                }

                if (pageToOpen > 0 && table.page() !== pageToOpen) {
                    table.page(pageToOpen).draw(false);
                }

                $('#diagnosa_page').val(pageToOpen);
                if (window.sessionStorage) {
                    sessionStorage.setItem(storageKey, pageToOpen);
                }
                return true;
            };

            initTable();
            restorePage();
            setTimeout(restorePage, 80);
            setTimeout(restorePage, 250);

            $(tableSelector).on('page.dt draw.dt', syncPage);
            $(document).on('click', tableSelector + '_paginate .paginate_button', function() {
                setTimeout(syncPage, 0);
                setTimeout(syncPage, 80);
            });
            $(document).on('mousedown click', '.diag-rawat-btn', function() {
                syncPage();
            });

            $('#modalDiagnosaAwal').on('show.bs.modal', function(event) {
                syncPage();
                var button = $(event.relatedTarget);
                var kodeIcd = button.data('kode-icd') || '';
                var icdText = button.data('icd-text') || '';
                $('#manual_no_rawat').val(button.data('no-rawat') || '');
                $('#manual_no_sep').val(button.data('no-sep') || '-');
                $('#manual_nama_pasien').val(button.data('nama') || '');
                $('#manual_diagnosa_awal').val(button.data('diagnosa-awal') || '-');
                $('#kode_icd').val(kodeIcd || '');
                $('#kode_icd_search').val(icdText || '');
                $('#kode_icd_results').hide().empty();
            });

            $('#modalDiagnosaAwal form').on('submit', function() {
                syncPage();
                if (!$('#kode_icd').val()) {
                    alert('Silakan pilih kode ICD dari hasil pencarian terlebih dahulu.');
                    $('#kode_icd_search').focus();
                    return false;
                }
            });

            var searchTimer = null;
            var appBasePath = window.location.pathname.replace(/\/main_app\/.*$/, '/main_app/');
            var searchUrl = appBasePath + 'page/t_klinis/diagnosa_sementara_search_penyakit.php';
            var renderIcdResults = function(items) {
                var results = $('#kode_icd_results');
                results.empty();
                if (!items || !items.length) {
                    results.append('<div class="diag-icd-empty">Data penyakit tidak ditemukan</div>');
                    results.show();
                    return;
                }

                items.forEach(function(item) {
                    $('<button type="button" class="diag-icd-item"></button>')
                        .text(item.text || '')
                        .attr('data-id', item.id || '')
                        .attr('data-text', item.text || '')
                        .appendTo(results);
                });
                results.show();
            };

            $('#kode_icd_search').on('input', function() {
                var term = $.trim($(this).val() || '');
                $('#kode_icd').val('');
                clearTimeout(searchTimer);

                if (term.length < 2) {
                    $('#kode_icd_results').hide().empty();
                    return;
                }

                searchTimer = setTimeout(function() {
                    $('#kode_icd_results').html('<div class="diag-icd-empty">Mencari...</div>').show();
                    $.ajax({
                        url: searchUrl,
                        type: 'GET',
                        dataType: 'json',
                        data: { q: term, term: term },
                        xhrFields: { withCredentials: true }
                    }).done(function(data) {
                        renderIcdResults(data && data.results ? data.results : []);
                    }).fail(function() {
                        $('#kode_icd_results').html('<div class="diag-icd-empty">Gagal memuat data penyakit</div>').show();
                    });
                }, 250);
            });

            $('#kode_icd_results').on('click', '.diag-icd-item', function() {
                $('#kode_icd').val($(this).attr('data-id') || '');
                $('#kode_icd_search').val($(this).attr('data-text') || '');
                $('#kode_icd_results').hide().empty();
            });

            $(document).on('click', function(event) {
                if (!$(event.target).closest('.diag-icd-search').length) {
                    $('#kode_icd_results').hide();
                }
            });
        });
    })();
</script>
