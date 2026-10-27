<?php
// CLI saja. Mode default hanya mencetak SQL dan tidak memuat koneksi database.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../main_app/page/t_non_klinis/keuangan/laporan_keuangan_ralan_schema.php';

$options = getopt('', ['plan', 'check', 'apply', 'help', 'confirm-server:', 'confirm-database:']);
$modes = array_intersect(['plan', 'check', 'apply', 'help'], array_keys($options));
if (count($modes) > 1) {
    fwrite(STDERR, "Pilih satu mode: --plan, --check, --apply, atau --help.\n");
    exit(2);
}
$mode = empty($modes) ? 'plan' : reset($modes);
foreach (array_slice($argv, 1) as $argument) {
    if (!preg_match('/^--(plan|check|apply|help|confirm-server=.+|confirm-database=.+)$/D', $argument)) {
        fwrite(STDERR, "Argumen tidak dikenal. Gunakan --help.\n");
        exit(2);
    }
}

if ($mode === 'help') {
    echo "Pemeliharaan skema laporan keuangan Ralan\n",
        "  --plan   : cetak definisi CREATE TABLE tanpa koneksi database (default).\n",
        "  --check  : koneksi database untuk pemeriksaan baca saja; cetak rencana SQL.\n",
        "  --apply --confirm-server=HOST --confirm-database=DB\n",
        "           : jalankan DDL setelah target cocok dengan konfigurasi aplikasi.\n",
        "Mode --apply hanya boleh dijalankan setelah persetujuan khusus pengelola database.\n";
    exit(0);
}

if ($mode === 'plan') {
    echo "-- Definisi untuk tabel baru; tidak ada koneksi database atau SQL yang dijalankan.\n",
        "-- Untuk rencana sesuai kondisi tabel yang sudah ada, gunakan --check dengan izin pemeriksaan.\n";
    foreach (array_keys(aptd_keu_ralan_schema_definitions()) as $table) {
        echo aptd_keu_ralan_schema_create_sql($table), ";\n\n";
    }
    exit(0);
}

if ($mode === 'apply' && (empty($options['confirm-server']) || empty($options['confirm-database']))) {
    fwrite(STDERR, "Dibatalkan sebelum koneksi: --apply wajib menyebut --confirm-server dan --confirm-database.\n");
    exit(2);
}

// Hanya --check dan --apply eksplisit yang mencapai bagian koneksi ini.
require __DIR__ . '/../config/koneksi.php';
try {
    if ($mode === 'apply' && (
        (string) $options['confirm-server'] !== (string) $server2
        || (string) $options['confirm-database'] !== (string) $database2
    )) {
        throw new RuntimeException('Target konfirmasi berbeda dari konfigurasi aplikasi. Tidak ada DDL yang dijalankan.');
    }

    echo 'Target: ' . $server2 . ' / ' . $database2 . PHP_EOL;
    $plan = aptd_keu_ralan_schema_plan($mysqli);
    foreach ($plan['blockers'] as $blocker) {
        fwrite(STDERR, $blocker . PHP_EOL);
    }
    foreach ($plan['statements'] as $sql) {
        echo $sql, ';', PHP_EOL;
    }

    if ($mode === 'check') {
        echo empty($plan['statements']) && empty($plan['blockers'])
            ? "Struktur siap. Tidak ada perubahan yang diperlukan.\n"
            : "Pemeriksaan baca saja selesai. Rencana di atas BELUM dijalankan.\n";
        exit(empty($plan['statements']) && empty($plan['blockers']) ? 0 : 2);
    }

    if (!empty($plan['blockers'])) {
        throw new RuntimeException('DDL dibatalkan: primary key memerlukan peninjauan manual.');
    }
    foreach ($plan['statements'] as $sql) {
        if (!$mysqli->query($sql)) {
            throw new RuntimeException('Penerapan struktur gagal: ' . $mysqli->error);
        }
    }
    $remaining = aptd_keu_ralan_schema_plan($mysqli);
    if (!empty($remaining['statements']) || !empty($remaining['blockers'])) {
        throw new RuntimeException('Struktur belum lengkap setelah penerapan. Periksa hasil pemeliharaan.');
    }
    echo "Struktur siap. Tidak ada data pasien atau nilai kalkulasi yang dihapus.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
