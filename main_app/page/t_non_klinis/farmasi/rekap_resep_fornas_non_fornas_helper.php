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
        'total_resep' => 0,
        'total_terklasifikasi' => 0,
        'belum_terklasifikasi' => 0,
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

    // Setiap baris pada derived table merepresentasikan tepat satu no_resep.
    $sql = <<<'SQL'
SELECT
    facts.jenis_rawat,
    facts.jenis_bayar,
    facts.jenis_racikan,
    facts.formularium,
    COUNT(*) AS total
FROM (
    SELECT
        flags.no_resep,
        flags.jenis_rawat,
        flags.jenis_bayar,
        flags.jenis_racikan,
        CASE
            WHEN flags.has_k91 = 1 THEN 'Non-Fornas'
            WHEN flags.has_k90 = 1
                AND flags.has_k91 = 0
                AND flags.has_k79 = 0 THEN 'Fornas'
            WHEN flags.has_k79 = 1
                AND flags.has_k91 = 0 THEN 'Non For RSPI'
            ELSE 'Belum Terklasifikasi'
        END AS formularium
    FROM (
        SELECT
            ro.no_resep,
            rp.status_lanjut AS jenis_rawat,
            CASE
                WHEN rp.kd_pj = 'BPJ' THEN 'BPJS'
                WHEN rp.kd_pj = 'A09' THEN 'Umum'
                ELSE 'Asuransi'
            END AS jenis_bayar,
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM resep_dokter_racikan rr
                    WHERE rr.no_resep = ro.no_resep
                ) OR EXISTS (
                    SELECT 1
                    FROM resep_dokter_racikan_detail rrdd
                    WHERE rrdd.no_resep = ro.no_resep
                ) THEN 'Racikan'
                ELSE 'Non-Racikan'
            END AS jenis_racikan,
            (
                EXISTS (
                    SELECT 1
                    FROM resep_dokter rd
                    INNER JOIN databarang db ON db.kode_brng = rd.kode_brng
                    INNER JOIN kategori_barang kb ON kb.kode = db.kode_kategori
                    WHERE rd.no_resep = ro.no_resep AND kb.kode = 'K90'
                ) OR EXISTS (
                    SELECT 1
                    FROM resep_dokter_racikan_detail rrdd
                    INNER JOIN databarang db ON db.kode_brng = rrdd.kode_brng
                    INNER JOIN kategori_barang kb ON kb.kode = db.kode_kategori
                    WHERE rrdd.no_resep = ro.no_resep AND kb.kode = 'K90'
                )
            ) AS has_k90,
            (
                EXISTS (
                    SELECT 1
                    FROM resep_dokter rd
                    INNER JOIN databarang db ON db.kode_brng = rd.kode_brng
                    INNER JOIN kategori_barang kb ON kb.kode = db.kode_kategori
                    WHERE rd.no_resep = ro.no_resep AND kb.kode = 'K91'
                ) OR EXISTS (
                    SELECT 1
                    FROM resep_dokter_racikan_detail rrdd
                    INNER JOIN databarang db ON db.kode_brng = rrdd.kode_brng
                    INNER JOIN kategori_barang kb ON kb.kode = db.kode_kategori
                    WHERE rrdd.no_resep = ro.no_resep AND kb.kode = 'K91'
                )
            ) AS has_k91,
            (
                EXISTS (
                    SELECT 1
                    FROM resep_dokter rd
                    INNER JOIN databarang db ON db.kode_brng = rd.kode_brng
                    INNER JOIN kategori_barang kb ON kb.kode = db.kode_kategori
                    WHERE rd.no_resep = ro.no_resep AND kb.kode = 'K79'
                ) OR EXISTS (
                    SELECT 1
                    FROM resep_dokter_racikan_detail rrdd
                    INNER JOIN databarang db ON db.kode_brng = rrdd.kode_brng
                    INNER JOIN kategori_barang kb ON kb.kode = db.kode_kategori
                    WHERE rrdd.no_resep = ro.no_resep AND kb.kode = 'K79'
                )
            ) AS has_k79
        FROM resep_obat ro
        INNER JOIN reg_periksa rp ON rp.no_rawat = ro.no_rawat
        INNER JOIN penjab pj ON pj.kd_pj = rp.kd_pj
        WHERE ro.tgl_peresepan >= ?
          AND ro.tgl_peresepan < ?
          AND rp.status_lanjut IN ('Ralan', 'Ranap')
          AND (
              EXISTS (SELECT 1 FROM resep_dokter rd WHERE rd.no_resep = ro.no_resep)
              OR EXISTS (SELECT 1 FROM resep_dokter_racikan rr WHERE rr.no_resep = ro.no_resep)
              OR EXISTS (
                  SELECT 1
                  FROM resep_dokter_racikan_detail rrdd
                  WHERE rrdd.no_resep = ro.no_resep
              )
          )
    ) flags
) facts
GROUP BY facts.jenis_rawat, facts.jenis_bayar, facts.jenis_racikan, facts.formularium
ORDER BY facts.jenis_rawat, facts.jenis_racikan, facts.jenis_bayar, facts.formularium
SQL;

    $statement = $mysqli->prepare($sql);
    $statement->bind_param('ss', $startDate, $endExclusive);
    $statement->execute();
    $result = $statement->get_result();
    $report = aptd_fornas_empty_report();
    $dimensions = aptd_fornas_dimensions();
    $classified = array_flip($dimensions['formularium']);

    while ($row = $result->fetch_assoc()) {
        $rawat = (string) $row['jenis_rawat'];
        $racikan = (string) $row['jenis_racikan'];
        $bayar = (string) $row['jenis_bayar'];
        $formularium = (string) $row['formularium'];
        $total = (int) $row['total'];

        if (!isset($classified[$formularium])) {
            $report['belum_terklasifikasi'] += $total;
            continue;
        }

        if (!isset($report['matrix'][$rawat][$racikan][$bayar][$formularium])) {
            continue;
        }

        $report['matrix'][$rawat][$racikan][$bayar][$formularium] = $total;
        $report['rawat_totals'][$rawat][$formularium] += $total;
        $report['formularium_totals'][$formularium] += $total;
        $report['total_resep'] += $total;
        $report['total_terklasifikasi'] += $total;
    }

    $statement->close();
    $report['query_seconds'] = microtime(true) - $startedAt;
    return $report;
}
