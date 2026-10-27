<?php
// Regresi offline: tidak memuat config/koneksi.php dan tidak membuka koneksi DB.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../main_app/page/t_non_klinis/keuangan/laporan_keuangan_ralan_schema.php';

class RalanSchemaFixtureResult extends mysqli_result
{
    private $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function fetch_assoc(): array|null|false
    {
        return array_shift($this->rows);
    }

    public function free(): void
    {
    }
}

class RalanSchemaFixtureConnection extends mysqli
{
    public $tables;
    public $queries = [];

    public function __construct(array $tables)
    {
        // Sengaja tidak memanggil parent: tidak ada koneksi jaringan.
        $this->tables = $tables;
    }

    public function query(string $query, int $result_mode = MYSQLI_STORE_RESULT): mysqli_result|bool
    {
        $this->queries[] = $query;
        if (!preg_match('/^SHOW COLUMNS FROM `([a-z_]+)`$/D', $query, $match)) {
            throw new LogicException('Fixture menolak SQL di luar pemeriksaan metadata: ' . $query);
        }
        $table = $match[1];
        if (!isset($this->tables[$table])) {
            throw new mysqli_sql_exception('Simulated missing table', 1146);
        }
        if ($this->tables[$table] instanceof Throwable) {
            throw $this->tables[$table];
        }
        return new RalanSchemaFixtureResult($this->tables[$table]);
    }
}

$checks = 0;
function ralanSchemaCheck($condition, $message)
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function ralanSchemaExpectFailure(callable $callback)
{
    try {
        $callback();
    } catch (AptdKeuRalanSchemaException $exception) {
        ralanSchemaCheck(strpos($exception->getMessage(), 'Hubungi administrator') !== false, 'Petunjuk administrator tidak tersedia.');
        ralanSchemaCheck(strpos($exception->getMessage(), 'SHOW') === false, 'SQL internal bocor ke pesan pengguna.');
        return $exception;
    }
    throw new RuntimeException('Struktur belum siap tetapi proses tidak dihentikan.');
}

$tables = [];
foreach (aptd_keu_ralan_schema_definitions() as $table => $definition) {
    foreach ($definition['columns'] as $column => $sql) {
        $tables[$table][] = [
            'Field' => $column,
            'Key' => in_array($column, $definition['primary_key'], true) ? 'PRI' : '',
        ];
    }
}

$complete = new RalanSchemaFixtureConnection($tables);
aptd_keu_ralan_ensure_cache_schema($complete);
aptd_keu_ralan_ensure_apotek_manual_schema($complete);
aptd_keu_ralan_ensure_cache_schema($complete);
aptd_keu_ralan_ensure_apotek_manual_schema($complete);
ralanSchemaCheck(count($complete->queries) === 2, 'Metadata dibaca ulang pada koneksi yang sudah tervalidasi.');

$missingTable = new RalanSchemaFixtureConnection([]);
ralanSchemaExpectFailure(function () use ($missingTable) { aptd_keu_ralan_ensure_cache_schema($missingTable); });
ralanSchemaCheck(count($missingTable->queries) === 1, 'Ada SQL tambahan setelah tabel hilang.');

$missingColumns = $tables;
$missingColumns['lap_keuangan_bpjs_ralan'] = array_values(array_filter(
    $missingColumns['lap_keuangan_bpjs_ralan'],
    function ($column) { return $column['Field'] !== 'calc_margin'; }
));
$incomplete = new RalanSchemaFixtureConnection($missingColumns);
$schemaException = ralanSchemaExpectFailure(function () use ($incomplete) { aptd_keu_ralan_ensure_cache_schema($incomplete); });
$incomplete->tables = $tables;
aptd_keu_ralan_ensure_cache_schema($incomplete);
ralanSchemaCheck(count($incomplete->queries) === 2, 'Kegagalan validasi tersimpan sebagai sukses.');

