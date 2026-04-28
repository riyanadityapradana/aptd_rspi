<?php
require_once __DIR__ . '/rl_32_helper.php';
$mysqli = rl32_bootstrap();

$bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : intval(date('m'));
$tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : intval(date('Y'));

if ($bulan < 1 || $bulan > 12) {
    $bulan = intval(date('m'));
}
if ($tahun < 2000 || $tahun > 2100) {
    $tahun = intval(date('Y'));
}

$data = rl32_get_main_report($mysqli, $tahun, $bulan);
$namaPelayananMap = rl32_service_labels();
$metricMap = rl32_metric_map();
?>
<style>
:root {
    --pastel-blue-bg: #f0f8ff;
    --white-card: #ffffff;
    --primary-blue: #a7c7e7;
    --primary-blue-dark: #8da9c4;
    --text-dark: #2c3e50;
    --text-light: #ffffff;
    --border-color: #dee2e6;
    --shadow-color: rgba(0, 0, 0, 0.08);
}

.content-wrapper {
    background-color: var(--white-card);
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px var(--shadow-color);
    margin: 2rem auto 5rem auto;
    max-width: 1800px;
}

.content-wrapper h2 {
    color: var(--text-dark);
    margin-bottom: 1.5rem;
    font-weight: 600;
    border-bottom: 2px solid var(--primary-blue);
    padding-bottom: 0.5rem;
}

.content-wrapper .btn-primary,
.content-wrapper .btn-success,
.content-wrapper .btn-danger {
    font-weight: 500;
    transition: all 0.2s ease-in-out;
}

.content-wrapper .btn-primary:hover,
.content-wrapper .btn-success:hover,
.content-wrapper .btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.content-wrapper .btn-primary {
    background-color: var(--primary-blue);
    border-color: var(--primary-blue);
    color: var(--text-dark);
}

.content-wrapper .btn-primary:hover {
    background-color: var(--primary-blue-dark);
    border-color: var(--primary-blue-dark);
    color: var(--text-dark);
}

.content-wrapper .table-responsive {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: hidden;
}

.content-wrapper .table {
    font-size: 0.85rem;
    width: 100%;
    table-layout: fixed;
}

.content-wrapper .table thead th {
    background-color: var(--primary-blue) !important;
    color: var(--text-dark);
    font-weight: 600;
    border-bottom: 2px solid var(--primary-blue-dark);
    vertical-align: middle;
    position: sticky;
    top: 0;
    z-index: 10;
    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.content-wrapper .table tbody tr:nth-of-type(even) {
    background-color: #f8f9fa;
}

.content-wrapper .table tbody tr:hover {
    background-color: #e9ecef;
}

.content-wrapper .table td,
.content-wrapper .table th {
    vertical-align: middle;
    padding: 0.5rem;
}

.content-wrapper .table td {
    text-align: left;
}

.content-wrapper .table th:not(:nth-child(2)),
.content-wrapper .table td:not(:nth-child(2)) {
    text-align: center;
}

.clickable-cell {
    color: #0056b3;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: color 0.2s;
}

.clickable-cell:hover {
    color: #003d80;
    text-decoration: underline;
}

#detailModal .modal-header {
    background-color: var(--primary-blue);
    color: var(--text-dark);
    border-bottom: 1px solid var(--primary-blue-dark);
}

#detailModal .modal-header .close {
    color: var(--text-dark);
    text-shadow: none;
}

#detailModal .modal-header .close:hover {
    color: #000;
}
</style>

