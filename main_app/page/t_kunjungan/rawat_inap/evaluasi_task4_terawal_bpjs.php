<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
require_once __DIR__ . '/evaluasi_task4_terawal_bpjs_helper.php';

$defaults = aptd_task4_terawal_default_filters();
$masterError = '';
$masters = ['polis' => [], 'doctors' => []];

try {
    $masters = aptd_task4_fetch_masters($mysqli);
} catch (Throwable $exception) {
    error_log('Master Evaluasi Task ID 4 Terawal: ' . $exception->getMessage());
    $masterError = 'Daftar poli dan dokter belum dapat dimuat. Data laporan masih dapat dicoba tanpa filter tersebut.';
}
?>
<br>
<style>
.task4-page{display:grid;gap:14px;padding-bottom:34px;color:#24364b}
.task4-panel,.task4-card{background:#fff;border:1px solid #dbe4ee;border-radius:8px;box-shadow:0 8px 22px rgba(35,58,88,.08)}
.task4-panel{padding:18px}
.task4-title-row{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
.task4-title{margin:0;font-size:26px;font-weight:800;color:#183b63}
.task4-subtitle{margin:5px 0 0;color:#60748a;font-size:13px}
.task4-source{white-space:nowrap;border-left:3px solid #2d7dd2;padding:4px 0 4px 10px;color:#536a82;font-size:12px}
.task4-filter{display:grid;grid-template-columns:repeat(5,minmax(150px,1fr));gap:10px;align-items:end;margin-top:16px}
.task4-filter label{display:block;margin-bottom:5px;font-size:12px;font-weight:700;color:#334d69}
.task4-filter .form-control{height:35px;border-color:#cbd8e6;font-size:12px}
.task4-filter .select2-container{width:100%!important;font-size:12px}
.task4-filter .select2-container .select2-selection--single{height:35px;border:1px solid #cbd8e6;border-radius:4px}
.task4-filter .select2-container .select2-selection--single .select2-selection__rendered{line-height:33px;padding-left:10px;padding-right:28px}
.task4-filter .select2-container .select2-selection--single .select2-selection__arrow{height:33px}
.select2-dropdown{font-size:12px}
.select2-container .select2-search--dropdown .select2-search__field{height:32px;border:1px solid #aebfd0;border-radius:4px;outline:none}
.task4-actions{display:flex;gap:8px;grid-column:span 2}
.task4-actions .btn{height:35px;display:inline-flex;align-items:center;gap:6px;justify-content:center;font-size:12px}
.task4-actions .btn-primary{background:#1769aa;border-color:#1769aa}
.task4-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.task4-card{padding:16px;border-top-width:4px}
.task4-card:nth-child(1){border-top-color:#2d7dd2}
.task4-card:nth-child(2){border-top-color:#24966d}
.task4-card:nth-child(3){border-top-color:#d1495b}
.task4-card:nth-child(4){border-top-color:#e0a126}
.task4-card-label{font-size:11px;font-weight:800;color:#657a90;text-transform:uppercase}
.task4-card-value{margin-top:6px;font-size:28px;line-height:1.1;font-weight:800;color:#1b3552}
.task4-card-note{margin-top:7px;font-size:11px;color:#7b8da0}
.task4-doctor-block{display:grid;gap:8px}.task4-doctor-title{margin:0;color:#526b83;font-size:12px;font-weight:800;text-transform:uppercase}.task4-doctor-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.task4-doctor-cards .task4-card:nth-child(1){border-top-color:#2d7dd2}.task4-doctor-cards .task4-card:nth-child(2){border-top-color:#d1495b}.task4-doctor-cards .task4-card:nth-child(3){border-top-color:#24966d}
.task4-chart-grid{display:grid;grid-template-columns:minmax(280px,.8fr) minmax(0,1.6fr);gap:12px}
.task4-chart-title,.task4-table-title{margin:0;font-size:17px;font-weight:800;color:#244565}
.task4-chart-note,.task4-table-note{margin:4px 0 0;color:#708399;font-size:12px}
.task4-chart{position:relative;height:310px;margin-top:12px}
.task4-chart-scroll{max-height:420px;overflow-y:auto;margin-top:12px}
.task4-chart-scroll .task4-chart{margin-top:0;min-height:310px}
.task4-trend{grid-column:1/-1}
.task4-table-head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:12px}
.task4-table-tools{display:flex;align-items:center;gap:8px}
.task4-search{position:relative}
.task4-search .glyphicon{position:absolute;left:10px;top:10px;color:#8294a6;font-size:12px}
.task4-search input{height:34px;width:230px;padding-left:30px;font-size:12px}
.task4-table-wrap{position:relative;min-height:180px;overflow:auto;border:1px solid #d8e1eb}
.task4-table{margin:0;min-width:1120px;font-size:11px}
.task4-table thead th{position:sticky;top:0;z-index:2;padding:0;background:#263746;color:#fff;border-color:#485765;vertical-align:middle;white-space:nowrap}
.task4-sort{display:flex;width:100%;align-items:center;justify-content:space-between;gap:6px;padding:9px 8px;border:0;background:transparent;color:inherit;font:inherit;font-weight:700;text-align:left;cursor:pointer}
.task4-sort-icon{color:#9fb3c5}
.task4-table td{padding:7px 8px;vertical-align:middle}
.task4-table tbody tr:nth-child(even){background:#f7f9fb}
.task4-status{display:inline-flex;align-items:center;justify-content:center;min-width:88px;padding:4px 7px;border-radius:6px;font-size:10px;font-weight:800}
.task4-status-sesuai{background:#dff4e9;color:#176945}
.task4-status-tidak{background:#fde6e8;color:#a52d3d}
.task4-loading{position:absolute;inset:0;z-index:5;display:none;align-items:center;justify-content:center;background:rgba(255,255,255,.86);font-size:13px;font-weight:700;color:#34526f}
.task4-loading.is-active{display:flex}
.task4-spinner{width:18px;height:18px;margin-right:9px;border:2px solid #bdd0e2;border-top-color:#1769aa;border-radius:50%;animation:task4-spin .7s linear infinite}
@keyframes task4-spin{to{transform:rotate(360deg)}}
.task4-empty{padding:30px;text-align:center;color:#71859a}
.task4-error{display:none;margin:0}
.task4-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;font-size:12px;color:#63778c}
.task4-pages{display:flex;align-items:center;gap:4px;flex-wrap:wrap;justify-content:flex-end}
.task4-pages button{min-width:32px;height:32px;border:1px solid #cbd8e6;background:#fff;color:#31516e;border-radius:5px}
.task4-pages button:hover:not(:disabled){background:#edf4fb;border-color:#8db2d5}
.task4-pages button.is-active{background:#1769aa;border-color:#1769aa;color:#fff}
.task4-pages button:disabled{opacity:.45;cursor:not-allowed}
@media(max-width:1100px){.task4-filter{grid-template-columns:repeat(3,minmax(150px,1fr))}.task4-cards,.task4-doctor-cards{grid-template-columns:repeat(2,minmax(0,1fr))}.task4-chart-grid{grid-template-columns:1fr}}
@media(max-width:720px){.task4-title-row,.task4-table-head,.task4-pagination{align-items:stretch;flex-direction:column}.task4-source{white-space:normal}.task4-filter{grid-template-columns:1fr 1fr}.task4-actions{grid-column:1/-1}.task4-actions .btn{flex:1}.task4-table-tools{justify-content:space-between}.task4-search{flex:1}.task4-search input{width:100%}.task4-pages{justify-content:flex-start}}
@media(max-width:480px){.task4-panel{padding:14px}.task4-title{font-size:22px}.task4-filter,.task4-cards,.task4-doctor-cards{grid-template-columns:1fr}.task4-actions{flex-wrap:wrap}.task4-actions .btn{flex:1 1 45%}.task4-card-value{font-size:24px}}
</style>

<div class="task4-page">
    <section class="task4-panel">
        <div class="task4-title-row">
            <div>
                <h1 class="task4-title">INDIKATOR KESESUAIAN JADWAL DOKTER (Berdasarkan Task ID 4 Terawal)</h1>
                <p class="task4-subtitle">Evaluasi jadwal dokter berdasarkan waktu Task ID 4 paling awal per tanggal, poli, dan dokter.</p>
            </div>
            <div class="task4-source">Rentang sesuai: 1 jam sebelum sampai 1 jam setelah jam mulai poli.</div>
        </div>

        <?php if ($masterError !== ''): ?>
            <div class="alert alert-warning mt-3 mb-0"><?php echo aptd_task4_h($masterError); ?></div>
        <?php endif; ?>
        <div class="alert alert-danger task4-error mt-3" id="task4Error" role="alert"></div>

        <form class="task4-filter" id="task4FilterForm" novalidate>
            <div>
                <label for="task4TanggalAwal">Tanggal Awal</label>
                <input type="date" class="form-control" id="task4TanggalAwal" value="<?php echo aptd_task4_h($defaults['tanggal_awal']); ?>" required>
            </div>
            <div>
                <label for="task4TanggalAkhir">Tanggal Akhir</label>
                <input type="date" class="form-control" id="task4TanggalAkhir" value="<?php echo aptd_task4_h($defaults['tanggal_akhir']); ?>" required>
            </div>
            <div>
                <label for="task4Poli">Poli</label>
                <select class="form-control select2 task4-searchable-filter" id="task4Poli" data-placeholder="Cari poli">
                    <option value="">Semua Poli</option>
                    <?php foreach ($masters['polis'] as $poli): ?>
                        <option value="<?php echo aptd_task4_h($poli['kd_poli']); ?>"><?php echo aptd_task4_h($poli['nm_poli']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="task4Dokter">Dokter</label>
                <select class="form-control select2 task4-searchable-filter" id="task4Dokter" data-placeholder="Cari dokter">
                    <option value="">Semua Dokter</option>
                </select>
            </div>
            <div>
                <label for="task4Status">Status Kesesuaian</label>
                <select class="form-control select2 task4-searchable-filter" id="task4Status" data-placeholder="Cari status">
                    <option value="semua">Semua</option>
                    <option value="sesuai">Sesuai</option>
                    <option value="tidak_sesuai">Tidak Sesuai</option>
                </select>
            </div>
            <div class="task4-actions">
                <button type="submit" class="btn btn-primary" id="task4Submit">
                    <span class="glyphicon glyphicon-filter"></span> Tampilkan Data
                </button>
                <button type="button" class="btn btn-outline-secondary" id="task4Reset">
                    <span class="glyphicon glyphicon-refresh"></span> Reset
                </button>
                <button type="button" class="btn btn-success" id="task4Export">
                    <span class="glyphicon glyphicon-download-alt"></span> Export Excel
                </button>
            </div>
        </form>
    </section>

    <section class="task4-cards" aria-label="Ringkasan evaluasi">
        <div class="task4-card">
            <div class="task4-card-label">Total Data</div>
            <div class="task4-card-value" id="task4Total">0</div>
            <div class="task4-card-note">Kombinasi tanggal, poli, dan dokter dianalisis.</div>
        </div>
        <div class="task4-card">
            <div class="task4-card-label">Sesuai</div>
            <div class="task4-card-value" id="task4Sesuai">0</div>
            <div class="task4-card-note">Task ID 4 berada dalam rentang toleransi.</div>
        </div>
        <div class="task4-card">
            <div class="task4-card-label">Tidak Sesuai</div>
            <div class="task4-card-value" id="task4TidakSesuai">0</div>
            <div class="task4-card-note">Task ID 4 berada di luar rentang toleransi.</div>
        </div>
        <div class="task4-card">
            <div class="task4-card-label">Persentase Kesesuaian</div>
            <div class="task4-card-value" id="task4Persentase">0%</div>
            <div class="task4-card-note">Jumlah sesuai dibandingkan total data.</div>
        </div>
    </section>

    <section class="task4-doctor-block" aria-labelledby="task4DoctorSummaryTitle">
        <h2 class="task4-doctor-title" id="task4DoctorSummaryTitle">Agregasi Dokter - Standar BPJS</h2>
        <div class="task4-doctor-cards">
            <div class="task4-card">
                <div class="task4-card-label">Total Dokter Praktek</div>
                <div class="task4-card-value" id="task4TotalDokter">0</div>
                <div class="task4-card-note">Dokter unik yang dievaluasi pada periode terpilih.</div>
            </div>
            <div class="task4-card">
                <div class="task4-card-label">Dokter Tidak Sesuai</div>
                <div class="task4-card-value" id="task4DokterTidakSesuai">0</div>
                <div class="task4-card-note">Memiliki minimal satu jadwal berstatus Tidak Sesuai.</div>
            </div>
            <div class="task4-card">
                <div class="task4-card-label">Persentase Kesesuaian Dokter</div>
                <div class="task4-card-value" id="task4PersentaseDokter">0%</div>
                <div class="task4-card-note">Dokter sesuai dibandingkan total dokter praktek.</div>
            </div>
        </div>
    </section>

    <section class="task4-chart-grid">
        <div class="task4-panel">
            <h2 class="task4-chart-title">Komposisi Kesesuaian</h2>
            <p class="task4-chart-note">Perbandingan data Sesuai dan Tidak Sesuai.</p>
            <div class="task4-chart"><canvas id="task4StatusChart"></canvas></div>
        </div>
        <div class="task4-panel">
            <h2 class="task4-chart-title">Kesesuaian per Poli</h2>
            <p class="task4-chart-note">Poli diurutkan dari jumlah evaluasi terbanyak.</p>
            <div class="task4-chart-scroll">
                <div class="task4-chart" id="task4PoliChartBox"><canvas id="task4PoliChart"></canvas></div>
            </div>
        </div>
        <div class="task4-panel task4-trend">
            <h2 class="task4-chart-title">Tren Harian</h2>
            <p class="task4-chart-note">Persentase kesesuaian Task ID 4 per tanggal registrasi.</p>
            <div class="task4-chart"><canvas id="task4TrendChart"></canvas></div>
        </div>
    </section>

    <section class="task4-panel">
        <div class="task4-table-head">
            <div>
                <h2 class="task4-table-title">Detail Evaluasi Task ID 4 (Berdasarkan Task ID 4 Terawal)</h2>
                <p class="task4-table-note" id="task4TableNote">Memuat data...</p>
            </div>
            <div class="task4-table-tools">
                <div class="task4-search">
                    <span class="glyphicon glyphicon-search"></span>
                    <input type="search" class="form-control" id="task4Search" placeholder="Cari no. rawat, poli, dokter..." aria-label="Cari data">
                </div>
                <select class="form-control" id="task4PerPage" aria-label="Jumlah baris per halaman" style="width:82px;height:34px;font-size:12px;">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        <div class="task4-table-wrap" id="task4TableWrap" aria-busy="true">
            <div class="task4-loading is-active" id="task4Loading"><span class="task4-spinner"></span>Memuat evaluasi...</div>
            <table class="table table-bordered table-hover task4-table">
                <thead>
                    <tr>
                        <th style="width:46px;"><span class="task4-sort" style="cursor:default;">No.</span></th>
                        <th><button type="button" class="task4-sort" data-sort="tanggal">Tanggal <span class="task4-sort-icon"></span></button></th>
                        <th><button type="button" class="task4-sort" data-sort="nama_poli">Nama Poli <span class="task4-sort-icon"></span></button></th>
                        <th><button type="button" class="task4-sort" data-sort="jam_buka_poli">Jam Buka Poli <span class="task4-sort-icon"></span></button></th>
                        <th><button type="button" class="task4-sort" data-sort="nama_dokter">Nama Dokter <span class="task4-sort-icon"></span></button></th>
                        <th style="min-width:155px;"><button type="button" class="task4-sort" data-sort="nomor_rawat">No. Rawat <span class="task4-sort-icon"></span></button></th>
                        <th><button type="button" class="task4-sort" data-sort="task_4_paling_awal">Task ID 4 Paling Awal <span class="task4-sort-icon"></span></button></th>
                        <th><button type="button" class="task4-sort" data-sort="selisih_detik">Selisih Waktu <span class="task4-sort-icon"></span></button></th>
                        <th><button type="button" class="task4-sort" data-sort="kesesuaian">Status <span class="task4-sort-icon"></span></button></th>
                    </tr>
                </thead>
                <tbody id="task4TableBody">
                    <tr><td colspan="9" class="task4-empty">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="task4-pagination">
            <div id="task4Info">Menampilkan 0 data</div>
            <div class="task4-pages" id="task4Pages"></div>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    'use strict';

    var apiUrl = 'page/t_kunjungan/rawat_inap/evaluasi_task4_terawal_bpjs_api.php';
    var exportUrl = 'page/t_kunjungan/rawat_inap/export_evaluasi_task4_terawal_bpjs.php';
    var doctorAssignments = <?php echo json_encode(
        $masters['doctors'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ); ?>;
    var defaultDates = {
        start: <?php echo json_encode($defaults['tanggal_awal']); ?>,
        end: <?php echo json_encode($defaults['tanggal_akhir']); ?>
    };
    var numberFormat = new Intl.NumberFormat('id-ID');
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var charts = { status: null, poli: null, trend: null };
    var activeRequest = null;
    var searchTimer = null;
    var state = {
        page: 1,
        per_page: 20,
        sort: 'tanggal',
        direction: 'asc',
        search: '',
        filters: null
    };

    var elements = {
        form: document.getElementById('task4FilterForm'),
        start: document.getElementById('task4TanggalAwal'),
        end: document.getElementById('task4TanggalAkhir'),
        poli: document.getElementById('task4Poli'),
        doctor: document.getElementById('task4Dokter'),
        status: document.getElementById('task4Status'),
        reset: document.getElementById('task4Reset'),
        exportButton: document.getElementById('task4Export'),
        submit: document.getElementById('task4Submit'),
        search: document.getElementById('task4Search'),
        perPage: document.getElementById('task4PerPage'),
        error: document.getElementById('task4Error'),
        loading: document.getElementById('task4Loading'),
        tableWrap: document.getElementById('task4TableWrap'),
        tableBody: document.getElementById('task4TableBody'),
        tableNote: document.getElementById('task4TableNote'),
        info: document.getElementById('task4Info'),
        pages: document.getElementById('task4Pages')
    };

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setLoading(isLoading) {
        elements.loading.classList.toggle('is-active', isLoading);
        elements.tableWrap.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        elements.submit.disabled = isLoading;
    }

    function showError(message) {
        elements.error.textContent = message || '';
        elements.error.style.display = message ? 'block' : 'none';
    }

    function rebuildDoctors() {
        var selectedPoli = elements.poli.value;
        var previous = elements.doctor.value;
        var doctors = {};

        doctorAssignments.forEach(function (item) {
            if (!selectedPoli || item.kd_poli === selectedPoli) {
                doctors[item.kd_dokter] = item.nm_dokter;
            }
        });

        elements.doctor.innerHTML = '<option value="">Semua Dokter</option>';
        Object.keys(doctors).sort(function (a, b) {
            return doctors[a].localeCompare(doctors[b], 'id');
        }).forEach(function (code) {
            var option = document.createElement('option');
            option.value = code;
            option.textContent = doctors[code];
            elements.doctor.appendChild(option);
        });

        if (doctors[previous]) {
            elements.doctor.value = previous;
        }
        syncSelect2(elements.doctor);
    }

    function syncSelect2(selectElement) {
        if (
            window.jQuery
            && window.jQuery.fn
            && typeof window.jQuery.fn.select2 === 'function'
            && window.jQuery(selectElement).data('select2')
        ) {
            window.jQuery(selectElement).trigger('change.select2');
        }
    }

    function validateDates() {
        if (!elements.start.value || !elements.end.value) {
            showError('Tanggal awal dan tanggal akhir wajib diisi.');
            return false;
        }
        if (elements.end.value < elements.start.value) {
            showError('Tanggal akhir tidak boleh lebih kecil dari tanggal awal.');
            return false;
        }
        var start = new Date(elements.start.value + 'T00:00:00');
        var end = new Date(elements.end.value + 'T00:00:00');
        if (Math.round((end - start) / 86400000) > 366) {
            showError('Rentang laporan maksimal 366 hari.');
            return false;
        }
        showError('');
        return true;
    }

    function currentParams() {
        return {
            tanggal_awal: elements.start.value,
            tanggal_akhir: elements.end.value,
            kd_poli: elements.poli.value,
            kd_dokter: elements.doctor.value,
            kesesuaian: elements.status.value || 'semua',
            search: state.search,
            page: state.page,
            per_page: state.per_page,
            sort: state.sort,
            direction: state.direction
        };
    }

    function updateSummary(summary) {
        document.getElementById('task4Total').textContent = numberFormat.format(summary.total || 0);
        document.getElementById('task4Sesuai').textContent = numberFormat.format(summary.sesuai || 0);
        document.getElementById('task4TidakSesuai').textContent = numberFormat.format(summary.tidak_sesuai || 0);
        document.getElementById('task4Persentase').textContent = numberFormat.format(summary.persentase_kesesuaian || 0) + '%';
    }

    function updateDoctorSummary(summary) {
        summary = summary || {};
        document.getElementById('task4TotalDokter').textContent = numberFormat.format(summary.total_dokter_praktek || 0);
        document.getElementById('task4DokterTidakSesuai').textContent = numberFormat.format(summary.dokter_tidak_sesuai || 0);
        document.getElementById('task4PersentaseDokter').textContent = numberFormat.format(summary.persentase_kesesuaian || 0) + '%';
    }

    function destroyChart(name) {
        if (charts[name]) {
            charts[name].destroy();
            charts[name] = null;
        }
    }

    function chartAnimation(type) {
        if (reduceMotion) {
            return { duration: 0 };
        }

        if (type === 'doughnut') {
            return {
                duration: 900,
                easing: 'easeOutQuart',
                animateRotate: true,
                animateScale: true
            };
        }

        return {
            duration: type === 'line' ? 1100 : 850,
            easing: type === 'line' ? 'easeOutCubic' : 'easeOutQuart',
            delay: function (context) {
                if (context.type !== 'data') return 0;
                var step = type === 'line' ? 22 : 14;
                return Math.min((context.dataIndex * step) + (context.datasetIndex * 90), 480);
            }
        };
    }

    function updateCharts(chartData, summary) {
        if (typeof Chart === 'undefined') {
            return;
        }

        destroyChart('status');
        destroyChart('poli');
        destroyChart('trend');

        var statusValues = chartData.status.map(function (item) { return item.value; });
        var hasStatusData = statusValues.some(function (value) { return value > 0; });
        charts.status = new Chart(document.getElementById('task4StatusChart'), {
            type: 'doughnut',
            data: {
                labels: hasStatusData ? chartData.status.map(function (item) { return item.label; }) : ['Belum Ada Data'],
                datasets: [{
                    data: hasStatusData ? statusValues : [1],
                    backgroundColor: hasStatusData ? ['#24966d', '#d1495b'] : ['#dfe7ef'],
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '64%',
                animation: chartAnimation('doughnut'),
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 9 } },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                if (!hasStatusData) return 'Belum ada data';
                                var total = summary.total || 0;
                                var percent = total > 0 ? (context.raw / total * 100) : 0;
                                return context.label + ': ' + numberFormat.format(context.raw) + ' (' + numberFormat.format(percent) + '%)';
                            }
                        }
                    }
                }
            }
        });

        var poliBox = document.getElementById('task4PoliChartBox');
        poliBox.style.height = Math.max(310, chartData.per_poli.length * 34) + 'px';
        charts.poli = new Chart(document.getElementById('task4PoliChart'), {
            type: 'bar',
            data: {
                labels: chartData.per_poli.map(function (item) { return item.label; }),
                datasets: [
                    { label: 'Sesuai', data: chartData.per_poli.map(function (item) { return item.sesuai; }), backgroundColor: '#24966d', borderRadius: 3 },
                    { label: 'Tidak Sesuai', data: chartData.per_poli.map(function (item) { return item.tidak_sesuai; }), backgroundColor: '#d1495b', borderRadius: 3 }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: chartAnimation('bar'),
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 9 } } },
                scales: {
                    x: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(70,92,116,.10)' } },
                    y: { stacked: true, grid: { display: false } }
                }
            }
        });

        var daily = chartData.tren_harian;
        charts.trend = new Chart(document.getElementById('task4TrendChart'), {
            type: 'line',
            data: {
                labels: daily.map(function (item) { return item.tanggal; }),
                datasets: [{
                    label: 'Persentase Kesesuaian',
                    data: daily.map(function (item) { return item.persentase; }),
                    borderColor: '#2d7dd2',
                    backgroundColor: 'rgba(45,125,210,.12)',
                    pointBackgroundColor: '#e0a126',
                    pointBorderColor: '#fff',
                    pointRadius: 3,
                    borderWidth: 2,
                    tension: .25,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: chartAnimation('line'),
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 9 } },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return 'Persentase: ' + numberFormat.format(context.raw || 0) + '%';
                            },
                            afterBody: function (items) {
                                if (!items.length) return [];
                                var item = daily[items[0].dataIndex];
                                return [
                                    'Total: ' + numberFormat.format(item.total),
                                    'Sesuai: ' + numberFormat.format(item.sesuai),
                                    'Tidak Sesuai: ' + numberFormat.format(item.tidak_sesuai)
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, max: 100, ticks: { callback: function (value) { return value + '%'; } } }
                }
            }
        });
    }

    function updateSortIndicators() {
        document.querySelectorAll('.task4-sort[data-sort]').forEach(function (button) {
            var icon = button.querySelector('.task4-sort-icon');
            icon.textContent = button.getAttribute('data-sort') === state.sort
                ? (state.direction === 'asc' ? '\u25b2' : '\u25bc')
                : '';
        });
    }

    function updateTable(rows, pagination) {
        if (!rows.length) {
            elements.tableBody.innerHTML = '<tr><td colspan="9" class="task4-empty">Tidak ada data yang sesuai dengan filter atau pencarian.</td></tr>';
        } else {
            var startNumber = ((pagination.page - 1) * pagination.per_page) + 1;
            elements.tableBody.innerHTML = rows.map(function (row, index) {
                var isMatch = row.kesesuaian === 'Sesuai';
                return '<tr>'
                    + '<td class="text-center">' + numberFormat.format(startNumber + index) + '</td>'
                    + '<td>' + escapeHtml(row.tanggal_label) + '</td>'
                    + '<td>' + escapeHtml(row.nama_poli) + '</td>'
                    + '<td class="text-center">' + escapeHtml(row.jam_buka_poli) + '</td>'
                    + '<td>' + escapeHtml(row.nama_dokter) + '</td>'
                    + '<td class="text-center" style="white-space:nowrap;">' + escapeHtml(row.nomor_rawat) + '</td>'
                    + '<td>' + escapeHtml(row.task_4_paling_awal || '-') + '</td>'
                    + '<td>' + escapeHtml(row.selisih_waktu) + '</td>'
                    + '<td class="text-center"><span class="task4-status ' + (isMatch ? 'task4-status-sesuai' : 'task4-status-tidak') + '">' + escapeHtml(row.kesesuaian) + '</span></td>'
                    + '</tr>';
            }).join('');
        }

        var from = pagination.total > 0 ? ((pagination.page - 1) * pagination.per_page) + 1 : 0;
        var to = Math.min(pagination.page * pagination.per_page, pagination.total);
        elements.info.textContent = 'Menampilkan ' + numberFormat.format(from) + '-' + numberFormat.format(to) + ' dari ' + numberFormat.format(pagination.total) + ' data';
        elements.tableNote.textContent = 'Data sesuai filter aktif. Gunakan judul kolom untuk mengurutkan hasil.';
        renderPagination(pagination);
        updateSortIndicators();
    }

    function pageButton(label, page, disabled, active, title) {
        var button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.disabled = disabled;
        button.className = active ? 'is-active' : '';
        if (title) button.title = title;
        button.addEventListener('click', function () {
            state.page = page;
            loadReport();
        });
        return button;
    }

    function renderPagination(pagination) {
        elements.pages.innerHTML = '';
        elements.pages.appendChild(pageButton('\u2039', pagination.page - 1, pagination.page <= 1, false, 'Halaman sebelumnya'));

        var start = Math.max(1, pagination.page - 2);
        var end = Math.min(pagination.total_pages, pagination.page + 2);
        if (start > 1) {
            elements.pages.appendChild(pageButton('1', 1, false, pagination.page === 1));
            if (start > 2) {
                var dotsLeft = document.createElement('span');
                dotsLeft.textContent = '...';
                elements.pages.appendChild(dotsLeft);
            }
        }
        for (var page = start; page <= end; page++) {
            elements.pages.appendChild(pageButton(String(page), page, false, page === pagination.page));
        }
        if (end < pagination.total_pages) {
            if (end < pagination.total_pages - 1) {
                var dotsRight = document.createElement('span');
                dotsRight.textContent = '...';
                elements.pages.appendChild(dotsRight);
            }
            elements.pages.appendChild(pageButton(String(pagination.total_pages), pagination.total_pages, false, pagination.page === pagination.total_pages));
        }

        elements.pages.appendChild(pageButton('\u203a', pagination.page + 1, pagination.page >= pagination.total_pages, false, 'Halaman berikutnya'));
    }

    function loadReport() {
        if (!validateDates()) return;
        if (activeRequest) activeRequest.abort();
        var request = new AbortController();
        activeRequest = request;
        setLoading(true);
        showError('');

        var params = currentParams();
        fetch(apiUrl + '?' + new URLSearchParams(params).toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            signal: request.signal
        })
            .then(function (response) {
                return response.json().catch(function () {
                    throw new Error('Respons server tidak dapat dibaca.');
                }).then(function (payload) {
                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message || 'Data evaluasi gagal dimuat.');
                    }
                    return payload;
                });
            })
            .then(function (payload) {
                state.page = payload.pagination.page;
                state.filters = payload.filters;
                updateSummary(payload.summary);
                updateDoctorSummary(payload.doctor_summary);
                updateCharts(payload.chart, payload.summary);
                updateTable(payload.data, payload.pagination);
            })
            .catch(function (error) {
                if (error.name === 'AbortError') return;
                showError(error.message || 'Data evaluasi gagal dimuat.');
                elements.tableBody.innerHTML = '<tr><td colspan="9" class="task4-empty">Terjadi kesalahan saat memuat data.</td></tr>';
                elements.tableNote.textContent = 'Data belum dapat ditampilkan.';
            })
            .finally(function () {
                if (activeRequest === request) {
                    setLoading(false);
                    activeRequest = null;
                }
            });
    }

    elements.form.addEventListener('submit', function (event) {
        event.preventDefault();
        state.page = 1;
        loadReport();
    });
    elements.poli.addEventListener('change', rebuildDoctors);
    elements.reset.addEventListener('click', function () {
        elements.start.value = defaultDates.start;
        elements.end.value = defaultDates.end;
        elements.poli.value = '';
        syncSelect2(elements.poli);
        rebuildDoctors();
        elements.doctor.value = '';
        syncSelect2(elements.doctor);
        elements.status.value = 'semua';
        syncSelect2(elements.status);
        elements.search.value = '';
        elements.perPage.value = '20';
        state.page = 1;
        state.per_page = 20;
        state.search = '';
        state.sort = 'tanggal';
        state.direction = 'asc';
        loadReport();
    });
    elements.search.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            state.search = elements.search.value.trim();
            state.page = 1;
            loadReport();
        }, 350);
    });
    elements.perPage.addEventListener('change', function () {
        state.per_page = parseInt(elements.perPage.value, 10) || 20;
        state.page = 1;
        loadReport();
    });
    document.querySelectorAll('.task4-sort[data-sort]').forEach(function (button) {
        button.addEventListener('click', function () {
            var sort = button.getAttribute('data-sort');
            if (state.sort === sort) {
                state.direction = state.direction === 'asc' ? 'desc' : 'asc';
            } else {
                state.sort = sort;
                state.direction = 'asc';
            }
            state.page = 1;
            loadReport();
        });
    });
    elements.exportButton.addEventListener('click', function () {
        if (!validateDates()) return;
        var params = currentParams();
        delete params.search;
        delete params.page;
        delete params.per_page;
        delete params.sort;
        delete params.direction;
        window.location.href = exportUrl + '?' + new URLSearchParams(params).toString();
    });

    rebuildDoctors();
    updateSortIndicators();
    loadReport();
})();
</script>
