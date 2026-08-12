<?php
require_once dirname(__DIR__) . '/report_helper.php';

function aptd_keu_ralan_is_valid_date($value)
{
    $date = DateTime::createFromFormat('Y-m-d', (string) $value);
    return $date && $date->format('Y-m-d') === (string) $value;
}

function aptd_keu_ralan_filters()
{
    $source = array_merge($_GET, $_POST);
    $startDate = isset($source['start_date']) && $source['start_date'] !== ''
        ? trim((string) $source['start_date'])
        : date('Y-m-01');
    $endDate = isset($source['end_date']) && $source['end_date'] !== ''
        ? trim((string) $source['end_date'])
        : date('Y-m-d');
    $kdPoli = isset($source['kd_poli']) ? trim((string) $source['kd_poli']) : '';

    $valid = true;
    $message = '';
    if (!aptd_keu_ralan_is_valid_date($startDate) || !aptd_keu_ralan_is_valid_date($endDate)) {
        $valid = false;
        $message = 'Format periode tanggal tidak valid.';
    } elseif ($endDate < $startDate) {
        $valid = false;
        $message = 'Tanggal Akhir tidak boleh lebih kecil dari Tanggal Awal.';
    }

    return [$startDate, $endDate, $kdPoli, $valid, $message];
}

function aptd_keu_ralan_fetch_poli(mysqli $mysqli)
{
    $result = $mysqli->query("SELECT kd_poli, nm_poli FROM poliklinik WHERE status = '1' ORDER BY nm_poli");
    if (!$result) {
        return [];
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function aptd_keu_ralan_inacbg_tariff_sql()
{
    return "
        SELECT igr.no_rawat,
               CAST(TRIM(igr.tariff) AS DECIMAL(16,2)) AS tariff,
               IFNULL(igr.datetime, '1000-01-01 00:00:00') AS tariff_datetime
        FROM tb_inacbg_grouping_result igr
        LEFT JOIN tb_inacbg_grouping_result newer
          ON newer.no_rawat = igr.no_rawat
         AND TRIM(IFNULL(newer.tariff, '')) REGEXP '^[0-9]+([.][0-9]+)?$'
         AND CAST(TRIM(newer.tariff) AS DECIMAL(16,2)) > 0
         AND (
                IFNULL(newer.datetime, '1000-01-01 00:00:00') > IFNULL(igr.datetime, '1000-01-01 00:00:00')
             OR (
                    IFNULL(newer.datetime, '1000-01-01 00:00:00') = IFNULL(igr.datetime, '1000-01-01 00:00:00')
                AND newer.no_sep > igr.no_sep
             )
         )
        WHERE TRIM(IFNULL(igr.tariff, '')) REGEXP '^[0-9]+([.][0-9]+)?$'
          AND CAST(TRIM(igr.tariff) AS DECIMAL(16,2)) > 0
          AND newer.no_rawat IS NULL
    ";
}

function aptd_keu_ralan_eklaim_tariff_sql()
{
    return "
        SELECT egr.no_rawat,
               CAST(TRIM(egr.tariff) AS DECIMAL(16,2)) AS tariff,
               IFNULL(egr.datetime, '1000-01-01 00:00:00') AS tariff_datetime
        FROM tb_eklaim_grouping_result egr
        LEFT JOIN tb_eklaim_grouping_result newer
          ON newer.no_rawat = egr.no_rawat
         AND TRIM(IFNULL(newer.tariff, '')) REGEXP '^[0-9]+([.][0-9]+)?$'
         AND CAST(TRIM(newer.tariff) AS DECIMAL(16,2)) > 0
         AND (
                IFNULL(newer.datetime, '1000-01-01 00:00:00') > IFNULL(egr.datetime, '1000-01-01 00:00:00')
             OR (
                    IFNULL(newer.datetime, '1000-01-01 00:00:00') = IFNULL(egr.datetime, '1000-01-01 00:00:00')
                AND newer.no_sep > egr.no_sep
             )
         )
        WHERE TRIM(IFNULL(egr.tariff, '')) REGEXP '^[0-9]+([.][0-9]+)?$'
          AND CAST(TRIM(egr.tariff) AS DECIMAL(16,2)) > 0
          AND newer.no_rawat IS NULL
    ";
}

function aptd_keu_ralan_search_where(mysqli $mysqli, $search)
{
    $search = trim((string) $search);
    if ($search === '') {
        return '';
    }

    $like = "'%" . $mysqli->real_escape_string($search) . "%'";
    return " AND (
           DATE_FORMAT(rp.tgl_registrasi, '%d-%m-%Y') LIKE $like
        OR rp.no_rawat LIKE $like
        OR rp.no_rkm_medis LIKE $like
        OR ps.nm_pasien LIKE $like
        OR d.nm_dokter LIKE $like
        OR bs.no_sep LIKE $like
        OR pl.nm_poli LIKE $like
        OR s.nm_sps LIKE $like
        OR rp.stts LIKE $like
        OR rp.status_bayar LIKE $like
        OR pj.png_jawab LIKE $like
    )";
}

function aptd_keu_ralan_bind_rows_statement(
    mysqli_stmt $stmt,
    &$startDate,
    &$endDate,
    &$kdPoli,
    &$onlyNoRawat
) {
    if ($kdPoli !== '' && $onlyNoRawat !== '') {
        $stmt->bind_param('ssss', $startDate, $endDate, $kdPoli, $onlyNoRawat);
    } elseif ($kdPoli !== '') {
        $stmt->bind_param('sss', $startDate, $endDate, $kdPoli);
    } elseif ($onlyNoRawat !== '') {
        $stmt->bind_param('sss', $startDate, $endDate, $onlyNoRawat);
    } else {
        $stmt->bind_param('ss', $startDate, $endDate);
    }
}

function aptd_keu_ralan_validate_apotek_upload(array $file, $requireUploadedFile = true)
{
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE => 'Ukuran berkas melebihi batas upload server.',
        UPLOAD_ERR_FORM_SIZE => 'Ukuran berkas melebihi batas form upload.',
        UPLOAD_ERR_PARTIAL => 'Berkas hanya terunggah sebagian. Silakan unggah ulang.',
        UPLOAD_ERR_NO_FILE => 'Pilih berkas Excel yang akan diimport.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara upload tidak tersedia di server.',
        UPLOAD_ERR_CANT_WRITE => 'Berkas upload tidak dapat ditulis di server.',
        UPLOAD_ERR_EXTENSION => 'Upload berkas dihentikan oleh ekstensi server.',
    ];
    $errorCode = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($errorCode !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'message' => isset($uploadErrors[$errorCode])
                ? $uploadErrors[$errorCode]
                : 'Berkas gagal diunggah.',
        ];
    }

    $originalName = isset($file['name'])
        ? basename(str_replace('\\', '/', trim((string) $file['name'])))
        : '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($originalName === '' || !in_array($extension, ['xls', 'xlsx'], true)) {
        return [
            'success' => false,
            'message' => 'Format berkas tidak didukung. Gunakan berkas .xls atau .xlsx.',
        ];
    }

    $temporaryPath = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
    $fileSize = isset($file['size']) ? (int) $file['size'] : 0;
    if ($temporaryPath === '' || $fileSize <= 0 || !is_file($temporaryPath)) {
        return ['success' => false, 'message' => 'Berkas Excel kosong atau tidak dapat dibaca.'];
    }
    if ($requireUploadedFile && !is_uploaded_file($temporaryPath)) {
        return ['success' => false, 'message' => 'Sumber berkas upload tidak valid.'];
    }

    return [
        'success' => true,
        'message' => 'Berkas Excel berhasil diterima.',
        'file_name' => $originalName,
        'extension' => $extension,
        'size' => $fileSize,
    ];
}

function aptd_keu_ralan_ensure_apotek_manual_schema(mysqli $mysqli)
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $sql = "CREATE TABLE IF NOT EXISTS klaim_apotek_online_manual (
        no_sep VARCHAR(40) NOT NULL,
        nominal_klaim DECIMAL(16,2) NOT NULL DEFAULT 0.00,
        tanggal_input TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        user_input VARCHAR(100) NOT NULL,
        PRIMARY KEY (no_sep)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    if (!$mysqli->query($sql)) {
        throw new RuntimeException('Tabel klaim apotek online tidak dapat disiapkan: ' . $mysqli->error);
    }
    $ready = true;
}

function aptd_keu_ralan_normalize_apotek_nominal($value)
{
    if ($value === null || is_bool($value)) {
        return null;
    }

    if (is_int($value) || is_float($value)) {
        $nominal = (float) $value;
    } else {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $text = str_ireplace(['Rp', 'IDR'], '', $text);
        $text = str_replace(["\xc2\xa0", ' '], '', $text);
        $text = preg_replace('/[^0-9,.\-]/', '', $text);
        if ($text === '' || !preg_match('/\d/', $text)) {
            return null;
        }

        if (strpos($text, ',') !== false) {
            $text = str_replace('.', '', $text);
            $parts = explode(',', $text);
            $decimal = array_pop($parts);
            $text = implode('', $parts) . '.' . $decimal;
        } else {
            $text = str_replace('.', '', $text);
        }
        if (!is_numeric($text)) {
            return null;
        }
        $nominal = (float) $text;
    }

    if (!is_finite($nominal) || $nominal < 0 || $nominal > 99999999999999.99) {
        return null;
    }
    return round($nominal, 2);
}

function aptd_keu_ralan_xlsx_archive($filePath)
{
    $binary = file_get_contents($filePath);
    if ($binary === false || substr($binary, 0, 2) !== 'PK') {
        throw new RuntimeException('Struktur berkas XLSX tidak valid.');
    }

    $eocdPosition = strrpos($binary, "\x50\x4b\x05\x06");
    if ($eocdPosition === false || strlen($binary) < $eocdPosition + 22) {
        throw new RuntimeException('Direktori berkas XLSX tidak ditemukan.');
    }
    $eocd = unpack(
        'Vsignature/vdisk/vcentral_disk/ventries_disk/ventries_total/Vcentral_size/Vcentral_offset/vcomment_length',
        substr($binary, $eocdPosition, 22)
    );
    if (!$eocd || $eocd['signature'] !== 0x06054b50) {
        throw new RuntimeException('Direktori berkas XLSX rusak.');
    }

    $entries = [];
    $position = (int) $eocd['central_offset'];
    for ($index = 0; $index < (int) $eocd['entries_total']; $index++) {
        if (strlen($binary) < $position + 46) {
            throw new RuntimeException('Daftar isi berkas XLSX tidak lengkap.');
        }
        $header = unpack(
            'Vsignature/vversion_made/vversion_needed/vflags/vcompression/vtime/vdate/Vcrc/Vcompressed_size/Vuncompressed_size/vname_length/vextra_length/vcomment_length/vdisk/vinternal/Vexternal/Vlocal_offset',
            substr($binary, $position, 46)
        );
        if (!$header || $header['signature'] !== 0x02014b50) {
            throw new RuntimeException('Entri berkas XLSX tidak valid.');
        }
        $name = substr($binary, $position + 46, (int) $header['name_length']);
        $entries[$name] = $header;
        $position += 46
            + (int) $header['name_length']
            + (int) $header['extra_length']
            + (int) $header['comment_length'];
    }

    return ['binary' => $binary, 'entries' => $entries];
}

function aptd_keu_ralan_xlsx_entry(array $archive, $name, $required = true)
{
    if (!isset($archive['entries'][$name])) {
        if ($required) {
            throw new RuntimeException('Komponen ' . $name . ' tidak ditemukan pada XLSX.');
        }
        return '';
    }
    $entry = $archive['entries'][$name];
    if (((int) $entry['flags'] & 1) === 1) {
        throw new RuntimeException('Berkas XLSX terenkripsi tidak dapat diproses.');
    }
    if ((int) $entry['uncompressed_size'] > 52428800) {
        throw new RuntimeException('Komponen XLSX terlalu besar untuk diproses.');
    }

    $offset = (int) $entry['local_offset'];
    $binary = $archive['binary'];
    if (strlen($binary) < $offset + 30) {
        throw new RuntimeException('Data komponen XLSX tidak lengkap.');
    }
    $local = unpack(
        'Vsignature/vversion/vflags/vcompression/vtime/vdate/Vcrc/Vcompressed_size/Vuncompressed_size/vname_length/vextra_length',
        substr($binary, $offset, 30)
    );
    if (!$local || $local['signature'] !== 0x04034b50) {
        throw new RuntimeException('Data komponen XLSX rusak.');
    }
    $dataOffset = $offset + 30 + (int) $local['name_length'] + (int) $local['extra_length'];
    $compressed = substr($binary, $dataOffset, (int) $entry['compressed_size']);
    if ((int) $entry['compression'] === 0) {
        $content = $compressed;
    } elseif ((int) $entry['compression'] === 8) {
        $content = gzinflate($compressed);
    } else {
        throw new RuntimeException('Metode kompresi XLSX tidak didukung.');
    }
    if ($content === false) {
        throw new RuntimeException('Komponen XLSX gagal didekompresi.');
    }
    if (strlen($content) > 52428800) {
        throw new RuntimeException('Komponen XLSX terlalu besar untuk diproses.');
    }
    return $content;
}

function aptd_keu_ralan_xlsx_xml($xmlContent, $componentName)
{
    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if ($xml === false) {
        throw new RuntimeException('XML ' . $componentName . ' tidak valid.');
    }
    $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    return $xml;
}

