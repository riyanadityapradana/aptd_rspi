<?php require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php'; ?>
<?php
$jenisIgdOptions = [
    'semua' => [
        'label' => 'Semua',
        'items' => [
            ['code' => 'IGDK', 'status' => 'Ranap'],
            ['code' => 'U0009', 'status' => 'Ralan'],
        ],
    ],
    'igd_ranap' => [
        'label' => 'IGD Ranap (IGDK / UGD)',
        'items' => [
            ['code' => 'IGDK', 'status' => 'Ranap'],
        ],
    ],
    'igd_ralan' => [
        'label' => 'IGD Ralan (U0009 / Poli Umum)',
        'items' => [
            ['code' => 'U0009', 'status' => 'Ralan'],
        ],
    ],
];

$penjamin = [
    'A09' => 'UMUM',
    'BPJ' => 'BPJS',
    'A92' => 'ASURANSI',
];

$jenisIgd = isset($_POST['jenis_igd']) ? trim((string) $_POST['jenis_igd']) : 'semua';
if (!isset($jenisIgdOptions[$jenisIgd])) {
    $jenisIgd = 'semua';
}

$tanggalAwal = isset($_POST['tanggal_awal']) ? trim((string) $_POST['tanggal_awal']) : date('Y-m-01');
$tanggalAkhir = isset($_POST['tanggal_akhir']) ? trim((string) $_POST['tanggal_akhir']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAwal)) {
    $tanggalAwal = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAkhir)) {
    $tanggalAkhir = date('Y-m-d');
}
if ($tanggalAwal > $tanggalAkhir) {
    $tmp = $tanggalAwal;
    $tanggalAwal = $tanggalAkhir;
    $tanggalAkhir = $tmp;
}

$selectedItems = $jenisIgdOptions[$jenisIgd]['items'];
$selectedCodes = array_map(function ($item) {
    return $item['code'];
}, $selectedItems);
$selectedStatuses = array_map(function ($item) {
    return $item['status'];
}, $selectedItems);
$igdWhereParts = array_map(function ($item) use ($mysqli) {
    return "(rp.kd_poli = '" . mysqli_real_escape_string($mysqli, $item['code']) . "' AND rp.status_lanjut = '" . mysqli_real_escape_string($mysqli, $item['status']) . "')";
}, $selectedItems);
$bpjsSepExists = "EXISTS (
            SELECT 1
            FROM bridging_sep bs
            WHERE bs.no_rawat = rp.no_rawat
                AND bs.no_sep IS NOT NULL
                AND bs.no_sep <> ''
        )";

$data = array_fill_keys(array_keys($penjamin), 0);
$total = 0;
$sql = "
    SELECT
        SUM(CASE WHEN rp.kd_pj = 'A09' THEN 1 ELSE 0 END) AS umum,
        SUM(CASE WHEN rp.kd_pj = 'BPJ' AND " . $bpjsSepExists . " THEN 1 ELSE 0 END) AS bpjs,
        SUM(CASE WHEN rp.kd_pj = 'A92' THEN 1 ELSE 0 END) AS asuransi
    FROM reg_periksa rp
    WHERE (" . implode(' OR ', $igdWhereParts) . ")
        AND rp.kd_pj IN ('A09', 'BPJ', 'A92')
        AND rp.stts = 'Sudah'
        AND rp.status_bayar = 'Sudah Bayar'
        AND rp.tgl_registrasi BETWEEN '" . mysqli_real_escape_string($mysqli, $tanggalAwal) . "' AND '" . mysqli_real_escape_string($mysqli, $tanggalAkhir) . "'
        AND rp.no_rkm_medis NOT IN (
            SELECT no_rkm_medis FROM pasien WHERE LOWER(nm_pasien) LIKE '%test%'
        )
