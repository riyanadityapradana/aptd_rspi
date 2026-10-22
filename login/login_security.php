<?php
// CAPTCHA dan pembatasan login lokal; tidak memerlukan layanan internet.
const APTD_LOGIN_DELAY = 30;
const APTD_CAPTCHA_TTL = 300;

function aptd_login_no_cache()
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

function aptd_login_security_db()
{
    static $db;
    if ($db === null) {
        $db = new PDO('sqlite:' . __DIR__ . '/../storage/login-security/attempts.sqlite');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA busy_timeout = 5000');
        $db->exec('CREATE TABLE IF NOT EXISTS login_attempts (
            ip_hash TEXT PRIMARY KEY,
            blocked_until INTEGER NOT NULL,
            lease TEXT NOT NULL
        )');
    }
    return $db;
}

function aptd_login_ip_key()
{
    // Header X-Forwarded-For dapat dipalsukan; gunakan alamat koneksi langsung.
    return hash('sha256', isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown');
}

function aptd_login_wait()
{
    $stmt = aptd_login_security_db()->prepare('SELECT blocked_until FROM login_attempts WHERE ip_hash = ?');
    $stmt->execute([aptd_login_ip_key()]);
    return max(0, (int) $stmt->fetchColumn() - time());
}

function aptd_login_reserve_attempt()
{
    $db = aptd_login_security_db();
    // Reservasi atomik mencegah dua sesi/IP yang sama lolos secara bersamaan.
    $db->exec('BEGIN IMMEDIATE');
    try {
        $remaining = aptd_login_wait();
        if ($remaining > 0) {
            $db->exec('ROLLBACK');
            return ['wait' => $remaining, 'lease' => null];
        }
        $lease = bin2hex(random_bytes(16));
        $stmt = $db->prepare('INSERT OR REPLACE INTO login_attempts (ip_hash, blocked_until, lease) VALUES (?, ?, ?)');
        $stmt->execute([aptd_login_ip_key(), time() + APTD_LOGIN_DELAY, $lease]);
        if (random_int(1, 100) === 1) {
            $cleanup = $db->prepare('DELETE FROM login_attempts WHERE blocked_until < ?');
            $cleanup->execute([time() - 86400]);
        }
        $db->exec('COMMIT');
        return ['wait' => 0, 'lease' => $lease];
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
}

function aptd_login_clear_attempt($lease)
{
    // Jangan hapus reservasi baru jika pemeriksaan sebelumnya berjalan lama.
    $stmt = aptd_login_security_db()->prepare('DELETE FROM login_attempts WHERE ip_hash = ? AND lease = ?');
    $stmt->execute([aptd_login_ip_key(), $lease]);
}

function aptd_login_csrf_token()
{
    if (empty($_SESSION['login_csrf'])) {
        $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['login_csrf'];
}

function aptd_login_create_captcha()
{
    if (!extension_loaded('gd')) {
        throw new RuntimeException('Ekstensi GD diperlukan untuk CAPTCHA login.');
    }
    $challenges = isset($_SESSION['login_captchas']) && is_array($_SESSION['login_captchas'])
        ? $_SESSION['login_captchas'] : [];
    foreach ($challenges as $key => $challenge) {
        if ($challenge['expires'] <= time()) {
            unset($challenges[$key]);
        }
    }
    // Beberapa tab dapat memiliki kode sendiri; batasi ukuran sesi.
    while (count($challenges) >= 5) {
        array_shift($challenges);
    }
    $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < 5; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    $id = bin2hex(random_bytes(16));
    $challenges[$id] = ['code' => $code, 'expires' => time() + APTD_CAPTCHA_TTL];
    $_SESSION['login_captchas'] = $challenges;
    return $id;
}

function aptd_login_check_captcha($id, $answer)
{
    $challenge = isset($_SESSION['login_captchas'][$id]) ? $_SESSION['login_captchas'][$id] : null;
    // Sekali pakai, termasuk bila jawaban atau kredensial salah.
    unset($_SESSION['login_captchas'][$id]);
    return $challenge !== null && $challenge['expires'] > time()
        && strlen($answer) === 5 && hash_equals($challenge['code'], strtoupper($answer));
}

function aptd_login_post_string($key)
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
}

function aptd_login_reject($message, $failedLease = null)
{
    if ($failedLease !== null) {
        try {
            // Hitung jeda dari saat kegagalan diketahui, termasuk jika kueri lambat.
            $stmt = aptd_login_security_db()->prepare('UPDATE login_attempts SET blocked_until = ? WHERE ip_hash = ? AND lease = ?');
            $stmt->execute([time() + APTD_LOGIN_DELAY, aptd_login_ip_key(), $failedLease]);
        } catch (Throwable $e) {
            error_log('APTD login limiter failure: ' . $e->getMessage());
            $message = 'Pengamanan login sedang tidak tersedia. Silakan hubungi administrator.';
        }
    }
    $_SESSION['login_error'] = $message;
    header('Location: login.php', true, 303);
    exit;
}