function aptd_keu_ralan_parse_xlsx_without_zip($filePath)
{
    $archive = aptd_keu_ralan_xlsx_archive($filePath);
    $sharedStrings = [];
    $sharedXmlContent = aptd_keu_ralan_xlsx_entry($archive, 'xl/sharedStrings.xml', false);
    if ($sharedXmlContent !== '') {
        $sharedXml = aptd_keu_ralan_xlsx_xml($sharedXmlContent, 'sharedStrings');
        foreach ($sharedXml->xpath('//x:si') as $sharedItem) {
            $sharedItem->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $text = '';
            foreach ($sharedItem->xpath('.//x:t') as $textPart) {
                $text .= (string) $textPart;
            }
            $sharedStrings[] = $text;
        }
    }

    $sheetNames = [];
    foreach (array_keys($archive['entries']) as $entryName) {
        if (preg_match('#^xl/worksheets/sheet[0-9]+\.xml$#i', $entryName)) {
            $sheetNames[] = $entryName;
        }
    }
    natsort($sheetNames);
    if (!$sheetNames) {
        throw new RuntimeException('Worksheet tidak ditemukan pada berkas XLSX.');
    }

    $records = [];
    $skipped = 0;
    $duplicateRows = 0;
    foreach ($sheetNames as $sheetName) {
        $sheetXml = aptd_keu_ralan_xlsx_xml(
            aptd_keu_ralan_xlsx_entry($archive, $sheetName),
            $sheetName
        );
        foreach ($sheetXml->xpath('//x:sheetData/x:row') as $row) {
            $row->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $values = ['E' => '', 'K' => ''];
            foreach ($row->xpath('./x:c') as $cell) {
                $reference = strtoupper((string) $cell['r']);
                $column = preg_replace('/[^A-Z]/', '', $reference);
                if (!array_key_exists($column, $values)) {
                    continue;
                }
                $cell->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $type = (string) $cell['t'];
                if ($type === 'inlineStr') {
                    $value = '';
                    foreach ($cell->xpath('.//x:t') as $textPart) {
                        $value .= (string) $textPart;
                    }
                } else {
                    $valueNodes = $cell->xpath('./x:v');
                    $value = $valueNodes ? (string) $valueNodes[0] : '';
                    if ($type === 's') {
                        $value = isset($sharedStrings[(int) $value]) ? $sharedStrings[(int) $value] : '';
                    } elseif (($type === '' || $type === 'n') && is_numeric($value)) {
                        $value = (float) $value;
                    }
                }
                $values[$column] = $value;
            }

            if (trim((string) $values['E']) === '' && trim((string) $values['K']) === '') {
                continue;
            }
            $noSep = ltrim(trim((string) $values['E']), "'");
            $noSep = preg_replace('/\s+/', '', $noSep);
            $nominal = aptd_keu_ralan_normalize_apotek_nominal($values['K']);
            if ($noSep === '' || strlen($noSep) > 40 || $nominal === null) {
                $skipped++;
                continue;
            }
            if (isset($records[$noSep])) {
                $duplicateRows++;
            }
            $records[$noSep] = $nominal;
        }
    }

    return [$records, $skipped, $duplicateRows];
}

function aptd_keu_ralan_import_apotek_excel(mysqli $mysqli, $filePath, $username)
{
    $filePath = (string) $filePath;
    if ($filePath === '' || !is_file($filePath)) {
        return ['success' => false, 'message' => 'Berkas Excel tidak dapat dibaca.'];
    }

    $records = [];
    $skipped = 0;
    $duplicateRows = 0;
    $signature = file_get_contents($filePath, false, null, 0, 8);
    $isXlsx = $signature !== false && substr($signature, 0, 2) === 'PK';
    if ($isXlsx && !class_exists('ZipArchive')) {
        list($records, $skipped, $duplicateRows) = aptd_keu_ralan_parse_xlsx_without_zip($filePath);
    } else {
        require_once dirname(dirname(__DIR__)) . '/export_excel_helper.php';
        if (!aptd_excel_bootstrap() || !class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            return ['success' => false, 'message' => 'Library pembaca Excel belum tersedia di server.'];
        }

        $spreadsheet = null;
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            $spreadsheet = $reader->load($filePath);

            foreach ($spreadsheet->getAllSheets() as $worksheet) {
                $highestRow = max(
                    (int) $worksheet->getHighestDataRow('E'),
                    (int) $worksheet->getHighestDataRow('K')
                );
                for ($rowNumber = 1; $rowNumber <= $highestRow; $rowNumber++) {
                    $sepValue = $worksheet->getCell('E' . $rowNumber)->getValue();
                    $claimCell = $worksheet->getCell('K' . $rowNumber);
                    $claimValue = $claimCell->getValue();
                    if ($claimCell->isFormula()) {
                        try {
                            $claimValue = $claimCell->getCalculatedValue();
                        } catch (Throwable $exception) {
                            $claimValue = $claimCell->getValue();
                        }
                    }

                    if (trim((string) $sepValue) === '' && trim((string) $claimValue) === '') {
                        continue;
                    }
                    $noSep = ltrim(trim((string) $sepValue), "'");
                    $noSep = preg_replace('/\s+/', '', $noSep);
                    $nominal = aptd_keu_ralan_normalize_apotek_nominal($claimValue);
                    if ($noSep === '' || strlen($noSep) > 40 || $nominal === null) {
                        $skipped++;
                        continue;
                    }
                    if (isset($records[$noSep])) {
                        $duplicateRows++;
                    }
                    $records[$noSep] = $nominal;
                }
            }
        } catch (Throwable $exception) {
            if ($spreadsheet) {
                $spreadsheet->disconnectWorksheets();
            }
            throw new RuntimeException('Berkas Excel gagal dibaca: ' . $exception->getMessage(), 0, $exception);
        }
        if ($spreadsheet) {
            $spreadsheet->disconnectWorksheets();
        }
        unset($spreadsheet);
    }

    if (!$records) {
        return [
            'success' => false,
            'message' => 'Tidak ditemukan data Nomor SEP di kolom E dan Nilai Klaim di kolom K.',
            'processed' => 0,
            'skipped' => $skipped,
        ];
    }

    aptd_keu_ralan_ensure_apotek_manual_schema($mysqli);
    $username = trim((string) $username);
    if ($username === '') {
        $username = 'system';
    }
    $username = function_exists('mb_substr')
        ? mb_substr($username, 0, 100, 'UTF-8')
        : substr($username, 0, 100);

    $sql = "INSERT INTO klaim_apotek_online_manual
                (no_sep, nominal_klaim, tanggal_input, user_input)
            VALUES (?, ?, CURRENT_TIMESTAMP, ?)
            ON DUPLICATE KEY UPDATE
                nominal_klaim = VALUES(nominal_klaim),
                tanggal_input = CURRENT_TIMESTAMP,
                user_input = VALUES(user_input)";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Penyimpanan klaim apotek tidak dapat dipersiapkan: ' . $mysqli->error);
    }

    $processed = 0;
    $mysqli->begin_transaction();
    try {
        $noSep = '';
        $nominal = 0.0;
        if (!$stmt->bind_param('sds', $noSep, $nominal, $username)) {
            throw new RuntimeException('Parameter klaim apotek tidak dapat dipersiapkan.');
        }
        foreach ($records as $recordNoSep => $recordNominal) {
            $noSep = $recordNoSep;
            $nominal = $recordNominal;
            if (!$stmt->execute()) {
                throw new RuntimeException('Klaim untuk SEP ' . $noSep . ' tidak dapat disimpan.');
            }
            $processed++;
        }
        $mysqli->commit();
    } catch (Throwable $exception) {
        $mysqli->rollback();
        $stmt->close();
        throw $exception;
    }
    $stmt->close();

    return [
        'success' => true,
        'message' => 'Berhasil mengimpor/memperbarui ' . $processed . ' data klaim apotek online.',
        'processed' => $processed,
        'skipped' => $skipped,
        'duplicate_rows' => $duplicateRows,
    ];
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

function aptd_keu_ralan_ensure_cache_schema(mysqli $mysqli)
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $columnSql = [
        'no_rawat VARCHAR(20) NOT NULL',
        'claim_used DECIMAL(16,2) NOT NULL DEFAULT 0',
        "claim_source VARCHAR(30) NOT NULL DEFAULT 'Belum Ada'",
        'claim_actual_snapshot DECIMAL(16,2) NOT NULL DEFAULT 0',
        'claim_history_snapshot DECIMAL(16,2) NOT NULL DEFAULT 0',
        'claim_apotek_snapshot DECIMAL(16,2) NOT NULL DEFAULT 0',
        'claim_history_no_rawat VARCHAR(20) NULL',
        'claim_diagnosis_code VARCHAR(20) NULL',
        'calculated_at DATETIME NULL',
        'calculated_by VARCHAR(50) NULL',
        'updated_at DATETIME NULL',
    ];
    foreach (aptd_keu_ralan_cache_fields() as $field) {
        $columnSql[] = $field['column'] . ' ' . $field['definition'];
    }

    $createSql = "CREATE TABLE IF NOT EXISTS lap_keuangan_bpjs_ralan ("
        . implode(', ', $columnSql)
        . ', PRIMARY KEY (no_rawat)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    if (!$mysqli->query($createSql)) {
        throw new RuntimeException('Tabel cache keuangan Ralan tidak dapat dibuat: ' . $mysqli->error);
    }

    $existing = [];
    $result = $mysqli->query('SHOW COLUMNS FROM lap_keuangan_bpjs_ralan');
    if (!$result) {
        throw new RuntimeException('Struktur tabel cache keuangan Ralan tidak dapat dibaca: ' . $mysqli->error);
    }
    while ($column = $result->fetch_assoc()) {
        $existing[$column['Field']] = true;
    }

    $definitions = [
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
        $definitions[$field['column']] = $field['definition'];
    }
    foreach ($definitions as $column => $definition) {
        if (!isset($existing[$column])) {
            if (!$mysqli->query("ALTER TABLE lap_keuangan_bpjs_ralan ADD COLUMN $column $definition")) {
                throw new RuntimeException('Kolom cache ' . $column . ' tidak dapat dibuat: ' . $mysqli->error);
            }
        }
    }

    $ready = true;
}

function aptd_keu_ralan_cache_select_sql()
{
    $select = '';
    foreach (aptd_keu_ralan_cache_fields() as $key => $field) {
        $select .= "MAX(cache." . $field['column'] . ") AS cache_" . $key . ",\n";
    }
    return $select;
}

function aptd_keu_ralan_claim_shifted_to_actual(array $row)
{
    if (empty($row['calculated_at'])) {
        return false;
    }
    $actualShifted = (float) $row['claim_actual'] > 0
        && (
            (string) $row['cached_claim_source'] !== 'Aktual'
            || abs((float) $row['cached_claim_used'] - (float) $row['claim_actual']) >= 0.01
        );
    $apotekShifted = abs(
        (float) (isset($row['cached_claim_apotek_snapshot']) ? $row['cached_claim_apotek_snapshot'] : 0)
        - (float) (isset($row['klaim_apotek_online']) ? $row['klaim_apotek_online'] : 0)
    ) >= 0.01;
    return $actualShifted || $apotekShifted;
}

function aptd_keu_ralan_apply_cached_calculations(array &$rows)
{
    foreach ($rows as $index => $row) {
        $hasCache = !empty($row['calculated_at']);
        // Simpan hasil pencarian klaim terbaru untuk proses Hitung/Hitung Ulang.
        // Field claim_history dan claim_used di bawah ini khusus menjadi snapshot UI (AR-155).
        $rows[$index]['calculation_claim_history'] = (float) $row['claim_history'];
        $rows[$index]['calculation_claim_used'] = (float) $row['claim_used'];
        $rows[$index]['calculation_claim_source'] = (string) $row['claim_source'];
        $rows[$index]['calculation_claim_history_no_rawat'] = (string) $row['claim_history_no_rawat'];
        $rows[$index]['calculation_claim_history_match_source'] = (string) $row['claim_history_match_source'];
        $rows[$index]['calculation_target_diagnosis_code'] = (string) $row['target_diagnosis_code'];
        $rows[$index]['calculation_target_diagnosis_source'] = (string) $row['target_diagnosis_source'];
        foreach (aptd_keu_ralan_cache_fields() as $key => $field) {
            $value = $hasCache && isset($row['cache_' . $key])
                ? $row['cache_' . $key]
                : $field['default'];
            if ($field['kind'] === 'decimal') {
                $value = (float) $value;
            } elseif ($field['kind'] === 'integer') {
                $value = (int) $value;
            } else {
                $value = (string) $value;
            }
            $rows[$index][$key] = $value;
        }
        $rows[$index]['claim_history'] = $hasCache
            ? (float) $row['cached_claim_history_snapshot']
            : 0;
        $rows[$index]['claim_used'] = $hasCache
            ? (float) $row['cached_claim_used']
            : 0;
        $rows[$index]['claim_source'] = $hasCache && trim((string) $row['cached_claim_source']) !== ''
            ? (string) $row['cached_claim_source']
            : 'Belum Ada';
        $rows[$index]['claim_history_no_rawat'] = $hasCache
            ? (string) $row['cached_claim_history_no_rawat']
            : '';
        $rows[$index]['claim_history_match_source'] = $hasCache
            && (float) $row['cached_claim_history_snapshot'] > 0
            ? 'Snapshot Kalkulasi'
            : '';
        $rows[$index]['target_diagnosis_code'] = $hasCache
            ? (string) $row['cached_claim_diagnosis_code']
            : '';
        $rows[$index]['target_diagnosis_source'] = $hasCache
            ? 'Snapshot Kalkulasi'
            : 'Belum Ada';
        if ($hasCache) {
            $claimUsed = (float) $row['cached_claim_used'];
            $claimApotek = (float) (isset($row['klaim_apotek_online']) ? $row['klaim_apotek_online'] : 0);
            $total = (float) $rows[$index]['total'];
            $rows[$index]['margin'] = round($claimUsed + $claimApotek - $total, 2);
            $rows[$index]['margin_rule'] = 'Klaim Digunakan Rp ' . aptd_currency($claimUsed)
                . ' + Klaim Apotek Online Rp ' . aptd_currency($claimApotek)
                . ' - TOTAL Rp ' . aptd_currency($total);
        }
        $rows[$index]['has_hitung'] = $hasCache ? 1 : 0;
        $rows[$index]['calculation_stale'] = aptd_keu_ralan_claim_shifted_to_actual($row) ? 1 : 0;
    }
}