";
$result = mysqli_query($mysqli, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $data['A09'] = isset($row['umum']) ? (int) $row['umum'] : 0;
    $data['BPJ'] = isset($row['bpjs']) ? (int) $row['bpjs'] : 0;
    $data['A92'] = isset($row['asuransi']) ? (int) $row['asuransi'] : 0;
    $total = array_sum($data);
}
?>
<br>
<div class="row text-left">
    <div class="col">
        <h3 class="text-lef" style="color: #666666; margin-bottom: 5px;">DATA KUNJUNGAN PASIEN IGD</h3>
        <hr style="height: 1px; background-image: linear-gradient(to right, rgba(0,0,0,0), rgba(102,102,102,1), rgba(0,0,0,0) ); margin-top: 0; margin-bottom: 10px;">
    </div>
</div>
<div class="row">
    <div class="col-sm-12" style="border-right: 1px solid #E5E5E5">
        <div class="dataTables_wrapper table-responsive-sm" style="padding-top: 0;">
            <div class="wrapper">
                <form id="filterFormIgd" method="post" class="form-inline mb-2">
                    <div class="form-group mr-2 mb-2">
                        <label for="jenis_igd">Jenis IGD:&nbsp;</label>
                        <select name="jenis_igd" id="jenis_igd" class="form-control form-control-sm ml-1">
                            <?php foreach ($jenisIgdOptions as $key => $option): ?>
                                <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $jenisIgd === $key ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mr-2 mb-2">
                        <label for="tanggal_awal">Tanggal Awal:&nbsp;</label>
                        <input type="date" name="tanggal_awal" id="tanggal_awal" class="form-control form-control-sm ml-1" value="<?php echo htmlspecialchars($tanggalAwal, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="form-group mr-2 mb-2">
                        <label for="tanggal_akhir">Tanggal Akhir:&nbsp;</label>
                        <input type="date" name="tanggal_akhir" id="tanggal_akhir" class="form-control form-control-sm ml-1" value="<?php echo htmlspecialchars($tanggalAkhir, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm mb-2">Terapkan</button>
                    <button type="button" class="btn btn-success btn-sm ml-2 mb-2" id="btnExportIgd">
                        <i class="fa fa-file-excel"></i> Export Excel
                    </button>
                </form>
                <table class="table table-sm table-bordered table-hover" id="table4" style="width:100%;margin-top: 10px;">
                    <thead class="thead-dark">
                        <tr>
                            <th style="text-align: center;">No.</th>
                            <th>Filter IGD</th>
                            <th>Kode Poli</th>
                            <th>Status Lanjut</th>
                            <?php foreach ($penjamin as $label): ?>
                                <th><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></th>
                            <?php endforeach; ?>
                            <th>Jumlah Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: center;">1</td>
                            <td><?php echo htmlspecialchars($jenisIgdOptions[$jenisIgd]['label'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(implode(', ', $selectedCodes), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(implode(', ', array_unique($selectedStatuses)), ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php foreach (array_keys($penjamin) as $kd): ?>
                                <td style="text-align: center;"><?php echo htmlspecialchars((string) $data[$kd], ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php endforeach; ?>
                            <td style="text-align: center; font-weight: bold;"><?php echo htmlspecialchars((string) $total, ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function(){
        $('#filterFormIgd').on('change', 'select,input[type="date"]', function(){
            $('#filterFormIgd').submit();
        });

        $('#btnExportIgd').on('click', function(){
            var formData = new FormData();
            formData.append('jenis_igd', $('#jenis_igd').val());
            formData.append('tanggal_awal', $('#tanggal_awal').val());
            formData.append('tanggal_akhir', $('#tanggal_akhir').val());
            formData.append('export', '1');

            $.ajax({
                type: 'POST',
                url: 'main_app.php?page=export_kunjungan_igd',
                data: formData,
                processData: false,
                contentType: false,
                xhrFields: {
                    responseType: 'blob'
                },
                success: function(data){
                    var filename = 'Data_Kunjungan_IGD_' + new Date().toISOString().split('T')[0] + '.xlsx';
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
                    alert('Gagal export data IGD');
                }
            });
        });
    });
    </script>
</div>
