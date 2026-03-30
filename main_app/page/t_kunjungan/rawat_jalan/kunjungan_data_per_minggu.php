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
];

// Read filter values from POST
$filter_month = isset($_POST['bulan']) ? intval($_POST['bulan']) : date('n');
$filter_year = isset($_POST['tahun']) ? intval($_POST['tahun']) : date('Y');

// Generate weeks for the month (Tuesday to Tuesday)
function generateWeeksFromTuesday($year, $month) {
    $first_day = strtotime("$year-$month-01");
    $last_day = strtotime(date('Y-m-t', $first_day));
    
    $weeks = [];
    $current = $first_day;
    
    // Find the first Tuesday of the month
    $first_tuesday = $first_day;
    $day_of_week = date('w', $first_tuesday); // 0=Sunday, 2=Tuesday
    
    if ($day_of_week != 2) {
        // If not Tuesday, find the next Tuesday
        $days_until_tuesday = ($day_of_week <= 2) ? (2 - $day_of_week) : (9 - $day_of_week);
        $first_tuesday = strtotime("+$days_until_tuesday days", $first_tuesday);
    }
    
    $current = $first_tuesday;
    while ($current <= $last_day) {
        $week_start = $current;
        $week_end = strtotime("+6 days", $week_start);
        
        if ($week_end > $last_day) {
            $week_end = $last_day;
        }
        
        $weeks[] = [
            'start' => date('Y-m-d', $week_start),
            'end' => date('Y-m-d', $week_end),
            'label' => date('d', $week_start) . ' - ' . date('d M Y', $week_end)
        ];
        
        $current = strtotime("+1 day", $week_end);
    }
    
    return $weeks;
}

$weeks = generateWeeksFromTuesday($filter_year, $filter_month);

// Query data untuk semua minggu dan semua poli
$data_by_poli = [];
$totals_by_penjamin = ['A09' => 0, 'BPJ' => 0, 'A92' => 0];
$totals_by_week = [];

