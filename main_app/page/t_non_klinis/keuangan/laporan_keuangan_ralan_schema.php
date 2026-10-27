<?php
// Definisi dan validasi skema bersama. File ini tidak membuka koneksi database.
class AptdKeuRalanSchemaException extends RuntimeException
{
}

function aptd_keu_ralan_cache_fields()
{
    $decimal = 'DECIMAL(16,2) NOT NULL DEFAULT 0';
    $integer = 'INT NOT NULL DEFAULT 0';
    $text = 'TEXT NULL';

    return [
        'jd_pemeriksaan' => ['column' => 'calc_jd_pemeriksaan', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'jd_prosedur' => ['column' => 'calc_jd_prosedur', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'jd_dokter_anestesi' => ['column' => 'calc_jd_dokter_anestesi', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'jd_dokter_anak' => ['column' => 'calc_jd_dokter_anak', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'jd_hd' => ['column' => 'calc_jd_hd', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'jd_usg' => ['column' => 'calc_jd_usg', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'jd_rontgen' => ['column' => 'calc_jd_rontgen', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'jd_lab' => ['column' => 'calc_jd_lab', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'jd_pa' => ['column' => 'calc_jd_pa', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'bhp_lab_pk' => ['column' => 'calc_bhp_lab_pk', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'bhp_lab_pa' => ['column' => 'calc_bhp_lab_pa', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'bhp_rad_usg' => ['column' => 'calc_bhp_rad_usg', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'bhp_rontgen' => ['column' => 'calc_bhp_rontgen', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'jasa_karyawan' => ['column' => 'calc_jasa_karyawan', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'biaya_bhp' => ['column' => 'calc_biaya_bhp', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'biaya_obat' => ['column' => 'calc_biaya_obat', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'biaya_ekg' => ['column' => 'calc_biaya_ekg', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'biaya_darah' => ['column' => 'calc_biaya_darah', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'makan_jumlah' => ['column' => 'calc_makan_jumlah', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'makan_harga' => ['column' => 'calc_makan_harga', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'makan_kali' => ['column' => 'calc_makan_kali', 'definition' => $integer, 'kind' => 'integer', 'default' => 0],
        'biaya_fototheraphy' => ['column' => 'calc_biaya_fototheraphy', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'biaya_oksigen' => ['column' => 'calc_biaya_oksigen', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'biaya_spirometri' => ['column' => 'calc_biaya_spirometri', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'total' => ['column' => 'calc_total', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'margin' => ['column' => 'calc_margin', 'definition' => $decimal, 'kind' => 'decimal', 'default' => 0],
        'keterangan_darah' => ['column' => 'calc_keterangan_darah', 'definition' => $integer, 'kind' => 'integer', 'default' => 0],
        'keterangan_albumin' => ['column' => 'calc_keterangan_albumin', 'definition' => $integer, 'kind' => 'integer', 'default' => 0],
        'keterangan_tindakan' => ['column' => 'calc_keterangan_tindakan', 'definition' => $text, 'kind' => 'text', 'default' => ''],
        'jd_rule' => ['column' => 'rule_jd', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'jd_anestesi_rule' => ['column' => 'rule_jd_anestesi', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'jd_anak_rule' => ['column' => 'rule_jd_anak', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'jd_usg_rule' => ['column' => 'rule_jd_usg', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'jd_rontgen_rule' => ['column' => 'rule_jd_rontgen', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'jd_lab_rule' => ['column' => 'rule_jd_lab', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'jd_pa_rule' => ['column' => 'rule_jd_pa', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'bhp_lab_pk_rule' => ['column' => 'rule_bhp_lab_pk', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'bhp_lab_pa_rule' => ['column' => 'rule_bhp_lab_pa', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'bhp_rad_usg_rule' => ['column' => 'rule_bhp_rad_usg', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'bhp_rontgen_rule' => ['column' => 'rule_bhp_rontgen', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'biaya_bhp_rule' => ['column' => 'rule_biaya_bhp', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'biaya_obat_rule' => ['column' => 'rule_biaya_obat', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'biaya_ekg_rule' => ['column' => 'rule_biaya_ekg', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'biaya_darah_rule' => ['column' => 'rule_biaya_darah', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'makan_rule' => ['column' => 'rule_makan', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'biaya_oksigen_rule' => ['column' => 'rule_biaya_oksigen', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'biaya_spirometri_rule' => ['column' => 'rule_biaya_spirometri', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'total_rule' => ['column' => 'rule_total', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
        'margin_rule' => ['column' => 'rule_margin', 'definition' => $text, 'kind' => 'text', 'default' => 'Belum dihitung'],
    ];
}

function aptd_keu_ralan_schema_definitions()
{
    $cacheColumns = [
        'no_rawat' => 'VARCHAR(20) NOT NULL',
        'claim_used' => 'DECIMAL(16,2) NOT NULL DEFAULT 0',
        'claim_source' => "VARCHAR(30) NOT NULL DEFAULT 'Belum Ada'",
        'claim_actual_snapshot' => 'DECIMAL(16,2) NOT NULL DEFAULT 0',
        'claim_history_snapshot' => 'DECIMAL(16,2) NOT NULL DEFAULT 0',
        'claim_apotek_snapshot' => 'DECIMAL(16,2) NOT NULL DEFAULT 0',
        'claim_history_no_rawat' => 'VARCHAR(20) NULL',
        'claim_diagnosis_code' => 'VARCHAR(20) NULL',
        'calculated_at' => 'DATETIME NULL',
        'calculated_by' => 'VARCHAR(50) NULL',
        'updated_at' => 'DATETIME NULL',
    ];
    foreach (aptd_keu_ralan_cache_fields() as $field) {
        $cacheColumns[$field['column']] = $field['definition'];
    }

    return [
        'lap_keuangan_bpjs_ralan' => [
            'columns' => $cacheColumns,
            'primary_key' => ['no_rawat'],
            'options' => 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        ],
        'klaim_apotek_online_manual' => [
            'columns' => [
                'no_sep' => 'VARCHAR(40) NOT NULL',
                'nominal_klaim' => 'DECIMAL(16,2) NOT NULL DEFAULT 0.00',
                'tanggal_input' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'user_input' => 'VARCHAR(100) NOT NULL',
            ],
            'primary_key' => ['no_sep'],
            'options' => 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
        ],
    ];
}

function aptd_keu_ralan_schema_definition($table)
{
    $definitions = aptd_keu_ralan_schema_definitions();
    if (!isset($definitions[$table])) {
        throw new InvalidArgumentException('Tabel bukan bagian dari skema keuangan Ralan.');
    }
    return $definitions[$table];
}

// Hanya membaca metadata. Tidak membuat atau mengubah tabel.
function aptd_keu_ralan_inspect_table_schema(mysqli $mysqli, $table)
{
    $definition = aptd_keu_ralan_schema_definition($table);
    try {
        $result = $mysqli->query('SHOW COLUMNS FROM `' . $table . '`');
        if (!$result) {
            throw new RuntimeException('Struktur tabel keuangan Ralan tidak dapat diperiksa.');
        }
    } catch (mysqli_sql_exception $exception) {
        if ((int) $exception->getCode() !== 1146) {
            throw $exception;
        }
        return [
            'exists' => false,
            'missing_columns' => array_keys($definition['columns']),
            'primary_key' => [],
        ];
    }

    $columns = [];
    $primaryKey = [];
    while ($column = $result->fetch_assoc()) {
        $columns[$column['Field']] = true;
        if (isset($column['Key']) && $column['Key'] === 'PRI') {
            $primaryKey[] = $column['Field'];
        }
    }
    $result->free();

    return [
        'exists' => true,
        'missing_columns' => array_values(array_diff(array_keys($definition['columns']), array_keys($columns))),
        'primary_key' => $primaryKey,
    ];
}

function aptd_keu_ralan_assert_table_schema(mysqli $mysqli, $table)
{
    // Cache validasi hanya selama request, terpisah untuk setiap koneksi.
    static $ready = null;
    if ($ready === null) {
        $ready = new SplObjectStorage();
    }
    $validated = isset($ready[$mysqli]) ? $ready[$mysqli] : [];
    if (isset($validated[$table])) {
        return;
    }

    $definition = aptd_keu_ralan_schema_definition($table);
    $inspection = aptd_keu_ralan_inspect_table_schema($mysqli, $table);
    if (
        !$inspection['exists']
        || !empty($inspection['missing_columns'])
        || $inspection['primary_key'] !== $definition['primary_key']
    ) {
        error_log('Struktur keuangan Ralan belum siap: ' . $table . '; ' . json_encode($inspection));
        throw new AptdKeuRalanSchemaException(
            'Struktur penyimpanan laporan keuangan Ralan belum lengkap. Hubungi administrator untuk pemeriksaan struktur tabel.'
        );
    }

    $validated[$table] = true;
    $ready[$mysqli] = $validated;
}

function aptd_keu_ralan_ensure_cache_schema(mysqli $mysqli)
{
    aptd_keu_ralan_assert_table_schema($mysqli, 'lap_keuangan_bpjs_ralan');
}

function aptd_keu_ralan_ensure_apotek_manual_schema(mysqli $mysqli)
{
    aptd_keu_ralan_assert_table_schema($mysqli, 'klaim_apotek_online_manual');
}

function aptd_keu_ralan_schema_user_message(Throwable $exception, $fallback)
{
    return $exception instanceof AptdKeuRalanSchemaException ? $exception->getMessage() : $fallback;
}

function aptd_keu_ralan_schema_create_sql($table)
{
    $definition = aptd_keu_ralan_schema_definition($table);
    $columns = [];
    foreach ($definition['columns'] as $column => $sql) {
        $columns[] = '`' . $column . '` ' . $sql;
    }
    $columns[] = 'PRIMARY KEY (`' . implode('`, `', $definition['primary_key']) . '`)';
    return 'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
        . implode(', ', $columns) . ') ' . $definition['options'];
}

// Menghasilkan rencana DDL; pemanggilan fungsi ini tidak mengeksekusi rencana.
function aptd_keu_ralan_schema_plan(mysqli $mysqli)
{
    $statements = [];
    $blockers = [];
    foreach (aptd_keu_ralan_schema_definitions() as $table => $definition) {
        $inspection = aptd_keu_ralan_inspect_table_schema($mysqli, $table);
        if (!$inspection['exists']) {
            $statements[] = aptd_keu_ralan_schema_create_sql($table);
            continue;
        }
        if ($inspection['primary_key'] !== $definition['primary_key']) {
            $blockers[] = 'Primary key tabel ' . $table . ' tidak sesuai; perlu peninjauan manual.';
            continue;
        }
        $addColumns = [];
        foreach ($inspection['missing_columns'] as $column) {
            $addColumns[] = 'ADD COLUMN `' . $column . '` ' . $definition['columns'][$column];
        }
        if (!empty($addColumns)) {
            $statements[] = 'ALTER TABLE `' . $table . '` ' . implode(', ', $addColumns);
        }
    }
    return ['statements' => $statements, 'blockers' => $blockers];
}