function aptd_keu_ralan_restore_calculation_claims(array $row)
{
    foreach ([
        'claim_history',
        'claim_used',
        'claim_source',
        'claim_history_no_rawat',
        'claim_history_match_source',
        'target_diagnosis_code',
        'target_diagnosis_source',
    ] as $field) {
        $calculationField = 'calculation_' . $field;
        if (array_key_exists($calculationField, $row)) {
            $row[$field] = $row[$calculationField];
        }
    }
    return $row;
}

function aptd_keu_ralan_fetch_rows(
    mysqli $mysqli,
    $startDate,
    $endDate,
    $kdPoli = '',
    $onlyNoRawat = '',
    $search = '',
    $limit = null,
    $offset = 0,
    $orderColumn = 0,
    $orderDirection = 'ASC',
    $calculateDetails = false
)
{
    aptd_keu_ralan_ensure_cache_schema($mysqli);
    aptd_keu_ralan_ensure_apotek_manual_schema($mysqli);
    $poliWhere = $kdPoli !== '' ? ' AND rp.kd_poli = ?' : '';
    $rawatWhere = $onlyNoRawat !== '' ? ' AND rp.no_rawat = ?' : '';
    $searchWhere = aptd_keu_ralan_search_where($mysqli, $search);
    $eklaimSql = aptd_keu_ralan_eklaim_tariff_sql();
    $cacheSelectSql = aptd_keu_ralan_cache_select_sql();
    $orderColumns = [
        0 => 'rp.tgl_registrasi',
        1 => 'rp.no_rawat',
        2 => 'rp.no_rkm_medis',
        3 => 'ps.nm_pasien',
        4 => 'd.nm_dokter',
        5 => 'no_sep',
        6 => 'pl.nm_poli',
        7 => 's.nm_sps',
        8 => 'rp.stts',
        9 => 'rp.status_bayar',
        10 => 'pj.png_jawab',
        12 => 'claim_actual',
    ];
    $orderSql = isset($orderColumns[(int) $orderColumn])
        ? $orderColumns[(int) $orderColumn]
        : $orderColumns[0];
    $directionSql = strtoupper((string) $orderDirection) === 'DESC' ? 'DESC' : 'ASC';
    $limitSql = '';
    if ($limit !== null) {
        $limitSql = ' LIMIT ' . max(1, min(100, (int) $limit))
            . ' OFFSET ' . max(0, (int) $offset);
    }
    $sql = "
        SELECT
            rp.tgl_registrasi,
            rp.no_rawat,
            rp.no_rkm_medis,
            ps.nm_pasien,
            pl.kd_poli,
            pl.nm_poli,
            d.nm_dokter,
            COALESCE(s.nm_sps, '') AS nm_sps,
            pj.png_jawab AS jenis_bayar,
            rp.stts AS status_periksa,
            rp.status_bayar,
            COALESCE(MAX(NULLIF(TRIM(bs.no_sep), '')), '') AS no_sep,
            COALESCE(MAX(apotek.nominal_klaim), 0) AS klaim_apotek_online,
            COUNT(dp.no_rawat) AS diagnosis_count,
            COALESCE(MAX(CASE WHEN dp.prioritas = 1 THEN NULLIF(TRIM(dp.kd_penyakit), '') END), '') AS diagnosis_priority_1,
            COALESCE(MAX(CASE WHEN dp.prioritas = 2 THEN NULLIF(TRIM(dp.kd_penyakit), '') END), '') AS diagnosis_priority_2,
            COALESCE(MAX(NULLIF(TRIM(bs.diagawal), '')), '') AS diagnosis_sep,
            COALESCE(MAX(eklaim.tariff), 0) AS claim_actual,
            COALESCE(MAX(manual.claim_selected), 0) AS claim_selected_raw,
            COALESCE(MAX(manual.claim_source), '') AS claim_selected_source,
            COALESCE(MAX(manual.claim_history), 0) AS stored_claim_history,
            COALESCE(MAX(manual.claim_history_no_rawat), '') AS stored_claim_history_no_rawat,
            MAX(cache.calculated_at) AS calculated_at,
            COALESCE(MAX(cache.claim_used), 0) AS cached_claim_used,
            COALESCE(MAX(cache.claim_source), '') AS cached_claim_source,
            COALESCE(MAX(cache.claim_actual_snapshot), 0) AS cached_claim_actual_snapshot,
            COALESCE(MAX(cache.claim_history_snapshot), 0) AS cached_claim_history_snapshot,
            COALESCE(MAX(cache.claim_apotek_snapshot), 0) AS cached_claim_apotek_snapshot,
            COALESCE(MAX(cache.claim_history_no_rawat), '') AS cached_claim_history_no_rawat,
            COALESCE(MAX(cache.claim_diagnosis_code), '') AS cached_claim_diagnosis_code,
            COALESCE(MAX(cache.calculated_by), '') AS calculated_by,
            $cacheSelectSql
            COALESCE((
                SELECT MAX(b.totalbiaya)
                FROM billing b
                WHERE b.no_rawat = rp.no_rawat
                  AND b.status = 'Tagihan'
            ), 0) AS total_tagihan
        FROM reg_periksa rp
        INNER JOIN pasien ps ON ps.no_rkm_medis = rp.no_rkm_medis
        INNER JOIN poliklinik pl ON pl.kd_poli = rp.kd_poli
        INNER JOIN dokter d ON d.kd_dokter = rp.kd_dokter
        LEFT JOIN spesialis s ON s.kd_sps = d.kd_sps
        INNER JOIN penjab pj ON pj.kd_pj = rp.kd_pj
        LEFT JOIN bridging_sep bs ON bs.no_rawat = rp.no_rawat
        LEFT JOIN klaim_apotek_online_manual apotek ON apotek.no_sep = bs.no_sep
        LEFT JOIN kamar_inap ki ON ki.no_rawat = rp.no_rawat
        LEFT JOIN diagnosa_pasien dp ON dp.no_rawat = rp.no_rawat
        LEFT JOIN ($eklaimSql) eklaim ON eklaim.no_rawat = rp.no_rawat
        LEFT JOIN lap_keuangan_bpjs manual ON manual.no_rawat = rp.no_rawat
        LEFT JOIN lap_keuangan_bpjs_ralan cache ON cache.no_rawat = rp.no_rawat
        WHERE rp.tgl_registrasi BETWEEN ? AND ?
          AND rp.status_lanjut = 'Ralan'
          AND rp.kd_pj = 'BPJ'
          AND rp.stts <> 'Batal'
          AND ki.no_rawat IS NULL
          $poliWhere
          $rawatWhere
          $searchWhere
        GROUP BY
            rp.tgl_registrasi,
            rp.no_rawat,
            rp.no_rkm_medis,
            ps.nm_pasien,
            pl.kd_poli,
            pl.nm_poli,
            d.nm_dokter,
            s.nm_sps,
            pj.png_jawab,
            rp.stts,
            rp.status_bayar
        ORDER BY $orderSql $directionSql, rp.no_rawat $directionSql
        $limitSql";

    $stmt = null;
    for ($attempt = 0; $attempt < 2; $attempt++) {
        try {
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Query laporan tidak dapat dipersiapkan: ' . $mysqli->error);
            }
            aptd_keu_ralan_bind_rows_statement($stmt, $startDate, $endDate, $kdPoli, $onlyNoRawat);
            if (!$stmt->execute()) {
                throw new RuntimeException('Query laporan tidak dapat dijalankan: ' . $stmt->error);
            }
            break;
        } catch (mysqli_sql_exception $exception) {
            if ($stmt instanceof mysqli_stmt) {
                $stmt->close();
            }
            $stmt = null;
            $needsReprepare = (int) $exception->getCode() === 1615
                || stripos($exception->getMessage(), 're-prepared') !== false;
            if ($attempt === 0 && $needsReprepare) {
                continue;
            }
            throw new RuntimeException(
                'Query laporan tidak dapat dijalankan: ' . $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }
    }
    if (!($stmt instanceof mysqli_stmt)) {
        throw new RuntimeException('Query laporan tidak dapat dijalankan setelah percobaan ulang.');
    }

    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    aptd_keu_ralan_apply_claims($mysqli, $rows);
    if ($calculateDetails) {
        aptd_keu_ralan_apply_doctor_fees($mysqli, $rows);
    } else {
        aptd_keu_ralan_apply_cached_calculations($rows);
    }
    return $rows;
}

function aptd_keu_ralan_count_rows(mysqli $mysqli, $startDate, $endDate, $kdPoli = '', $search = '')
{
    $poliWhere = $kdPoli !== ''
        ? " AND rp.kd_poli = '" . $mysqli->real_escape_string($kdPoli) . "'"
        : '';
    $searchWhere = aptd_keu_ralan_search_where($mysqli, $search);
    $startDate = $mysqli->real_escape_string($startDate);
    $endDate = $mysqli->real_escape_string($endDate);
    $sql = "
        SELECT COUNT(DISTINCT rp.no_rawat) AS total
        FROM reg_periksa rp
        INNER JOIN pasien ps ON ps.no_rkm_medis = rp.no_rkm_medis
        INNER JOIN poliklinik pl ON pl.kd_poli = rp.kd_poli
        INNER JOIN dokter d ON d.kd_dokter = rp.kd_dokter
        LEFT JOIN spesialis s ON s.kd_sps = d.kd_sps
        INNER JOIN penjab pj ON pj.kd_pj = rp.kd_pj
        LEFT JOIN bridging_sep bs ON bs.no_rawat = rp.no_rawat
        WHERE rp.tgl_registrasi BETWEEN '$startDate' AND '$endDate'
          AND rp.status_lanjut = 'Ralan'
          AND rp.kd_pj = 'BPJ'
          AND rp.stts <> 'Batal'
          AND NOT EXISTS (
              SELECT 1 FROM kamar_inap ki WHERE ki.no_rawat = rp.no_rawat
          )
          $poliWhere
          $searchWhere";
    $result = $mysqli->query($sql);
    if (!$result) {
        throw new RuntimeException('Jumlah data laporan tidak dapat dimuat: ' . $mysqli->error);
    }
    return (int) $result->fetch_assoc()['total'];
}

function aptd_keu_ralan_fetch_summary(mysqli $mysqli, $startDate, $endDate, $kdPoli = '')
{
    aptd_keu_ralan_ensure_cache_schema($mysqli);
    $poliWhere = $kdPoli !== ''
        ? " AND rp.kd_poli = '" . $mysqli->real_escape_string($kdPoli) . "'"
        : '';
    $startDate = $mysqli->real_escape_string($startDate);
    $endDate = $mysqli->real_escape_string($endDate);
    $eklaimSql = aptd_keu_ralan_eklaim_tariff_sql();
    $sql = "
        SELECT COUNT(*) AS jumlah_kunjungan,
               COALESCE(SUM(report.cached_claim_used), 0) AS total_klaim,
               COALESCE(SUM(report.total_jasa_dokter), 0) AS total_jasa_dokter,
               COALESCE(SUM(report.total_obat), 0) AS total_obat
        FROM (
            SELECT rp.no_rawat,
                   COALESCE(MAX(eklaim.tariff), 0) AS claim_actual,
                   COALESCE(MAX(cache.claim_used), 0) AS cached_claim_used,
                   COALESCE(MAX(manual.claim_selected), 0) AS manual_claim_selected,
                   CASE
                       WHEN COUNT(dp.no_rawat) = 0 THEN
                           CASE WHEN MAX(NULLIF(TRIM(bs.diagawal), '')) IS NOT NULL THEN 1 ELSE 0 END
                       WHEN UPPER(LEFT(COALESCE(MAX(CASE
                           WHEN dp.prioritas = 1 THEN NULLIF(TRIM(dp.kd_penyakit), '')
                       END), ''), 1)) = 'Z' THEN
                           CASE WHEN MAX(CASE
                               WHEN dp.prioritas = 2 THEN NULLIF(TRIM(dp.kd_penyakit), '')
                           END) IS NOT NULL THEN 1 ELSE 0 END
                       ELSE
                           CASE WHEN MAX(CASE
                               WHEN dp.prioritas = 1 THEN NULLIF(TRIM(dp.kd_penyakit), '')
                           END) IS NOT NULL THEN 1 ELSE 0 END
                   END AS has_target_diagnosis,
                   COALESCE(MAX(cache.calc_jd_pemeriksaan), 0)
                       + COALESCE(MAX(cache.calc_jd_prosedur), 0)
                       + COALESCE(MAX(cache.calc_jd_dokter_anestesi), 0)
                       + COALESCE(MAX(cache.calc_jd_dokter_anak), 0)
                       + COALESCE(MAX(cache.calc_jd_hd), 0)
                       + COALESCE(MAX(cache.calc_jd_usg), 0)
                       + COALESCE(MAX(cache.calc_jd_rontgen), 0)
                       + COALESCE(MAX(cache.calc_jd_lab), 0)
                       + COALESCE(MAX(cache.calc_jd_pa), 0) AS total_jasa_dokter,
                   COALESCE(MAX(cache.calc_biaya_obat), 0) AS total_obat
            FROM reg_periksa rp
            LEFT JOIN ($eklaimSql) eklaim ON eklaim.no_rawat = rp.no_rawat
            LEFT JOIN bridging_sep bs ON bs.no_rawat = rp.no_rawat
            LEFT JOIN diagnosa_pasien dp ON dp.no_rawat = rp.no_rawat
            LEFT JOIN lap_keuangan_bpjs manual ON manual.no_rawat = rp.no_rawat
            LEFT JOIN lap_keuangan_bpjs_ralan cache ON cache.no_rawat = rp.no_rawat
            WHERE rp.tgl_registrasi BETWEEN '$startDate' AND '$endDate'
              AND rp.status_lanjut = 'Ralan'
              AND rp.kd_pj = 'BPJ'
              AND rp.stts <> 'Batal'
              AND NOT EXISTS (
                  SELECT 1 FROM kamar_inap ki WHERE ki.no_rawat = rp.no_rawat
              )
              $poliWhere
            GROUP BY rp.no_rawat
        ) report";
    $result = $mysqli->query($sql);
    if (!$result) {
        throw new RuntimeException('Ringkasan laporan tidak dapat dimuat: ' . $mysqli->error);
    }
    $summary = $result->fetch_assoc();
    return [
        'jumlah_kunjungan' => (int) $summary['jumlah_kunjungan'],
        'total_klaim' => (float) $summary['total_klaim'],
        'total_jasa_dokter' => (float) $summary['total_jasa_dokter'],
        'total_obat' => (float) $summary['total_obat'],
    ];
}