$wrongKeys = $tables;
$wrongKeys['klaim_apotek_online_manual'][0]['Key'] = '';
$wrongKeyConnection = new RalanSchemaFixtureConnection($wrongKeys);
ralanSchemaExpectFailure(function () use ($wrongKeyConnection) { aptd_keu_ralan_ensure_apotek_manual_schema($wrongKeyConnection); });

// Koneksi kedua tetap harus diperiksa, walaupun koneksi pertama sudah valid.
$missingApotek = new RalanSchemaFixtureConnection(['lap_keuangan_bpjs_ralan' => $tables['lap_keuangan_bpjs_ralan']]);
ralanSchemaExpectFailure(function () use ($missingApotek) { aptd_keu_ralan_ensure_apotek_manual_schema($missingApotek); });

$permissionError = new RalanSchemaFixtureConnection(['lap_keuangan_bpjs_ralan' => new mysqli_sql_exception('Simulated permission denied', 1142)]);
try {
    aptd_keu_ralan_ensure_cache_schema($permissionError);
    throw new RuntimeException('Kesalahan izin ditelan.');
} catch (mysqli_sql_exception $exception) {
    ralanSchemaCheck($exception->getCode() === 1142, 'Kesalahan izin salah diklasifikasi sebagai tabel hilang.');
}

ralanSchemaCheck(aptd_keu_ralan_schema_user_message($schemaException, 'Fallback') === $schemaException->getMessage(), 'Pesan struktur tidak diteruskan.');
ralanSchemaCheck(aptd_keu_ralan_schema_user_message(new RuntimeException('Internal detail'), 'Fallback') === 'Fallback', 'Detail kesalahan umum bocor.');
try {
    aptd_keu_ralan_schema_create_sql('reg_periksa');
    throw new RuntimeException('Tabel SIMRS lain diterima sebagai target pemeliharaan.');
} catch (InvalidArgumentException $exception) {
    ralanSchemaCheck(true, 'Target terbatasi.');
}

$plan = aptd_keu_ralan_schema_plan(new RalanSchemaFixtureConnection($tables));
ralanSchemaCheck($plan === ['statements' => [], 'blockers' => []], 'Struktur lengkap masih menghasilkan perubahan.');
$newTables = new RalanSchemaFixtureConnection([]);
$plan = aptd_keu_ralan_schema_plan($newTables);
ralanSchemaCheck(count($plan['statements']) === 2 && empty($plan['blockers']), 'Rencana dua tabel baru salah.');
ralanSchemaCheck(strpos($plan['statements'][0], 'CREATE TABLE IF NOT EXISTS `lap_keuangan_bpjs_ralan`') === 0, 'Rencana tabel cache salah.');
ralanSchemaCheck(count($newTables->queries) === 2, 'Pembuatan rencana mengeksekusi DDL.');
$plan = aptd_keu_ralan_schema_plan(new RalanSchemaFixtureConnection($missingColumns));
ralanSchemaCheck(count($plan['statements']) === 1, 'Rencana kolom hilang mengubah tabel lain.');
ralanSchemaCheck($plan['statements'][0] === 'ALTER TABLE `lap_keuangan_bpjs_ralan` ADD COLUMN `calc_margin` DECIMAL(16,2) NOT NULL DEFAULT 0', 'Rencana kolom hilang bukan ADD COLUMN yang diharapkan.');
$plan = aptd_keu_ralan_schema_plan(new RalanSchemaFixtureConnection($wrongKeys));
ralanSchemaCheck(!empty($plan['blockers']) && empty($plan['statements']), 'Primary key salah diperbaiki otomatis.');

// Memuat hanya definisi fungsi helper, melewati require koneksi pada awal file.
$helperSource = file_get_contents(__DIR__ . '/../main_app/page/t_non_klinis/keuangan/laporan_keuangan_ralan_helper.php');
$functionsStart = strpos($helperSource, 'function aptd_keu_ralan_is_valid_date(');
ralanSchemaCheck($functionsStart !== false, 'Definisi fungsi helper tidak ditemukan.');
eval(substr($helperSource, $functionsStart));