foreach ($mapping_poli as $poli_name => $poli_codes) {
    $data_by_poli[$poli_name] = [];
    
    foreach ($weeks as $week_idx => $week) {
        if (!isset($totals_by_week[$week_idx])) {
            $totals_by_week[$week_idx] = ['A09' => 0, 'BPJ' => 0, 'A92' => 0];
        }
        
        $poli_codes_str = "'" . implode("','", array_map(function($v) use ($conn) {
            return $conn->real_escape_string($v);
        }, $poli_codes)) . "'";
        
        $data_by_poli[$poli_name][$week_idx] = [];
        
        foreach ($penjamin as $kd_pj => $label) {
            $sql = "SELECT COUNT(*) as jml FROM reg_periksa rp
                    WHERE rp.kd_poli IN ($poli_codes_str)
                    AND rp.kd_pj = '$kd_pj'
                    AND rp.status_lanjut = 'Ralan'
                    AND rp.stts <> 'Batal'
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
                        <label for="bulan">Bulan:&nbsp;</label>
                        <select name="bulan" id="bulan" class="form-control form-control-sm ml-1">
                            <?php
                            $months = [1=>"Januari",2=>"Februari",3=>"Maret",4=>"April",5=>"Mei",6=>"Juni",7=>"Juli",8=>"Agustus",9=>"September",10=>"Oktober",11=>"November",12=>"Desember"];
                            foreach($months as $num=>$name){
                                $sel = ($filter_month===$num)?'selected':'';
                                echo "<option value=\"$num\" $sel>$name</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group mr-2">
                        <label for="tahun">Tahun:&nbsp;</label>
                        <select name="tahun" id="tahun" class="form-control form-control-sm ml-1">
                            <?php
                            $startYear = 2020;
                            $endYear = date('Y');
                            for($y=$startYear;$y<=$endYear;$y++){
                                $sel = ($filter_year===$y)?'selected':'';
                                echo "<option value=\"$y\" $sel>$y</option>";
                            }
                            ?>
                        </select>
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
                                <th colspan="4" style="text-align: center; background-color: #FFA500; color: white;">
                                    <?php echo $week['label']; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                        <tr style="background-color: #FFA500; color: white;">
                            <th style="text-align: center;"></th>
                            <?php foreach ($weeks as $week): ?>
                                <th style="text-align: center;">UMUM</th>
                                <th style="text-align: center;">BPJS</th>
                                <th style="text-align: center;">ASURANSI</th>
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
                                $umum = isset($data_by_poli[$poli_name][$week_idx]['A09']) ? $data_by_poli[$poli_name][$week_idx]['A09'] : 0;
                                $bpjs = isset($data_by_poli[$poli_name][$week_idx]['BPJ']) ? $data_by_poli[$poli_name][$week_idx]['BPJ'] : 0;
                                $asuransi = isset($data_by_poli[$poli_name][$week_idx]['A92']) ? $data_by_poli[$poli_name][$week_idx]['A92'] : 0;
                                $total = $umum + $bpjs + $asuransi;
                                
                                echo '<td style="text-align: center;">' . $umum . '</td>';
                                echo '<td style="text-align: center;">' . $bpjs . '</td>';
                                echo '<td style="text-align: center;">' . $asuransi . '</td>';
                                echo '<td style="text-align: center; background-color: #FFEB99; font-weight: bold;">' . $total . '</td>';
                            }
                            
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>

                <!-- Summary rows -->
                <table class="table table-sm table-bordered" style="width:100%; margin-top: 5px; font-size: 12px; margin-bottom: 15px;">
                    <tbody>
                        <tr style="background-color: #FF6B6B; color: white; font-weight: bold;">
                            <td colspan="<?php echo count($weeks) * 4 + 1; ?>" style="text-align: left;">JUMLAH PER JENIS BAYAR</td>
                        </tr>
                        <tr style="background-color: #FF6B6B; color: white; font-weight: bold;">
                            <td style="text-align: center;">UMUM</td>
                            <?php foreach ($weeks as $week_idx => $week): ?>
                                <td style="text-align: center;"><?php echo isset($totals_by_week[$week_idx]['A09']) ? $totals_by_week[$week_idx]['A09'] : 0; ?></td>
                                <td style="text-align: center;">BPJS</td>
                                <td style="text-align: center;"><?php echo isset($totals_by_week[$week_idx]['BPJ']) ? $totals_by_week[$week_idx]['BPJ'] : 0; ?></td>
                                <td style="text-align: center;">ASURANSI</td>
                            <?php endforeach; ?>
                        </tr>
                        <tr style="background-color: #FF6B6B; color: white; font-weight: bold;">
                            <td style="text-align: center;">JUMLAH PX PER MINGGU</td>
                            <?php foreach ($weeks as $week_idx => $week): ?>
                                <td colspan="4" style="text-align: center;">
                                    <?php 
                                    $total_minggu = (isset($totals_by_week[$week_idx]['A09']) ? $totals_by_week[$week_idx]['A09'] : 0) +
                                                   (isset($totals_by_week[$week_idx]['BPJ']) ? $totals_by_week[$week_idx]['BPJ'] : 0) +
                                                   (isset($totals_by_week[$week_idx]['A92']) ? $totals_by_week[$week_idx]['A92'] : 0);
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
    $('#filterForm').on('change', 'select', function(){
        $('#filterForm').submit();
    });

    // Export to Excel
    $('#btnExport').on('click', function(){
        var formData = new FormData();
        formData.append('bulan', $('#bulan').val());
        formData.append('tahun', $('#tahun').val());
        formData.append('export', '1');

        $.ajax({
            type: 'POST',
            url: 'main_app.php?page=export_kunjungan_per_minggu',
            data: formData,
            processData: false,
            contentType: false,
            xhrFields: {
                responseType: 'blob'
            },
            success: function(data, status, xhr){
                var filename = 'Data_Kunjungan_Per_Minggu_' + new Date().toISOString().split('T')[0] + '.xlsx';
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

