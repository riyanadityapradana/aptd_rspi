<?php

function aptd_poli_specialty_excluded_codes()
{
    return ['IGDK', 'U0014', 'U0056', 'U0062', 'U0078'];
}

function aptd_poli_specialty_exclusion_sql($mysqli, $column = 'rp.kd_poli')
{
    $codes = aptd_poli_specialty_excluded_codes();
    $escaped = array_map(function ($code) use ($mysqli) {
        return "'" . $mysqli->real_escape_string($code) . "'";
    }, $codes);

    return $column . ' NOT IN (' . implode(',', $escaped) . ')';
}

function aptd_poli_specialty_mapping($mysqli)
{
    $exclusionSql = aptd_poli_specialty_exclusion_sql($mysqli, 'p.kd_poli');
    $sql = "SELECT DISTINCT
                s.nm_sps,
                p.kd_poli
            FROM poliklinik p
            INNER JOIN jadwal j ON j.kd_poli = p.kd_poli
            INNER JOIN dokter d ON d.kd_dokter = j.kd_dokter
            INNER JOIN spesialis s ON s.kd_sps = d.kd_sps
            WHERE p.status = '1'
              AND $exclusionSql
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

    // Poli ralan valid yang tidak selalu memiliki jadwal tetap diarahkan ke spesialis master.
    $manualSpecialtyFallback = [
        'S0016' => [
            'default_name' => 'Umum',
            'codes' => ['U0009', 'U0071'],
        ],
        'S0001' => [
            'default_name' => 'Obgyn',
            'codes' => ['U0074'],
        ],
    ];

    $specialtyNames = [];
    $specialtyCodes = array_keys($manualSpecialtyFallback);
    if (!empty($specialtyCodes)) {
        $escapedSpecialties = array_map(function ($code) use ($mysqli) {
            return "'" . $mysqli->real_escape_string($code) . "'";
        }, $specialtyCodes);
        $specialtyResult = $mysqli->query(
            "SELECT kd_sps, nm_sps
             FROM spesialis
             WHERE kd_sps IN (" . implode(',', $escapedSpecialties) . ")"
        );
        if ($specialtyResult) {
            while ($row = $specialtyResult->fetch_assoc()) {
                $name = trim((string) $row['nm_sps']);
                if ($name !== '') {
                    $specialtyNames[$row['kd_sps']] = $name;
                }
            }
        }
    }

    $fallbackCodes = [];
    foreach ($manualSpecialtyFallback as $fallback) {
        foreach ($fallback['codes'] as $code) {
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
               AND " . aptd_poli_specialty_exclusion_sql($mysqli, 'kd_poli') . "
               AND kd_poli IN (" . implode(',', $escapedCodes) . ")"
        );
        $activeFallbackCodes = [];
        while ($row = $fallbackResult->fetch_assoc()) {
            $activeFallbackCodes[$row['kd_poli']] = true;
        }

        foreach ($groups as $groupName => $codes) {
            foreach ($fallbackCodes as $code) {
                unset($groups[$groupName][$code]);
            }
        }

        foreach ($manualSpecialtyFallback as $kdSps => $fallback) {
            $groupName = isset($specialtyNames[$kdSps]) ? $specialtyNames[$kdSps] : $fallback['default_name'];
            foreach ($fallback['codes'] as $code) {
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
        if (empty($codes)) {
            unset($groups[$groupName]);
            continue;
        }
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

    $aliases = [
        'KANDUNGAN' => ['Obgyn', 'Obstetri dan Ginekologi', 'Obstetri & Ginekologi'],
    ];
    $aliasKey = strtoupper($requested);
    if (isset($aliases[$aliasKey])) {
        foreach ($aliases[$aliasKey] as $aliasTarget) {
            foreach (array_keys($groups) as $groupName) {
                if (strcasecmp($aliasTarget, $groupName) === 0) {
                    return $groupName;
                }
            }
        }
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
