<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
$conn = $mysqli;

// Comprehensive poli mapping
$mapping_poli = [
    'GIGI' => ['U0008', 'U0025', 'U0042', 'U0043', 'U0052', 'U0057', 'U0065'],
    'BEDAH' => ['U0002', 'U0004', 'U0015', 'U0054', 'U0066'],
    'ANAK' => ['U0003', 'U0069', 'U0068', 'U0070'],
    'THT' => ['U0006', 'U0011'],
    'PENYAKIT DALAM' => ['U0023', 'U0030', 'U0031', 'U0032', 'U0033', 'U0034', 'U0035', 'U0036', 'U0037', 'U0038', 'U0039', 'U0040', 'U0041', 'U0063'],
    'PARU' => ['U0019'],
    'SARAF' => ['U0007', 'U0049', 'U0050'],
    'MATA' => ['U0005', 'U0061'],
    'KANDUNGAN' => ['U0010', 'U0024', 'U0028', 'U0044', 'U0045', 'U0046', 'U0047', 'U0048', 'U0051', 'U0059', 'U0060', 'U0075', 'U0076'],
    'REHABILITASI MEDIK' => ['kfr'],
    'JANTUNG' => ['U0012', 'U0032'],
    'JIWA' => ['U0013', 'U0018'],
    'ORTHOPEDI' => ['U0014', 'U0016'],
    'VAKSIN' => ['U0053'],
    'MCU' => ['U0071'],
    'HEMODIALISA' => ['U0023'],
    'IGD' => ['IGDK', 'U0009', 'U0013'],
];

// Mapping jenis pembayar
$penjamin = [
    'A09' => 'UMUM',
    'BPJ' => 'BPJS',
    'A92' => 'ASURANSI',
    'A96' => 'Pancar Tour',
];

// Tambahkan Pancar Tour (A96)

// Read filter values from POST (Tanggal Awal - Tanggal Akhir)
$filter_start_date = isset($_POST['tgl_awal']) ? trim($_POST['tgl_awal']) : date('Y-m-01');
$filter_end_date = isset($_POST['tgl_akhir']) ? trim($_POST['tgl_akhir']) : date('Y-m-d');

// Generate weeks between two dates (aligned to Tuesday-to-Tuesday where possible)
function generateWeeksBetweenDates($start_date, $end_date) {
    $start_ts = strtotime($start_date);
    $end_ts = strtotime($end_date);
    if ($start_ts === false) $start_ts = strtotime(date('Y-m-01'));
    if ($end_ts === false) $end_ts = strtotime(date('Y-m-d'));
    if ($start_ts > $end_ts) {
        // swap
        $tmp = $start_ts; $start_ts = $end_ts; $end_ts = $tmp;
    }

    $weeks = [];

    // Find first Tuesday on or after start
    $first = $start_ts;
    $day_of_week = date('w', $first); // 0=Sunday, 2=Tuesday
    if ($day_of_week != 2) {
        $days_until_tuesday = ($day_of_week <= 2) ? (2 - $day_of_week) : (9 - $day_of_week);
        $first = strtotime("+$days_until_tuesday days", $first);
    }

    // If first Tuesday is beyond end date, return single range from start to end
    if ($first > $end_ts) {
        $weeks[] = [
            'start' => date('Y-m-d', $start_ts),
            'end' => date('Y-m-d', $end_ts),
            'label' => date('d M Y', $start_ts) . ' - ' . date('d M Y', $end_ts)
        ];
        return $weeks;
    }

    $current = $first;
    while ($current <= $end_ts) {
        $week_start = $current;
        $week_end = strtotime('+6 days', $week_start);
        if ($week_end > $end_ts) $week_end = $end_ts;

        $weeks[] = [
            'start' => date('Y-m-d', $week_start),
            'end' => date('Y-m-d', $week_end),
            'label' => date('d', $week_start) . ' - ' . date('d M Y', $week_end)
        ];

        $current = strtotime('+7 days', $week_start);
    }

    return $weeks;
}

$weeks = generateWeeksBetweenDates($filter_start_date, $filter_end_date);

// Query data untuk semua minggu dan semua poli
$data_by_poli = [];
$totals_by_penjamin = array_fill_keys(array_keys($penjamin), 0);
$totals_by_week = [];

foreach ($mapping_poli as $poli_name => $poli_codes) {
    $data_by_poli[$poli_name] = [];
    
    foreach ($weeks as $week_idx => $week) {
        if (!isset($totals_by_week[$week_idx])) {
            $totals_by_week[$week_idx] = array_fill_keys(array_keys($penjamin), 0);
        }
        
        $poli_codes_str = "'" . implode("','", array_map(function($v) use ($conn) {
            return $conn->real_escape_string($v);
        }, $poli_codes)) . "'";
        
        $data_by_poli[$poli_name][$week_idx] = [];
        
        foreach ($penjamin as $kd_pj => $label) {
            $sql = "SELECT COUNT(*) as jml FROM reg_periksa rp
                    WHERE rp.kd_poli IN ($poli_codes_str)
                    AND rp.kd_pj = '$kd_pj'
                    AND rp.stts = 'Sudah'
                    AND rp.status_bayar = 'Sudah Bayar'
                    AND rp.no_rkm_medis NOT IN (SELECT no_rkm_medis FROM pasien WHERE LOWER(nm_pasien) LIKE '%test%')
                    AND DAYOFWEEK(rp.tgl_registrasi) <> 1
                    AND rp.tgl_registrasi BETWEEN '" . $week['start'] . "' AND '" . $week['end'] . "'";
            
            $result = $conn->query($sql);
            $row = $result->fetch_assoc();
            $jml = isset($row['jml']) ? (int)$row['jml'] : 0;
            
            $data_by_poli[$poli_name][$week_idx][$kd_pj] = $jml;
            $totals_by_penjamin[$kd_pj] += $jml;
            $totals_by_week[$week_idx][$kd_pj] += $jml;
        }
    }
}

