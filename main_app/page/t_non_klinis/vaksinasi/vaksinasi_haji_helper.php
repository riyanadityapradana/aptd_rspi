<?php

function aptd_vh_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function aptd_vh_is_date($value)
{
    if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value)) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function aptd_vh_filters(array $source = null)
{
    $source = $source === null ? $_GET : $source;
    $defaults = ['tanggal_awal' => date('Y-m-01'), 'tanggal_akhir' => date('Y-m-d'), 'jenis_bayar' => ''];
    $filters = [];
    foreach ($defaults as $key => $default) {
        $filters[$key] = array_key_exists($key, $source)
            ? (is_string($source[$key]) ? trim($source[$key]) : '') : $default;
    }
    $message = '';
    if (!aptd_vh_is_date($filters['tanggal_awal']) || !aptd_vh_is_date($filters['tanggal_akhir'])) {
        $message = 'Tanggal awal dan tanggal akhir harus berupa tanggal yang valid.';
    } elseif ($filters['tanggal_akhir'] < $filters['tanggal_awal']) {
        $message = 'Tanggal akhir tidak boleh sebelum tanggal awal.';
    } elseif (!in_array($filters['jenis_bayar'], ['', 'A09', 'A96'], true)
        || (isset($source['jenis_bayar']) && !is_string($source['jenis_bayar']))) {
        $message = 'Jenis bayar tidak valid.';
    }
    $filters['valid'] = $message === '';
    $filters['message'] = $message;
    return $filters;
}

function aptd_vh_payment_options(mysqli $connection)
{
    $options = ['A09' => 'A09', 'A96' => 'A96'];
    $result = $connection->query("SELECT kd_pj, png_jawab FROM penjab WHERE kd_pj IN ('A09', 'A96') ORDER BY kd_pj");
    while ($row = $result->fetch_assoc()) {
        $options[$row['kd_pj']] = $row['png_jawab'];
    }
    $result->free();
    return $options;
}

function aptd_vh_empty_report()
{
    return ['rows' => [], 'total_kunjungan' => 0, 'total_pasien' => 0, 'per_bayar' => ['A09' => 0, 'A96' => 0]];
}

function aptd_vh_fetch_report(mysqli $connection, array $filters)
{
    $validated = aptd_vh_filters($filters);
    if (!$validated['valid']) {
        throw new InvalidArgumentException($validated['message']);
    }
    // tgl_registrasi bertipe DATE: kedua batas tanggal termasuk dalam laporan.
    $sql = <<<'SQL'
SELECT rp.no_rawat, rp.tgl_registrasi, rp.jam_reg, rp.no_rkm_medis,
       p.nm_pasien, rp.kd_poli, pl.nm_poli, rp.kd_pj,
       pj.png_jawab AS jenis_bayar, rp.status_bayar
FROM reg_periksa rp
INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
INNER JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli
INNER JOIN penjab pj ON rp.kd_pj = pj.kd_pj
WHERE rp.kd_poli = 'U0053'
  AND rp.status_bayar = 'Sudah Bayar'
  AND rp.kd_pj IN ('A96', 'A09')
  AND rp.tgl_registrasi >= ? AND rp.tgl_registrasi <= ?
SQL;
    if ($validated['jenis_bayar'] !== '') {
        $sql .= ' AND rp.kd_pj = ?';
    }
    $sql .= ' ORDER BY rp.tgl_registrasi DESC, rp.jam_reg DESC, rp.no_rawat DESC';
    $statement = $connection->prepare($sql);
    if ($validated['jenis_bayar'] === '') {
        $statement->bind_param('ss', $validated['tanggal_awal'], $validated['tanggal_akhir']);
    } else {
        $statement->bind_param('sss', $validated['tanggal_awal'], $validated['tanggal_akhir'], $validated['jenis_bayar']);
    }
    try {
        $statement->execute();
        $result = $statement->get_result();
        $report = aptd_vh_empty_report();
        $medicalRecords = [];
        while ($row = $result->fetch_assoc()) {
            $report['rows'][] = $row;
            // Kolasi SIMRS menerima A09/a09 sebagai penjamin yang sama.
            $paymentCode = strtoupper(trim($row['kd_pj']));
            $report['per_bayar'][$paymentCode]++;
            $medicalRecords[$row['no_rkm_medis']] = true;
        }
        $result->free();
        $report['total_kunjungan'] = count($report['rows']);
        $report['total_pasien'] = count($medicalRecords);
        return $report;
    } finally {
        $statement->close();
    }
}

function aptd_vh_hospital(mysqli $connection)
{
    $result = $connection->query('SELECT nama_instansi, alamat_instansi, kabupaten, propinsi, kontak, email FROM setting LIMIT 1');
    $hospital = $result->fetch_assoc();
    $result->free();
    if (!$hospital || trim($hospital['nama_instansi']) === '') {
        throw new RuntimeException('Identitas rumah sakit pada tabel setting belum tersedia.');
    }
    return $hospital;
}
