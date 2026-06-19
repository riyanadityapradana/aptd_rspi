<?php
require_once dirname(dirname(dirname(__DIR__))) . '/config/koneksi.php';

$allowedLevels = ['admin', 'manajemen', 'kepegawaian', 'medis', 'non medis', 'users', 'rekammedis', 'gizi', 'keuangan'];
$message = '';
$error = '';
$currentUserId = isset($_SESSION['id_users']) ? (int) $_SESSION['id_users'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $id = isset($_POST['id_users']) ? (int) $_POST['id_users'] : 0;
    $nama = isset($_POST['nama_lengkap']) ? trim($_POST['nama_lengkap']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $jabatan = isset($_POST['jabatan']) ? trim($_POST['jabatan']) : '';
    $level = isset($_POST['level']) ? trim($_POST['level']) : '';

    if ($action === 'delete') {
        if ($id <= 0) {
            $error = 'User tidak valid.';
        } elseif ($id === $currentUserId) {
            $error = 'User yang sedang login tidak boleh dihapus.';
        } else {
            $stmt = $mysqli->prepare('DELETE FROM tb_users WHERE id_users = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $message = 'User berhasil dihapus.';
        }
    } elseif ($nama === '' || $username === '' || $jabatan === '' || $level === '') {
        $error = 'Nama, username, jabatan, dan level wajib diisi.';
    } elseif (!in_array($level, $allowedLevels, true)) {
        $error = 'Level user tidak valid.';
    } elseif (strlen($username) < 4) {
        $error = 'Username minimal 4 karakter.';
    } else {
        $check = $mysqli->prepare('SELECT id_users FROM tb_users WHERE username = ? AND id_users <> ? LIMIT 1');
        $check->bind_param('si', $username, $id);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $error = 'Username sudah digunakan user lain.';
        }
        $check->close();

        if ($error === '') {
            if ($action === 'create') {
                if (strlen($password) < 6) {
                    $error = 'Password user baru minimal 6 karakter.';
                } else {
                    $passwordMd5 = md5($password);
                    $tglLog = date('Y-m-d H:i:s');
                    $jamLog = date('H:i:s');
                    $stmt = $mysqli->prepare('INSERT INTO tb_users (nama_lengkap, username, password, jabatan, level, tgl_log, jam_log) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $stmt->bind_param('sssssss', $nama, $username, $passwordMd5, $jabatan, $level, $tglLog, $jamLog);
                    $stmt->execute();
                    $stmt->close();
                    $message = 'User baru berhasil ditambahkan.';
                }
            } elseif ($action === 'update' && $id > 0) {
                if ($password !== '') {
                    if (strlen($password) < 6) {
                        $error = 'Password minimal 6 karakter.';
                    } else {
                        $passwordMd5 = md5($password);
                        $stmt = $mysqli->prepare('UPDATE tb_users SET nama_lengkap = ?, username = ?, password = ?, jabatan = ?, level = ? WHERE id_users = ?');
                        $stmt->bind_param('sssssi', $nama, $username, $passwordMd5, $jabatan, $level, $id);
                        $stmt->execute();
                        $stmt->close();
                        $message = 'User berhasil diperbarui.';
                    }
                } else {
                    $stmt = $mysqli->prepare('UPDATE tb_users SET nama_lengkap = ?, username = ?, jabatan = ?, level = ? WHERE id_users = ?');
                    $stmt->bind_param('ssssi', $nama, $username, $jabatan, $level, $id);
                    $stmt->execute();
                    $stmt->close();
                    $message = 'User berhasil diperbarui.';
                }
            }
        }
    }
}

$users = [];
$result = $mysqli->query('SELECT id_users, nama_lengkap, username, jabatan, level, tgl_log, jam_log FROM tb_users ORDER BY id_users DESC');
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
?>
<br>
<div class="row text-left"><div class="col"><h3 style="color:#666;margin-bottom:5px;">CRUD USER APLIKASI</h3><hr style="height:1px;background-image:linear-gradient(to right,rgba(0,0,0,0),rgba(102,102,102,1),rgba(0,0,0,0));margin-top:0;margin-bottom:10px;"></div></div>
<?php if ($message !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<div class="row">
    <div class="col-lg-4 mb-3">
        <div class="card"><div class="card-header"><strong>Tambah User</strong></div><div class="card-body">
            <form method="post">
                <input type="hidden" name="action" value="create">
                <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" class="form-control" required></div>
                <div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" required></div>
                <div class="form-group"><label>Password</label><input type="password" name="password" class="form-control" required></div>
                <div class="form-group"><label>Jabatan</label><input type="text" name="jabatan" class="form-control" required></div>
                <div class="form-group"><label>Level</label><select name="level" class="form-control" required><?php foreach ($allowedLevels as $level): ?><option value="<?php echo htmlspecialchars($level, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($level, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
                <button type="submit" class="btn btn-primary btn-sm">Simpan User</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="table-responsive-sm">
            <table class="table table-sm table-bordered table-hover" id="table4" style="width:100%;font-size:12px;">
                <thead class="thead-dark"><tr><th>ID</th><th>Nama</th><th>Username</th><th>Jabatan</th><th>Level</th><th>Tgl Log</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td style="text-align:center;"><?php echo (int) $user['id_users']; ?></td>
                        <td><?php echo htmlspecialchars($user['nama_lengkap'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($user['jabatan'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($user['level'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($user['tgl_log'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="min-width:260px;">
                            <form method="post" class="mb-1" style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                                <input type="hidden" name="action" value="update"><input type="hidden" name="id_users" value="<?php echo (int) $user['id_users']; ?>">
                                <input type="text" name="nama_lengkap" class="form-control form-control-sm" value="<?php echo htmlspecialchars($user['nama_lengkap'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                <input type="text" name="username" class="form-control form-control-sm" value="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                <input type="text" name="jabatan" class="form-control form-control-sm" value="<?php echo htmlspecialchars($user['jabatan'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                <select name="level" class="form-control form-control-sm"><?php foreach ($allowedLevels as $level): ?><option value="<?php echo htmlspecialchars($level, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $user['level'] === $level ? 'selected' : ''; ?>><?php echo htmlspecialchars($level, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select>
                                <input type="password" name="password" class="form-control form-control-sm" placeholder="Password baru opsional">
                                <button type="submit" class="btn btn-warning btn-sm">Update</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Hapus user ini?');">
                                <input type="hidden" name="action" value="delete"><input type="hidden" name="id_users" value="<?php echo (int) $user['id_users']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" <?php echo (int) $user['id_users'] === $currentUserId ? 'disabled' : ''; ?>>Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
