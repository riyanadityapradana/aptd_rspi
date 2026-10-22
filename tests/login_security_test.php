<?php
// Jalankan melalui CLI. Seluruh sesi, penyimpanan, dan koneksi DB adalah fixture terisolasi.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = sys_get_temp_dir() . '/aptd-login-test-' . bin2hex(random_bytes(6));
mkdir($root . '/login', 0777, true);
mkdir($root . '/config');
mkdir($root . '/sessions');
mkdir($root . '/storage/login-security', 0777, true);
foreach (['login.php', 'proses_login.php', 'login_security.php', 'captcha.php'] as $file) {
    copy(__DIR__ . '/../login/' . $file, $root . '/login/' . $file);
}

file_put_contents($root . '/config/koneksi.php', <<<'PHP'
<?php
file_put_contents(__DIR__ . '/../db-opened', '1', FILE_APPEND);
date_default_timezone_set('Asia/Makassar');
class FixtureResult {
    private $user;
    public function __construct($user) { $this->user = $user; }
    public function fetch_assoc() { return $this->user; }
}
class FixtureStatement {
    private $sql;
    private $params;
    public function __construct($sql) { $this->sql = $sql; }
    public function bind_param($types, &...$params) { $this->params = &$params; }
    public function execute() {
        if (strpos($this->sql, 'UPDATE') === 0) {
            file_put_contents(__DIR__ . '/../updated.json', json_encode($this->params));
        }
    }
    public function get_result() {
        $valid = $this->params[0] === 'demo' && $this->params[1] === md5('correct-password');
        return new FixtureResult($valid ? ['id_users' => 17, 'nama_lengkap' => 'Uji Login',
            'username' => 'demo', 'jabatan' => 'Staf', 'level' => 'users'] : null);
    }
    public function close() {}
}
class FixtureConnection {
    public function prepare($sql) { return new FixtureStatement($sql); }
}
$mysqli = new FixtureConnection();
PHP
);
// Endpoint ini HANYA dibuat di direktori fixture sementara, tidak di aplikasi.
file_put_contents($root . '/fixture.php', <<<'PHP'
<?php
session_start();
if (isset($_GET['expire'])) {
    foreach ($_SESSION['login_captchas'] as &$challenge) { $challenge['expires'] = time() - 1; }
}
header('Content-Type: application/json');
echo json_encode($_SESSION);
PHP
);

$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
$address = stream_socket_get_name($socket, false);
fclose($socket);
$log = $root . '/server.log';
$server = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $root . '/sessions', '-S', $address, '-t', $root],
    [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root);
fclose($pipes[0]);
$base = 'http://' . $address;
$checks = 0;

function check($condition, $message)
{
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
    echo 'PASS ' . $message . PHP_EOL;
}

function request(&$cookie, $path, $fields = null, $headers = [])
{
    global $base;
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 10, CURLOPT_COOKIE => $cookie, CURLOPT_HTTPHEADER => $headers]);
    if ($fields !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $raw = curl_exec($curl);
    if ($raw === false) { throw new RuntimeException(curl_error($curl)); }
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $head = substr($raw, 0, curl_getinfo($curl, CURLINFO_HEADER_SIZE));
    $body = substr($raw, curl_getinfo($curl, CURLINFO_HEADER_SIZE));
    curl_close($curl);
    if (preg_match('/Set-Cookie: (PHPSESSID=[^;\r\n]+)/i', $head, $match)) { $cookie = $match[1]; }
    return ['status' => $status, 'headers' => $head, 'body' => $body];
}

function form(&$cookie)
{
    $page = request($cookie, '/login/login.php');
    preg_match('/name="csrf_token" value="([^"]+)"/', $page['body'], $csrf);
    preg_match('/name="captcha_id" value="([^"]+)"/', $page['body'], $id);
    $session = json_decode(request($cookie, '/fixture.php')['body'], true);
    return ['csrf_token' => $csrf[1], 'captcha_id' => $id[1],
        'captcha' => $session['login_captchas'][$id[1]]['code'],
        'username' => 'demo', 'password' => 'correct-password'];
}

