<?php

function aptd_adime_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function aptd_adime_status_options()
{
    return [
        'all' => 'Semua Status',
        'SUDAH ADIME' => 'SUDAH ADIME',
        'BELUM ADIME' => 'BELUM ADIME',
    ];
}

function aptd_adime_filter_from_request()
{
    $startDate = isset($_POST['tgl_awal']) && $_POST['tgl_awal'] !== '' ? trim($_POST['tgl_awal']) : date('Y-m-01');
    $endDate = isset($_POST['tgl_akhir']) && $_POST['tgl_akhir'] !== '' ? trim($_POST['tgl_akhir']) : date('Y-m-d');
    $status = isset($_POST['status_adime']) ? trim($_POST['status_adime']) : 'all';

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || strtotime($startDate) === false) {
        $startDate = date('Y-m-01');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || strtotime($endDate) === false) {
        $endDate = date('Y-m-d');
    }

    if ($startDate > $endDate) {
        $temp = $startDate;
        $startDate = $endDate;
        $endDate = $temp;
    }

    if (!array_key_exists($status, aptd_adime_status_options())) {
        $status = 'all';
    }

    return [$startDate, $endDate, $status];
}

function aptd_adime_fetch($conn, $startDate, $endDate, $status = 'all')
{
    $sql = "
        SELECT
            ki.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            ki.kd_kamar,
            ki.tgl_masuk,
            ki.jam_masuk,
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM catatan_adime_gizi ag
                    WHERE ag.no_rawat = ki.no_rawat
                ) THEN 'SUDAH ADIME'
                ELSE 'BELUM ADIME'
            END AS status_adime,
            ki.stts_pulang
        FROM kamar_inap ki
        INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        WHERE ki.stts_pulang <> '-'
            AND ki.stts_pulang <> 'Pindah Kamar'
            AND ki.tgl_masuk BETWEEN ? AND ?
    ";

    $types = 'ss';
    $params = [$startDate, $endDate];

    if ($status === 'SUDAH ADIME') {
        $sql .= "
            AND EXISTS (
                SELECT 1
                FROM catatan_adime_gizi ag
                WHERE ag.no_rawat = ki.no_rawat
            )
        ";
    } elseif ($status === 'BELUM ADIME') {
        $sql .= "
            AND NOT EXISTS (
                SELECT 1
                FROM catatan_adime_gizi ag
                WHERE ag.no_rawat = ki.no_rawat
            )
        ";
    }

    $sql .= "
        GROUP BY ki.no_rawat
        ORDER BY ki.tgl_masuk DESC, ki.jam_masuk DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die('Query prepare gagal: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    $summary = [
        'total' => 0,
        'SUDAH ADIME' => 0,
        'BELUM ADIME' => 0,
    ];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
        $summary['total']++;
        if (isset($summary[$row['status_adime']])) {
            $summary[$row['status_adime']]++;
        }
    }

    $stmt->close();

    return [
        'rows' => $rows,
        'summary' => $summary,
    ];
}

?>
