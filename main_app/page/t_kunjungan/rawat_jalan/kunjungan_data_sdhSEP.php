<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
$conn = $mysqli;

// Read filter values from POST
$filter_tgl_awal = isset($_POST['tgl_awal']) ? trim($_POST['tgl_awal']) : date('Y-m-01');
$filter_tgl_akhir = isset($_POST['tgl_akhir']) ? trim($_POST['tgl_akhir']) : date('Y-m-d');
$filter_stts = isset($_POST['stts']) ? trim($_POST['stts']) : 'Belum';

// Build WHERE clause
$where_parts = [
    "pj.kd_pj = 'BPJ'",
    "rp.status_lanjut = 'Ralan'",
    "bs.no_sep IS NOT NULL",
    "bs.no_sep <> ''",
    "rp.tgl_registrasi BETWEEN '" . $conn->real_escape_string($filter_tgl_awal) . "' AND '" . $conn->real_escape_string($filter_tgl_akhir) . "'"
];

// Add status filter if selected
if ($filter_stts !== '') {
    $where_parts[] = "rp.stts = '" . $conn->real_escape_string($filter_stts) . "'";
}

$where_clause = implode(' AND ', $where_parts);

// Query data
$sql = "SELECT 
    rp.no_rawat,
    rp.no_rkm_medis,
    ps.nm_pasien,
    rp.tgl_registrasi,
    pl.nm_poli,
    d.nm_dokter,
    rp.stts,
    rp.status_lanjut,
    pj.png_jawab AS penjamin,
    bs.no_sep,
    bs.tglsep
FROM reg_periksa rp
INNER JOIN pasien ps 
    ON rp.no_rkm_medis = ps.no_rkm_medis
INNER JOIN poliklinik pl 
    ON rp.kd_poli = pl.kd_poli
INNER JOIN dokter d 
    ON rp.kd_dokter = d.kd_dokter
INNER JOIN penjab pj 
    ON rp.kd_pj = pj.kd_pj
INNER JOIN bridging_sep bs 
    ON rp.no_rawat = bs.no_rawat
WHERE $where_clause
ORDER BY rp.tgl_registrasi DESC";

$result = $conn->query($sql);
if (!$result) {
    die('Query error: ' . $conn->error);
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

?>
<br>
<div class="row text-left">
    <div class="col">
        <h3 class="text-left" style="color: #666666; margin-bottom: 5px;">DATA PASIEN BPJS RAWAT JALAN SUDAH SEP</h3>
        <hr style="height: 1px; background-image: linear-gradient(to right, rgba(0,0,0,0), rgba(102,102,102,1), rgba(0,0,0,0)); margin-top: 0; margin-bottom: 10px;">
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="dataTables_wrapper table-responsive-sm" style="padding-top: 0;">
            <div class="wrapper">
                <form id="filterForm" method="post" class="form-inline mb-3">
                    <div class="form-group mr-2">
                        <label for="tgl_awal">Tanggal Awal:&nbsp;</label>
                        <input type="date" name="tgl_awal" id="tgl_awal" class="form-control form-control-sm ml-1" value="<?php echo htmlspecialchars($filter_tgl_awal); ?>">
                    </div>
                    <div class="form-group mr-2">
                        <label for="tgl_akhir">Tanggal Akhir:&nbsp;</label>
                        <input type="date" name="tgl_akhir" id="tgl_akhir" class="form-control form-control-sm ml-1" value="<?php echo htmlspecialchars($filter_tgl_akhir); ?>">
                    </div>
                    <div class="form-group mr-2">
                        <label for="stts">Status Periksa:&nbsp;</label>
                        <select name="stts" id="stts" class="form-control form-control-sm ml-1">
                            <option value="Sudah" <?php echo ($filter_stts === 'Sudah') ? 'selected' : ''; ?>>Sudah</option>
                            <option value="Belum" <?php echo ($filter_stts === 'Belum') ? 'selected' : ''; ?>>Belum</option>
                            <option value="Batal" <?php echo ($filter_stts === 'Batal') ? 'selected' : ''; ?>>Batal</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Tampilkan Data</button>
                    <button type="button" class="btn btn-success btn-sm ml-2" id="btnExport">
                        <i class="fa fa-file-excel"></i> Export Excel
                    </button>
                </form>

                <table class="table table-sm table-bordered table-hover" id="tableSdhSEP" style="width:100%; margin-top: 10px;">
                    <thead class="thead-dark">
                        <tr>
                            <th style="text-align: center;">No.</th>
                            <th>No. Rawat</th>
                            <th>No. Rekam Medis</th>
                            <th>Nama Pasien</th>
                            <th>Tanggal Registrasi</th>
                            <th>Poliklinik</th>
                            <th>Dokter</th>
                            <th>Status Periksa</th>
                            <th>Penjamin</th>
                            <th>No. SEP</th>
                            <th>Tanggal SEP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (count($data) > 0) {
                            $row_num = 0;
                            foreach ($data as $row) {
                                $row_num++;
                                ?>
                                <tr>
                                    <td style="text-align: center;"><?php echo $row_num; ?></td>
                                    <td><?php echo htmlspecialchars($row['no_rawat']); ?></td>
                                    <td><?php echo htmlspecialchars($row['no_rkm_medis']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nm_pasien']); ?></td>
                                    <td><?php echo date('d-m-Y H:i', strtotime($row['tgl_registrasi'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['nm_poli']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nm_dokter']); ?></td>
                                    <td style="text-align: center;">
                                        <?php
                                        $stts_class = '';
                                        if ($row['stts'] === 'Sudah') {
                                            $stts_class = 'badge-success';
                                        } elseif ($row['stts'] === 'Belum') {
                                            $stts_class = 'badge-warning';
                                        } elseif ($row['stts'] === 'Batal') {
                                            $stts_class = 'badge-danger';
                                        }
                                        ?>
                                        <span class="badge <?php echo $stts_class; ?>"><?php echo htmlspecialchars($row['stts']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['penjamin']); ?></td>
                                    <td style="text-align: center; background-color: #E5F5E5;">
                                        <span style="color: green; font-weight: bold;"><?php echo htmlspecialchars($row['no_sep']); ?></span>
                                    </td>
                                    <td style="text-align: center;"><?php echo date('d-m-Y', strtotime($row['tglsep'])); ?></td>
                                </tr>
                                <?php
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan="11" style="text-align: center; color: #999;">Tidak ada data</td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #f8f9fa; font-weight: bold;">
                            <td colspan="11" style="text-align: right; padding-right: 15px;">
                                Total Data: <?php echo count($data); ?> pasien
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    // Export to Excel
    $('#btnExport').on('click', function(){
        var formData = new FormData();
        formData.append('tgl_awal', $('#tgl_awal').val());
        formData.append('tgl_akhir', $('#tgl_akhir').val());
        formData.append('stts', $('#stts').val());
        formData.append('export', '1');

        $.ajax({
            type: 'POST',
            url: 'main_app.php?page=export_sdhSEP',
            data: formData,
            processData: false,
            contentType: false,
            xhrFields: {
                responseType: 'blob'
            },
            success: function(data, status, xhr){
                var filename = 'Pasien_BPJS_SdhSEP_' + new Date().toISOString().split('T')[0] + '.xlsx';
                var link = document.createElement('a');
                var url = URL.createObjectURL(data);
                link.href = url;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            },
            error: function(){
                alert('Gagal export data');
            }
        });
    });
});
</script>