function aptd_keu_ralan_pick_latest_history(array $candidatePool, $currentNoRawat)
{
    if (empty($candidatePool)) {
        return null;
    }

    usort($candidatePool, function ($left, $right) {
        foreach (['visit_date', 'tariff_datetime', 'no_rawat'] as $field) {
            $comparison = strcmp((string) $right[$field], (string) $left[$field]);
            if ($comparison !== 0) {
                return $comparison;
            }
        }
        return 0;
    });

    foreach ($candidatePool as $candidate) {
        if ((string) $candidate['no_rawat'] === (string) $currentNoRawat || (float) $candidate['tariff'] <= 0) {
            continue;
        }
        return $candidate;
    }
    return null;
}

function aptd_keu_ralan_apply_claims(mysqli $mysqli, array &$rows)
{
    if (empty($rows)) {
        return;
    }

    $codesByPriority = [1 => [], 2 => []];
    foreach ($rows as $index => $row) {
        $priorityOne = trim((string) $row['diagnosis_priority_1']);
        $priorityTwo = trim((string) $row['diagnosis_priority_2']);
        $sepDiagnosis = trim((string) $row['diagnosis_sep']);
        $diagnosisCount = (int) $row['diagnosis_count'];

        if ($diagnosisCount === 0) {
            $targetCode = $sepDiagnosis;
            $targetPriority = 1;
            $targetSource = $targetCode !== '' ? 'SEP' : 'Belum Ada';
        } elseif ($priorityOne !== '' && strtoupper(substr($priorityOne, 0, 1)) === 'Z') {
            $targetCode = $priorityTwo;
            $targetPriority = 2;
            $targetSource = $targetCode !== '' ? 'Diagnosa Prioritas 2' : 'Belum Ada';
        } else {
            $targetCode = $priorityOne;
            $targetPriority = 1;
            $targetSource = $targetCode !== '' ? 'Diagnosa Prioritas 1' : 'Belum Ada';
        }

        $rows[$index]['target_diagnosis_code'] = $targetCode;
        $rows[$index]['target_diagnosis_priority'] = $targetPriority;
        $rows[$index]['target_diagnosis_source'] = $targetSource;
        $rows[$index]['claim_history'] = 0;
        $rows[$index]['claim_history_no_rawat'] = '';
        $rows[$index]['claim_history_match_source'] = '';
        $rows[$index]['has_hitung'] = !empty($row['calculated_at']) ? 1 : 0;

        if ($targetCode !== '') {
            $codesByPriority[$targetPriority][strtoupper($targetCode)] = $targetCode;
            if ($targetSource === 'SEP') {
                $codesByPriority[2][strtoupper($targetCode)] = $targetCode;
            }
        }
    }

    $inacbgSql = aptd_keu_ralan_inacbg_tariff_sql();
    $historyCandidates = [];
    foreach ($codesByPriority as $priority => $codes) {
        if (empty($codes)) {
            continue;
        }

        $quotedCodes = [];
        foreach ($codes as $code) {
            $quotedCodes[] = "'" . $mysqli->real_escape_string($code) . "'";
        }

        $sql = "
            SELECT dp.kd_penyakit AS diagnosis_code,
                   dp.prioritas AS diagnosis_priority,
                   dp.no_rawat,
                   rp.tgl_registrasi,
                   tariff.tariff,
                   tariff.tariff_datetime,
                   CASE WHEN EXISTS (
                       SELECT 1
                       FROM diagnosa_pasien history_primary
                       WHERE history_primary.no_rawat = dp.no_rawat
                         AND history_primary.prioritas = 1
                         AND history_primary.status = 'Ralan'
                         AND UPPER(TRIM(history_primary.kd_penyakit)) LIKE 'Z%'
                   ) THEN 1 ELSE 0 END AS priority_one_is_z
            FROM diagnosa_pasien dp
            INNER JOIN reg_periksa rp ON rp.no_rawat = dp.no_rawat
            INNER JOIN ($inacbgSql) tariff ON tariff.no_rawat = dp.no_rawat
            WHERE dp.prioritas = " . (int) $priority . "
              AND dp.status = 'Ralan'
              AND rp.status_lanjut = 'Ralan'
              AND NOT EXISTS (
                  SELECT 1
                  FROM kamar_inap history_ki
                  WHERE history_ki.no_rawat = dp.no_rawat
              )
              AND EXISTS (
                  SELECT 1
                  FROM bridging_sep history_sep
                  WHERE history_sep.no_rawat = dp.no_rawat
                    AND history_sep.jnspelayanan = '2'
              )
              AND dp.kd_penyakit IN (" . implode(',', $quotedCodes) . ")
            ORDER BY dp.kd_penyakit ASC,
                     rp.tgl_registrasi DESC,
                     tariff.tariff_datetime DESC,
                     dp.no_rawat DESC";

        $result = $mysqli->query($sql);
        if (!$result) {
            throw new RuntimeException('Riwayat klaim tidak dapat dimuat: ' . $mysqli->error);
        }

        while ($history = $result->fetch_assoc()) {
            $key = (int) $history['diagnosis_priority'] . '|' . strtoupper(trim((string) $history['diagnosis_code']));
            if (!isset($historyCandidates[$key])) {
                $historyCandidates[$key] = [];
            }
            $historyCandidates[$key][] = [
                'no_rawat' => (string) $history['no_rawat'],
                'tariff' => (float) $history['tariff'],
                'visit_date' => (string) $history['tgl_registrasi'],
                'tariff_datetime' => (string) $history['tariff_datetime'],
                'priority_one_is_z' => (int) $history['priority_one_is_z'],
            ];
        }
    }

    $unresolvedCodes = [];
    foreach ($rows as $index => $row) {
        $targetCode = $rows[$index]['target_diagnosis_code'];
        $targetPriority = $rows[$index]['target_diagnosis_priority'];
        $key = $targetPriority . '|' . strtoupper($targetCode);
        $candidatePool = $targetCode !== '' && !empty($historyCandidates[$key])
            ? $historyCandidates[$key]
            : [];

        if ($targetCode !== '' && $rows[$index]['target_diagnosis_source'] === 'SEP') {
            $priorityTwoKey = '2|' . strtoupper($targetCode);
            if (!empty($historyCandidates[$priorityTwoKey])) {
                foreach ($historyCandidates[$priorityTwoKey] as $priorityTwoCandidate) {
                    if ((int) $priorityTwoCandidate['priority_one_is_z'] === 1) {
                        $candidatePool[] = $priorityTwoCandidate;
                    }
                }
            }
        }

        $candidate = aptd_keu_ralan_pick_latest_history($candidatePool, $row['no_rawat']);
        if ($candidate !== null) {
            $rows[$index]['claim_history'] = $candidate['tariff'];
            $rows[$index]['claim_history_no_rawat'] = $candidate['no_rawat'];
            $rows[$index]['claim_history_match_source'] = 'Diagnosa Pasien';
        } elseif ($targetCode !== '') {
            $unresolvedCodes[strtoupper(trim((string) $targetCode))] = $targetCode;
        }
    }

    $sepHistoryCandidates = [];
    if (!empty($unresolvedCodes)) {
        $quotedCodes = [];
        foreach ($unresolvedCodes as $code) {
            $quotedCodes[] = "'" . $mysqli->real_escape_string(strtoupper(trim((string) $code))) . "'";
        }

        $sql = "
            SELECT DISTINCT
                   history_sep.diagawal AS diagnosis_code,
                   history_sep.no_rawat,
                   rp.tgl_registrasi,
                   tariff.tariff,
                   tariff.tariff_datetime
            FROM bridging_sep history_sep
            INNER JOIN reg_periksa rp ON rp.no_rawat = history_sep.no_rawat
            INNER JOIN ($inacbgSql) tariff ON tariff.no_rawat = history_sep.no_rawat
            WHERE history_sep.jnspelayanan = '2'
              AND TRIM(IFNULL(history_sep.diagawal, '')) <> ''
              AND history_sep.diagawal IN (" . implode(',', $quotedCodes) . ")
              AND rp.status_lanjut = 'Ralan'
              AND rp.stts <> 'Batal'
              AND NOT EXISTS (
                  SELECT 1
                  FROM kamar_inap history_ki
                  WHERE history_ki.no_rawat = history_sep.no_rawat
              )
            ORDER BY diagnosis_code ASC,
                     rp.tgl_registrasi DESC,
                     tariff.tariff_datetime DESC,
                     history_sep.no_rawat DESC";

        $result = $mysqli->query($sql);
        if (!$result) {
            throw new RuntimeException('Fallback riwayat klaim SEP tidak dapat dimuat: ' . $mysqli->error);
        }

        while ($history = $result->fetch_assoc()) {
            $key = strtoupper(trim((string) $history['diagnosis_code']));
            if (!isset($sepHistoryCandidates[$key])) {
                $sepHistoryCandidates[$key] = [];
            }
            $sepHistoryCandidates[$key][] = [
                'no_rawat' => (string) $history['no_rawat'],
                'tariff' => (float) $history['tariff'],
                'visit_date' => (string) $history['tgl_registrasi'],
                'tariff_datetime' => (string) $history['tariff_datetime'],
            ];
        }
    }

    foreach ($rows as $index => $row) {
        $targetCode = strtoupper(trim((string) $rows[$index]['target_diagnosis_code']));
        if ((float) $rows[$index]['claim_history'] > 0 || $targetCode === '' || empty($sepHistoryCandidates[$targetCode])) {
            continue;
        }

        $candidate = aptd_keu_ralan_pick_latest_history($sepHistoryCandidates[$targetCode], $row['no_rawat']);
        if ($candidate !== null) {
            $rows[$index]['claim_history'] = $candidate['tariff'];
            $rows[$index]['claim_history_no_rawat'] = $candidate['no_rawat'];
            $rows[$index]['claim_history_match_source'] = 'SEP';
        }
    }

    foreach ($rows as $index => $row) {
        $actual = (float) $row['claim_actual'];
        $history = (float) $rows[$index]['claim_history'];
        if ($actual > 0) {
            $rows[$index]['claim_used'] = $actual;
            $rows[$index]['claim_source'] = 'Aktual';
        } elseif ($history > 0) {
            $rows[$index]['claim_used'] = $history;
            $rows[$index]['claim_source'] = 'Riwayat';
        } else {
            $rows[$index]['claim_used'] = 0;
            $rows[$index]['claim_source'] = 'Belum Ada';
        }
    }
}

function aptd_keu_ralan_bhp_rate($specialistName)
{
    $normalized = strtolower(trim(preg_replace('/\s+/', ' ', (string) $specialistName)));
    if ($normalized === 'bedah' || $normalized === 'tht') {
        return 15000;
    }
    if ($normalized === 'gigi & mulut' || $normalized === 'konservasi & gigi estetik') {
        return 25000;
    }
    return 10000;
}

function aptd_keu_ralan_is_general_poli($poliName)
{
    $normalized = strtolower(trim(preg_replace('/\s+/', ' ', (string) $poliName)));
    return $normalized === 'poli umum';
}

