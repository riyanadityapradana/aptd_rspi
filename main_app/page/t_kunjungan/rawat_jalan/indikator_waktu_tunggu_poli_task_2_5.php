<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$projectRoot = dirname(dirname(dirname(dirname(__DIR__))));
require_once $projectRoot . '/config/akses.php';

$level = isset($_SESSION['level']) ? (string) $_SESSION['level'] : '';
if (!isset($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true
    || !aptd_can_access($level, 'indikator_waktu_tunggu_poli_task_2_5')) {
    http_response_code(403);
    exit('Akses ditolak.');
}

require_once $projectRoot . '/config/koneksi.php';
require_once __DIR__ . '/indikator_waktu_tunggu_poli_task_2_5_helper.php';

$defaults = aptd_wt25_default_filters();
$masters = ['polis' => [], 'doctors' => []];
$masterError = '';

try {
    $masters = aptd_wt25_fetch_masters($mysqli);
} catch (Throwable $exception) {
    error_log('Master Indikator WT Poli Task 2-5: ' . $exception->getMessage());
    $masterError = 'Daftar poli dan dokter belum dapat dimuat. Laporan masih dapat ditarik tanpa filter tersebut.';
}
?>
<br>
<style>
.wt25-page{display:grid;gap:14px;padding-bottom:38px;color:#24364b}.wt25-panel,.wt25-card{min-width:0;background:#fff;border:1px solid #dbe4ee;border-radius:8px;box-shadow:0 8px 22px rgba(35,58,88,.08)}.wt25-panel{padding:18px}.wt25-title-row{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.wt25-title{margin:0;font-size:26px;font-weight:800;color:#183b63}.wt25-subtitle{margin:5px 0 0;color:#60748a;font-size:13px}.wt25-source{white-space:nowrap;border-left:3px solid #2d7dd2;padding:4px 0 4px 10px;color:#536a82;font-size:12px}.wt25-filter{display:grid;grid-template-columns:repeat(5,minmax(150px,1fr));gap:10px;align-items:end;margin-top:16px}.wt25-filter label{display:block;margin-bottom:5px;color:#334d69;font-size:12px;font-weight:700}.wt25-filter .form-control,.wt25-filter .btn{height:35px;font-size:12px}.wt25-filter .select2-container{width:100%!important;font-size:12px}.wt25-filter .select2-selection--single{height:35px!important;border:1px solid #cbd8e6!important;border-radius:4px!important}.wt25-filter .select2-selection__rendered{line-height:33px!important}.wt25-filter .select2-selection__arrow{height:33px!important}.wt25-actions{grid-column:1/-1;display:flex;align-items:center;flex-wrap:wrap;gap:8px}.wt25-actions .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px}.wt25-task2-only{display:inline-flex!important;align-items:center;gap:7px;height:35px;margin:0 0 0 4px!important;padding:0 10px;border-left:1px solid #d5e0ea;color:#334d69!important;cursor:pointer;white-space:nowrap}.wt25-task2-only input{width:16px;height:16px;margin:0;accent-color:#1769aa;cursor:pointer}.wt25-error{display:none;margin:12px 0 0;font-size:12px}.wt25-cards{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.wt25-card{padding:15px;border-top:4px solid var(--accent)}.wt25-card.total{--accent:#2d7dd2}.wt25-card.available{--accent:#24966d}.wt25-card.average{--accent:#6a5acd}.wt25-card.cancelled{--accent:#d1495b}.wt25-card.task99{--accent:#e0a126}.wt25-card-label{color:#657a90;font-size:11px;font-weight:800;text-transform:uppercase}.wt25-card-value{margin-top:6px;color:#1b3552;font-size:26px;font-weight:800;line-height:1.1}.wt25-card-note{margin-top:7px;color:#7b8da0;font-size:11px}.wt25-chart-grid{display:grid;grid-template-columns:minmax(270px,.65fr) minmax(0,1.35fr);gap:14px}.wt25-heading{margin:0;color:#244565;font-size:17px;font-weight:800}.wt25-heading-note{margin:4px 0 0;color:#708399;font-size:12px}.wt25-chart{position:relative;height:280px;margin-top:12px}.wt25-table-head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:12px}.wt25-table-tools{display:flex;align-items:center;gap:8px}.wt25-search{position:relative}.wt25-search .glyphicon{position:absolute;left:10px;top:10px;color:#71869b}.wt25-search input{width:250px;height:34px;padding-left:30px;font-size:12px}.wt25-table-wrap{position:relative;min-height:180px;overflow:auto;border:1px solid #d8e1eb}.wt25-table{min-width:2180px;margin:0;font-size:11.5px}.wt25-table th,.wt25-table td{padding:7px;vertical-align:middle!important}.wt25-table thead th{position:sticky;top:0;z-index:2;background:#263746;color:#fff;text-align:center;border-color:#4b5966}.wt25-sort{padding:0;border:0;background:transparent;color:inherit;font:inherit;font-weight:700;white-space:nowrap}.wt25-sort-icon{display:inline-block;width:8px;margin-left:3px}.wt25-table tbody tr:nth-child(even){background:#f7f9fb}.wt25-nowrap{white-space:nowrap}.wt25-wt{background:#fff6d8;font-weight:800;text-align:center}.wt25-status{display:inline-flex;align-items:center;min-height:22px;padding:3px 7px;border-radius:8px;font-size:10px;font-weight:800;white-space:nowrap}.wt25-status.sent{background:#e7f6ef;color:#19704f}.wt25-status.missing{background:#fdebed;color:#a93245}.wt25-status.normal{background:#edf1f5;color:#5d6d7d}.wt25-loading{display:none;position:absolute;inset:0;z-index:5;align-items:center;justify-content:center;gap:9px;background:rgba(255,255,255,.82);color:#3b5874;font-size:12px}.wt25-loading.is-active{display:flex}.wt25-spinner{width:20px;height:20px;border:3px solid #d7e3ef;border-top-color:#2d7dd2;border-radius:50%;animation:wt25-spin .8s linear infinite}.wt25-empty{padding:28px!important;text-align:center;color:#71859a}.wt25-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;color:#667b90;font-size:12px}.wt25-pages{display:flex;flex-wrap:wrap;gap:4px}.wt25-pages .btn{min-width:34px;height:30px;padding:4px 8px;font-size:11px}.wt25-pages .btn.active{background:#2d7dd2;color:#fff;border-color:#2d7dd2}.wt25-query{margin-top:6px;color:#8797a8;font-size:10px;text-align:right}@keyframes wt25-spin{to{transform:rotate(360deg)}}@media(max-width:1180px){.wt25-filter{grid-template-columns:repeat(3,minmax(150px,1fr))}.wt25-cards{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:850px){.wt25-title-row,.wt25-table-head,.wt25-pagination{align-items:stretch;flex-direction:column}.wt25-source{white-space:normal}.wt25-filter{grid-template-columns:1fr 1fr}.wt25-chart-grid{grid-template-columns:1fr}.wt25-table-tools{justify-content:space-between}.wt25-search{flex:1}.wt25-search input{width:100%}}@media(max-width:575px){.wt25-panel{padding:14px}.wt25-title{font-size:22px}.wt25-filter,.wt25-cards{grid-template-columns:1fr}.wt25-actions .btn{flex:1 1 45%}.wt25-task2-only{width:100%;margin-left:0!important;padding-left:0;border-left:0}.wt25-card-value{font-size:23px}}
</style>

<div class="wt25-page">
    <section class="wt25-panel">
        <div class="wt25-title-row">
            <div>
                <h1 class="wt25-title">Indikator Waktu Tunggu Poli (Task ID 2-5)</h1>
                <p class="wt25-subtitle">Waktu tunggu dihitung dari Task ID 4 dikurangi Task ID 2; jika Task ID 2 kosong, sistem menggunakan Task ID 3.</p>
            </div>
            <div class="wt25-source">Pasien BPJS Rawat Jalan</div>
        </div>

        <?php if ($masterError !== ''): ?>
            <div class="alert alert-warning mt-3 mb-0"><?php echo aptd_wt25_h($masterError); ?></div>
        <?php endif; ?>
        <div class="alert alert-danger wt25-error" id="wt25Error" role="alert"></div>

        <form class="wt25-filter" id="wt25FilterForm" novalidate>
            <div>
                <label for="wt25TanggalAwal">Tanggal Awal</label>
                <input type="date" class="form-control" id="wt25TanggalAwal" value="<?php echo aptd_wt25_h($defaults['tanggal_awal']); ?>" required>
            </div>
            <div>
                <label for="wt25TanggalAkhir">Tanggal Akhir</label>
                <input type="date" class="form-control" id="wt25TanggalAkhir" value="<?php echo aptd_wt25_h($defaults['tanggal_akhir']); ?>" required>
            </div>
            <div>
                <label for="wt25Poli">Poli</label>
                <select class="form-control wt25-searchable" id="wt25Poli" data-placeholder="Cari poli">
                    <option value="">Semua Poli</option>
                    <?php foreach ($masters['polis'] as $poli): ?>
                        <option value="<?php echo aptd_wt25_h($poli['kd_poli']); ?>"><?php echo aptd_wt25_h($poli['nm_poli']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="wt25Dokter">Dokter</label>
                <select class="form-control wt25-searchable" id="wt25Dokter" data-placeholder="Cari dokter">
                    <option value="">Semua Dokter</option>
                </select>
            </div>
            <div>
                <label for="wt25Task99">Status Task 99</label>
                <select class="form-control wt25-searchable" id="wt25Task99" data-placeholder="Cari status">
                    <option value="semua">Semua</option>
                    <option value="terkirim">Batal - Terkirim</option>
                    <option value="belum_terkirim">Batal - Belum Terkirim</option>
                    <option value="bukan_batal">Bukan Batal</option>
                </select>
            </div>
            <div class="wt25-actions">
                <button type="submit" class="btn btn-primary" id="wt25Submit"><span class="glyphicon glyphicon-filter"></span> Tampilkan Data</button>
                <button type="button" class="btn btn-outline-secondary" id="wt25Reset"><span class="glyphicon glyphicon-refresh"></span> Reset</button>
                <button type="button" class="btn btn-success" id="wt25Export"><span class="glyphicon glyphicon-download-alt"></span> Export Excel</button>
                <label class="wt25-task2-only" for="wt25Task2Only">
                    <input type="checkbox" id="wt25Task2Only" value="1">
                    <span>Hanya Tampilkan Task 2</span>
                </label>
            </div>
        </form>
    </section>

    <section class="wt25-cards" aria-label="Ringkasan indikator waktu tunggu poli">
        <article class="wt25-card total"><div class="wt25-card-label">Total Kunjungan</div><div class="wt25-card-value" id="wt25Total">0</div><div class="wt25-card-note">Pasien BPJS Ralan dengan Task ID 2-5 terisi.</div></article>
        <article class="wt25-card available"><div class="wt25-card-label">WT Tersedia</div><div class="wt25-card-value" id="wt25Available">0</div><div class="wt25-card-note">Memiliki sumber Task 2/3 dan Task 4.</div></article>
        <article class="wt25-card average"><div class="wt25-card-label">Rata-rata WT Poli</div><div class="wt25-card-value" id="wt25Average">-</div><div class="wt25-card-note">Rata-rata Task 4 dikurangi sumber WT.</div></article>
        <article class="wt25-card cancelled"><div class="wt25-card-label">Pendaftaran Batal</div><div class="wt25-card-value" id="wt25Cancelled">0</div><div class="wt25-card-note">Pendaftaran dengan status Batal.</div></article>
        <article class="wt25-card task99"><div class="wt25-card-label">Task 99 Terkirim</div><div class="wt25-card-value" id="wt25Task99Sent">0</div><div class="wt25-card-note">Task 99 tersedia pada pendaftaran Batal.</div></article>
    </section>

    <section class="wt25-chart-grid">
        <div class="wt25-panel">
            <h2 class="wt25-heading">Kelengkapan Waktu Tunggu</h2>
            <p class="wt25-heading-note">Perbandingan WT tersedia dan tidak tersedia.</p>
            <div class="wt25-chart"><canvas id="wt25CompletenessChart"></canvas></div>
        </div>
        <div class="wt25-panel">
            <h2 class="wt25-heading">Rata-rata Waktu Tunggu per Poli</h2>
            <p class="wt25-heading-note">Maksimal 20 poli berdasarkan jumlah data WT terbanyak.</p>
            <div class="wt25-chart"><canvas id="wt25PoliChart"></canvas></div>
        </div>
    </section>

    <section class="wt25-panel">
        <div class="wt25-table-head">
            <div>
                <h2 class="wt25-heading">Detail Task ID 2-5</h2>
                <p class="wt25-heading-note" id="wt25TableNote">Memuat data...</p>
            </div>
            <div class="wt25-table-tools">
                <div class="wt25-search"><span class="glyphicon glyphicon-search"></span><input type="search" class="form-control" id="wt25Search" placeholder="Cari pasien, no. rawat, poli, dokter..." aria-label="Cari data"></div>
                <select class="form-control" id="wt25PerPage" aria-label="Jumlah baris per halaman" style="width:82px;height:34px;font-size:12px;"><option value="10">10</option><option value="20" selected>20</option><option value="50">50</option><option value="100">100</option></select>
            </div>
        </div>

        <div class="wt25-table-wrap" id="wt25TableWrap" aria-busy="true">
            <div class="wt25-loading is-active" id="wt25Loading"><span class="wt25-spinner"></span>Memuat indikator...</div>
            <table class="table table-bordered table-hover wt25-table">
                <thead><tr>
                    <th style="width:45px;">No.</th>
                    <th><button type="button" class="wt25-sort" data-sort="tanggal">Tanggal <span class="wt25-sort-icon"></span></button></th>
                    <th><button type="button" class="wt25-sort" data-sort="no_rawat">No. Rawat <span class="wt25-sort-icon"></span></button></th>
                    <th><button type="button" class="wt25-sort" data-sort="nama_pasien">Nama Pasien <span class="wt25-sort-icon"></span></button></th>
                    <th><button type="button" class="wt25-sort" data-sort="nama_poli">Poli <span class="wt25-sort-icon"></span></button></th>
                    <th><button type="button" class="wt25-sort" data-sort="nama_dokter">Dokter <span class="wt25-sort-icon"></span></button></th>
                    <th><button type="button" class="wt25-sort" data-sort="jam_buka_poli">Jam Buka <span class="wt25-sort-icon"></span></button></th>
                    <th><button type="button" class="wt25-sort" data-sort="task_2">Task 2 <span class="wt25-sort-icon"></span></button></th>
                    <th><button type="button" class="wt25-sort" data-sort="task_3">Task 3 <span class="wt25-sort-icon"></span></button></th>
                    <th><button type="button" class="wt25-sort" data-sort="task_4">Task 4 <span class="wt25-sort-icon"></span></button></th>
                    <th><button type="button" class="wt25-sort" data-sort="task_5">Task 5 <span class="wt25-sort-icon"></span></button></th>
                    <th><button type="button" class="wt25-sort" data-sort="wt_seconds">WT Poli <span class="wt25-sort-icon"></span></button></th>
                    <th>Sumber WT</th>
                    <th><button type="button" class="wt25-sort" data-sort="status_daftar">Status Daftar <span class="wt25-sort-icon"></span></button></th>
                    <th><button type="button" class="wt25-sort" data-sort="status_batal_task99">Status Task 99 <span class="wt25-sort-icon"></span></button></th>
                </tr></thead>
                <tbody id="wt25TableBody"><tr><td colspan="15" class="wt25-empty">Memuat data...</td></tr></tbody>
            </table>
        </div>
        <div class="wt25-pagination"><div id="wt25Info">Menampilkan 0 data</div><div class="wt25-pages" id="wt25Pages"></div></div>
        <div class="wt25-query" id="wt25QueryTime"></div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function () {
    'use strict';

    var apiUrl = 'page/t_kunjungan/rawat_jalan/indikator_waktu_tunggu_poli_task_2_5_api.php';
    var exportUrl = 'page/t_kunjungan/rawat_jalan/export_indikator_waktu_tunggu_poli_task_2_5.php';
    var doctorAssignments = <?php echo json_encode($masters['doctors'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var defaults = {start:<?php echo json_encode($defaults['tanggal_awal']); ?>,end:<?php echo json_encode($defaults['tanggal_akhir']); ?>};
    var numberFormat = new Intl.NumberFormat('id-ID');
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var activeRequest = null;
    var searchTimer = null;
    var charts = {completeness:null,poli:null};
    var state = {page:1,per_page:20,sort:'tanggal',direction:'asc',search:''};
    var el = {
        form:document.getElementById('wt25FilterForm'),start:document.getElementById('wt25TanggalAwal'),end:document.getElementById('wt25TanggalAkhir'),poli:document.getElementById('wt25Poli'),doctor:document.getElementById('wt25Dokter'),status:document.getElementById('wt25Task99'),task2Only:document.getElementById('wt25Task2Only'),submit:document.getElementById('wt25Submit'),reset:document.getElementById('wt25Reset'),exportButton:document.getElementById('wt25Export'),search:document.getElementById('wt25Search'),perPage:document.getElementById('wt25PerPage'),error:document.getElementById('wt25Error'),loading:document.getElementById('wt25Loading'),tableWrap:document.getElementById('wt25TableWrap'),tableBody:document.getElementById('wt25TableBody'),tableNote:document.getElementById('wt25TableNote'),info:document.getElementById('wt25Info'),pages:document.getElementById('wt25Pages'),queryTime:document.getElementById('wt25QueryTime')
    };

    function escapeHtml(value) { return String(value === null || value === undefined ? '' : value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
    function showError(message) { el.error.textContent = message || ''; el.error.style.display = message ? 'block' : 'none'; }
    function setLoading(value) { el.loading.classList.toggle('is-active', value); el.tableWrap.setAttribute('aria-busy', value ? 'true' : 'false'); el.submit.disabled = value; }
    function syncSelect(select) { if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 && window.jQuery(select).data('select2')) window.jQuery(select).trigger('change.select2'); }

    function rebuildDoctors() {
        var selectedPoli = el.poli.value;
        var previous = el.doctor.value;
        var doctors = {};
        doctorAssignments.forEach(function (item) { if (!selectedPoli || item.kd_poli === selectedPoli) doctors[item.kd_dokter] = item.nm_dokter; });
        el.doctor.innerHTML = '<option value="">Semua Dokter</option>';
        Object.keys(doctors).sort(function (a,b) { return doctors[a].localeCompare(doctors[b], 'id'); }).forEach(function (code) { var option=document.createElement('option'); option.value=code; option.textContent=doctors[code]; el.doctor.appendChild(option); });
        if (doctors[previous]) el.doctor.value = previous;
        syncSelect(el.doctor);
    }

    function validateDates() {
        if (!el.start.value || !el.end.value) { showError('Tanggal awal dan tanggal akhir wajib diisi.'); return false; }
        if (el.end.value < el.start.value) { showError('Tanggal akhir tidak boleh lebih kecil dari tanggal awal.'); return false; }
        var start = new Date(el.start.value + 'T00:00:00');
        var end = new Date(el.end.value + 'T00:00:00');
        if (Math.round((end - start) / 86400000) > 366) { showError('Rentang laporan maksimal 366 hari.'); return false; }
        showError(''); return true;
    }

    function buildParams(includePaging) {
        var params = new URLSearchParams();
        params.set('tanggal_awal', el.start.value); params.set('tanggal_akhir', el.end.value); params.set('kd_poli', el.poli.value); params.set('kd_dokter', el.doctor.value); params.set('status_task99', el.status.value); params.set('hanya_task_2', el.task2Only.checked ? '1' : '0'); params.set('search', state.search); params.set('sort', state.sort); params.set('direction', state.direction); params.set('per_page', state.per_page);
        if (includePaging) params.set('page', state.page);
        return params;
    }

    function renderSummary(summary) {
        document.getElementById('wt25Total').textContent = numberFormat.format(summary.total || 0);
        document.getElementById('wt25Available').textContent = numberFormat.format(summary.wt_tersedia || 0);
        document.getElementById('wt25Average').textContent = summary.rata_rata_wt || '-';
        document.getElementById('wt25Cancelled').textContent = numberFormat.format(summary.batal || 0);
        document.getElementById('wt25Task99Sent').textContent = numberFormat.format(summary.task99_terkirim || 0);
    }

    function renderCharts(chart) {
        if (typeof Chart === 'undefined') return;
        var animation = reduceMotion ? false : {duration:750};
        if (charts.completeness) charts.completeness.destroy();
        charts.completeness = new Chart(document.getElementById('wt25CompletenessChart'), {type:'doughnut',data:{labels:chart.kelengkapan.map(function(i){return i.label;}),datasets:[{data:chart.kelengkapan.map(function(i){return i.value;}),backgroundColor:['#24966d','#d1495b'],borderWidth:0,hoverOffset:6}]},options:{responsive:true,maintainAspectRatio:false,cutout:'63%',animation:animation,plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:10}}}}});
        if (charts.poli) charts.poli.destroy();
        charts.poli = new Chart(document.getElementById('wt25PoliChart'), {type:'bar',data:{labels:chart.per_poli.map(function(i){return i.label;}),datasets:[{label:'Rata-rata (menit)',data:chart.per_poli.map(function(i){return i.rata_rata_menit;}),backgroundColor:'#2d7dd2',borderRadius:4,borderSkipped:false}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,animation:animation,plugins:{legend:{display:false},tooltip:{callbacks:{afterLabel:function(context){return 'Jumlah data: ' + numberFormat.format(chart.per_poli[context.dataIndex].jumlah);}}}},scales:{x:{beginAtZero:true,title:{display:true,text:'Menit'}},y:{grid:{display:false}}}}});
    }

    function statusBadge(status) {
        var className = status === 'Terkirim' ? 'sent' : (status === 'Belum Terkirim' ? 'missing' : 'normal');
        return '<span class="wt25-status ' + className + '">' + escapeHtml(status) + '</span>';
    }

    function renderTable(rows, pagination) {
        if (!rows.length) { el.tableBody.innerHTML = '<tr><td colspan="15" class="wt25-empty">Tidak ada data yang sesuai dengan filter.</td></tr>'; return; }
        var startNo = ((pagination.page - 1) * pagination.per_page) + 1;
        el.tableBody.innerHTML = rows.map(function (row,index) {
            return '<tr><td style="text-align:center">' + numberFormat.format(startNo + index) + '</td>' +
                '<td class="wt25-nowrap">' + escapeHtml(row.tanggal_label) + '</td><td class="wt25-nowrap">' + escapeHtml(row.no_rawat) + '</td><td>' + escapeHtml(row.nama_pasien) + '</td><td>' + escapeHtml(row.nama_poli) + '</td><td>' + escapeHtml(row.nama_dokter) + '</td>' +
                '<td class="wt25-nowrap" style="text-align:center">' + escapeHtml(row.jam_buka_poli) + '</td><td class="wt25-nowrap">' + escapeHtml(row.task_2) + '</td><td class="wt25-nowrap">' + escapeHtml(row.task_3) + '</td><td class="wt25-nowrap">' + escapeHtml(row.task_4) + '</td><td class="wt25-nowrap">' + escapeHtml(row.task_5) + '</td>' +
                '<td class="wt25-wt wt25-nowrap">' + escapeHtml(row.wt_poli) + '</td><td style="text-align:center">' + escapeHtml(row.sumber_wt) + '</td><td style="text-align:center">' + escapeHtml(row.status_daftar) + '</td><td style="text-align:center">' + statusBadge(row.status_batal_task99) + '</td></tr>';
        }).join('');
    }

    function pageButton(label, page, active, disabled) { var button=document.createElement('button'); button.type='button'; button.className='btn btn-outline-secondary' + (active ? ' active' : ''); button.textContent=label; button.disabled=disabled; if (!disabled && !active) button.addEventListener('click',function(){state.page=page;loadData();}); el.pages.appendChild(button); }
    function renderPagination(pagination) {
        el.pages.innerHTML = '';
        pageButton('Sebelumnya', pagination.page - 1, false, pagination.page <= 1);
        var start=Math.max(1,pagination.page-2), end=Math.min(pagination.total_pages,pagination.page+2);
        if (start > 1) { pageButton('1',1,false,false); if (start > 2) { var dots=document.createElement('span'); dots.textContent='...'; dots.style.padding='6px 3px'; el.pages.appendChild(dots); } }
        for (var page=start; page<=end; page++) pageButton(String(page),page,page===pagination.page,false);
        if (end < pagination.total_pages) { if (end < pagination.total_pages-1) { var endDots=document.createElement('span'); endDots.textContent='...'; endDots.style.padding='6px 3px'; el.pages.appendChild(endDots); } pageButton(String(pagination.total_pages),pagination.total_pages,false,false); }
        pageButton('Berikutnya', pagination.page + 1, false, pagination.page >= pagination.total_pages);
        var from=pagination.total ? ((pagination.page-1)*pagination.per_page)+1 : 0, to=Math.min(pagination.page*pagination.per_page,pagination.total);
        el.info.textContent='Menampilkan ' + numberFormat.format(from) + '-' + numberFormat.format(to) + ' dari ' + numberFormat.format(pagination.total) + ' data';
    }

    function updateSortIcons() { document.querySelectorAll('.wt25-sort[data-sort]').forEach(function(button){ var icon=button.querySelector('.wt25-sort-icon'); icon.textContent=button.getAttribute('data-sort')===state.sort ? (state.direction==='asc'?'\u25B2':'\u25BC') : ''; }); }
    function loadData() {
        if (!validateDates()) return;
        if (activeRequest) activeRequest.abort();
        activeRequest = new AbortController(); setLoading(true); showError('');
        fetch(apiUrl + '?' + buildParams(true).toString(), {credentials:'same-origin',signal:activeRequest.signal,headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(function(response){return response.json().then(function(payload){if(!response.ok||!payload.success)throw new Error(payload.message||'Data indikator belum dapat dimuat.');return payload;});})
            .then(function(payload){state.page=payload.pagination.page;renderSummary(payload.summary);renderCharts(payload.chart);renderTable(payload.data,payload.pagination);renderPagination(payload.pagination);updateSortIcons();el.tableNote.textContent=(payload.filters.hanya_task_2===1?'Filter aktif: hanya Task ID 2 terisi. ':'Hanya baris dengan minimal satu Task ID 2-5 terisi. ')+'Waktu tunggu: Task 4 - COALESCE(Task 2, Task 3).';el.queryTime.textContent='Waktu proses: ' + Number(payload.query_seconds||0).toLocaleString('id-ID',{minimumFractionDigits:3,maximumFractionDigits:3}) + ' detik';})
            .catch(function(error){if(error.name==='AbortError')return;showError(error.message);el.tableBody.innerHTML='<tr><td colspan="15" class="wt25-empty">Data belum dapat ditampilkan.</td></tr>';})
            .finally(function(){setLoading(false);activeRequest=null;});
    }

    el.form.addEventListener('submit',function(event){event.preventDefault();state.page=1;loadData();});
    el.poli.addEventListener('change',rebuildDoctors);
    el.reset.addEventListener('click',function(){el.start.value=defaults.start;el.end.value=defaults.end;el.poli.value='';rebuildDoctors();el.doctor.value='';el.status.value='semua';el.task2Only.checked=false;el.search.value='';state={page:1,per_page:20,sort:'tanggal',direction:'asc',search:''};el.perPage.value='20';syncSelect(el.poli);syncSelect(el.doctor);syncSelect(el.status);loadData();});
    el.task2Only.addEventListener('change',function(){state.page=1;loadData();});
    el.search.addEventListener('input',function(){clearTimeout(searchTimer);searchTimer=setTimeout(function(){state.search=el.search.value.trim();state.page=1;loadData();},350);});
    el.perPage.addEventListener('change',function(){state.per_page=parseInt(el.perPage.value,10)||20;state.page=1;loadData();});
    el.exportButton.addEventListener('click',function(){if(validateDates())window.location.href=exportUrl+'?'+buildParams(false).toString();});
    document.querySelectorAll('.wt25-sort[data-sort]').forEach(function(button){button.addEventListener('click',function(){var sort=button.getAttribute('data-sort');if(state.sort===sort)state.direction=state.direction==='asc'?'desc':'asc';else{state.sort=sort;state.direction='asc';}state.page=1;loadData();});});

    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) { window.jQuery('.wt25-searchable').select2({width:'100%',allowClear:false}); }
    rebuildDoctors(); updateSortIcons(); loadData();
})();
</script>
