<?php
$projectRoot = dirname(dirname(dirname(__DIR__)));
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once $projectRoot . '/config/akses.php';

$currentLevel = isset($_SESSION['level']) ? (string) $_SESSION['level'] : '';
if (!isset($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true
    || !aptd_can_access($currentLevel, 'rekap_pasien_prmrj')) {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses ke Rekap Pasien PRMRJ.');
}

require_once $projectRoot . '/config/koneksi.php';
require_once __DIR__ . '/rekap_pasien_prmrj_helper.php';

$period = aptd_prmrj_period_from_request();
$search = aptd_prmrj_search_from_request();
$requestedPage = isset($_GET['prmrj_page']) ? max(1, (int) $_GET['prmrj_page']) : 1;
$summary = [
    'rows' => [],
    'total_pasien' => 0,
    'total_kunjungan' => 0,
    'filtered_total' => 0,
    'page' => 1,
    'last_page' => 1,
    'per_page' => 25,
    'query_seconds' => 0,
];
$loadError = '';

if ($period['valid']) {
    try {
        $summary = aptd_prmrj_fetch_summary(
            $mysqli,
            $period['tanggal_awal'],
            $period['tanggal_akhir'],
            $search,
            $requestedPage,
            25
        );
    } catch (Throwable $exception) {
        error_log('Rekap Pasien PRMRJ: ' . $exception->getMessage());
        $loadError = 'Data Rekap Pasien PRMRJ belum dapat dimuat.';
    }
} else {
    $loadError = $period['message'];
}

function aptd_prmrj_page_url($pageNumber, array $period, $search)
{
    return 'main_app.php?' . http_build_query([
        'page' => 'rekap_pasien_prmrj',
        'tanggal_awal' => $period['tanggal_awal'],
        'tanggal_akhir' => $period['tanggal_akhir'],
        'q' => $search,
        'prmrj_page' => $pageNumber,
    ]);
}

$firstShown = $summary['filtered_total'] > 0
    ? (($summary['page'] - 1) * $summary['per_page']) + 1
    : 0;
$lastShown = min($summary['filtered_total'], $summary['page'] * $summary['per_page']);
$detailUrl = 'page/t_klinis/rekap_pasien_prmrj_detail.php';
$exportUrl = 'page/t_klinis/export_rekap_pasien_prmrj_pdf.php';
?>

<br>
<style>
.prmrj-page{display:grid;gap:14px;padding-bottom:42px;color:#22364b}.prmrj-panel,.prmrj-stat{background:#fff;border:1px solid #d8e3ee;border-radius:10px;box-shadow:0 8px 24px rgba(35,58,88,.08)}.prmrj-panel{padding:18px}.prmrj-heading-row{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}.prmrj-title{margin:0;color:#173d67;font-size:26px;font-weight:800}.prmrj-subtitle{margin:5px 0 0;color:#63788e;font-size:13px}.prmrj-period{padding:5px 0 5px 11px;border-left:3px solid #2878c7;color:#536b84;font-size:12px;white-space:nowrap}.prmrj-filter{display:flex;flex-wrap:wrap;align-items:flex-end;gap:10px;margin-top:17px}.prmrj-field{min-width:175px}.prmrj-field.search{flex:1;min-width:230px}.prmrj-field label{display:block;margin:0 0 5px;color:#36516e;font-size:12px;font-weight:700}.prmrj-filter .form-control,.prmrj-filter .btn{height:36px;font-size:12px}.prmrj-actions{display:flex;gap:8px}.prmrj-stat{display:flex;align-items:center;gap:16px;padding:16px 18px;border-left:5px solid #2878c7}.prmrj-stat-icon{display:grid;place-items:center;width:46px;height:46px;border-radius:50%;background:#eaf4ff;color:#1e6db8;font-size:22px;font-weight:800}.prmrj-stat-label{color:#698096;font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}.prmrj-stat-value{margin-top:2px;color:#183b61;font-size:29px;line-height:1;font-weight:800}.prmrj-stat-note{margin-left:auto;color:#71869b;font-size:12px;text-align:right}.prmrj-alert{margin:0;font-size:12px}.prmrj-table-wrap{overflow:hidden;border:1px solid #d8e3ee;border-radius:8px}.prmrj-table{width:100%;margin:0!important}.prmrj-table thead th{padding:11px 14px;background:#263e57;color:#fff;border-color:#40576d;font-size:12px}.prmrj-parent td{padding:0!important;vertical-align:middle;border-color:#e1e8ef!important}.prmrj-parent:nth-of-type(4n+1){background:#f8fafc}.prmrj-expand{display:flex;align-items:center;width:100%;padding:12px 14px;border:0;background:transparent;color:#203951;text-align:left;cursor:pointer}.prmrj-expand:hover,.prmrj-expand:focus{background:#edf6ff;outline:none}.prmrj-chevron{display:inline-flex;align-items:center;justify-content:center;width:25px;height:25px;margin-right:9px;border-radius:50%;background:#e6f0fa;color:#2169aa;font-size:15px;font-weight:800;transition:transform .2s}.prmrj-expand[aria-expanded=true] .prmrj-chevron{transform:rotate(90deg);background:#2878c7;color:#fff}.prmrj-rm{font-weight:800;letter-spacing:.02em}.prmrj-name-cell{display:flex;align-items:center;justify-content:space-between;gap:12px}.prmrj-name{font-weight:700}.prmrj-visit-count{display:inline-flex;align-items:center;border-radius:999px;padding:4px 9px;background:#eaf4ff;color:#2268a7;font-size:11px;font-weight:800;white-space:nowrap}.prmrj-detail-row>td{padding:0!important;background:#f2f6fa;border-color:#dce5ee!important}.prmrj-detail-shell{padding:14px}.prmrj-loading,.prmrj-empty,.prmrj-error{padding:18px;border-radius:7px;background:#fff;color:#62788f;font-size:12px;text-align:center}.prmrj-error{color:#a13636;background:#fff3f3}.prmrj-visits{display:grid;gap:11px}.prmrj-visit{overflow:hidden;border:1px solid #d4e0eb;border-radius:8px;background:#fff}.prmrj-visit-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 12px;background:#e9f2fb;color:#1f4c78;font-size:12px}.prmrj-visit-time{font-weight:800}.prmrj-rawat{color:#58728c}.prmrj-detail-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(180px,.55fr)}.prmrj-detail-block{min-width:0;padding:12px;border-right:1px solid #e2e8ee}.prmrj-detail-block:last-child{border-right:0}.prmrj-detail-label{margin-bottom:8px;color:#31577c;font-size:11px;font-weight:800;letter-spacing:.03em;text-transform:uppercase}.prmrj-detail-item{margin-top:7px;color:#2f4357;font-size:12px;line-height:1.45;white-space:pre-wrap;overflow-wrap:anywhere}.prmrj-detail-item:first-of-type{margin-top:0}.prmrj-detail-item strong{color:#1c354e}.prmrj-dpjp{font-size:13px;font-weight:800;color:#1c4f79}.prmrj-meta{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;color:#74889c;font-size:11px}.prmrj-pagination{display:flex;align-items:center;justify-content:center;gap:5px;margin-top:15px;flex-wrap:wrap}.prmrj-pagination a,.prmrj-pagination span{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:32px;padding:0 9px;border:1px solid #d2dee9;border-radius:5px;background:#fff;color:#315879;font-size:12px}.prmrj-pagination .active{background:#2878c7;border-color:#2878c7;color:#fff;font-weight:800}.prmrj-pagination .disabled{color:#9cacba;background:#f4f6f8}.prmrj-noscript{margin-top:12px}.prmrj-noscript a{color:#1769aa;font-weight:700}@media(max-width:850px){.prmrj-heading-row{flex-direction:column}.prmrj-period{white-space:normal}.prmrj-stat{align-items:flex-start}.prmrj-stat-note{margin-left:0;text-align:left}.prmrj-detail-grid{grid-template-columns:1fr}.prmrj-detail-block{border-right:0;border-bottom:1px solid #e2e8ee}.prmrj-detail-block:last-child{border-bottom:0}}@media(max-width:575px){.prmrj-panel{padding:14px}.prmrj-title{font-size:22px}.prmrj-filter{align-items:stretch;flex-direction:column}.prmrj-field,.prmrj-field.search,.prmrj-filter .btn{width:100%}.prmrj-actions{display:grid;grid-template-columns:1fr 1fr;width:100%}.prmrj-stat{flex-wrap:wrap}.prmrj-name-cell{align-items:flex-start;flex-direction:column}.prmrj-table thead th{padding:9px}.prmrj-expand{padding:11px 9px}.prmrj-visit-head{align-items:flex-start;flex-direction:column}}
.prmrj-history-note{margin-bottom:11px;padding:9px 12px;border:1px solid #bcd8ef;border-radius:7px;background:#eaf5ff;color:#245c88;font-size:12px;font-weight:700}
</style>

<div class="prmrj-page">
    <section class="prmrj-panel">
        <div class="prmrj-heading-row">
            <div>
                <h1 class="prmrj-title">Rekap Pasien PRMRJ</h1>
                <p class="prmrj-subtitle">Periode menyaring daftar pasien; rincian expand selalu menampilkan maksimal 5 riwayat PRMRJ terakhir secara historis.</p>
            </div>
            <div class="prmrj-period"><?php echo aptd_prmrj_h($period['tanggal_awal'] . ' s.d ' . $period['tanggal_akhir']); ?></div>
        </div>

        <?php if ($loadError !== ''): ?>
            <div class="alert alert-danger prmrj-alert mt-3" role="alert"><?php echo aptd_prmrj_h($loadError); ?></div>
        <?php endif; ?>

        <form method="get" action="main_app.php" class="prmrj-filter" id="prmrjFilterForm" novalidate>
            <input type="hidden" name="page" value="rekap_pasien_prmrj">
            <div class="prmrj-field">
                <label for="prmrjTanggalAwal">Tanggal Awal</label>
                <input type="date" class="form-control" id="prmrjTanggalAwal" name="tanggal_awal" value="<?php echo aptd_prmrj_h($period['tanggal_awal']); ?>" required>
            </div>
            <div class="prmrj-field">
                <label for="prmrjTanggalAkhir">Tanggal Akhir</label>
                <input type="date" class="form-control" id="prmrjTanggalAkhir" name="tanggal_akhir" value="<?php echo aptd_prmrj_h($period['tanggal_akhir']); ?>" required>
            </div>
            <div class="prmrj-field search">
                <label for="prmrjSearch">Cari Pasien</label>
                <input type="search" class="form-control" id="prmrjSearch" name="q" value="<?php echo aptd_prmrj_h($search); ?>" placeholder="No. RM atau nama pasien">
            </div>
            <div class="prmrj-actions">
                <button type="submit" class="btn btn-primary px-3">Tampilkan</button>
                <button type="button" class="btn btn-danger px-3" id="prmrjExportPdf">Export PDF</button>
            </div>
        </form>
    </section>

    <section class="prmrj-stat" aria-label="Jumlah pasien PRMRJ unik">
        <div class="prmrj-stat-icon" aria-hidden="true">P</div>
        <div>
            <div class="prmrj-stat-label">Total Pasien PRMRJ</div>
            <div class="prmrj-stat-value"><?php echo number_format($summary['total_pasien'], 0, ',', '.'); ?></div>
        </div>
        <div class="prmrj-stat-note">
            Dihitung unik berdasarkan No. RM<br>
            <?php echo number_format($summary['total_kunjungan'], 0, ',', '.'); ?> kunjungan PRMRJ dalam periode
        </div>
    </section>

    <section class="prmrj-panel">
        <div class="prmrj-table-wrap">
            <table class="table prmrj-table" aria-label="Daftar pasien PRMRJ">
                <thead>
                    <tr>
                        <th style="width:34%">No. Rekam Medis</th>
                        <th>Nama Pasien</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($summary['rows'])): ?>
                    <tr><td colspan="2"><div class="prmrj-empty">Tidak ada pasien PRMRJ pada periode atau pencarian yang dipilih.</div></td></tr>
                <?php else: ?>
                    <?php foreach ($summary['rows'] as $index => $row): ?>
                        <?php $detailId = 'prmrj-detail-' . $index; ?>
                        <tr class="prmrj-parent">
                            <td>
                                <button type="button" class="prmrj-expand" aria-expanded="false" aria-controls="<?php echo aptd_prmrj_h($detailId); ?>" data-no-rm="<?php echo aptd_prmrj_h($row['no_rkm_medis']); ?>">
                                    <span class="prmrj-chevron" aria-hidden="true">›</span>
                                    <span class="prmrj-rm"><?php echo aptd_prmrj_h($row['no_rkm_medis']); ?></span>
                                </button>
                            </td>
                            <td>
                                <button type="button" class="prmrj-expand prmrj-name-cell" aria-expanded="false" aria-controls="<?php echo aptd_prmrj_h($detailId); ?>" data-no-rm="<?php echo aptd_prmrj_h($row['no_rkm_medis']); ?>">
                                    <span class="prmrj-name"><?php echo aptd_prmrj_h($row['nm_pasien']); ?></span>
                                    <span class="prmrj-visit-count"><?php echo number_format($row['jumlah_kunjungan'], 0, ',', '.'); ?> kunjungan pada periode</span>
                                </button>
                            </td>
                        </tr>
                        <tr class="prmrj-detail-row" id="<?php echo aptd_prmrj_h($detailId); ?>" hidden>
                            <td colspan="2"><div class="prmrj-detail-shell" data-detail-container></div></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="prmrj-meta">
            <span>Menampilkan <?php echo number_format($firstShown, 0, ',', '.'); ?>-<?php echo number_format($lastShown, 0, ',', '.'); ?> dari <?php echo number_format($summary['filtered_total'], 0, ',', '.'); ?> pasien<?php echo $search !== '' ? ' hasil pencarian' : ''; ?>.</span>
            <span>Waktu query ringkasan: <?php echo number_format($summary['query_seconds'], 3, ',', '.'); ?> detik</span>
        </div>

        <?php if ($summary['last_page'] > 1): ?>
            <nav class="prmrj-pagination" aria-label="Navigasi halaman pasien PRMRJ">
                <?php if ($summary['page'] > 1): ?>
                    <a href="<?php echo aptd_prmrj_h(aptd_prmrj_page_url($summary['page'] - 1, $period, $search)); ?>" aria-label="Halaman sebelumnya">‹</a>
                <?php else: ?><span class="disabled">‹</span><?php endif; ?>
                <?php
                $pageStart = max(1, $summary['page'] - 2);
                $pageEnd = min($summary['last_page'], $summary['page'] + 2);
                for ($pageNumber = $pageStart; $pageNumber <= $pageEnd; $pageNumber++):
                ?>
                    <?php if ($pageNumber === $summary['page']): ?>
                        <span class="active" aria-current="page"><?php echo $pageNumber; ?></span>
                    <?php else: ?>
                        <a href="<?php echo aptd_prmrj_h(aptd_prmrj_page_url($pageNumber, $period, $search)); ?>"><?php echo $pageNumber; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($summary['page'] < $summary['last_page']): ?>
                    <a href="<?php echo aptd_prmrj_h(aptd_prmrj_page_url($summary['page'] + 1, $period, $search)); ?>" aria-label="Halaman berikutnya">›</a>
                <?php else: ?><span class="disabled">›</span><?php endif; ?>
            </nav>
        <?php endif; ?>
        <noscript><div class="alert alert-warning prmrj-noscript">JavaScript diperlukan untuk membuka rincian kunjungan.</div></noscript>
    </section>
</div>

<script>
(function () {
    'use strict';
    var filterForm = document.getElementById('prmrjFilterForm');
    var detailUrl = <?php echo json_encode($detailUrl); ?>;
    var exportUrl = <?php echo json_encode($exportUrl); ?>;

    function validPeriod() {
        var start = document.getElementById('prmrjTanggalAwal').value;
        var end = document.getElementById('prmrjTanggalAkhir').value;
        if (!start || !end || end < start) {
            alert('Rentang tanggal tidak valid.');
            return false;
        }
        return true;
    }

    function text(value) {
        value = value == null ? '' : String(value).trim();
        return value === '' ? '-' : value;
    }

    function addItem(container, label, value) {
        var item = document.createElement('div');
        item.className = 'prmrj-detail-item';
        var strong = document.createElement('strong');
        strong.textContent = label + ': ';
        item.appendChild(strong);
        item.appendChild(document.createTextNode(text(value)));
        container.appendChild(item);
    }

    function addBlock(grid, label) {
        var block = document.createElement('div');
        block.className = 'prmrj-detail-block';
        var heading = document.createElement('div');
        heading.className = 'prmrj-detail-label';
        heading.textContent = label;
        block.appendChild(heading);
        grid.appendChild(block);
        return block;
    }

    function renderVisits(container, visits) {
        container.textContent = '';
        var historyNote = document.createElement('div');
        historyNote.className = 'prmrj-history-note';
        historyNote.textContent = 'Menampilkan maksimal 5 riwayat PRMRJ terakhir secara historis.';
        container.appendChild(historyNote);
        if (!visits || visits.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'prmrj-empty';
            empty.textContent = 'Rincian kunjungan tidak ditemukan.';
            container.appendChild(empty);
            return;
        }

        var list = document.createElement('div');
        list.className = 'prmrj-visits';
        visits.forEach(function (visit) {
            var article = document.createElement('article');
            article.className = 'prmrj-visit';
            var head = document.createElement('div');
            head.className = 'prmrj-visit-head';
            var visitTime = document.createElement('span');
            visitTime.className = 'prmrj-visit-time';
            visitTime.textContent = text(visit.tgl_kunjungan) + ' ' + text(visit.jam_kunjungan);
            var noRawat = document.createElement('span');
            noRawat.className = 'prmrj-rawat';
            noRawat.textContent = 'No. Rawat: ' + text(visit.no_rawat);
            head.appendChild(visitTime);
            head.appendChild(noRawat);
            article.appendChild(head);

            var grid = document.createElement('div');
            grid.className = 'prmrj-detail-grid';
            var diagnosis = addBlock(grid, 'Diagnosa & Penilaian');
            addItem(diagnosis, 'Subjektif', visit.keluhan);
            addItem(diagnosis, 'Asesmen', visit.penilaian);
            addItem(diagnosis, 'ICD-10', visit.diagnosa && visit.diagnosa.length ? visit.diagnosa.join('\n') : '-');

            var therapy = addBlock(grid, 'Terapi & Tindakan');
            addItem(therapy, 'RTL', visit.rtl);
            addItem(therapy, 'Instruksi', visit.instruksi);
            addItem(therapy, 'Obat', visit.obat && visit.obat.length ? visit.obat.join('\n') : '-');

            var doctor = addBlock(grid, 'Pemberi Asuhan (DPJP)');
            var doctorName = document.createElement('div');
            doctorName.className = 'prmrj-dpjp';
            doctorName.textContent = text(visit.dpjp);
            doctor.appendChild(doctorName);
            article.appendChild(grid);
            list.appendChild(article);
        });
        container.appendChild(list);
    }

    function setExpanded(parentRow, expanded) {
        var buttons = parentRow.querySelectorAll('.prmrj-expand');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
    }

    function toggleDetails(button) {
        var parentRow = button.closest('tr');
        var detailRow = parentRow.nextElementSibling;
        var willOpen = detailRow.hasAttribute('hidden');
        if (!willOpen) {
            detailRow.setAttribute('hidden', 'hidden');
            setExpanded(parentRow, false);
            return;
        }

        detailRow.removeAttribute('hidden');
        setExpanded(parentRow, true);
        var container = detailRow.querySelector('[data-detail-container]');
        if (container.getAttribute('data-loaded') === '1' || container.getAttribute('data-loading') === '1') return;
        container.setAttribute('data-loading', '1');
        container.innerHTML = '<div class="prmrj-loading">Memuat rincian kunjungan...</div>';
        var url = detailUrl + '?' + new URLSearchParams({
            no_rkm_medis: button.getAttribute('data-no-rm')
        }).toString();

        fetch(url, {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
            .then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok) throw new Error(payload.message || 'Rincian gagal dimuat.');
                    return payload;
                });
            })
            .then(function (payload) {
                container.removeAttribute('data-loading');
                container.setAttribute('data-loaded', '1');
                renderVisits(container, payload.data || []);
            })
            .catch(function (error) {
                container.removeAttribute('data-loading');
                container.innerHTML = '';
                var message = document.createElement('div');
                message.className = 'prmrj-error';
                message.textContent = error.message || 'Rincian gagal dimuat.';
                container.appendChild(message);
            });
    }

    var expandButtons = document.querySelectorAll('.prmrj-expand');
    for (var i = 0; i < expandButtons.length; i++) {
        expandButtons[i].addEventListener('click', function () { toggleDetails(this); });
    }

    if (filterForm) {
        filterForm.addEventListener('submit', function (event) {
            if (!validPeriod()) event.preventDefault();
        });
    }

    var exportButton = document.getElementById('prmrjExportPdf');
    if (exportButton) {
        exportButton.addEventListener('click', function () {
            if (!validPeriod()) return;
            var exportForm = document.createElement('form');
            exportForm.method = 'post';
            exportForm.action = exportUrl;
            exportForm.style.display = 'none';
            ['tanggal_awal', 'tanggal_akhir'].forEach(function (name) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = filterForm.querySelector('[name="' + name + '"]').value;
                exportForm.appendChild(input);
            });
            document.body.appendChild(exportForm);
            exportForm.submit();
            document.body.removeChild(exportForm);
        });
    }
})();
</script>