function aptd_keu_ralan_calculate_doctor_fee($claimUsed, array $facts)
{
    $claimUsed = max(0, (float) $claimUsed);
    $specialistName = trim((string) (isset($facts['specialist_name']) ? $facts['specialist_name'] : ''));
    $bhpRate = aptd_keu_ralan_bhp_rate($specialistName);
    $nutritionCount = max(0, (int) (isset($facts['nutrition_count']) ? $facts['nutrition_count'] : 0));
    $nutritionTotal = max(0, (float) (isset($facts['nutrition_material_cost']) ? $facts['nutrition_material_cost'] : 0));
    $nutritionUnitPrice = $nutritionCount > 0 ? $nutritionTotal / $nutritionCount : 0;
    $medicineBaseCost = max(0, (float) (isset($facts['medicine_cost']) ? $facts['medicine_cost'] : 0));
    $medicineMarkup = round($medicineBaseCost * 0.15, 2);
    $medicineCostWithMarkup = round($medicineBaseCost + $medicineMarkup, 2);
    $result = [
        'jd_pemeriksaan' => 0,
        'jd_prosedur' => 0,
        'jd_dokter_anestesi' => 0,
        'jd_dokter_anak' => 0,
        'jd_hd' => 0,
        'jd_usg' => 0,
        'jd_rontgen' => 0,
        'jd_lab' => 0,
        'jd_pa' => 0,
        'bhp_lab_pk' => 0,
        'bhp_lab_pa' => 0,
        'bhp_rad_usg' => 0,
        'bhp_rontgen' => 0,
        'jasa_karyawan' => round($claimUsed * 0.15, 2),
        'biaya_bhp' => $bhpRate,
        'biaya_obat' => $medicineCostWithMarkup,
        'biaya_ekg' => round(max(0, (float) (isset($facts['ekg_cost']) ? $facts['ekg_cost'] : 0)), 2),
        'biaya_darah' => round(max(0, (float) (isset($facts['blood_cost']) ? $facts['blood_cost'] : 0)), 2),
        'makan_jumlah' => round($nutritionTotal, 2),
        'makan_harga' => round($nutritionUnitPrice, 2),
        'makan_kali' => $nutritionCount,
        'biaya_fototheraphy' => 0,
        'biaya_oksigen' => round(max(0, (float) (isset($facts['oxygen_cost']) ? $facts['oxygen_cost'] : 0)), 2),
        'biaya_spirometri' => round(max(0, (float) (isset($facts['spirometry_cost']) ? $facts['spirometry_cost'] : 0)), 2),
        'total' => 0,
        'margin' => 0,
        'keterangan_darah' => max(0, (int) (isset($facts['blood_count']) ? $facts['blood_count'] : 0)),
        'keterangan_albumin' => 0,
        'keterangan_tindakan' => trim((string) (isset($facts['operation_names']) ? $facts['operation_names'] : '')),
        'jd_rule' => 'Tidak Ada',
        'jd_anestesi_rule' => 'Tidak Ada Dokter Anestesi',
        'jd_anak_rule' => 'Bukan Tindakan Partus',
        'jd_usg_rule' => 'Tidak Ada Pemeriksaan USG',
        'jd_rontgen_rule' => 'Tidak Ada Pemeriksaan Rontgen',
        'jd_lab_rule' => 'Tidak Ada Pemeriksaan Lab',
        'jd_pa_rule' => 'Tidak Ada Pemeriksaan PA',
        'bhp_lab_pk_rule' => 'BHP Lab PK: Induk Rp 0 + Detail Rp 0',
        'bhp_lab_pa_rule' => 'BHP Lab PA: Rp 0',
        'bhp_rad_usg_rule' => 'BHP Radiologi USG: Rp 0',
        'bhp_rontgen_rule' => 'BHP Radiologi Non-USG: Rp 0',
        'biaya_bhp_rule' => 'Tarif BHP Spesialistik '
            . ($specialistName !== '' ? $specialistName : 'Lainnya')
            . ': Rp ' . aptd_currency($bhpRate),
        'biaya_ekg_rule' => 'BHP Tindakan EKG: Rp '
            . aptd_currency(isset($facts['ekg_cost']) ? $facts['ekg_cost'] : 0),
        'biaya_darah_rule' => 'Tarif Tindakan Harga Darah: Rp '
            . aptd_currency(isset($facts['blood_cost']) ? $facts['blood_cost'] : 0),
        'makan_rule' => 'Pemberian Nutrisi ' . $nutritionCount
            . ' kali: Total Material Rp ' . aptd_currency($nutritionTotal)
            . ', Harga Rata-rata Rp ' . aptd_currency($nutritionUnitPrice),
        'biaya_oksigen_rule' => 'Tambahan Biaya Oksigen: Rp '
            . aptd_currency(isset($facts['oxygen_cost']) ? $facts['oxygen_cost'] : 0),
        'biaya_spirometri_rule' => 'BHP Tindakan Spirometri: Rp '
            . aptd_currency(isset($facts['spirometry_cost']) ? $facts['spirometry_cost'] : 0),
    ];

    if (!empty($facts['has_dokter_anestesi'])) {
        $result['jd_dokter_anestesi'] = round($claimUsed * 0.08, 2);
        $result['jd_anestesi_rule'] = 'Dokter Anestesi 8%';
    }
    if (!empty($facts['operation_has_partus'])) {
        $result['jd_dokter_anak'] = 115000;
        $result['jd_anak_rule'] = 'Tindakan Partus (tarif tetap)';
    }
    if (!empty($facts['has_usg'])) {
        $result['jd_usg'] = 115000;
        $result['jd_usg_rule'] = 'Pemeriksaan USG (tarif tetap)';
    }
    if (!empty($facts['has_rontgen'])) {
        $result['jd_rontgen'] = 23100;
        $result['jd_rontgen_rule'] = 'Pemeriksaan Radiologi Non-USG (tarif tetap)';
    }
    $bhpRadUsg = max(0, (float) (isset($facts['bhp_rad_usg']) ? $facts['bhp_rad_usg'] : 0));
    $bhpRontgen = max(0, (float) (isset($facts['bhp_rontgen']) ? $facts['bhp_rontgen'] : 0));
    $result['bhp_rad_usg'] = round($bhpRadUsg, 2);
    $result['bhp_rontgen'] = round($bhpRontgen, 2);
    $result['bhp_rad_usg_rule'] = 'BHP Radiologi USG: Rp ' . aptd_currency($bhpRadUsg);
    $result['bhp_rontgen_rule'] = 'BHP Radiologi Non-USG: Rp ' . aptd_currency($bhpRontgen);
    $totalLabCost = max(
        0,
        (float) (isset($facts['lab_base_cost']) ? $facts['lab_base_cost'] : 0)
        + (float) (isset($facts['lab_detail_cost']) ? $facts['lab_detail_cost'] : 0)
    );
    if ($totalLabCost > 0) {
        $result['jd_lab'] = round($totalLabCost * 0.07, 2);
        $result['jd_lab_rule'] = '7% dari Total Biaya Lab Rp ' . aptd_currency($totalLabCost);
    }
    $paDoctorFee = max(0, (float) (isset($facts['pa_doctor_fee']) ? $facts['pa_doctor_fee'] : 0));
    if ($paDoctorFee > 0) {
        $result['jd_pa'] = round($paDoctorFee, 2);
        $result['jd_pa_rule'] = 'Tarif Tindakan Dokter PA';
    }
    $bhpLabPkBase = max(0, (float) (isset($facts['bhp_lab_pk_base']) ? $facts['bhp_lab_pk_base'] : 0));
    $bhpLabDetail = max(0, (float) (isset($facts['bhp_lab_detail']) ? $facts['bhp_lab_detail'] : 0));
    $bhpLabPa = max(0, (float) (isset($facts['bhp_lab_pa']) ? $facts['bhp_lab_pa'] : 0));
    $result['bhp_lab_pk'] = round($bhpLabPkBase + $bhpLabDetail, 2);
    $result['bhp_lab_pa'] = round($bhpLabPa, 2);
    $result['bhp_lab_pk_rule'] = 'BHP Lab PK: Induk Rp ' . aptd_currency($bhpLabPkBase)
        . ' + Detail Rp ' . aptd_currency($bhpLabDetail);
    $result['bhp_lab_pa_rule'] = 'BHP Lab PA: Rp ' . aptd_currency($bhpLabPa);

    // AR-153: dokter Poli Umum hanya menerima jasa pemeriksaan. Semua tindakan
    // tambahan tidak boleh memicu JD Prosedur, termasuk HD, operasi, atau injeksi.
    if (aptd_keu_ralan_is_general_poli(isset($facts['poli_name']) ? $facts['poli_name'] : '')) {
        $result['jd_pemeriksaan'] = round(
            max(0, (float) (isset($facts['general_poli_doctor_fee']) ? $facts['general_poli_doctor_fee'] : 0)),
            2
        );
        $result['jd_prosedur'] = 0;
        $result['jd_rule'] = !empty($facts['has_general_poli_exam'])
            ? 'Poli Umum: JD Pemeriksaan tarif_tindakandr; JD Prosedur Rp 0'
            : 'Poli Umum: Pemeriksaan Poli Umum tidak ditemukan; JD Prosedur Rp 0';
        return $result;
    }

    if (!empty($facts['has_hd'])) {
        $result['jd_hd'] = round($claimUsed * 0.21, 2);
        $result['jd_rule'] = 'Hemodialisa 21%';
        return $result;
    }

    if (!empty($facts['has_operation'])) {
        if (!empty($facts['operation_has_phaco'])) {
            $result['jd_prosedur'] = round($claimUsed * 0.24, 2);
            $result['jd_rule'] = 'Operasi Phaco 24%';
        } elseif (!empty($facts['operation_has_partus_kuret'])) {
            $result['jd_prosedur'] = round($claimUsed * 0.19, 2);
            $result['jd_rule'] = 'Operasi Partus/Kuret 19%';
        } else {
            // Belum ada persentase bisnis untuk paket operasi selain keyword di atas.
            $result['jd_rule'] = 'Operasi Lain (belum ada persentase)';
        }
        return $result;
    }

    if (!empty($facts['has_injection'])) {
        $result['jd_prosedur'] = 150000;
        $result['jd_rule'] = 'Poliklinik dengan Tindakan Injeksi (tarif tetap)';
        return $result;
    }

    if (!empty($facts['has_poli'])) {
        if (!empty($facts['has_other_treatment'])) {
            $result['jd_prosedur'] = round($claimUsed * 0.40, 2);
            $result['jd_rule'] = 'Poliklinik dengan Tindakan 40%';
        } else {
            $result['jd_pemeriksaan'] = round(max(0, (float) $facts['poli_doctor_fee']), 2);
            $result['jd_rule'] = !empty($facts['has_nebulizer'])
                ? 'Pemeriksaan Poliklinik (Nebulizer dikecualikan)'
                : 'Pemeriksaan Poliklinik';
        }
    }

    return $result;
}

function aptd_keu_ralan_expense_total(array $calculation)
{
    $expenseFields = [
        'jd_pemeriksaan',
        'jd_prosedur',
        'jd_dokter_anestesi',
        'jd_dokter_anak',
        'jd_hd',
        'jd_usg',
        'jd_rontgen',
        'jd_lab',
        'jd_pa',
        'jasa_karyawan',
        'biaya_bhp',
        'biaya_obat',
        'bhp_lab_pk',
        'bhp_lab_pa',
        'bhp_rad_usg',
        'bhp_rontgen',
        'biaya_ekg',
        'biaya_darah',
        'makan_jumlah',
        'biaya_fototheraphy',
        'biaya_oksigen',
        'biaya_spirometri',
    ];

    $total = 0;
    foreach ($expenseFields as $field) {
        $total += max(0, (float) (isset($calculation[$field]) ? $calculation[$field] : 0));
    }
    return round($total, 2);
}