?>
<br>
<div class="row text-left">
    <div class="col">
        <h3 class="text-left" style="color: #666666; margin-bottom: 5px;">REKAP KUNJUNGAN PASIEN HARIAN RAWAT JALAN</h3>
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
                        <input type="date" name="tgl_awal" id="tgl_awal" class="form-control form-control-sm ml-1" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                    </div>
                    <div class="form-group mr-2">
                        <label for="tgl_akhir">Tanggal Akhir:&nbsp;</label>
                        <input type="date" name="tgl_akhir" id="tgl_akhir" class="form-control form-control-sm ml-1" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Tampilkan Data</button>
                    <button type="button" class="btn btn-success btn-sm ml-2" id="btnExport">
                        <i class="fa fa-file-excel"></i> Export Excel
                    </button>
                </form>

                <table class="table table-sm table-bordered table-hover" id="tablePerMinggu" style="width:100%; margin-top: 10px; font-size: 12px;">
                    <thead>
                        <tr style="background-color: #FFA500;">
                            <th style="text-align: center; vertical-align: middle; background-color: #FFA500; color: white;">POLIKLINIK</th>
                            <?php foreach ($weeks as $week): ?>
                                <th colspan="<?php echo count($penjamin) + 1; ?>" style="text-align: center; background-color: #FFA500; color: white;">
                                    <?php echo $week['label']; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                        <tr style="background-color: #FFA500; color: white;">
                            <th style="text-align: center;"></th>
                            <?php foreach ($weeks as $week): ?>
                                <?php foreach($penjamin as $kd => $label): ?>
                                    <th style="text-align: center;"><?php echo htmlspecialchars($label); ?></th>
                                <?php endforeach; ?>
                                <th style="text-align: center;">JLH</th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $row_num = 0;
                        foreach ($mapping_poli as $poli_name => $codes) {
                            $row_num++;
                            echo '<tr>';
                            echo '<td style="font-weight: bold;">' . htmlspecialchars($poli_name) . '</td>';
                            
                            foreach ($weeks as $week_idx => $week) {
                                $total = 0;
                                foreach($penjamin as $kd => $label){
                                    $val = isset($data_by_poli[$poli_name][$week_idx][$kd]) ? $data_by_poli[$poli_name][$week_idx][$kd] : 0;
                                    echo '<td style="text-align: center;">' . $val . '</td>';
                                    $total += $val;
                                }
                                echo '<td style="text-align: center; background-color: #FFEB99; font-weight: bold;">' . $total . '</td>';
                            }
                            
                            echo '</tr>';
                        }
                        ?>
                        <tr style="background-color: #FF6B6B; color: white; font-weight: bold;">
                            <td colspan="2" style="text-align: left; background-color: #FF6B6B;">JUMLAH PER JENIS BAYAR</td>
                            <?php foreach ($weeks as $week_idx => $week): ?>
                                <?php foreach($penjamin as $kd => $label): ?>
                                    <td style="text-align: center; background-color: #FF6B6B;"><?php echo isset($totals_by_week[$week_idx][$kd]) ? $totals_by_week[$week_idx][$kd] : 0; ?></td>
                                <?php endforeach; ?>
                                <td style="text-align: center; background-color: #FF6B6B;"></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr style="background-color: #FF6B6B; color: white; font-weight: bold;">
                            <td colspan="2" style="text-align: left; background-color: #FF6B6B;">JUMLAH PX PER MINGGU</td>
                            <?php foreach ($weeks as $week_idx => $week): ?>
                                <td colspan="<?php echo count($penjamin) + 1; ?>" style="text-align: center; background-color: #FF6B6B;">
                                    <?php 
                                    $total_minggu = 0;
                                    foreach(array_keys($penjamin) as $kd){
                                        $total_minggu += isset($totals_by_week[$week_idx][$kd]) ? $totals_by_week[$week_idx][$kd] : 0;
                                    }
                                    echo $total_minggu;
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    // Auto-submit filter form when any select changes
    $('#filterForm').on('change', 'select, input', function(){
        $('#filterForm').submit();
    });

    // Export to Excel (simple form submit)
    $('#btnExport').on('click', function(){
        var start = $('#tgl_awal').val();
        var end = $('#tgl_akhir').val();
        var form = $('<form method="POST" action="page/t_kunjungan/rawat_jalan/export_kunjungan_ralan.php"></form>');
        form.append($('<input type="hidden" name="tgl_awal" value="' + start + '">'));
        form.append($('<input type="hidden" name="tgl_akhir" value="' + end + '">'));
        $('body').append(form);
        form.submit();
        form.remove();
    });
});
</script>

