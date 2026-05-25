<?php

// Memuat dan membaca file .env jika tersedia
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    if ($envContent) {
        $lines = explode("\n", $envContent);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2) + [null, null];
                if ($key && $value !== null) {
                    $key = trim($key);
                    $value = trim($value);
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                }
            }
        }
    }
}

// Konfigurasi database pertama


// Konfigurasi database kedua
//$server2   = getenv('DB_SERVER2') ?: '192.168.1.4';
$server2   = getenv('DB_SERVER') ?: 'localhost';
$username2 = getenv('DB_USERNAME2') ?: 'root';
$password2 = getenv('DB_PASSWORD2') ?: '';
$database2 = getenv('DB_DATABASE2') ?: 'sik9';

try {
    // Mengaktifkan mode error reporting untuk mysqli
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Koneksi ke database pertama

    // Koneksi ke database kedua
    $mysqli = new mysqli($server2, $username2, $password2, $database2);
    $mysqli->set_charset('utf8');

} catch (mysqli_sql_exception $e) {
    exit('Database Connection Error: ' . $e->getMessage());
}

// Set zona waktu default
date_default_timezone_set(getenv('TIMEZONE') ?: 'Asia/Makassar');

?>