function aptd_keu_ralan_apply_doctor_fees(mysqli $mysqli, array &$rows)
{
    if (empty($rows)) {
        return;
    }

    $factsByNoRawat = [];
    $noRawatList = [];
    foreach ($rows as $index => $row) {
        $noRawat = (string) $row['no_rawat'];
        $noRawatList[$noRawat] = $noRawat;
        $factsByNoRawat[$noRawat] = [
            'specialist_name' => isset($row['nm_sps']) ? (string) $row['nm_sps'] : '',
            'poli_name' => isset($row['nm_poli']) ? (string) $row['nm_poli'] : '',
            'has_hd' => 0,
            'has_poli' => 0,
            'has_general_poli_exam' => 0,
            'has_nebulizer' => 0,
            'has_other_treatment' => 0,
            'has_injection' => 0,
            'poli_doctor_fee' => 0,
            'general_poli_doctor_fee' => 0,
            'ekg_cost' => 0,
            'blood_cost' => 0,
            'blood_count' => 0,
            'spirometry_cost' => 0,
            'oxygen_cost' => 0,
            'nutrition_count' => 0,
            'nutrition_material_cost' => 0,
            'has_operation' => 0,
            'operation_has_partus_kuret' => 0,
            'operation_has_phaco' => 0,
            'has_dokter_anestesi' => 0,
            'operation_has_partus' => 0,
            'operation_names' => '',
            'has_usg' => 0,
            'has_rontgen' => 0,
            'bhp_rad_usg' => 0,
            'bhp_rontgen' => 0,
            'lab_base_cost' => 0,
            'lab_detail_cost' => 0,
            'pa_doctor_fee' => 0,
            'bhp_lab_pk_base' => 0,
            'bhp_lab_detail' => 0,
            'bhp_lab_pa' => 0,
            'medicine_cost' => 0,
        ];
        $rows[$index]['jd_pemeriksaan'] = 0;
        $rows[$index]['jd_prosedur'] = 0;
        $rows[$index]['jd_dokter_anestesi'] = 0;
        $rows[$index]['jd_dokter_anak'] = 0;
        $rows[$index]['jd_hd'] = 0;
        $rows[$index]['jd_usg'] = 0;
        $rows[$index]['jd_rontgen'] = 0;
        $rows[$index]['jd_lab'] = 0;
        $rows[$index]['jd_pa'] = 0;
        $rows[$index]['jd_rule'] = 'Tidak Ada';
        $rows[$index]['jd_anestesi_rule'] = 'Tidak Ada Dokter Anestesi';
        $rows[$index]['jd_anak_rule'] = 'Bukan Tindakan Partus';
        $rows[$index]['jd_usg_rule'] = 'Tidak Ada Pemeriksaan USG';
        $rows[$index]['jd_rontgen_rule'] = 'Tidak Ada Pemeriksaan Rontgen';
        $rows[$index]['jd_lab_rule'] = 'Tidak Ada Pemeriksaan Lab';
        $rows[$index]['jd_pa_rule'] = 'Tidak Ada Pemeriksaan PA';
        $rows[$index]['biaya_obat'] = 0;
        $rows[$index]['biaya_obat_rule'] = 'HPP detail_pemberian_obat Rp 0 + 15% Rp 0 = Rp 0';
    }

    foreach (array_chunk(array_values($noRawatList), 400) as $noRawatChunk) {
        $quotedNoRawat = [];
        foreach ($noRawatChunk as $noRawat) {
            $quotedNoRawat[] = "'" . $mysqli->real_escape_string($noRawat) . "'";
        }
        $inList = implode(',', $quotedNoRawat);

        $treatmentSql = "
            SELECT treatments.no_rawat,
                   MAX(CASE
                       WHEN LOWER(jp.nm_perawatan) LIKE '%hemodialisa%' THEN 1
                       ELSE 0
                   END) AS has_hd,
                   MAX(CASE
                       WHEN LOWER(jp.nm_perawatan) LIKE '%pemeriksaan poliklinik%'
                         OR LOWER(jp.nm_perawatan) LIKE '%pemeriksaan poli umum%'
                       THEN 1 ELSE 0
                   END) AS has_poli,
                   MAX(CASE
                       WHEN LOWER(jp.nm_perawatan) LIKE '%pemeriksaan poli umum%'
                       THEN 1 ELSE 0
                   END) AS has_general_poli_exam,
                   MAX(CASE
                       WHEN LOWER(jp.nm_perawatan) LIKE '%nebulizer%'
                       THEN 1 ELSE 0
                   END) AS has_nebulizer,
                   MAX(CASE
                       WHEN LOWER(jp.nm_perawatan) NOT LIKE '%pemeriksaan poliklinik%'
                        AND LOWER(jp.nm_perawatan) NOT LIKE '%pemeriksaan poli umum%'
                        AND LOWER(jp.nm_perawatan) NOT LIKE '%nebulizer%'
                       THEN 1 ELSE 0
                   END) AS has_other_treatment,
                   MAX(CASE
                       WHEN LOWER(jp.nm_perawatan) LIKE '%inj.%'
                       THEN 1 ELSE 0
                   END) AS has_injection,
                   SUM(CASE
                       WHEN LOWER(jp.nm_perawatan) LIKE '%pemeriksaan poliklinik%'
                         OR LOWER(jp.nm_perawatan) LIKE '%pemeriksaan poli umum%'
                       THEN COALESCE(jp.tarif_tindakandr, 0) ELSE 0
                   END) AS poli_doctor_fee,
                   SUM(CASE
                       WHEN LOWER(jp.nm_perawatan) LIKE '%pemeriksaan poli umum%'
                       THEN COALESCE(jp.tarif_tindakandr, 0) ELSE 0
                   END) AS general_poli_doctor_fee,
                   SUM(CASE
                       WHEN LOWER(jp.nm_perawatan) LIKE '%ekg%'
                       THEN COALESCE(jp.bhp, 0) ELSE 0
                   END) AS ekg_cost,
                   SUM(CASE
                       WHEN LOWER(jp.nm_perawatan) LIKE '%harga darah%'
                       THEN COALESCE(jp.total_byrdrpr, 0) ELSE 0
                   END) AS blood_cost,
                   SUM(CASE
                       WHEN LOWER(jp.nm_perawatan) LIKE '%harga darah%'
                       THEN 1 ELSE 0
                   END) AS blood_count,
                   SUM(CASE
                       WHEN LOWER(jp.nm_perawatan) LIKE '%spirometri%'
                       THEN COALESCE(jp.bhp, 0) ELSE 0
                   END) AS spirometry_cost,
                   SUM(CASE
                       WHEN LOWER(jp.nm_perawatan) LIKE '%pemberian nutrisi%'
                       THEN 1 ELSE 0
                   END) AS nutrition_count,
                   SUM(CASE
                       WHEN LOWER(jp.nm_perawatan) LIKE '%pemberian nutrisi%'
                       THEN COALESCE(jp.material, 0) ELSE 0
                   END) AS nutrition_material_cost
            FROM (
                SELECT no_rawat, kd_jenis_prw
                FROM rawat_jl_dr
                WHERE no_rawat IN ($inList)
                UNION ALL
                SELECT no_rawat, kd_jenis_prw
                FROM rawat_jl_pr
                WHERE no_rawat IN ($inList)
                UNION ALL
                SELECT no_rawat, kd_jenis_prw
                FROM rawat_jl_drpr
                WHERE no_rawat IN ($inList)
            ) treatments
            INNER JOIN jns_perawatan jp ON jp.kd_jenis_prw = treatments.kd_jenis_prw
            WHERE LOWER(jp.nm_perawatan) NOT LIKE '%administrasi%'
            GROUP BY treatments.no_rawat";

        $treatmentResult = $mysqli->query($treatmentSql);
        if (!$treatmentResult) {
            throw new RuntimeException('Data tindakan jasa dokter tidak dapat dimuat: ' . $mysqli->error);
        }
        while ($treatment = $treatmentResult->fetch_assoc()) {
            $noRawat = (string) $treatment['no_rawat'];
            if (!isset($factsByNoRawat[$noRawat])) {
                continue;
            }
            $factsByNoRawat[$noRawat]['has_hd'] = (int) $treatment['has_hd'];
            $factsByNoRawat[$noRawat]['has_poli'] = (int) $treatment['has_poli'];
            $factsByNoRawat[$noRawat]['has_general_poli_exam'] = (int) $treatment['has_general_poli_exam'];
            $factsByNoRawat[$noRawat]['has_nebulizer'] = (int) $treatment['has_nebulizer'];
            $factsByNoRawat[$noRawat]['has_other_treatment'] = (int) $treatment['has_other_treatment'];
            $factsByNoRawat[$noRawat]['has_injection'] = (int) $treatment['has_injection'];
            $factsByNoRawat[$noRawat]['poli_doctor_fee'] = (float) $treatment['poli_doctor_fee'];
            $factsByNoRawat[$noRawat]['general_poli_doctor_fee'] = (float) $treatment['general_poli_doctor_fee'];
            $factsByNoRawat[$noRawat]['ekg_cost'] = (float) $treatment['ekg_cost'];
            $factsByNoRawat[$noRawat]['blood_cost'] = (float) $treatment['blood_cost'];
            $factsByNoRawat[$noRawat]['blood_count'] = (int) $treatment['blood_count'];
            $factsByNoRawat[$noRawat]['spirometry_cost'] = (float) $treatment['spirometry_cost'];
            $factsByNoRawat[$noRawat]['nutrition_count'] = (int) $treatment['nutrition_count'];
            $factsByNoRawat[$noRawat]['nutrition_material_cost'] = (float) $treatment['nutrition_material_cost'];
        }

        $oxygenSql = "
            SELECT tb.no_rawat,
                   SUM(COALESCE(tb.besar_biaya, 0)) AS oxygen_cost
            FROM tambahan_biaya tb
            WHERE tb.no_rawat IN ($inList)
              AND LOWER(tb.nama_biaya) LIKE '%oksigen%'
            GROUP BY tb.no_rawat";

        $oxygenResult = $mysqli->query($oxygenSql);
        if (!$oxygenResult) {
            throw new RuntimeException('Data tambahan biaya oksigen tidak dapat dimuat: ' . $mysqli->error);
        }
        while ($oxygen = $oxygenResult->fetch_assoc()) {
            $noRawat = (string) $oxygen['no_rawat'];
            if (!isset($factsByNoRawat[$noRawat])) {
                continue;
            }
            $factsByNoRawat[$noRawat]['oxygen_cost'] = (float) $oxygen['oxygen_cost'];
        }

        $operationSql = "
            SELECT o.no_rawat,
                   1 AS has_operation,
                   MAX(CASE
                       WHEN LOWER(COALESCE(po.nm_perawatan, '')) LIKE '%partus%'
                         OR LOWER(COALESCE(po.nm_perawatan, '')) LIKE '%kuret%'
                       THEN 1 ELSE 0
                   END) AS operation_has_partus_kuret,
                   MAX(CASE
                       WHEN LOWER(COALESCE(po.nm_perawatan, '')) LIKE '%partus%'
                       THEN 1 ELSE 0
                   END) AS operation_has_partus,
                   MAX(CASE
                       WHEN LOWER(COALESCE(po.nm_perawatan, '')) LIKE '%phaco%'
                       THEN 1 ELSE 0
                   END) AS operation_has_phaco,
                   MAX(CASE
                       WHEN TRIM(COALESCE(o.dokter_anestesi, '')) NOT IN ('', '-')
                       THEN 1 ELSE 0
                   END) AS has_dokter_anestesi,
                   COALESCE(
                       GROUP_CONCAT(
                           DISTINCT NULLIF(TRIM(po.nm_perawatan), '')
                           ORDER BY po.nm_perawatan
                           SEPARATOR ', '
                       ),
                       ''
                   ) AS operation_names
            FROM operasi o
            LEFT JOIN paket_operasi po ON po.kode_paket = o.kode_paket
            WHERE o.no_rawat IN ($inList)
            GROUP BY o.no_rawat";

        $operationResult = $mysqli->query($operationSql);
        if (!$operationResult) {
            throw new RuntimeException('Data operasi jasa dokter tidak dapat dimuat: ' . $mysqli->error);
        }
        while ($operation = $operationResult->fetch_assoc()) {
            $noRawat = (string) $operation['no_rawat'];
            if (!isset($factsByNoRawat[$noRawat])) {
                continue;
            }
            $factsByNoRawat[$noRawat]['has_operation'] = 1;
            $factsByNoRawat[$noRawat]['operation_has_partus_kuret'] = (int) $operation['operation_has_partus_kuret'];
            $factsByNoRawat[$noRawat]['operation_has_partus'] = (int) $operation['operation_has_partus'];
            $factsByNoRawat[$noRawat]['operation_has_phaco'] = (int) $operation['operation_has_phaco'];
            $factsByNoRawat[$noRawat]['has_dokter_anestesi'] = (int) $operation['has_dokter_anestesi'];
            $factsByNoRawat[$noRawat]['operation_names'] = (string) $operation['operation_names'];
        }

        $radiologySql = "
            SELECT pr.no_rawat,
                   MAX(CASE
                       WHEN LOWER(jpr.nm_perawatan) LIKE '%usg%'
                       THEN 1 ELSE 0
                   END) AS has_usg,
                   MAX(CASE
                       WHEN TRIM(COALESCE(jpr.nm_perawatan, '')) <> ''
                        AND LOWER(jpr.nm_perawatan) NOT LIKE '%usg%'
                       THEN 1 ELSE 0
                   END) AS has_rontgen,
                   SUM(CASE
                       WHEN LOWER(jpr.nm_perawatan) LIKE '%usg%'
                       THEN COALESCE(jpr.bhp, 0) ELSE 0
                   END) AS bhp_rad_usg,
                   SUM(CASE
                       WHEN TRIM(COALESCE(jpr.nm_perawatan, '')) <> ''
                        AND LOWER(jpr.nm_perawatan) NOT LIKE '%usg%'
                       THEN COALESCE(jpr.bhp, 0) ELSE 0
                   END) AS bhp_rontgen
            FROM periksa_radiologi pr
            INNER JOIN jns_perawatan_radiologi jpr
                    ON jpr.kd_jenis_prw = pr.kd_jenis_prw
            WHERE pr.no_rawat IN ($inList)
            GROUP BY pr.no_rawat";

        $radiologyResult = $mysqli->query($radiologySql);
        if (!$radiologyResult) {
            throw new RuntimeException('Data radiologi jasa dokter tidak dapat dimuat: ' . $mysqli->error);
        }
        while ($radiology = $radiologyResult->fetch_assoc()) {
            $noRawat = (string) $radiology['no_rawat'];
            if (!isset($factsByNoRawat[$noRawat])) {
                continue;
            }
            $factsByNoRawat[$noRawat]['has_usg'] = (int) $radiology['has_usg'];
            $factsByNoRawat[$noRawat]['has_rontgen'] = (int) $radiology['has_rontgen'];
            $factsByNoRawat[$noRawat]['bhp_rad_usg'] = (float) $radiology['bhp_rad_usg'];
            $factsByNoRawat[$noRawat]['bhp_rontgen'] = (float) $radiology['bhp_rontgen'];
        }

        $laboratorySql = "
            SELECT lab.no_rawat,
                   lab.lab_base_cost,
                   COALESCE(detail.lab_detail_cost, 0) AS lab_detail_cost,
                   lab.pa_doctor_fee,
                   lab.bhp_lab_pk_base,
                   COALESCE(detail.bhp_lab_detail, 0) AS bhp_lab_detail,
                   lab.bhp_lab_pa
            FROM (
                SELECT pl.no_rawat,
                       SUM(COALESCE(pl.biaya, 0)) AS lab_base_cost,
                       SUM(CASE
                           WHEN pl.kategori = 'PA'
                           THEN COALESCE(pl.tarif_tindakan_dokter, 0)
                           ELSE 0
                       END) AS pa_doctor_fee,
                       SUM(CASE
                           WHEN pl.kategori = 'PK'
                           THEN COALESCE(pl.bhp, 0)
                           ELSE 0
                       END) AS bhp_lab_pk_base,
                       SUM(CASE
                           WHEN pl.kategori = 'PA'
                           THEN COALESCE(pl.bhp, 0)
                           ELSE 0
                       END) AS bhp_lab_pa
                FROM periksa_lab pl
                WHERE pl.no_rawat IN ($inList)
                GROUP BY pl.no_rawat
            ) lab
            LEFT JOIN (
                SELECT dpl.no_rawat,
                       SUM(COALESCE(dpl.biaya_item, 0)) AS lab_detail_cost,
                       SUM(COALESCE(dpl.bhp, 0)) AS bhp_lab_detail
                FROM detail_periksa_lab dpl
                WHERE dpl.no_rawat IN ($inList)
                GROUP BY dpl.no_rawat
            ) detail ON detail.no_rawat = lab.no_rawat";

        $laboratoryResult = $mysqli->query($laboratorySql);
        if (!$laboratoryResult) {
            throw new RuntimeException('Data laboratorium jasa dokter tidak dapat dimuat: ' . $mysqli->error);
        }
        while ($laboratory = $laboratoryResult->fetch_assoc()) {
            $noRawat = (string) $laboratory['no_rawat'];
            if (!isset($factsByNoRawat[$noRawat])) {
                continue;
            }
            $factsByNoRawat[$noRawat]['lab_base_cost'] = (float) $laboratory['lab_base_cost'];
            $factsByNoRawat[$noRawat]['lab_detail_cost'] = (float) $laboratory['lab_detail_cost'];
            $factsByNoRawat[$noRawat]['pa_doctor_fee'] = (float) $laboratory['pa_doctor_fee'];
            $factsByNoRawat[$noRawat]['bhp_lab_pk_base'] = (float) $laboratory['bhp_lab_pk_base'];
            $factsByNoRawat[$noRawat]['bhp_lab_detail'] = (float) $laboratory['bhp_lab_detail'];
            $factsByNoRawat[$noRawat]['bhp_lab_pa'] = (float) $laboratory['bhp_lab_pa'];
        }

        // detail_pemberian_obat sudah memuat obat non-racikan dan seluruh komponen
        // racikan yang benar-benar diberikan. Menambahkan resep_dokter_racikan_detail
        // akan menghitung komponen racikan untuk kedua kalinya (AR-152).
        $medicineSql = "
            SELECT dpo.no_rawat,
                   SUM(COALESCE(dpo.jml, 0) * COALESCE(db.dasar, 0)) AS medicine_cost
            FROM detail_pemberian_obat dpo
            INNER JOIN databarang db ON db.kode_brng = dpo.kode_brng
            WHERE dpo.no_rawat IN ($inList)
            GROUP BY dpo.no_rawat";

        $medicineResult = $mysqli->query($medicineSql);
        if (!$medicineResult) {
            throw new RuntimeException('Data biaya dasar obat tidak dapat dimuat: ' . $mysqli->error);
        }
        while ($medicine = $medicineResult->fetch_assoc()) {
            $noRawat = (string) $medicine['no_rawat'];
            if (!isset($factsByNoRawat[$noRawat])) {
                continue;
            }
            $factsByNoRawat[$noRawat]['medicine_cost'] = max(0, (float) $medicine['medicine_cost']);
        }
    }

    foreach ($rows as $index => $row) {
        $calculation = aptd_keu_ralan_calculate_doctor_fee(
            (float) $row['claim_used'],
            $factsByNoRawat[(string) $row['no_rawat']]
        );
        $calculation['total'] = aptd_keu_ralan_expense_total($calculation);
        $claimApotek = (float) (isset($row['klaim_apotek_online']) ? $row['klaim_apotek_online'] : 0);
        $calculation['margin'] = round(
            (float) $row['claim_used'] + $claimApotek - $calculation['total'],
            2
        );
        $calculation['total_rule'] = 'Total seluruh komponen biaya/HPP';
        $calculation['margin_rule'] = 'Klaim Digunakan Rp ' . aptd_currency($row['claim_used'])
            . ' + Klaim Apotek Online Rp ' . aptd_currency($claimApotek)
            . ' - TOTAL Rp ' . aptd_currency($calculation['total']);
        foreach ($calculation as $field => $value) {
            $rows[$index][$field] = $value;
        }
        $facts = $factsByNoRawat[(string) $row['no_rawat']];
        $medicineBaseCost = max(0, (float) $facts['medicine_cost']);
        $medicineMarkup = round($medicineBaseCost * 0.15, 2);
        $rows[$index]['biaya_obat_rule'] = 'HPP detail_pemberian_obat Rp '
            . aptd_currency($medicineBaseCost)
            . ' + 15% Rp ' . aptd_currency($medicineMarkup)
            . ' = Rp ' . aptd_currency($medicineBaseCost + $medicineMarkup);
    }
}