<div class="container-fluid content-wrapper">
    <h2>RL 3.2 Rekapitulasi Kegiatan Pelayanan Rawat Inap</h2>

    <form id="filterForm" method="get" class="form-inline mb-4">
        <input type="hidden" name="page" value="rl32_ranap">

        <label for="bulan" class="mr-2">Bulan:</label>
        <select name="bulan" id="bulan" class="form-control mr-3">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m; ?>" <?= $m === $bulan ? 'selected' : ''; ?>><?= htmlspecialchars(date('F', mktime(0, 0, 0, $m, 1)), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endfor; ?>
        </select>

        <label for="tahun" class="mr-2">Tahun:</label>
        <input type="number" name="tahun" id="tahun" class="form-control mr-3" value="<?= htmlspecialchars((string) $tahun, ENT_QUOTES, 'UTF-8'); ?>" min="2000" max="2100" />

        <button type="submit" class="btn btn-primary">Tampilkan Data</button>
    </form>

    <div class="mb-4">
        <a href="page/t_rl_32/rl_32_ranap_export.php?bulan=<?= urlencode((string) $bulan); ?>&tahun=<?= urlencode((string) $tahun); ?>" class="btn btn-success">Export Excel</a>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-sm" id="mainTable">
            <thead>
                <tr>
                    <?php if (count($data) > 0): ?>
                        <?php foreach (array_keys($data[0]) as $col): ?>
                            <th><?= htmlspecialchars($col, ENT_QUOTES, 'UTF-8'); ?></th>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <th>Tidak ada data untuk ditampilkan</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr><td colspan="22" class="text-center">Tidak ada data yang ditemukan untuk periode yang dipilih.</td></tr>
                <?php else: ?>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <?php foreach ($row as $colName => $value): ?>
                                <?php if ($colName === 'Jenis Pelayanan'): ?>
                                    <td><?= htmlspecialchars($namaPelayananMap[$value] ?? $value, ENT_QUOTES, 'UTF-8'); ?></td>
                                <?php elseif ($colName !== 'No' && is_numeric($value) && intval($value) > 0): ?>
                                    <?php $metric = $metricMap[$colName] ?? ''; ?>
                                    <?php if ($metric !== ''): ?>
                                        <td class="clickable-cell" data-metric="<?= htmlspecialchars($metric, ENT_QUOTES, 'UTF-8'); ?>" data-service="<?= htmlspecialchars((string) $row['Jenis Pelayanan'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <?php else: ?>
                                        <td><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <td><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="detailModal" tabindex="-1" role="dialog" aria-labelledby="detailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailModalLabel">Rincian Data</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="modalBody">
                    Loading...
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (!window.jQuery) {
        console.error('jQuery belum tersedia untuk modul RL 3.2');
        return;
    }

    $('#mainTable').on('click', '.clickable-cell', function() {
        const metric = $(this).data('metric');
        const service = $(this).data('service');
        const bulan = $('#bulan').val();
        const tahun = $('#tahun').val();
        const colIndex = $(this).index();
        const headerText = $('#mainTable th').eq(colIndex).text();
        const serviceFullName = $(this).closest('tr').find('td:nth-child(2)').text();

        $('#detailModalLabel').text(`Rincian ${headerText} - ${serviceFullName}`);
        $('#modalBody').html('<div class="text-center p-5">Loading...</div>');
        $('#detailModal').modal('show');

        $.ajax({
            url: 'page/t_rl_32/rl_32_ranap_details.php',
            method: 'GET',
            data: {
                metric,
                service,
                bulan,
                tahun
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    let html = '<div class="table-responsive"><table class="table table-sm table-bordered table-striped"><thead><tr>';
                    if (response.data.length > 0) {
                        html = `<p class="mb-2"><strong>Total Data Ditemukan: ${response.data.length}</strong></p>`;
                        html += '<div class="table-responsive"><table class="table table-sm table-bordered table-striped"><thead><tr>';
                        Object.keys(response.data[0]).forEach(function(key) {
                            html += `<th>${key}</th>`;
                        });
                        html += '</tr></thead><tbody>';
                        response.data.forEach(function(row) {
                            html += '<tr>';
                            Object.values(row).forEach(function(val) {
                                const escapedVal = $('<div/>').text(val).html();
                                html += `<td>${escapedVal}</td>`;
                            });
                            html += '</tr>';
                        });
                        html += '</tbody></table></div>';
                    } else {
                        html = '<p class="text-center p-4">Tidak ada data rincian yang ditemukan.</p>';
                    }
                    $('#modalBody').html(html);
                } else {
                    let errorMessage = response.message;
                    if (response.debug && response.debug.stray_output) {
                        errorMessage += '<br><small>Debug Info: ' + response.debug.stray_output + '</small>';
                    }
                    if (response.debug && response.debug.exception) {
                        errorMessage += '<br><small>Exception: ' + response.debug.exception + '</small>';
                    }
                    $('#modalBody').html('<div class="alert alert-danger">Error: ' + errorMessage + '</div>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $('#modalBody').html('<div class="alert alert-danger">Gagal mengambil data rincian. Silakan cek console untuk detail. Error: ' + textStatus + '</div>');
                console.error('AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
            }
        });
    });
});
</script>


