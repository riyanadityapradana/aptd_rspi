<?php

function aptd_admin_post_string(array $source, $key)
{
    return isset($source[$key]) && is_string($source[$key]) ? trim($source[$key]) : '';
}

function aptd_admin_write_user(mysqli $connection, array $source, $currentUserId, array $allowedLevels)
{
    if (empty($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true
        || !aptd_can_access(isset($_SESSION['level']) ? $_SESSION['level'] : '', 'users_admin')) {
        throw new InvalidArgumentException('Hanya admin yang dapat mengelola user dan akses.');
    }
    $token = aptd_admin_post_string($source, 'csrf_token');
    if (empty($_SESSION['user_admin_csrf']) || !hash_equals($_SESSION['user_admin_csrf'], $token)) {
        throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
    }
    $action = aptd_admin_post_string($source, 'action');
    $idText = aptd_admin_post_string($source, 'id_users');
    $id = ctype_digit($idText) ? (int) $idText : 0;
    if (!in_array($action, ['create', 'update', 'delete'], true) || ($action !== 'create' && $id <= 0)) {
        throw new InvalidArgumentException('User atau tindakan tidak valid.');
    }
    $nama = aptd_admin_post_string($source, 'nama_lengkap');
    $username = aptd_admin_post_string($source, 'username');
    $password = aptd_admin_post_string($source, 'password');
    $jabatan = aptd_admin_post_string($source, 'jabatan');
    $level = aptd_admin_post_string($source, 'level');
    $menus = [];
    if ($action !== 'delete') {
        if ($nama === '' || $jabatan === '' || strlen($username) < 4 || !in_array($level, $allowedLevels, true)) {
            throw new InvalidArgumentException('Nama, jabatan, username minimal 4 karakter, dan level yang valid wajib diisi.');
        }
        if (mb_strlen($nama) > 100 || mb_strlen($username) > 100 || mb_strlen($jabatan) > 100) {
            throw new InvalidArgumentException('Nama, username, dan jabatan maksimal 100 karakter.');
        }
        if (($action === 'create' || $password !== '') && strlen($password) < 6) {
            throw new InvalidArgumentException('Password minimal 6 karakter.');
        }
        $menus = aptd_validate_extra_menus(isset($source['akses_menu']) ? $source['akses_menu'] : []);
    }
    if ($id === (int) $currentUserId && ($action === 'delete' || ($action === 'update' && $level !== 'admin'))) {
        throw new InvalidArgumentException('Akun admin yang sedang digunakan tidak boleh dihapus atau diturunkan levelnya.');
    }
    $accessJson = empty($menus) ? null : json_encode($menus);
    $connection->begin_transaction();
    try {
        // Kunci admin dengan urutan tetap agar dua perubahan tidak menghapus admin terakhir.
        $admins = $connection->query("SELECT id_users FROM tb_users WHERE level = 'admin' ORDER BY id_users FOR UPDATE")->fetch_all(MYSQLI_ASSOC);
        if ($action !== 'create') {
            $statement = $connection->prepare('SELECT level FROM tb_users WHERE id_users = ? FOR UPDATE');
            $statement->bind_param('i', $id);
            $statement->execute();
            $existing = $statement->get_result()->fetch_assoc();
            $statement->close();
            if (!$existing) {
                throw new InvalidArgumentException('User tidak ditemukan.');
            }
            if ($existing['level'] === 'admin' && count($admins) <= 1 && ($action === 'delete' || $level !== 'admin')) {
                throw new InvalidArgumentException('Minimal satu akun admin harus tetap tersedia.');
            }
        }
        if ($action === 'delete') {
            $statement = $connection->prepare('DELETE FROM tb_users WHERE id_users = ?');
            $statement->bind_param('i', $id);
        } else {
            $checkId = $action === 'create' ? 0 : $id;
            $statement = $connection->prepare('SELECT id_users FROM tb_users WHERE username = ? AND id_users <> ? LIMIT 1');
            $statement->bind_param('si', $username, $checkId);
            $statement->execute();
            $duplicate = $statement->get_result()->fetch_assoc();
            $statement->close();
            if ($duplicate) {
                throw new InvalidArgumentException('Username sudah digunakan user lain.');
            }
            if ($action === 'create') {
                $hash = md5($password);
                $date = date('Y-m-d H:i:s');
                $time = date('H:i:s');
                $statement = $connection->prepare('INSERT INTO tb_users (nama_lengkap, username, password, jabatan, level, tgl_log, jam_log, akses_menu) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $statement->bind_param('ssssssss', $nama, $username, $hash, $jabatan, $level, $date, $time, $accessJson);
            } elseif ($password !== '') {
                $hash = md5($password);
                $statement = $connection->prepare('UPDATE tb_users SET nama_lengkap = ?, username = ?, password = ?, jabatan = ?, level = ?, akses_menu = ? WHERE id_users = ?');
                $statement->bind_param('ssssssi', $nama, $username, $hash, $jabatan, $level, $accessJson, $id);
            } else {
                $statement = $connection->prepare('UPDATE tb_users SET nama_lengkap = ?, username = ?, jabatan = ?, level = ?, akses_menu = ? WHERE id_users = ?');
                $statement->bind_param('sssssi', $nama, $username, $jabatan, $level, $accessJson, $id);
            }
        }
        $statement->execute();
        $statement->close();
        $connection->commit();
    } catch (Throwable $exception) {
        $connection->rollback();
        throw $exception;
    }
    aptd_access_current_user(true);
    return $action === 'create' ? 'User baru dan akses berhasil ditambahkan.'
        : ($action === 'delete' ? 'User berhasil dihapus.' : 'User dan akses berhasil diperbarui.');
}

function aptd_admin_render_access_select($id)
{
    echo '<div class="form-group mt-3 mb-0"><label for="' . $id . '">Akses Tambahan</label>';
    echo '<select multiple id="' . $id . '" name="akses_menu[]" class="form-control user-extra-access" aria-describedby="' . $id . 'Summary">';
    $lastGroup = null;
    foreach (aptd_extra_menu_catalog() as $page => $item) {
        if ($lastGroup !== $item['group']) {
            if ($lastGroup !== null) { echo '</optgroup>'; }
            echo '<optgroup label="' . htmlspecialchars($item['group'], ENT_QUOTES, 'UTF-8') . '">';
            $lastGroup = $item['group'];
        }
        echo '<option value="' . htmlspecialchars($page, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    if ($lastGroup !== null) { echo '</optgroup>'; }
    echo '</select><div class="user-access-summary" id="' . $id . 'Summary"></div></div>';
}