function aptd_keu_ralan_store_calculation(mysqli $mysqli, array $row, $username = '')
{
    aptd_keu_ralan_ensure_cache_schema($mysqli);

    $noRawat = trim((string) (isset($row['no_rawat']) ? $row['no_rawat'] : ''));
    if ($noRawat === '') {
        return ['success' => false, 'message' => 'No rawat tidak boleh kosong.'];
    }

    $now = date('Y-m-d H:i:s');
    $columns = [
        'no_rawat',
        'claim_used',
        'claim_source',
        'claim_actual_snapshot',
        'claim_history_snapshot',
        'claim_apotek_snapshot',
        'claim_history_no_rawat',
        'claim_diagnosis_code',
        'calculated_at',
        'calculated_by',
        'updated_at',
    ];
    $values = [
        $noRawat,
        isset($row['claim_used']) ? $row['claim_used'] : 0,
        isset($row['claim_source']) ? $row['claim_source'] : 'Belum Ada',
        isset($row['claim_actual']) ? $row['claim_actual'] : 0,
        isset($row['claim_history']) ? $row['claim_history'] : 0,
        isset($row['klaim_apotek_online']) ? $row['klaim_apotek_online'] : 0,
        isset($row['claim_history_no_rawat']) ? $row['claim_history_no_rawat'] : '',
        isset($row['target_diagnosis_code']) ? $row['target_diagnosis_code'] : '',
        $now,
        trim((string) $username),
        $now,
    ];

    foreach (aptd_keu_ralan_cache_fields() as $key => $field) {
        $columns[] = $field['column'];
        $values[] = isset($row[$key]) ? $row[$key] : $field['default'];
    }

    $placeholders = array_fill(0, count($columns), '?');
    $updates = [];
    foreach (array_slice($columns, 1) as $column) {
        $updates[] = $column . ' = VALUES(' . $column . ')';
    }
    $sql = 'INSERT INTO lap_keuangan_bpjs_ralan (' . implode(', ', $columns) . ')'
        . ' VALUES (' . implode(', ', $placeholders) . ')'
        . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Penyimpanan kalkulasi tidak dapat dipersiapkan.'];
    }

    $types = str_repeat('s', count($values));
    $bindParams = [$types];
    foreach ($values as $index => $value) {
        $values[$index] = (string) $value;
        $bindParams[] = &$values[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        error_log('Kalkulasi Ralan tidak dapat disimpan: ' . $error);
        return ['success' => false, 'message' => 'Kalkulasi tidak dapat disimpan. Silakan coba kembali.'];
    }
    $stmt->close();

    return ['success' => true, 'message' => 'Kalkulasi berhasil disimpan.'];
}

function aptd_keu_ralan_action_label(array $row)
{
    $calculationRow = aptd_keu_ralan_restore_calculation_claims($row);
    $historySelected = (float) $calculationRow['claim_selected_raw'] > 0
        && (string) $calculationRow['claim_selected_source'] === 'history_diagnose'
        && abs((float) $calculationRow['stored_claim_history'] - (float) $calculationRow['claim_history']) < 0.01
        && (string) $calculationRow['stored_claim_history_no_rawat'] === (string) $calculationRow['claim_history_no_rawat'];
    if (
        (float) $calculationRow['claim_actual'] <= 0
        && (float) $calculationRow['claim_history'] > 0
        && !$historySelected
    ) {
        return 'Pakai Riwayat';
    }
    if ((float) $calculationRow['claim_used'] <= 0) {
        return 'Tidak Aktif';
    }
    return !empty($calculationRow['has_hitung']) ? 'Hitung Ulang' : 'Hitung';
}

function aptd_keu_ralan_store_claim(mysqli $mysqli, array $row, $username = '', $markCalculated = false)
{
    $noRawat = trim((string) $row['no_rawat']);
    $actual = (float) $row['claim_actual'];
    $history = (float) $row['claim_history'];
    $used = (float) $row['claim_used'];
    $source = $actual > 0 ? 'eklaim_current' : ($history > 0 ? 'history_diagnose' : 'none');
    $historyNoRawat = (string) $row['claim_history_no_rawat'];
    $diagnosisCode = (string) $row['target_diagnosis_code'];
    $username = trim((string) $username);
    if ($noRawat === '' || $used <= 0) {
        return ['success' => false, 'message' => 'Klaim yang dapat digunakan belum tersedia.'];
    }

    $check = $mysqli->prepare('SELECT COUNT(*) AS total FROM lap_keuangan_bpjs WHERE no_rawat = ?');
    $check->bind_param('s', $noRawat);
    $check->execute();
    $exists = ((int) $check->get_result()->fetch_assoc()['total']) > 0;
    $check->close();
    $calculatedSql = $markCalculated ? ', calculated_at = NOW()' : '';

    if ($exists) {
        $stmt = $mysqli->prepare("
            UPDATE lap_keuangan_bpjs
            SET claim_selected = ?,
                claim_source = ?,
                claim_actual = ?,
                claim_history = ?,
                claim_history_no_rawat = ?,
                claim_history_diagnose_code = ?,
                claim_selected_at = NOW(),
                claim_selected_by = ?
                $calculatedSql
            WHERE no_rawat = ?");
        $stmt->bind_param('dsddssss', $used, $source, $actual, $history, $historyNoRawat, $diagnosisCode, $username, $noRawat);
    } else {
        $calculatedColumn = $markCalculated ? ', calculated_at' : '';
        $calculatedValue = $markCalculated ? ', NOW()' : '';
        $zero = 0;
        $stmt = $mysqli->prepare("
            INSERT INTO lap_keuangan_bpjs
                (no_rawat, jum_claim, jum_jdoperator, claim_selected, claim_source, claim_actual, claim_history, claim_history_no_rawat, claim_history_diagnose_code, claim_selected_at, claim_selected_by$calculatedColumn)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?$calculatedValue)");
        $stmt->bind_param('sdddsddsss', $noRawat, $zero, $zero, $used, $source, $actual, $history, $historyNoRawat, $diagnosisCode, $username);
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        error_log('Klaim Ralan tidak dapat disimpan: ' . $error);
        return ['success' => false, 'message' => 'Klaim tidak dapat disimpan. Silakan coba kembali atau hubungi administrator.'];
    }
    $stmt->close();
    return ['success' => true, 'message' => $markCalculated ? 'Data keuangan rawat jalan berhasil dihitung.' : 'Klaim riwayat berhasil dipakai. Silakan lanjutkan Hitung.'];
}

function aptd_keu_ralan_use_history_claim(mysqli $mysqli, $noRawat, $startDate, $endDate, $kdPoli = '', $username = '')
{
    $rows = aptd_keu_ralan_fetch_rows($mysqli, $startDate, $endDate, $kdPoli, trim((string) $noRawat));
    if (empty($rows)) {
        return ['success' => false, 'message' => 'Data pasien tidak ditemukan pada filter yang dipilih.'];
    }
    $row = aptd_keu_ralan_restore_calculation_claims($rows[0]);
    if ((float) $row['claim_actual'] > 0) {
        return ['success' => false, 'message' => 'Klaim aktual sudah tersedia dan otomatis menjadi klaim yang digunakan.'];
    }
    if ((float) $row['claim_history'] <= 0) {
        return ['success' => false, 'message' => 'Klaim riwayat belum tersedia untuk nomor rawat ini.'];
    }
    return aptd_keu_ralan_store_claim($mysqli, $row, $username, false);
}

function aptd_keu_ralan_calculate_claim(mysqli $mysqli, $noRawat, $startDate, $endDate, $kdPoli = '', $username = '')
{
    $rows = aptd_keu_ralan_fetch_rows(
        $mysqli,
        $startDate,
        $endDate,
        $kdPoli,
        trim((string) $noRawat),
        '',
        null,
        0,
        0,
        'ASC',
        true
    );
    if (empty($rows)) {
        return ['success' => false, 'message' => 'Data pasien tidak ditemukan pada filter yang dipilih.'];
    }
    if ((float) $rows[0]['claim_used'] <= 0) {
        return ['success' => false, 'message' => 'Klaim yang dapat digunakan belum tersedia.'];
    }

    $claimResult = aptd_keu_ralan_store_claim($mysqli, $rows[0], $username, false);
    if (!$claimResult['success']) {
        return $claimResult;
    }
    $cacheResult = aptd_keu_ralan_store_calculation($mysqli, $rows[0], $username);
    if (!$cacheResult['success']) {
        return $cacheResult;
    }

    return [
        'success' => true,
        'message' => 'Data keuangan rawat jalan berhasil dihitung dan disimpan untuk '
            . trim((string) $noRawat) . '.',
    ];
}

function aptd_keu_ralan_calculate_daily_batch(
    mysqli $mysqli,
    $visitDate,
    $offset = 0,
    $batchSize = 10,
    $username = ''
) {
    $visitDate = trim((string) $visitDate);
    $offset = max(0, (int) $offset);
    $batchSize = max(1, min(20, (int) $batchSize));
    $dateObject = DateTime::createFromFormat('Y-m-d', $visitDate);
    $dateErrors = DateTime::getLastErrors();
    if (
        !$dateObject
        || ($dateErrors !== false && ((int) $dateErrors['warning_count'] > 0 || (int) $dateErrors['error_count'] > 0))
        || $dateObject->format('Y-m-d') !== $visitDate
    ) {
        return [
            'success' => false,
            'message' => 'Tanggal kunjungan tidak valid.',
        ];
    }

    $total = aptd_keu_ralan_count_rows($mysqli, $visitDate, $visitDate);
    $rows = aptd_keu_ralan_fetch_rows(
        $mysqli,
        $visitDate,
        $visitDate,
        '',
        '',
        '',
        $batchSize,
        $offset,
        0,
        'ASC'
    );

    $eligibleRows = [];
    $skipped = 0;
    foreach ($rows as $row) {
        $row = aptd_keu_ralan_restore_calculation_claims($row);
        if ((float) $row['claim_used'] > 0) {
            $eligibleRows[] = $row;
        } else {
            $skipped++;
        }
    }
    if (!empty($eligibleRows)) {
        aptd_keu_ralan_apply_doctor_fees($mysqli, $eligibleRows);
    }

    $processed = 0;
    $failed = 0;
    $failures = [];
    foreach ($eligibleRows as $row) {
        $claimResult = aptd_keu_ralan_store_claim($mysqli, $row, $username, false);
        if (!$claimResult['success']) {
            $failed++;
            $failures[] = $row['no_rawat'] . ': ' . $claimResult['message'];
            continue;
        }
        $cacheResult = aptd_keu_ralan_store_calculation($mysqli, $row, $username);
        if (!$cacheResult['success']) {
            $failed++;
            $failures[] = $row['no_rawat'] . ': ' . $cacheResult['message'];
            continue;
        }
        $processed++;
    }

    $readCount = count($rows);
    $nextOffset = $offset + $readCount;
    $done = $readCount === 0 || $nextOffset >= $total;

    return [
        'success' => true,
        'message' => $done ? 'Kalkulasi harian selesai.' : 'Batch kalkulasi berhasil.',
        'visit_date' => $visitDate,
        'total' => $total,
        'offset' => $offset,
        'read' => $readCount,
        'processed' => $processed,
        'skipped' => $skipped,
        'failed' => $failed,
        'failures' => $failures,
        'next_offset' => $nextOffset,
        'done' => $done,
    ];
}

function aptd_keu_ralan_summary(array $rows)
{
    $poli = [];
    $totalTagihan = 0;
    $sudahSep = 0;
    $totalKlaim = 0;
    $totalJasaDokter = 0;
    $totalObat = 0;
    foreach ($rows as $row) {
        $poli[$row['kd_poli']] = true;
        $totalTagihan += (float) $row['total_tagihan'];
        $totalKlaim += isset($row['claim_used']) ? (float) $row['claim_used'] : 0;
        foreach ([
            'jd_pemeriksaan',
            'jd_prosedur',
            'jd_dokter_anestesi',
            'jd_dokter_anak',
            'jd_hd',
            'jd_usg',
            'jd_rontgen',
            'jd_lab',
            'jd_pa',
        ] as $doctorFeeField) {
            $totalJasaDokter += isset($row[$doctorFeeField]) ? (float) $row[$doctorFeeField] : 0;
        }
        $totalObat += isset($row['biaya_obat']) ? (float) $row['biaya_obat'] : 0;
        if (trim((string) $row['no_sep']) !== '') {
            $sudahSep++;
        }
    }

    $jumlah = count($rows);
    return [
        'jumlah_kunjungan' => $jumlah,
        'jumlah_poli' => count($poli),
        'sudah_sep' => $sudahSep,
        'total_tagihan' => $totalTagihan,
        'rata_tagihan' => $jumlah > 0 ? $totalTagihan / $jumlah : 0,
        'total_klaim' => $totalKlaim,
        'total_jasa_dokter' => $totalJasaDokter,
        'total_obat' => $totalObat,
    ];
}

function aptd_keu_ralan_export_url($startDate, $endDate, $kdPoli = '')
{
    return 'page/t_non_klinis/keuangan/export_laporan_keuangan_ralan.php?'
        . aptd_keu_ralan_filter_query($startDate, $endDate, $kdPoli);
}

function aptd_keu_ralan_filter_query($startDate, $endDate, $kdPoli = '')
{
    return http_build_query([
        'start_date' => $startDate,
        'end_date' => $endDate,
        'kd_poli' => $kdPoli,
    ]);
}

function aptd_keu_ralan_xml($value)
{
    $value = (string) $value;
    if (!preg_match('//u', $value)) {
        $cleanValue = function_exists('iconv') ? iconv('UTF-8', 'UTF-8//IGNORE', $value) : false;
        $value = $cleanValue !== false ? $cleanValue : preg_replace('/[\x80-\xFF]/', '', $value);
    }
    $value = preg_replace(
        '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
        '',
        $value
    );

    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

function aptd_keu_ralan_excel_column($index)
{
    $name = '';
    $index++;
    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = (int) floor($index / 26);
    }
    return $name;
}

function aptd_keu_ralan_xlsx_cell($coordinate, $value, $type = 'text', $style = 0)
{
    $styleAttribute = $style > 0 ? ' s="' . (int) $style . '"' : '';
    if ($type === 'number') {
        $number = is_numeric($value) ? (float) $value : 0;
        if (is_nan($number) || is_infinite($number)) {
            $number = 0;
        }
        return '<c r="' . $coordinate . '"' . $styleAttribute . '><v>' . $number . '</v></c>';
    }

    return '<c r="' . $coordinate . '" t="inlineStr"' . $styleAttribute . '><is><t xml:space="preserve">'
        . aptd_keu_ralan_xml($value) . '</t></is></c>';
}

function aptd_keu_ralan_zip(array $files)
{
    $body = '';
    $central = '';
    $offset = 0;
    $dosTime = 0;
    $dosDate = 33;

    foreach ($files as $name => $data) {
        $name = str_replace('\\', '/', $name);
        $size = strlen($data);
        $crc = crc32($data);
        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, strlen($name), 0)
            . $name . $data;
        $body .= $local;

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            20,
            20,
            0,
            0,
            $dosTime,
            $dosDate,
            $crc,
            $size,
            $size,
            strlen($name),
            0,
            0,
            0,
            0,
            0,
            $offset
        ) . $name;
        $offset += strlen($local);
    }

    $count = count($files);
    return $body . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), strlen($body), 0);
}

