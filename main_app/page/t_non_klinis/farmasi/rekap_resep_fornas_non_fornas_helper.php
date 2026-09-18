<?php

function aptd_fornas_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function aptd_fornas_is_date($value)
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function aptd_fornas_period_from_request(array $source = null)
{
    $source = $source === null ? $_POST : $source;
    $defaultEnd = date('Y-m-d');
    $defaultStart = date('Y-m-01', strtotime($defaultEnd));
    $startDate = isset($source['tanggal_awal']) ? trim((string) $source['tanggal_awal']) : $defaultStart;
    $endDate = isset($source['tanggal_akhir']) ? trim((string) $source['tanggal_akhir']) : $defaultEnd;
    $message = '';

    if (!aptd_fornas_is_date($startDate) || !aptd_fornas_is_date($endDate)) {
        $startDate = $defaultStart;
        $endDate = $defaultEnd;
        $message = 'Format tanggal tidak valid.';
    } elseif ($endDate < $startDate) {
        $message = 'Tanggal Akhir tidak boleh lebih kecil dari Tanggal Awal.';
    }

    return [
        'tanggal_awal' => $startDate,
        'tanggal_akhir' => $endDate,
        'valid' => $message === '',
        'message' => $message,
    ];
}

function aptd_fornas_dimensions()
{
    return [
        'rawat' => ['Ralan', 'Ranap'],
        'racikan' => ['Racikan', 'Non-Racikan'],
        'bayar' => ['BPJS', 'Umum', 'Asuransi'],
        'formularium' => ['Fornas', 'Non-Fornas', 'Non For RSPI'],
    ];
}

function aptd_fornas_empty_report()
{
    $dimensions = aptd_fornas_dimensions();
    $matrix = [];
    $rawatTotals = [];
    $formulariumTotals = array_fill_keys($dimensions['formularium'], 0);

    foreach ($dimensions['rawat'] as $rawat) {
        $rawatTotals[$rawat] = array_fill_keys($dimensions['formularium'], 0);
        foreach ($dimensions['racikan'] as $racikan) {
            foreach ($dimensions['bayar'] as $bayar) {
                $matrix[$rawat][$racikan][$bayar] = array_fill_keys($dimensions['formularium'], 0);
            }
        }
    }

    return [
        'matrix' => $matrix,
        'rawat_totals' => $rawatTotals,
        'formularium_totals' => $formulariumTotals,
        'total_item' => 0,
        'query_seconds' => 0.0,
    ];
}

function aptd_fornas_fetch_report(mysqli $mysqli, $startDate, $endDate)
{
    if (!aptd_fornas_is_date($startDate) || !aptd_fornas_is_date($endDate) || $endDate < $startDate) {
        throw new InvalidArgumentException('Rentang tanggal laporan tidak valid.');
    }

    $endExclusive = (new DateTimeImmutable($endDate))->modify('+1 day')->format('Y-m-d');
    $startedAt = microtime(true);

    // AR-178: setiap baris fakta adalah satu kemunculan item obat; nilai jml/qty tidak digunakan.
    $sql = <<<'SQL'
SELECT
    items.jenis_rawat,
    items.jenis_bayar,
    items.jenis_racikan,
    items.formularium,
    COUNT(*) AS total
FROM (
    SELECT
        rp.status_lanjut AS jenis_rawat,
        CASE
            WHEN pj.kd_pj = 'BPJ' THEN 'BPJS'
            WHEN pj.kd_pj = 'A09' THEN 'Umum'
            ELSE 'Asuransi'
        END AS jenis_bayar,
        'Non-Racikan' AS jenis_racikan,
        CASE db.kode_kategori
            WHEN 'K90' THEN 'Fornas'
            WHEN 'K91' THEN 'Non-Fornas'
            WHEN 'K79' THEN 'Non For RSPI'
        END AS formularium
    FROM resep_obat ro
    INNER JOIN reg_periksa rp ON rp.no_rawat = ro.no_rawat
    INNER JOIN penjab pj ON pj.kd_pj = rp.kd_pj
    INNER JOIN resep_dokter rd ON rd.no_resep = ro.no_resep
    INNER JOIN databarang db ON db.kode_brng = rd.kode_brng
    WHERE ro.tgl_peresepan >= ?
      AND ro.tgl_peresepan < ?
      AND rp.status_lanjut IN ('Ralan', 'Ranap')
      AND db.kode_kategori IN ('K90', 'K91', 'K79')

    UNION ALL

    SELECT
        rp.status_lanjut AS jenis_rawat,
        CASE
            WHEN pj.kd_pj = 'BPJ' THEN 'BPJS'
            WHEN pj.kd_pj = 'A09' THEN 'Umum'
            ELSE 'Asuransi'
        END AS jenis_bayar,
        'Racikan' AS jenis_racikan,
        CASE db.kode_kategori
            WHEN 'K90' THEN 'Fornas'
            WHEN 'K91' THEN 'Non-Fornas'
            WHEN 'K79' THEN 'Non For RSPI'
        END AS formularium
    FROM resep_obat ro
    INNER JOIN reg_periksa rp ON rp.no_rawat = ro.no_rawat
    INNER JOIN penjab pj ON pj.kd_pj = rp.kd_pj
    INNER JOIN resep_dokter_racikan_detail rrdd ON rrdd.no_resep = ro.no_resep
    INNER JOIN databarang db ON db.kode_brng = rrdd.kode_brng
    WHERE ro.tgl_peresepan >= ?
      AND ro.tgl_peresepan < ?
      AND rp.status_lanjut IN ('Ralan', 'Ranap')
      AND db.kode_kategori IN ('K90', 'K91', 'K79')
) items
GROUP BY items.jenis_rawat, items.jenis_bayar, items.jenis_racikan, items.formularium
ORDER BY items.jenis_rawat, items.jenis_racikan, items.jenis_bayar, items.formularium
SQL;

    $statement = $mysqli->prepare($sql);
    $statement->bind_param('ssss', $startDate, $endExclusive, $startDate, $endExclusive);
    $statement->execute();
    $result = $statement->get_result();
    $report = aptd_fornas_empty_report();
    $dimensions = aptd_fornas_dimensions();
    while ($row = $result->fetch_assoc()) {
        $rawat = (string) $row['jenis_rawat'];
        $racikan = (string) $row['jenis_racikan'];
        $bayar = (string) $row['jenis_bayar'];
        $formularium = (string) $row['formularium'];
        $total = (int) $row['total'];

        if (!isset($report['matrix'][$rawat][$racikan][$bayar][$formularium])) {
            continue;
        }

        $report['matrix'][$rawat][$racikan][$bayar][$formularium] += $total;
        $report['rawat_totals'][$rawat][$formularium] += $total;
        $report['formularium_totals'][$formularium] += $total;
        $report['total_item'] += $total;
    }

    $statement->close();
    $report['query_seconds'] = microtime(true) - $startedAt;
    return $report;
}
