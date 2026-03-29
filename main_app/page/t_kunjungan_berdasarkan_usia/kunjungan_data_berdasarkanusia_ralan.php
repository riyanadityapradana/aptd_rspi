<?php
require_once('../config/koneksi.php');
$conn = $mysqli;

// Kategori Usia
$usia_categories = [
    'semua' => 'Semua',
    'anak' => 'Anak-Anak (0-12)',
    'remaja' => 'Remaja (13-17)',
    'dewasa' => 'Dewasa (18-59)',
    'lansia' => 'Lanjut Usia (60+)'
];

// Read filter values from POST
$filter_tgl_awal = isset($_POST['tgl_awal']) ? trim($_POST['tgl_awal']) : date('Y-m-01');
$filter_tgl_akhir = isset($_POST['tgl_akhir']) ? trim($_POST['tgl_akhir']) : date('Y-m-d');
$filter_stts = isset($_POST['stts']) ? trim($_POST['stts']) : 'semua';
$filter_usia = isset($_POST['usia']) ? trim($_POST['usia']) : 'semua';
$filter_jenis_bayar = isset($_POST['jenis_bayar']) ? trim($_POST['jenis_bayar']) : 'semua';

// Build WHERE clause
$where_parts = [
    "r.status_lanjut = 'Ralan'",
    "r.tgl_registrasi BETWEEN '" . $conn->real_escape_string($filter_tgl_awal) . "' AND '" . $conn->real_escape_string($filter_tgl_akhir) . "'"
];

// Add status filter
if ($filter_stts !== 'semua') {
    $where_parts[] = "r.stts = '" . $conn->real_escape_string($filter_stts) . "'";
}

// Add usia filter
if ($filter_usia !== 'semua') {
    $age_condition = '';
    switch ($filter_usia) {
        case 'anak':
            $age_condition = "DATEDIFF(r.tgl_registrasi, p.tgl_lahir) / 365.25 BETWEEN 0 AND 12";
            break;
        case 'remaja':
            $age_condition = "DATEDIFF(r.tgl_registrasi, p.tgl_lahir) / 365.25 BETWEEN 13 AND 17";
            break;
        case 'dewasa':
            $age_condition = "DATEDIFF(r.tgl_registrasi, p.tgl_lahir) / 365.25 BETWEEN 18 AND 59";
            break;
        case 'lansia':
            $age_condition = "DATEDIFF(r.tgl_registrasi, p.tgl_lahir) / 365.25 >= 60";
            break;
    }
    if ($age_condition) {
        $where_parts[] = $age_condition;
    }
}

// Add jenis bayar filter
if ($filter_jenis_bayar !== 'semua') {
    $where_parts[] = "r.kd_pj = '" . $conn->real_escape_string($filter_jenis_bayar) . "'";
}

$where_clause = implode(' AND ', $where_parts);

// Get penjab list - only specific payment types
$penjab_list = [
    'A09' => 'UMUM',
    'BPJ' => 'BPJS',
    'A92' => 'ASURANSI',
    'A96' => 'Pancar Tour'
];

// Query data
$sql = "SELECT r.no_reg, r.no_rawat, r.tgl_registrasi, r.status_lanjut, r.kd_pj, r.stts, p.nm_pasien, p.tgl_lahir, j.png_jawab,
        YEAR(CURDATE()) - YEAR(p.tgl_lahir) - (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(p.tgl_lahir, '%m%d')) AS umur
        FROM reg_periksa r
        JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
        JOIN penjab j ON r.kd_pj = j.kd_pj
        WHERE $where_clause
        ORDER BY r.tgl_registrasi DESC";

