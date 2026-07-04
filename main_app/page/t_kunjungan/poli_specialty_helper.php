<?php

function aptd_poli_specialty_mapping($mysqli)
{
    $sql = "SELECT DISTINCT
                s.nm_sps,
                p.kd_poli
            FROM poliklinik p
            INNER JOIN jadwal j ON j.kd_poli = p.kd_poli
            INNER JOIN dokter d ON d.kd_dokter = j.kd_dokter
            INNER JOIN spesialis s ON s.kd_sps = d.kd_sps
            WHERE p.status = '1'
              AND s.nm_sps IS NOT NULL
              AND TRIM(s.nm_sps) <> ''
            ORDER BY s.nm_sps ASC, p.kd_poli ASC";

    $result = null;
    for ($attempt = 0; $attempt < 2; $attempt++) {
        try {
            $result = $mysqli->query($sql);
            break;
        } catch (mysqli_sql_exception $exception) {
            if ((int) $exception->getCode() !== 1615 || $attempt > 0) {
                throw $exception;
            }
        }
    }

    $groups = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $groupName = trim((string) $row['nm_sps']);
            $poliCode = trim((string) $row['kd_poli']);
            if ($groupName === '' || $poliCode === '') {
                continue;
            }
            if (!isset($groups[$groupName])) {
                $groups[$groupName] = [];
            }
            $groups[$groupName][$poliCode] = $poliCode;
        }
    }

    // Entitas emergency tidak memiliki relasi jadwal/spesialis pada master SIMRS.
    $emergencyFallback = [
        'IGD' => ['IGDK', 'U0009'],
        'PONEK RALAN' => ['U0074'],
        'PONEK RANAP' => ['U0056'],
    ];
    $fallbackCodes = [];
    foreach ($emergencyFallback as $codes) {
        foreach ($codes as $code) {
            $fallbackCodes[$code] = $code;
        }
    }

    if (!empty($fallbackCodes)) {
        $escapedCodes = array_map(function ($code) use ($mysqli) {
            return "'" . $mysqli->real_escape_string($code) . "'";
        }, array_values($fallbackCodes));
        $fallbackResult = $mysqli->query(
            "SELECT kd_poli
             FROM poliklinik
             WHERE status = '1'
               AND kd_poli IN (" . implode(',', $escapedCodes) . ")"
        );
        $activeFallbackCodes = [];
        while ($row = $fallbackResult->fetch_assoc()) {
            $activeFallbackCodes[$row['kd_poli']] = true;
        }

        foreach ($emergencyFallback as $groupName => $codes) {
            foreach ($codes as $code) {
                if (!isset($activeFallbackCodes[$code])) {
                    continue;
                }
                if (!isset($groups[$groupName])) {
                    $groups[$groupName] = [];
                }
                $groups[$groupName][$code] = $code;
            }
        }
    }

    foreach ($groups as $groupName => $codes) {
        $groups[$groupName] = array_values($codes);
        sort($groups[$groupName], SORT_NATURAL | SORT_FLAG_CASE);
    }
    uksort($groups, 'strnatcasecmp');

    return $groups;
}

function aptd_poli_specialty_selected_group(array $groups, $requested, $preferred = 'Penyakit Dalam')
{
    $requested = trim((string) $requested);
    if ($requested !== '' && isset($groups[$requested])) {
        return $requested;
    }

    foreach (array_keys($groups) as $groupName) {
        if ($requested !== '' && strcasecmp($requested, $groupName) === 0) {
            return $groupName;
        }
    }

    foreach (array_keys($groups) as $groupName) {
        if (strcasecmp($preferred, $groupName) === 0) {
            return $groupName;
        }
    }

    $groupNames = array_keys($groups);
    return isset($groupNames[0]) ? $groupNames[0] : '';
}
