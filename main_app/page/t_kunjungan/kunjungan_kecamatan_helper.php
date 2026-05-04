<?php
function aptd_kecamatan_month_labels()
{
    return [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
}

function aptd_kecamatan_payment_labels()
{
    return ['Umum', 'Asuransi', 'BPJS'];
}

function aptd_kecamatan_wilayah_config()
{
    return [
        'Banjarmasin' => [
            'kab_like' => '%masin%',
            'kecamatan_like' => [
                '%Banjarmasin Selatan%', '%Banjarmasin Barat%', '%Banjarmasin Tengah%', '%Banjarmasin Timur%', '%Banjarmasin Utara%',
            ],
        ],
        'Banjarbaru' => [
            'kab_like' => '%baru%',
            'kecamatan_like' => [
                '%LANDASAN%', '%CEMPAKA%', '%BANJARBARU UTARA%', '%BANJARBARU SELATAN%', '%LIANG ANGGANG%',
            ],
        ],
        'Kabupaten Banjar' => [
            'kab_like' => '%banjar%',
            'kecamatan_like' => [
                '%ALUH%', '%ARANIO%', '%Astambul%', '%Beruntung Baru%', '%Cintapuri Darussalam%', '%Karang Intan%', '%Kertak Hanyar%', '%Mataraman%', '%Martapura%', '%Martapura Barat%', '%Martapura Timur%', '%Paramasan%', '%Pengaron%', '%Sambung Makmur%', '%Simpang Empat%', '%Sungai Pinang%', '%Sungai Tabuk%', '%Tatah Makmur%', '%Telaga Bauntung%', '%GAMBUT%',
            ],
        ],
    ];
}

function aptd_kecamatan_wilayah_list()
{
    return array_keys(aptd_kecamatan_wilayah_config());
}

function aptd_kecamatan_selected_wilayah($value)
{
    $value = trim((string) $value);
    return in_array($value, aptd_kecamatan_wilayah_list(), true) ? $value : '';
}

function aptd_kecamatan_period_from_request()
{
    $month = isset($_POST['month']) ? (int) $_POST['month'] : (int) date('n');
    $year = isset($_POST['year']) ? (int) $_POST['year'] : (int) date('Y');
    if ($month < 1 || $month > 12) $month = (int) date('n');
    if ($year < 2020 || $year > ((int) date('Y') + 1)) $year = (int) date('Y');

    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = date('Y-m-d', strtotime($startDate . ' +1 month'));

    return [$month, $year, $startDate, $endDate];
}

function aptd_kecamatan_escape_like_list($conn, $items)
{
    $escaped = [];
    foreach ($items as $item) {
        $escaped[] = "kec.nm_kec LIKE '" . $conn->real_escape_string($item) . "'";
    }
    return implode(' OR ', $escaped);
}

function aptd_kecamatan_fetch($conn, $jenisRawat, $startDate, $endDate, $selectedWilayah = '')
{
    $kategoriList = aptd_kecamatan_payment_labels();
    $config = aptd_kecamatan_wilayah_config();
    $wilayahList = $selectedWilayah !== '' ? [$selectedWilayah] : array_keys($config);
    $data = [];
    $rawRows = [];
    $totalWilayah = [];
    $totalKategori = array_fill_keys($kategoriList, 0);

    foreach ($wilayahList as $wilayah) {
        $data[$wilayah] = [];
        $totalWilayah[$wilayah] = array_fill_keys($kategoriList, 0);

        $joins = "JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
JOIN penjab pj ON pj.kd_pj = rp.kd_pj
JOIN kabupaten kab ON kab.kd_kab = p.kd_kab
JOIN kecamatan kec ON kec.kd_kec = p.kd_kec";
        $extraWhere = '';
        $statusLanjut = 'Ralan';

        if ($jenisRawat === 'ranap') {
            $joins = "JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
JOIN penjab pj ON pj.kd_pj = rp.kd_pj
JOIN kamar_inap ki ON ki.no_rawat = rp.no_rawat
JOIN kamar k ON k.kd_kamar = ki.kd_kamar
JOIN bangsal b ON b.kd_bangsal = k.kd_bangsal
JOIN kabupaten kab ON kab.kd_kab = p.kd_kab
JOIN kecamatan kec ON kec.kd_kec = p.kd_kec";
            $extraWhere = "
    AND ki.stts_pulang NOT IN ('Pindah Kamar', '-')";
            $statusLanjut = 'Ranap';
        }

        $kabLike = $conn->real_escape_string($config[$wilayah]['kab_like']);
        $kecamatanWhere = aptd_kecamatan_escape_like_list($conn, $config[$wilayah]['kecamatan_like']);
        $sql = "SELECT
  kec.nm_kec,
  CASE rp.kd_pj
    WHEN 'A09' THEN 'Umum'
    WHEN 'A92' THEN 'Asuransi'
    WHEN 'BPJ' THEN 'BPJS'
  END AS kategori,
  COUNT(DISTINCT rp.no_rawat) AS total
FROM reg_periksa rp
$joins
WHERE rp.tgl_registrasi >= ?
    AND rp.tgl_registrasi < ?
    AND rp.status_lanjut = '$statusLanjut'
    AND rp.stts <> 'Batal'$extraWhere
    AND kab.nm_kab LIKE '$kabLike'
    AND ($kecamatanWhere)
    AND rp.kd_pj IN ('A09','A92','BPJ')
GROUP BY kec.nm_kec, kategori
ORDER BY kec.nm_kec, FIELD(kategori,'Umum','Asuransi','BPJS')";

        $stmt = $conn->prepare($sql);
        if (!$stmt) die('Query prepare gagal: ' . $conn->error);
        $stmt->bind_param('ss', $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            if (empty($row['kategori'])) continue;
            $kecamatan = $row['nm_kec'];
            $kategori = $row['kategori'];
            $total = (int) $row['total'];

            if (!isset($data[$wilayah][$kecamatan])) $data[$wilayah][$kecamatan] = array_fill_keys($kategoriList, 0);
            $data[$wilayah][$kecamatan][$kategori] = $total;
            $totalWilayah[$wilayah][$kategori] += $total;
            $totalKategori[$kategori] += $total;
            $rawRows[] = ['wilayah' => $wilayah, 'nm_kec' => $kecamatan, 'kategori' => $kategori, 'total' => $total];
        }

        $stmt->close();
    }

    return [
        'wilayah_list' => $wilayahList,
        'data' => $data,
        'raw_rows' => $rawRows,
        'total_wilayah' => $totalWilayah,
        'total_kategori' => $totalKategori,
        'grand_total' => array_sum($totalKategori),
    ];
}

function aptd_kecamatan_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