$result = $conn->query($sql);
if (!$result) {
    die('Query error: ' . $conn->error);
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// Calculate summary by age category
$summary_usia = ['anak' => 0, 'remaja' => 0, 'dewasa' => 0, 'lansia' => 0];
foreach ($data as $row) {
    $umur = $row['umur'];
    if ($umur >= 0 && $umur <= 12) {
        $summary_usia['anak']++;
    } elseif ($umur >= 13 && $umur <= 17) {
        $summary_usia['remaja']++;
    } elseif ($umur >= 18 && $umur <= 59) {
        $summary_usia['dewasa']++;
    } elseif ($umur >= 60) {
        $summary_usia['lansia']++;
    }
}

?>
<br>
<div class="row text-left">
    <div class="col">
        <h3 class="text-left" style="color: #666666; margin-bottom: 5px;">DATA KUNJUNGAN PASIEN RAWAT JALAN BERDASARKAN USIA</h3>
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
                            <option value="semua" <?php echo ($filter_stts === 'semua') ? 'selected' : ''; ?>>Semua</option>
                            <option value="Sudah" <?php echo ($filter_stts === 'Sudah') ? 'selected' : ''; ?>>Sudah</option>
                            <option value="Belum" <?php echo ($filter_stts === 'Belum') ? 'selected' : ''; ?>>Belum</option>
                            <option value="Batal" <?php echo ($filter_stts === 'Batal') ? 'selected' : ''; ?>>Batal</option>
                        </select>
                    </div>
                    <div class="form-group mr-2">
                        <label for="usia">Kategori Usia:&nbsp;</label>
                        <select name="usia" id="usia" class="form-control form-control-sm ml-1">
                            <?php
                            foreach ($usia_categories as $key => $label) {
                                $sel = ($filter_usia === $key) ? 'selected' : '';
                                echo "<option value=\"$key\" $sel>" . htmlspecialchars($label) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group mr-2">
                        <label for="jenis_bayar">Jenis Bayar:&nbsp;</label>
                        <select name="jenis_bayar" id="jenis_bayar" class="form-control form-control-sm ml-1">
                            <option value="semua" <?php echo ($filter_jenis_bayar === 'semua') ? 'selected' : ''; ?>>Semua</option>
                            <?php
                            foreach ($penjab_list as $kd => $label) {
                                $sel = ($filter_jenis_bayar === $kd) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($kd) . "\" $sel>" . htmlspecialchars($label) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Tampilkan Data</button>
                    <button type="button" class="btn btn-success btn-sm ml-2" id="btnExport">
                        <i class="fa fa-file-excel"></i> Export Excel
                    </button>
                </form>

                <table class="table table-sm table-bordered table-hover" id="table4" style="width:100%; margin-top: 10px;">
                    <thead class="thead-dark">
                        <tr>
                            <th style="text-align: center;">No.</th>
                            <th>No. Reg</th>
                            <th>No. Rawat</th>
                            <th>Tgl Registrasi</th>
                            <th>Nama Pasien</th>
                            <th>TTL</th>
                            <th>Umur</th>
                            <th>Kategori Usia</th>
                            <th>Status Periksa</th>
                            <th>Jenis Bayar</th>
                            <th>Status Lanjut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (count($data) > 0) {
                            $row_num = 0;
                            foreach ($data as $row) {
                                $row_num++;
                                
                                // Tentukan kategori usia
                                $kategori_usia = 'Tidak Diketahui';
                                $umur = $row['umur'];
                                if ($umur >= 0 && $umur <= 12) {
                                    $kategori_usia = 'Anak-Anak';
                                } elseif ($umur >= 13 && $umur <= 17) {
                                    $kategori_usia = 'Remaja';
                                } elseif ($umur >= 18 && $umur <= 59) {
                                    $kategori_usia = 'Dewasa';
                                } elseif ($umur >= 60) {
                                    $kategori_usia = 'Lanjut Usia';
                                }
                                
                                // Status badge
                                $stts_class = 'badge-secondary';
                                if ($row['stts'] === 'Sudah') {
                                    $stts_class = 'badge-success';
                                } elseif ($row['stts'] === 'Belum') {
                                    $stts_class = 'badge-warning';
                                } elseif ($row['stts'] === 'Batal') {
                                    $stts_class = 'badge-danger';
                                }
                                ?>
                                <tr>
                                    <td style="text-align: center;"><?php echo $row_num; ?></td>
                                    <td><?php echo htmlspecialchars($row['no_reg']); ?></td>
                                    <td><?php echo htmlspecialchars($row['no_rawat']); ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($row['tgl_registrasi'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['nm_pasien']); ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($row['tgl_lahir'])); ?></td>
                                    <td style="text-align: center;"><?php echo $umur . ' thn'; ?></td>
                                    <td style="text-align: center;"><?php echo $kategori_usia; ?></td>
                                    <td style="text-align: center;">
                                        <span class="badge <?php echo $stts_class; ?>"><?php echo htmlspecialchars($row['stts']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['png_jawab']); ?></td>
                                    <td><?php echo htmlspecialchars($row['status_lanjut']); ?></td>
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

                <!-- Summary by Age Category -->
                <div style="margin-top: 20px; padding: 15px; background-color: #f0f7ff; border-radius: 5px; border-left: 4px solid #007bff;">
                    <h5 style="margin-top: 0; color: #333;">Ringkasan Jumlah Pasien Berdasarkan Kategori Usia</h5>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                        <div style="padding: 10px; background-color: white; border-radius: 3px; border-left: 3px solid #28a745;">
                            <strong>Anak-Anak (0-12):</strong> <?php echo $summary_usia['anak']; ?> pasien
                        </div>
                        <div style="padding: 10px; background-color: white; border-radius: 3px; border-left: 3px solid #ffc107;">
                            <strong>Remaja (13-17):</strong> <?php echo $summary_usia['remaja']; ?> pasien
                        </div>
                        <div style="padding: 10px; background-color: white; border-radius: 3px; border-left: 3px solid #17a2b8;">
                            <strong>Dewasa (18-59):</strong> <?php echo $summary_usia['dewasa']; ?> pasien
                        </div>
                        <div style="padding: 10px; background-color: white; border-radius: 3px; border-left: 3px solid #dc3545;">
                            <strong>Lanjut Usia (60+):</strong> <?php echo $summary_usia['lansia']; ?> pasien
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    // Auto-submit filter form when any select/input changes
    $('#filterForm').on('change', 'select, input', function(){
        $('#filterForm').submit();
    });

    // Export to Excel
    $('#btnExport').on('click', function(){
        var formData = new FormData();
        formData.append('tgl_awal', $('#tgl_awal').val());
        formData.append('tgl_akhir', $('#tgl_akhir').val());
        formData.append('stts', $('#stts').val());
        formData.append('usia', $('#usia').val());
        formData.append('jenis_bayar', $('#jenis_bayar').val());
        formData.append('export', '1');

        $.ajax({
            type: 'POST',
            url: 'main_app.php?page=export_kunjungan_usia',
            data: formData,
            processData: false,
            contentType: false,
            xhrFields: {
                responseType: 'blob'
            },
            success: function(data, status, xhr){
                var filename = 'Kunjungan_Berdasarkan_Usia_' + new Date().toISOString().split('T')[0] + '.xlsx';
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

