<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$mode = isset($argv[1]) ? $argv[1] : '--plan';
$sql = 'ALTER TABLE tb_users ADD COLUMN akses_menu TEXT NULL DEFAULT NULL';
if ($mode === '--plan') {
    echo $sql, ';', PHP_EOL;
    exit;
}
if (!in_array($mode, ['--check', '--apply'], true) || count($argv) !== 2) {
    fwrite(STDERR, "Gunakan --plan, --check atau --apply.\n");
    exit(2);
}
require __DIR__ . '/../config/koneksi.php';
$column = $mysqli->query("SHOW COLUMNS FROM tb_users LIKE 'akses_menu'")->fetch_assoc();
if ($column) {
    if (strtolower($column['Type']) !== 'text' || $column['Null'] !== 'YES') {
        throw new RuntimeException('Kolom akses_menu sudah ada dengan tipe berbeda; tidak diubah.');
    }
    echo "Kolom akses_menu siap. Tidak ada perubahan.\n";
} elseif ($mode === '--apply') {
    $mysqli->query($sql);
    echo "Kolom akses_menu ditambahkan; akun lama tetap mengikuti akses bawaan level.\n";
} else {
    echo $sql, ";\nBelum dijalankan.\n";
}
