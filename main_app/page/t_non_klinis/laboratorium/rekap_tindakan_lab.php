<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';

function rekapLabValidMonth($value)
{
    $month = (int) $value;
    return ($month >= 1 && $month <= 12) ? $month : (int) date('n');
}

function rekapLabValidYear($value)
{
    $year = (int) $value;
    return ($year >= 2000 && $year <= 2100) ? $year : (int) date('Y');
}

$filterMonth = rekapLabValidMonth(isset($_POST['bulan']) ? $_POST['bulan'] : date('n'));
$filterYear = rekapLabValidYear(isset($_POST['tahun']) ? $_POST['tahun'] : date('Y'));
$monthNames = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember',
];

$startDate = sprintf('%04d-%02d-01', $filterYear, $filterMonth);
$nextMonthDate = date('Y-m-d', strtotime($startDate . ' +1 month'));

$sql = "SELECT
            COALESCE(NULLIF(TRIM(jpl.nm_perawatan), ''), pl.kd_jenis_prw) AS pemeriksaan_lab,
            pl.kategori,
            COUNT(*) AS jumlah_pemeriksaan
        FROM periksa_lab pl
        LEFT JOIN jns_perawatan_lab jpl ON jpl.kd_jenis_prw = pl.kd_jenis_prw
        WHERE pl.tgl_periksa >= ?
          AND pl.tgl_periksa < ?
          AND pl.kategori IN ('PA', 'PK')
        GROUP BY pemeriksaan_lab, pl.kategori
        ORDER BY jumlah_pemeriksaan DESC, pemeriksaan_lab ASC, pl.kategori ASC";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ss', $startDate, $nextMonthDate);
try {
    $stmt->execute();
} catch (mysqli_sql_exception $exception) {
    if ((int) $exception->getCode() !== 1615) {
        throw $exception;
    }

    $stmt->close();
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ss', $startDate, $nextMonthDate);
    $stmt->execute();
}

$result = $stmt->get_result();
$rows = [];
$totalPemeriksaan = 0;
while ($row = $result->fetch_assoc()) {
    $row['jumlah_pemeriksaan'] = (int) $row['jumlah_pemeriksaan'];
    $rows[] = $row;
    $totalPemeriksaan += $row['jumlah_pemeriksaan'];
}
$stmt->close();
?>
<br>
<div class="row text-left">
    <div class="col">
        <h3 class="text-left" style="color: #666666; margin-bottom: 5px;">REKAPITULASI JUMLAH TINDAKAN PEMERIKSAAN LABORATORIUM</h3>
        <hr style="height: 1px; background-image: linear-gradient(to right, rgba(0,0,0,0), rgba(102,102,102,1), rgba(0,0,0,0)); margin-top: 0; margin-bottom: 10px;">
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="dataTables_wrapper table-responsive-sm" style="padding-top: 0;">
            <div class="wrapper">
                <form method="post" class="form-inline mb-3">
                    <div class="form-group mr-2">
                        <label for="bulan">Bulan:&nbsp;</label>
                        <select name="bulan" id="bulan" class="form-control form-control-sm ml-1">
                            <?php foreach ($monthNames as $number => $name): ?>
                                <option value="<?php echo $number; ?>" <?php echo $filterMonth === $number ? 'selected' : ''; ?>><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mr-2">
                        <label for="tahun">Tahun:&nbsp;</label>
                        <select name="tahun" id="tahun" class="form-control form-control-sm ml-1">
                            <?php
                            $currentYear = (int) date('Y');
                            for ($year = $currentYear - 6; $year <= $currentYear + 1; $year++):
                            ?>
                                <option value="<?php echo $year; ?>" <?php echo $filterYear === $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Tampilkan Data</button>
                </form>

                <div class="alert alert-info py-2" style="font-size: 13px;">
                    Periode: <strong><?php echo htmlspecialchars($monthNames[$filterMonth] . ' ' . $filterYear, ENT_QUOTES, 'UTF-8'); ?></strong>
                    &nbsp;|&nbsp; Total pemeriksaan: <strong><?php echo number_format($totalPemeriksaan, 0, ',', '.'); ?></strong>
                </div>

                <table class="table table-sm table-bordered table-hover" id="table4" style="width:100%; margin-top: 10px; font-size: 12px;">
                    <thead class="thead-dark">
                        <tr>
                            <th style="text-align: center; width: 60px;">No</th>
                            <th>Pemeriksaan Lab</th>
                            <th style="text-align: center; width: 100px;">Kategori</th>
                            <th style="text-align: center; width: 180px;">Jumlah Pemeriksaan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center;">Tidak ada data/pemeriksaan laboratorium pada periode ini</td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($rows as $row): ?>
                                <tr>
                                    <td style="text-align: center;"><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($row['pemeriksaan_lab'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td style="text-align: center;"><?php echo htmlspecialchars($row['kategori'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td style="text-align: center; font-weight: bold;"><?php echo number_format($row['jumlah_pemeriksaan'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
