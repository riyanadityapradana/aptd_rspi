<?php
require_once('../config/koneksi.php');
$conn = $mysqli;

// Kategori Usia (sama seperti ralan)
$usia_categories = [
    'semua' => 'Semua',
    'anak' => 'Anak-Anak (0-12)',
    'remaja' => 'Remaja (13-17)',
    'dewasa' => 'Dewasa (18-59)',
    'lansia' => 'Lanjut Usia (60+)'
];

// Read filter values from POST (samakan dengan ralan)
$filter_tgl_awal = isset($_POST['tgl_awal']) ? trim($_POST['tgl_awal']) : date('Y-m-01');
$filter_tgl_akhir = isset($_POST['tgl_akhir']) ? trim($_POST['tgl_akhir']) : date('Y-m-d');
$filter_stts = isset($_POST['stts']) ? trim($_POST['stts']) : 'semua';
$filter_usia = isset($_POST['usia']) ? trim($_POST['usia']) : 'semua';
$filter_jenis_bayar = isset($_POST['jenis_bayar']) ? trim($_POST['jenis_bayar']) : 'semua';

// Build WHERE clause
$where_parts = [
    "r.status_lanjut = 'Ranap'",
    "r.tgl_registrasi BETWEEN '" . $conn->real_escape_string($filter_tgl_awal) . "' AND '" . $conn->real_escape_string($filter_tgl_akhir) . "'"
];

// Add status pulang filter (menggunakan stts_pulang dari kamar_inap)
if ($filter_stts !== 'semua') {
    $where_parts[] = "ki.stts_pulang = '" . $conn->real_escape_string($filter_stts) . "'";
}

// Add usia filter (tahun seperti ralan)
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

// Get penjab list - only specific payment types (sama seperti ralan)
$penjab_list = [
    'A09' => 'UMUM',
    'BPJ' => 'BPJS',
    'A92' => 'ASURANSI',
    'A96' => 'Pancar Tour'
];

// Exclude move-room / invalid pulang statuses (sesuai query ranap biasa)
$where_parts[] = "ki.stts_pulang NOT IN ('Pindah Kamar', '-', '')";
$where_parts[] = "ki.stts_pulang IS NOT NULL";

$where_clause = implode(' AND ', $where_parts);

// Main query (group by no_rawat to avoid duplicates)
$sql = "SELECT 
    CONCAT('\\'', IFNULL(p.no_rkm_medis, '-')) AS no_rm, 
    IFNULL(p.nm_pasien, '-') AS nama_pasien,
    IFNULL(r.no_rawat, '-') AS no_rawat,
    IFNULL(p.tgl_lahir, '-') AS tgl_lahir,
    IFNULL(r.tgl_registrasi, '-') AS tgl_registrasi,
    IFNULL(r.umurdaftar, '-') AS umur_daftar,
    IFNULL(r.sttsumur, '-') AS status_umur,
    IFNULL(k.kd_kamar, '-') AS kode_kamar,
    IFNULL(b.nm_bangsal, '-') AS nama_bangsal,
    IFNULL(ki.tgl_masuk, '-') AS tgl_masuk,
    IFNULL(ki.tgl_keluar, '-') AS tgl_keluar,
    IFNULL(ki.stts_pulang, '-') AS status_pulang,
    IFNULL(j.png_jawab, '-') AS jenis_bayar
FROM pasien p
INNER JOIN reg_periksa r ON p.no_rkm_medis = r.no_rkm_medis
INNER JOIN kamar_inap ki ON r.no_rawat = ki.no_rawat
INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
LEFT JOIN penjab j ON r.kd_pj = j.kd_pj
WHERE $where_clause
GROUP BY r.no_rawat
ORDER BY r.tgl_registrasi DESC";

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
        <h3 class="text-left" style="color: #666666; margin-bottom: 5px;">DATA KUNJUNGAN PASIEN RAWAT INAP BERDASARKAN USIA</h3>
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
                        <label for="stts">Status Pulang:&nbsp;</label>
                        <select name="stts" id="stts" class="form-control form-control-sm ml-1">
                            <option value="semua" <?php echo ($filter_stts === 'semua') ? 'selected' : ''; ?>>Semua</option>
                            <option value="Pulang" <?php echo ($filter_stts === 'Pulang') ? 'selected' : ''; ?>>Pulang</option>
                            <option value="Membaik" <?php echo ($filter_stts === 'Membaik') ? 'selected' : ''; ?>>Membaik</option>
                            <option value="Meninggal" <?php echo ($filter_stts === 'Meninggal') ? 'selected' : ''; ?>>Meninggal</option>
                            <option value="Pindah Kamar" <?php echo ($filter_stts === 'Pindah Kamar') ? 'selected' : ''; ?>>Pindah Kamar</option>
                            <option value="Atas Persetujuan Dokter" <?php echo ($filter_stts === 'Atas Persetujuan Dokter') ? 'selected' : ''; ?>>Atas Persetujuan Dokter</option>
                            <option value="-" <?php echo ($filter_stts === '-') ? 'selected' : ''; ?>>-</option>
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

                <table class="table table-sm table-bordered table-hover" id="table_ranap" style="width:100%; margin-top: 10px;">
                    <thead class="thead-dark">
                        <tr>
                            <th style="text-align: center;">No.</th>
                            <th>No. RM</th>
                            <th>Nama Pasien</th>
                            <th>No. Rawat</th>
                            <th>Tgl Lahir</th>
                            <th>Tgl Registrasi</th>
                            <th>Umur Daftar</th>
                            <th>Status Umur</th>
                            <th>Kode Kamar</th>
                            <th>Nama Bangsal</th>
                            <th>Tgl Masuk</th>
                            <th>Tgl Keluar</th>
                            <th>Jenis Bayar</th>
                            <th>Status Pulang</th>
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
                                    <td><?php echo htmlspecialchars($row['no_rm']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_pasien']); ?></td>
                                    <td><?php echo htmlspecialchars($row['no_rawat']); ?></td>
                                    <td><?php echo htmlspecialchars($row['tgl_lahir']); ?></td>
                                    <td><?php echo htmlspecialchars($row['tgl_registrasi']); ?></td>
                                    <td><?php echo htmlspecialchars($row['umur_daftar']); ?></td>
                                    <td><?php echo htmlspecialchars($row['status_umur']); ?></td>
                                    <td><?php echo htmlspecialchars($row['kode_kamar']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_bangsal']); ?></td>
                                    <td><?php echo htmlspecialchars($row['tgl_masuk']); ?></td>
                                    <td><?php echo htmlspecialchars($row['tgl_keluar']); ?></td>
                                    <td><?php echo htmlspecialchars($row['jenis_bayar']); ?></td>
                                    <td><?php echo htmlspecialchars($row['status_pulang']); ?></td>
                                </tr>
                                <?php
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan="13" style="text-align: center; color: #999;">Tidak ada data</td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #f8f9fa; font-weight: bold;">
                            <td colspan="13" style="text-align: right; padding-right: 15px;">
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
    // Auto-submit filter form when any select/input changes
    $('#filterForm').on('change', 'select, input', function(){
        $('#filterForm').submit();
    });

    // Export to Excel (mengirim parameter sama seperti ralan)
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
            url: 'main_app.php?page=export_kunjungan_usia_ranap',
            data: formData,
            processData: false,
            contentType: false,
            xhrFields: {
                responseType: 'blob'
            },
            success: function(data, status, xhr){
                var filename = 'Kunjungan_Ranap_Berdasarkan_Usia_' + new Date().toISOString().split('T')[0] + '.xlsx';
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