function store()
{
    global $root;
    $db = new PDO('sqlite:' . $root . '/storage/login-security/attempts.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $db;
}

function resetWait() { store()->exec('DELETE FROM login_attempts'); }
function databaseOpens() {
    global $root;
    return file_exists($root . '/db-opened') ? strlen(file_get_contents($root . '/db-opened')) : 0;
}

function removeFixture($path)
{
    global $root;
    // Semua penghapusan terbatas pada fixture yang dibuat oleh proses ini.
    if (strpos($path, $root) !== 0) { throw new RuntimeException('Unsafe cleanup path'); }
    foreach (new FilesystemIterator($path) as $item) {
        if ($item->isDir()) { removeFixture($item->getPathname()); }
        else { unlink($item->getPathname()); }
    }
    rmdir($path);
}

$failed = false;
try {
    for ($i = 0; $i < 50; $i++) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($probe) { fclose($probe); break; }
        usleep(100000);
    }
    $cookie = '';
    $a = form($cookie);
    $page = request($cookie, '/login/login.php');
    check($page['status'] === 200 && strpos($page['headers'], 'no-store') !== false, 'form dan token tidak di-cache');
    $image = request($cookie, '/login/captcha.php?id=' . $a['captcha_id']);
    check($image['status'] === 200 && substr($image['body'], 0, 8) === "\x89PNG\r\n\x1a\n", 'CAPTCHA berupa PNG dan kode tab sebelumnya tetap berlaku');
    check(strpos($image['headers'], 'no-store') !== false, 'gambar CAPTCHA tidak di-cache');
    $otherCookie = '';
    check(request($otherCookie, '/login/captcha.php?id=' . $a['captcha_id'])['status'] === 404, 'CAPTCHA terikat sesi');
    check(request($cookie, '/login/proses_login.php')['status'] === 302, 'GET handler tidak memproses login');
    $invalidCsrf = $a;
    $invalidCsrf['csrf_token'] = 'forged';
    request($cookie, '/login/proses_login.php', $invalidCsrf);
    check(databaseOpens() === 0 && (int) store()->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn() === 0,
        'CSRF palsu ditolak sebelum kueri kredensial');

    $badCaptcha = $a;
    $badCaptcha['captcha'] = 'WRONG';
    request($cookie, '/login/proses_login.php', $badCaptcha);
    $until = (int) store()->query('SELECT blocked_until FROM login_attempts')->fetchColumn();
    check($until - time() >= 29 && databaseOpens() === 0, 'CAPTCHA salah memulai jeda 30 detik tanpa kueri kredensial');
    $page = request($cookie, '/login/login.php');
    check(strpos($page['body'], 'id="loginButton" disabled') !== false && strpos($page['body'], 'Kode CAPTCHA salah') !== false,
        'refresh menampilkan pesan, hitung mundur, dan tombol terkunci');
    $newCookie = '';
    $b = form($newCookie);
    $b['username'] = 'different-user';
    $blocked = request($newCookie, '/login/proses_login.php', $b, ['X-Forwarded-For: 203.0.113.99']);
    check($blocked['status'] === 303 && strpos($blocked['headers'], 'Retry-After:') !== false && databaseOpens() === 0,
        'sesi baru, username berbeda, header IP palsu, dan POST langsung tetap dibatasi');
    check((int) store()->query('SELECT blocked_until FROM login_attempts')->fetchColumn() === $until,
        'percobaan selama jeda tidak memperpanjang waktu');
    check(isset(json_decode(request($newCookie, '/fixture.php')['body'], true)['login_captchas'][$b['captcha_id']]),
        'percobaan saat jeda tidak menghabiskan CAPTCHA yang belum dipakai');

    echo 'Menunggu batas 30 detik asli...' . PHP_EOL;
    while (time() <= $until) { usleep(200000); }
    $b['username'] = 'demo';
    $b['password'] = 'wrong-password';
    $b['captcha'] = strtolower($b['captcha']);
    request($newCookie, '/login/proses_login.php', $b);
    check(databaseOpens() === 1 && (int) store()->query('SELECT blocked_until FROM login_attempts')->fetchColumn() > time(),
        'setelah 30 detik boleh mencoba; CAPTCHA huruf kecil diterima dan password salah memulai jeda baru');
    $session = json_decode(request($newCookie, '/fixture.php')['body'], true);
    check(empty($session['login_aptd_rspi']), 'password salah tidak membuat sesi login');
    resetWait();
    request($newCookie, '/login/proses_login.php', $b);
    check(databaseOpens() === 1, 'CAPTCHA yang sudah dipakai tidak dapat diulang');

    resetWait();
    $expired = form($cookie);
    request($cookie, '/fixture.php?expire=1');
    request($cookie, '/login/proses_login.php', $expired);
    check(databaseOpens() === 1 && store()->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn() == 1,
        'CAPTCHA kedaluwarsa ditolak dan memulai jeda');
    resetWait();
    $missing = form($cookie);
    unset($missing['captcha']);
    request($cookie, '/login/proses_login.php', $missing);
    check(databaseOpens() === 1, 'menghapus field CAPTCHA tidak melewati pemeriksaan');
    resetWait();
    $malformed = form($cookie);
    $malformed['captcha'] = ['invalid'];
    $malformed['username'] = ['invalid'];
    check(request($cookie, '/login/proses_login.php', $malformed)['status'] === 303 && databaseOpens() === 1,
        'payload array ditolak tanpa fatal error');

    resetWait();
    $success = form($cookie);
    $oldCookie = $cookie;
    $result = request($cookie, '/login/proses_login.php', $success);
    $session = json_decode(request($cookie, '/fixture.php')['body'], true);
    check(strpos($result['headers'], '../main_app/main_app.php?page=beranda') !== false && $cookie !== $oldCookie,
        'login benar menuju beranda dan mengganti ID sesi');
    check($session['login_aptd_rspi'] === true && $session['id_user'] === 17 && $session['level'] === 'users',
        'identitas dan hak akses sesi dipertahankan');
    $update = json_decode(file_get_contents($root . '/updated.json'), true);
    check($update[2] === 17 && $update[0] === $session['tgl_log'] && $update[1] === $session['jam_log'],
        'login berhasil tetap memperbarui tanggal dan jam login');
    check(store()->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn() == 0 && !isset($session['login_captchas'], $session['login_csrf']),
        'login berhasil menghapus jeda dan challenge');

    // Proses PHP berbeda mensimulasikan worker/sesi yang mencoba bersamaan.
    $workers = [];
    $workerCode = '$_SERVER["REMOTE_ADDR"]="198.51.100.27"; require ' . var_export($root . '/login/login_security.php', true)
        . '; echo json_encode(aptd_login_reserve_attempt());';
    for ($i = 0; $i < 8; $i++) {
        $process = proc_open([PHP_BINARY, '-r', $workerCode], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $log, 'a']], $workerPipes);
        fclose($workerPipes[0]);
        $workers[] = [$process, $workerPipes[1]];
    }
    $allowed = 0;
    foreach ($workers as [$process, $output]) {
        $value = json_decode(stream_get_contents($output), true);
        fclose($output);
        $exit = proc_close($process);
        if ($exit !== 0 || !is_array($value)) { throw new RuntimeException('Worker gagal'); }
        if ($value['wait'] === 0) { $allowed++; }
    }
    check($allowed === 1, 'delapan worker bersamaan hanya mendapat satu reservasi');
    $anotherCookie = '';
    $another = form($anotherCookie);
    request($anotherCookie, '/login/proses_login.php', array_merge($another, ['captcha' => 'WRONG']));
    check(store()->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn() == 2, 'IP berbeda memiliki jeda sendiri');
    resetWait();

    rename($root . '/storage/login-security', $root . '/storage/unavailable');
    $unavailable = request($anotherCookie, '/login/login.php');
    $rejected = request($anotherCookie, '/login/proses_login.php', $another);
    check(strpos($unavailable['body'], 'Pengamanan login sedang tidak tersedia') !== false
        && strpos($unavailable['body'], 'id="loginButton" disabled') !== false && $rejected['status'] === 303 && databaseOpens() === 2,
        'penyimpanan rusak menolak login dengan pesan umum');
    rename($root . '/storage/unavailable', $root . '/storage/login-security');
    echo 'Selesai: ' . $checks . ' pemeriksaan lulus. Database asli tidak diakses.' . PHP_EOL;
} catch (Throwable $e) {
    $failed = true;
    fwrite(STDERR, 'FAIL ' . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, file_get_contents($log));
} finally {
    proc_terminate($server);
    proc_close($server);
    gc_collect_cycles();
    removeFixture($root);
}
exit($failed ? 1 : 0);
