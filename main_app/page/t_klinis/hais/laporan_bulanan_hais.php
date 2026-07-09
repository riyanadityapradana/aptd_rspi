<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
require_once __DIR__ . '/laporan_bulanan_hais_helper.php';

$monthNames = aptd_hais_month_names();
$report = aptd_hais_report($mysqli, aptd_hais_filters_from_request());
$filters = $report['filters'];
$rows = $report['rows'];
$totals = $report['totals'];
?>
<br>
<style>
.hais-wrap{display:grid;gap:14px;margin-bottom:55px}.hais-card{background:#fff;border:1px solid rgba(130,155,190,.22);box-shadow:0 16px 34px rgba(70,96,130,.10);border-radius:18px;padding:18px}.hais-title{margin:0;color:#1e3d6a;font-size:28px;font-weight:850}.hais-subtitle{margin:6px 0 0;color:#637b96;font-size:13px}.hais-filter{display:flex;flex-wrap:wrap;align-items:end;gap:10px;margin-top:15px}.hais-filter .form-control,.hais-filter .btn{border-radius:10px}.hais-table{font-size:11px;background:#fff}.hais-table th,.hais-table td{vertical-align:middle;text-align:center;white-space:nowrap}.hais-table thead th{background:#f9f4f4;border-color:#ddd;color:#363636}.hais-table .hais-group{font-weight:700;background:#f5eeee}.hais-table tfoot td{font-weight:800;background:#fff8dc}.hais-table-responsive{max-height:62vh;overflow:auto;border:1px solid #e1e6ef;border-radius:12px}.hais-note{font-size:12px;color:#5d7188;background:#f5f8fc;border:1px solid #dce8f5;border-radius:12px;padding:10px 12px}
@media(max-width:768px){.hais-title{font-size:23px}.hais-filter{align-items:stretch;flex-direction:column}}
</style>

<div class="hais-wrap">
    <section class="hais-card">
        <h3 class="hais-title">Laporan Bulanan Data HAIs</h3>
        <p class="hais-subtitle">Rekapitulasi harian pemasangan alat, kejadian infeksi, kultur, dan antibiotik dalam satu bulan.</p>
        <form method="post" class="hais-filter" id="haisFilterForm">
            <div class="form-group mb-0">
                <label for="bulan"><strong>Bulan</strong></label>
                <select name="bulan" id="bulan" class="form-control form-control-sm">
                    <?php foreach ($monthNames as $number => $name): ?>
                        <option value="<?php echo $number; ?>" <?php echo (int) $filters['bulan'] === $number ? 'selected' : ''; ?>><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <label for="tahun"><strong>Tahun</strong></label>
                <select name="tahun" id="tahun" class="form-control form-control-sm">
                    <?php $currentYear = (int) date('Y'); for ($year = $currentYear - 6; $year <= $currentYear + 1; $year++): ?>
                        <option value="<?php echo $year; ?>" <?php echo (int) $filters['tahun'] === $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <label for="kd_bangsal"><strong>Ruang/Bangsal</strong></label>
                <select name="kd_bangsal" id="kd_bangsal" class="form-control form-control-sm">
                    <option value="">Semua Ruang</option>
                    <?php foreach ($report['bangsal_options'] as $option): ?>
                        <option value="<?php echo htmlspecialchars($option['kd_bangsal'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filters['kd_bangsal'] === $option['kd_bangsal'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($option['nm_bangsal'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <label for="kd_pj"><strong>Cara Bayar</strong></label>
                <select name="kd_pj" id="kd_pj" class="form-control form-control-sm">
                    <option value="">Semua Cara Bayar</option>
                    <?php foreach ($report['penjab_options'] as $option): ?>
                        <option value="<?php echo htmlspecialchars($option['kd_pj'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filters['kd_pj'] === $option['kd_pj'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($option['png_jawab'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm px-4">Tampilkan Data</button>
            <button type="submit" class="btn btn-success btn-sm px-4" formaction="main_app.php?page=export_laporan_bulanan_hais">Export Excel</button>
        </form>
    </section>

    <div class="hais-note">
        Periode <strong><?php echo htmlspecialchars($report['start_date'] . ' s.d. ' . $report['end_date'], ENT_QUOTES, 'UTF-8'); ?></strong>.
        Deku dihitung dari baris <code>DEKU = 'IYA'</code>; kultur dan antibiotik dihitung dari isian yang tidak kosong.
    </div>

    <section class="hais-card">
        <div class="table-responsive hais-table-responsive">
            <table class="table table-sm table-bordered table-hover hais-table">
                <thead>
                    <tr>
                        <th rowspan="2">No.</th>
                        <th rowspan="2">Tanggal</th>
                        <th rowspan="2">Jml. Pasien</th>
                        <th colspan="4" class="hais-group">Hari Pemasangan</th>
                        <th colspan="8" class="hais-group">Infeksi</th>
                        <th rowspan="2">Deku</th>
                        <th colspan="3" class="hais-group">Hasil Kultur</th>
                        <th rowspan="2">Antibiotik</th>
                    </tr>
                    <tr>
                        <th>ETT</th><th>CVL</th><th>IVL</th><th>UC</th>
                        <th>VAP</th><th>IAD</th><th>Pleb</th><th>ISK</th><th>ILO</th><th>HAP</th><th>Tinea</th><th>Scabies</th>
                        <th>Sputum</th><th>Darah</th><th>Urine</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['tanggal'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo number_format($row['jml_pasien'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format($row['ETT'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format($row['CVL'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format($row['IVL'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format($row['UC'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format($row['VAP'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format($row['IAD'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format($row['PLEB'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format($row['ISK'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format($row['ILO'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format($row['HAP'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format($row['Tinea'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format($row['Scabies'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format($row['DEKU'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format($row['SPUTUM'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format($row['DARAH'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format($row['URINE'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format($row['ANTIBIOTIK'], 0, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" style="text-align:right;">Total :</td>
                        <td><?php echo number_format($totals['jml_pasien'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($totals['ETT'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($totals['CVL'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($totals['IVL'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($totals['UC'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($totals['VAP'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($totals['IAD'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($totals['PLEB'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($totals['ISK'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($totals['ILO'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($totals['HAP'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($totals['Tinea'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($totals['Scabies'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($totals['DEKU'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($totals['SPUTUM'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($totals['DARAH'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($totals['URINE'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($totals['ANTIBIOTIK'], 0, ',', '.'); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</div>
