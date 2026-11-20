<?php

function aptd_ranap_operasi_filters(array $request)
{
    $filters = [];
    $defaults = ['start_date' => date('Y-m-01'), 'end_date' => date('Y-m-t'), 'date_type' => 'masuk', 'kd_bangsal' => ''];
    foreach ($defaults as $key => $default) {
        if (isset($request[$key]) && !is_string($request[$key])) {
            throw new InvalidArgumentException('Parameter filter tidak valid.');
        }
        if (isset($request[$key]) && preg_match('/[\x00-\x1F\x7F]/', $request[$key])) {
            throw new InvalidArgumentException('Parameter filter tidak valid.');
        }
        $filters[$key] = isset($request[$key]) ? trim($request[$key]) : $default;
    }
    foreach (['start_date', 'end_date'] as $key) {
        if (!preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', $filters[$key])) {
            throw new InvalidArgumentException('Tanggal harus berformat YYYY-MM-DD dan merupakan tanggal yang valid.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$key]);
        if (!$date || $date->format('Y-m-d') !== $filters[$key]) {
            throw new InvalidArgumentException('Tanggal harus berformat YYYY-MM-DD dan merupakan tanggal yang valid.');
        }
    }
    if ($filters['start_date'] > $filters['end_date']) {
        throw new InvalidArgumentException('Tanggal awal tidak boleh melewati tanggal akhir.');
    }
    if (!in_array($filters['date_type'], ['masuk', 'keluar'], true)) {
        throw new InvalidArgumentException('Basis tanggal harus tanggal masuk atau tanggal keluar.');
    }
    if (strlen($filters['kd_bangsal']) > 5 || preg_match('/[\x00-\x1F\x7F]/', $filters['kd_bangsal'])) {
        throw new InvalidArgumentException('Kode bangsal tidak valid.');
    }
    return $filters;
}

function aptd_ranap_operasi_select($db, $sql, array $params = [])
{
    $statement = $db->prepare($sql);
    try {
        if ($params) {
            $types = str_repeat('s', count($params));
            $statement->bind_param($types, ...$params);
        }
        $statement->execute();
        $result = $statement->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    } finally {
        $statement->close();
    }
}

function aptd_ranap_operasi_bangsal($db)
{
    return aptd_ranap_operasi_select($db, "SELECT kd_bangsal, nm_bangsal FROM bangsal WHERE status = ? ORDER BY nm_bangsal, kd_bangsal", ['1']);
}

function aptd_ranap_operasi_query(array $filters)
{
    // The final room is chosen BEFORE applying the period/ward filters.
    // PK (no_rawat, tgl_masuk, jam_masuk) provides a deterministic tie-break.
    $dateColumn = $filters['date_type'] === 'keluar' ? 'ki.tgl_keluar' : 'rp.tgl_registrasi';
    $from = $filters['date_type'] === 'keluar'
        ? 'FROM kamar_inap ki INNER JOIN reg_periksa rp ON rp.no_rawat = ki.no_rawat'
        : 'FROM reg_periksa rp INNER JOIN kamar_inap ki ON ki.no_rawat = rp.no_rawat';
    $sql = "SELECT STRAIGHT_JOIN rp.no_rawat, rp.no_rkm_medis, p.nm_pasien,
        b.kd_bangsal, b.nm_bangsal, ki.kd_kamar AS nama_ruangan,
        rp.tgl_registrasi, ki.tgl_keluar,
        DATEDIFF(ki.tgl_keluar, rp.tgl_registrasi) AS lama_rawat,
        CASE WHEN rp.kd_pj = 'BPJ' THEN 'BPJS' WHEN rp.kd_pj = 'A09' THEN 'Umum' ELSE 'Asuransi' END AS kategori_bayar,
        (SELECT GROUP_CONCAT(DISTINCT TRIM(COALESCE(NULLIF(po.nm_perawatan, ''), o.kode_paket))
             ORDER BY TRIM(COALESCE(NULLIF(po.nm_perawatan, ''), o.kode_paket)) SEPARATOR ', ')
         FROM operasi o LEFT JOIN paket_operasi po ON po.kode_paket = o.kode_paket
         WHERE o.no_rawat = rp.no_rawat) AS nama_tindakan
        $from
        INNER JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
        INNER JOIN kamar k ON k.kd_kamar = ki.kd_kamar
        INNER JOIN bangsal b ON b.kd_bangsal = k.kd_bangsal
        WHERE ki.stts_pulang NOT IN ('-', 'Pindah Kamar') AND ki.tgl_keluar > '0000-00-00'
        AND $dateColumn BETWEEN ? AND ?
        AND EXISTS (SELECT 1 FROM operasi o WHERE o.no_rawat = rp.no_rawat)
        AND NOT EXISTS (
            SELECT 1 FROM kamar_inap newer WHERE newer.no_rawat = ki.no_rawat
            AND newer.stts_pulang NOT IN ('-', 'Pindah Kamar') AND newer.tgl_keluar > '0000-00-00'
            AND (newer.tgl_keluar > ki.tgl_keluar
                OR (newer.tgl_keluar = ki.tgl_keluar AND COALESCE(newer.jam_keluar, '00:00:00') > COALESCE(ki.jam_keluar, '00:00:00'))
                OR (newer.tgl_keluar = ki.tgl_keluar AND COALESCE(newer.jam_keluar, '00:00:00') = COALESCE(ki.jam_keluar, '00:00:00')
                    AND (newer.tgl_masuk > ki.tgl_masuk OR (newer.tgl_masuk = ki.tgl_masuk AND newer.jam_masuk > ki.jam_masuk))))
        )";
    $params = [$filters['start_date'], $filters['end_date']];
    if ($filters['kd_bangsal'] !== '') {
        $sql .= ' AND b.kd_bangsal = ?';
        $params[] = $filters['kd_bangsal'];
    }
    return [$sql . ' ORDER BY b.nm_bangsal, b.kd_bangsal, p.nm_pasien, rp.no_rawat', $params];
}

function aptd_ranap_operasi_aggregate(array $rows)
{
    $summary = [];
    $totals = ['BPJS' => 0, 'Umum' => 0, 'Asuransi' => 0, 'Total' => 0];
    $seen = [];
    foreach ($rows as $row) {
        if (isset($seen[$row['no_rawat']])) {
            throw new RuntimeException('Duplikasi episode perawatan pada laporan operasi.');
        }
        $seen[$row['no_rawat']] = true;
        $key = $row['kd_bangsal'];
        if (!isset($summary[$key])) {
            $summary[$key] = ['kd_bangsal' => $key, 'nm_bangsal' => $row['nm_bangsal'], 'BPJS' => 0, 'Umum' => 0, 'Asuransi' => 0, 'Total' => 0];
        }
        $category = $row['kategori_bayar'];
        $summary[$key][$category]++;
        $summary[$key]['Total']++;
        $totals[$category]++;
        $totals['Total']++;
    }
    return ['summary' => array_values($summary), 'totals' => $totals];
}

function aptd_ranap_operasi_report($db, array $request)
{
    $filters = aptd_ranap_operasi_filters($request);
    list($sql, $params) = aptd_ranap_operasi_query($filters);
    $started = microtime(true);
    $rows = aptd_ranap_operasi_select($db, $sql, $params);
    // MariaDB's default GROUP_CONCAT limit is 1024 bytes. Recover complete names
    // on warning 1260 rather than changing session/global configuration.
    $truncated = false;
    if ($db->warning_count) {
        $warning = $db->get_warnings();
        if ($warning) {
            do { $truncated = $truncated || $warning->errno === 1260; } while ($warning->next());
        }
    }
    if ($truncated) {
        foreach ($rows as &$row) {
            $names = aptd_ranap_operasi_select($db,
                "SELECT DISTINCT TRIM(COALESCE(NULLIF(po.nm_perawatan, ''), o.kode_paket)) AS nama
                 FROM operasi o LEFT JOIN paket_operasi po ON po.kode_paket = o.kode_paket
                 WHERE o.no_rawat = ? ORDER BY nama", [$row['no_rawat']]);
            $row['nama_tindakan'] = implode(', ', array_column($names, 'nama'));
        }
        unset($row);
    }
    foreach ($rows as &$row) { $row['lama_rawat'] = (int) $row['lama_rawat']; }
    unset($row);
    return array_merge(['filters' => $filters, 'rows' => $rows, 'query_seconds' => microtime(true) - $started], aptd_ranap_operasi_aggregate($rows));
}

function aptd_ranap_operasi_subtitle(array $filters, array $wards)
{
    $ward = $filters['kd_bangsal'] === '' ? 'Semua Bangsal' : $filters['kd_bangsal'];
    foreach ($wards as $item) {
        if ($item['kd_bangsal'] === $filters['kd_bangsal']) { $ward = $item['nm_bangsal']; break; }
    }
    return 'Periode ' . $filters['start_date'] . ' s.d. ' . $filters['end_date']
        . ' | Tanggal ' . ($filters['date_type'] === 'keluar' ? 'Keluar' : 'Masuk') . ' | ' . $ward;
}

function aptd_ranap_operasi_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