function aptd_keu_ralan_build_xlsx(array $rows, $startDate, $endDate, $poliLabel)
{
    $headers = [
        'Tanggal Kunjungan', 'Nomor Rawat', 'No. RM', 'Nama Pasien',
        'Dokter Poliklinik', 'No. SEP', 'Poliklinik', 'Spesialistik',
        'Status Periksa', 'Status Bayar', 'Jenis Bayar', 'Klaim Riwayat',
        'Klaim Aktual', 'Klaim Digunakan', 'Klaim Apotek Online', 'JD Pemeriksaan',
        'JD dgn Prosedur atau Tindakan', 'Dokter Anestesi', 'Dokter Anak', 'JD HD',
        'JD USG', 'JD Rontgen', 'JD Lab', 'JD PA',
        'LAB PK', 'LAB PA', 'Rad USG', 'Rontgen',
        'JK', 'BHP', 'Obat', 'EKG', 'Darah',
        'Jumlah', 'Harga', 'Kali',
        'Fototheraphy', 'Oksigen', 'Spirometri', 'TOTAL', 'MARGIN',
        'Darah', 'Albumin', 'Tindakan',
        'Sumber', 'Aksi'
    ];
    $headerGroups = [
        ['start' => 15, 'end' => 23, 'label' => 'Jasa Dokter'],
        ['start' => 24, 'end' => 27, 'label' => 'BHP Penunjang'],
        ['start' => 33, 'end' => 35, 'label' => 'Makan'],
        ['start' => 41, 'end' => 43, 'label' => 'Keterangan'],
    ];

    $sheetRows = [];
    $sheetRows[] = '<row r="1" ht="24"><c r="A1" t="inlineStr" s="3"><is><t>LAPORAN KEUANGAN RAWAT JALAN (BPJS)</t></is></c></row>';
    $sheetRows[] = '<row r="2"><c r="A2" t="inlineStr"><is><t xml:space="preserve">Periode: '
        . aptd_keu_ralan_xml($startDate . ' s.d. ' . $endDate . ' | Poliklinik: ' . $poliLabel)
        . '</t></is></c></row>';

    $groupByColumn = [];
    foreach ($headerGroups as $groupIndex => $headerGroup) {
        for ($column = $headerGroup['start']; $column <= $headerGroup['end']; $column++) {
            $groupByColumn[$column] = $groupIndex;
        }
    }

    $topHeaderCells = '';
    $childHeaderCells = '';
    $mergeRanges = ['A1:AT1', 'A2:AT2'];
    foreach ($headers as $index => $header) {
        $columnName = aptd_keu_ralan_excel_column($index);
        if (!isset($groupByColumn[$index])) {
            $topHeaderCells .= aptd_keu_ralan_xlsx_cell($columnName . '4', $header, 'text', 1);
            $mergeRanges[] = $columnName . '4:' . $columnName . '5';
            continue;
        }

        $headerGroup = $headerGroups[$groupByColumn[$index]];
        if ($index === $headerGroup['start']) {
            $endColumnName = aptd_keu_ralan_excel_column($headerGroup['end']);
            $topHeaderCells .= aptd_keu_ralan_xlsx_cell($columnName . '4', $headerGroup['label'], 'text', 1);
            $mergeRanges[] = $columnName . '4:' . $endColumnName . '4';
        }
        $childHeaderCells .= aptd_keu_ralan_xlsx_cell($columnName . '5', $header, 'text', 1);
    }
    $sheetRows[] = '<row r="4" ht="30">' . $topHeaderCells . '</row>';
    $sheetRows[] = '<row r="5" ht="34">' . $childHeaderCells . '</row>';

    $excelRow = 6;
    $expenseGrandTotal = 0;
    $marginGrandTotal = 0;
    foreach ($rows as $row) {
        $expenseGrandTotal += (float) $row['total'];
        $marginGrandTotal += (float) $row['margin'];
        $values = [
            ['value' => $row['tgl_registrasi'], 'type' => 'text', 'style' => 0],
            ['value' => $row['no_rawat'], 'type' => 'text', 'style' => 0],
            ['value' => $row['no_rkm_medis'], 'type' => 'text', 'style' => 0],
            ['value' => $row['nm_pasien'], 'type' => 'text', 'style' => 0],
            ['value' => $row['nm_dokter'], 'type' => 'text', 'style' => 0],
            ['value' => $row['no_sep'] !== '' ? $row['no_sep'] : '-', 'type' => 'text', 'style' => 0],
            ['value' => $row['nm_poli'], 'type' => 'text', 'style' => 0],
            ['value' => $row['nm_sps'] !== '' ? $row['nm_sps'] : '-', 'type' => 'text', 'style' => 0],
            ['value' => $row['status_periksa'], 'type' => 'text', 'style' => 0],
            ['value' => $row['status_bayar'], 'type' => 'text', 'style' => 0],
            ['value' => $row['jenis_bayar'], 'type' => 'text', 'style' => 0],
            ['value' => $row['claim_history'], 'type' => 'number', 'style' => 2],
            ['value' => $row['claim_actual'], 'type' => 'number', 'style' => 2],
            ['value' => $row['claim_used'], 'type' => 'number', 'style' => 2],
            ['value' => $row['klaim_apotek_online'], 'type' => 'number', 'style' => 2],
            ['value' => $row['jd_pemeriksaan'], 'type' => 'number', 'style' => 2],
            ['value' => $row['jd_prosedur'], 'type' => 'number', 'style' => 2],
            ['value' => $row['jd_dokter_anestesi'], 'type' => 'number', 'style' => 2],
            ['value' => $row['jd_dokter_anak'], 'type' => 'number', 'style' => 2],
            ['value' => $row['jd_hd'], 'type' => 'number', 'style' => 2],
            ['value' => $row['jd_usg'], 'type' => 'number', 'style' => 2],
            ['value' => $row['jd_rontgen'], 'type' => 'number', 'style' => 2],
            ['value' => $row['jd_lab'], 'type' => 'number', 'style' => 2],
            ['value' => $row['jd_pa'], 'type' => 'number', 'style' => 2],
            ['value' => $row['bhp_lab_pk'], 'type' => 'number', 'style' => 2],
            ['value' => $row['bhp_lab_pa'], 'type' => 'number', 'style' => 2],
            ['value' => $row['bhp_rad_usg'], 'type' => 'number', 'style' => 2],
            ['value' => $row['bhp_rontgen'], 'type' => 'number', 'style' => 2],
            ['value' => $row['jasa_karyawan'], 'type' => 'number', 'style' => 2],
            ['value' => $row['biaya_bhp'], 'type' => 'number', 'style' => 2],
            ['value' => $row['biaya_obat'], 'type' => 'number', 'style' => 2],
            ['value' => $row['biaya_ekg'], 'type' => 'number', 'style' => 2],
            ['value' => $row['biaya_darah'], 'type' => 'number', 'style' => 2],
            ['value' => $row['makan_jumlah'], 'type' => 'number', 'style' => 2],
            ['value' => $row['makan_harga'], 'type' => 'number', 'style' => 2],
            ['value' => $row['makan_kali'], 'type' => 'number', 'style' => 2],
            ['value' => $row['biaya_fototheraphy'], 'type' => 'number', 'style' => 2],
            ['value' => $row['biaya_oksigen'], 'type' => 'number', 'style' => 2],
            ['value' => $row['biaya_spirometri'], 'type' => 'number', 'style' => 2],
            ['value' => $row['total'], 'type' => 'number', 'style' => 2],
            ['value' => $row['margin'], 'type' => 'number', 'style' => (float) $row['margin'] < 0 ? 4 : 2],
            ['value' => $row['keterangan_darah'], 'type' => 'number', 'style' => 0],
            ['value' => $row['keterangan_albumin'], 'type' => 'number', 'style' => 0],
            [
                'value' => trim((string) $row['keterangan_tindakan']) !== ''
                    ? $row['keterangan_tindakan']
                    : '-',
                'type' => 'text',
                'style' => 0,
            ],
            ['value' => $row['claim_source'], 'type' => 'text', 'style' => 0],
            ['value' => aptd_keu_ralan_action_label($row), 'type' => 'text', 'style' => 0],
        ];

        $cells = '';
        foreach ($values as $column => $cell) {
            $cells .= aptd_keu_ralan_xlsx_cell(
                aptd_keu_ralan_excel_column($column) . $excelRow,
                $cell['value'],
                $cell['type'],
                $cell['style']
            );
        }
        $sheetRows[] = '<row r="' . $excelRow . '">' . $cells . '</row>';
        $excelRow++;
    }

    $lastDataRow = max(5, $excelRow - 1);
    $lastSheetRow = $lastDataRow;
    if (!empty($rows)) {
        $footerRow = $excelRow;
        $footerCells = aptd_keu_ralan_xlsx_cell('AM' . $footerRow, 'TOTAL LAPORAN', 'text', 3)
            . aptd_keu_ralan_xlsx_cell('AN' . $footerRow, round($expenseGrandTotal, 2), 'number', 2)
            . aptd_keu_ralan_xlsx_cell(
                'AO' . $footerRow,
                round($marginGrandTotal, 2),
                'number',
                $marginGrandTotal < 0 ? 4 : 2
            );
        $sheetRows[] = '<row r="' . $footerRow . '" ht="22">' . $footerCells . '</row>';
        $lastSheetRow = $footerRow;
    }
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="A1:AT' . $lastSheetRow . '"/>'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<cols><col min="1" max="1" width="18" customWidth="1"/><col min="2" max="3" width="22" customWidth="1"/>'
        . '<col min="4" max="5" width="30" customWidth="1"/><col min="6" max="6" width="24" customWidth="1"/>'
        . '<col min="7" max="7" width="28" customWidth="1"/><col min="8" max="8" width="22" customWidth="1"/>'
        . '<col min="9" max="11" width="18" customWidth="1"/><col min="12" max="15" width="20" customWidth="1"/>'
        . '<col min="16" max="24" width="22" customWidth="1"/><col min="25" max="28" width="18" customWidth="1"/>'
        . '<col min="29" max="33" width="20" customWidth="1"/><col min="34" max="43" width="18" customWidth="1"/>'
        . '<col min="44" max="44" width="30" customWidth="1"/><col min="45" max="46" width="18" customWidth="1"/></cols>'
        . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
        . '<mergeCells count="' . count($mergeRanges) . '"><mergeCell ref="'
        . implode('"/><mergeCell ref="', $mergeRanges)
        . '"/></mergeCells>'
        . '</worksheet>';

    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Keuangan Ralan BPJS" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0"/></numFmts><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFDC2626"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center"/></xf><xf numFmtId="164" fontId="2" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
        'xl/worksheets/sheet1.xml' => $sheetXml,
    ];

    return aptd_keu_ralan_zip($files);
}