foreach (['report', 'summary', 'save', 'daily'] as $operation) {
    $connection = new RalanSchemaFixtureConnection([]);
    ralanSchemaExpectFailure(function () use ($operation, $connection) {
        if ($operation === 'report') {
            aptd_keu_ralan_fetch_rows($connection, '2026-10-12', '2026-10-12');
        } elseif ($operation === 'summary') {
            aptd_keu_ralan_fetch_summary($connection, '2026-10-12', '2026-10-12');
        } elseif ($operation === 'daily') {
            aptd_keu_ralan_calculate_daily_batch($connection, '2026-10-12');
        } else {
            aptd_keu_ralan_store_calculation($connection, ['no_rawat' => 'OFFLINE_TEST'], 'OFFLINE_TEST');
        }
    });
    ralanSchemaCheck(count($connection->queries) === 1, $operation . ': ada SQL setelah struktur tidak siap.');
}

$pageSource = file_get_contents(__DIR__ . '/../main_app/page/t_non_klinis/keuangan/laporan_keuangan_ralan.php');
$actionStart = strpos($pageSource, 'if ($isReportRowAction) {');
$actionEnd = strpos($pageSource, 'if (isset($_SESSION[', $actionStart);
ralanSchemaCheck($actionStart !== false && $actionEnd !== false, 'Blok aksi halaman tidak ditemukan.');
$actionCode = substr($pageSource, $actionStart, $actionEnd - $actionStart);
foreach (['use_history_claim', 'calculate_keu_row'] as $action) {
    $mysqli = new RalanSchemaFixtureConnection([]);
    $_POST = [$action => '1'];
    $_SESSION = ['username' => 'OFFLINE_TEST'];
    $isReportRowAction = true;
    $filterValid = true;
    $canCalculateKeuangan = true;
    $startDate = $endDate = '2026-10-12';
    $kdPoli = 'U0073';
    $reportPage = 4;
    ob_start();
    eval($actionCode);
    $html = ob_get_clean();
    ralanSchemaCheck($_SESSION['keu_ralan_flash']['success'] === false, $action . ': kegagalan menjadi sukses.');
    ralanSchemaCheck(strpos($_SESSION['keu_ralan_flash']['message'], 'Hubungi administrator') !== false, $action . ': petunjuk struktur tidak muncul.');
    ralanSchemaCheck(strpos($html, 'report_page=4') !== false && strpos($html, 'kd_poli=U0073') !== false, $action . ': filter/halaman tidak dipertahankan.');
    ralanSchemaCheck(count($mysqli->queries) === 1, $action . ': ada SQL setelah validasi gagal.');
}

// Jalankan hanya mode CLI yang tidak membuka DB. open_basedir menolak config/koneksi.php.
$root = dirname(__DIR__);
$allowedPaths = $root . '/maintenance' . PATH_SEPARATOR . $root . '/main_app/page/t_non_klinis/keuangan';
foreach ([
    [[], 0],
    [['--plan'], 0],
    [['--help'], 0],
    [['--apply'], 2],
    [['--apply', '--confirm-server=OFFLINE'], 2],
    [['--plan', '--apply'], 2],
    [['--unknown'], 2],
] as $case) {
    $command = array_merge([PHP_BINARY, '-d', 'open_basedir="' . $allowedPaths . '"', $root . '/maintenance/keuangan_ralan_schema.php'], $case[0]);
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    ralanSchemaCheck(is_resource($process), 'CLI offline tidak dapat dijalankan.');
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    ralanSchemaCheck($exitCode === $case[1], 'Exit code CLI salah: ' . implode(' ', $case[0]) . ' ' . $errors);
    ralanSchemaCheck(strpos($output . $errors, 'open_basedir restriction') === false, 'Mode tanpa DB mencoba membuka file koneksi.');
}

echo json_encode(['result' => 'PASS', 'checks' => $checks, 'database_connections' => 0], JSON_PRETTY_PRINT), PHP_EOL;
