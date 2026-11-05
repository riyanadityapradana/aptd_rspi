<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once dirname(__DIR__, 3) . '/config/akses.php';
if (empty($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true
    || !aptd_can_access(isset($_SESSION['level']) ? $_SESSION['level'] : '', 'users_admin')) {
    http_response_code(403);
    exit('Hanya admin yang dapat mengelola user dan akses.');
}
require_once dirname(dirname(dirname(__DIR__))) . '/config/koneksi.php';
require_once __DIR__ . '/users_helper.php';
if (empty($_SESSION['user_admin_csrf'])) {
    $_SESSION['user_admin_csrf'] = bin2hex(random_bytes(32));
}

$allowedLevels = ['admin', 'manajemen', 'kepegawaian', 'medis', 'non medis', 'users', 'rekammedis', 'gizi', 'moneta', 'keuangan', 'pemasaran', 'direktur', 'farmasi', 'perawat'];
$levelLabels = [
    'admin' => 'Admin',
    'manajemen' => 'Manajemen',
    'kepegawaian' => 'Kepegawaian',
    'medis' => 'Medis',
    'non medis' => 'Non Medis',
    'users' => 'Users',
    'rekammedis' => 'Rekam Medis',
    'gizi' => 'Gizi',
    'moneta' => 'Moneta',
    'keuangan' => 'Keuangan',
    'direktur' => 'Direktur',
    'farmasi' => 'Farmasi',
    'pemasaran' => 'Pemasaran',
    'perawat' => 'Perawat',
];
$message = '';
$error = '';
$currentUserId = isset($_SESSION['id_user'])
    ? (int) $_SESSION['id_user']
    : (isset($_SESSION['id_users']) ? (int) $_SESSION['id_users'] : 0);

function aptd_admin_user_level_label($level, array $labels)
{
    return isset($labels[$level]) ? $labels[$level] : ucfirst((string) $level);
}

function aptd_admin_user_last_login($tglLog, $jamLog)
{
    $tglLog = trim((string) $tglLog);
    $jamLog = trim((string) $jamLog);
    if ($tglLog === '' || strpos($tglLog, '0000-00-00') === 0) {
        return 'Belum pernah';
    }

    $timestamp = strtotime($tglLog);
    if ($timestamp === false) {
        return $tglLog;
    }

    $time = date('H:i:s', $timestamp);
    if ($time === '00:00:00' && $jamLog !== '' && $jamLog !== '00:00:00') {
        $time = $jamLog;
    }

    return date('d/m/Y', $timestamp) . ' ' . $time;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $message = aptd_admin_write_user($mysqli, $_POST, $currentUserId, $allowedLevels);
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('APTD simpan user: ' . $exception->getMessage());
        $error = 'User belum dapat disimpan. Silakan coba kembali.';
    }
}

$users = [];
$result = $mysqli->query('SELECT id_users, nama_lengkap, username, jabatan, level, tgl_log, jam_log, akses_menu FROM tb_users ORDER BY id_users ASC');
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
?>
<style>
    .user-admin-page{padding:22px 0 34px;color:#17365d}
    .user-admin-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:18px}
    .user-admin-title{display:flex;align-items:center;gap:12px;margin:0 0 5px;font-size:22px;font-weight:800;color:#14375e}
    .user-admin-title:after{content:"";display:block;width:300px;max-width:28vw;height:1px;background:#b9cbe0}
    .user-admin-subtitle{margin:0;color:#607899;font-size:13px}
    .user-add-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;height:39px;padding:0 20px;border:0;border-radius:8px;background:#2c6fab;color:#fff;font-size:13px;font-weight:800;white-space:nowrap}
    .user-add-btn:hover{background:#215b8e;color:#fff}
    .user-search-bar{display:flex;align-items:center;gap:13px;padding:15px 20px;margin-bottom:20px;border:1px solid #dce6f1;border-radius:8px;background:#fff;box-shadow:0 8px 20px rgba(47,77,112,.08)}
    .user-search-label{margin:0;color:#526d90;font-size:13px;font-weight:700;white-space:nowrap}
    .user-search-wrap{position:relative;width:380px;max-width:100%}
    .user-search-input{width:100%;height:35px;padding:7px 12px 7px 34px;border:1px solid #c7d5e5;border-radius:7px;background:#f7f9fc;color:#253d5d;font-size:13px}
    .user-search-icon{position:absolute;left:12px;top:10px;color:#6c85a5;font-size:13px}
    .user-count{margin-left:auto;color:#587292;font-size:13px;font-weight:800;white-space:nowrap}
    .user-table-panel{overflow:hidden;border:1px solid #d5e1ed;border-radius:8px;background:#fff;box-shadow:0 10px 24px rgba(47,77,112,.1)}
    .user-table-title{padding:16px 22px;background:#1e4268;color:#fff;font-size:15px;font-weight:800}
    .user-table-scroll{overflow-x:auto}
    .user-table{width:100%;min-width:1040px;margin:0!important;border-collapse:collapse;color:#17365d}
    .user-table th{padding:11px 14px;border:0;border-bottom:1px solid #c7d5e5;background:#edf2f7;color:#607899;font-size:12px;font-weight:800;text-align:center;text-transform:uppercase}
    .user-table td{padding:10px 14px;border:0;border-bottom:1px solid #e4ebf3;vertical-align:middle;font-size:13px}
    .user-table tbody tr:last-child td{border-bottom:0}
    .user-table tbody tr:hover td{background:#f8fbff}
    .user-name{font-weight:800;color:#122f52}
    .user-you{display:inline-flex;margin-left:7px;padding:3px 7px;border-radius:999px;background:#daf5e4;color:#14723a;font-size:10px;font-weight:800;vertical-align:middle}
    .user-level{display:inline-flex;align-items:center;justify-content:center;padding:5px 10px;border-radius:999px;font-size:11px;font-weight:800;white-space:nowrap}
    .level-admin{background:#ffead4;color:#9b4809}.level-moneta{background:#dcecff;color:#15578f}.level-direktur{background:#efe0ff;color:#5c2788}
    .level-keuangan{background:#dcf5ea;color:#176747}.level-rekammedis{background:#fff2c9;color:#815d00}.level-pemasaran{background:#ffe5f0;color:#9a2c5a}.level-perawat{background:#e4f7ff;color:#126276}.level-default{background:#e8edf4;color:#48617f}
    .user-actions{display:flex;justify-content:center;gap:6px}
    .user-action-btn{display:inline-flex;align-items:center;justify-content:center;gap:5px;height:29px;padding:0 10px;border-radius:6px;background:#fff;font-size:12px;font-weight:700;white-space:nowrap}
    .user-edit-btn{border:1px solid #8fc7f2;color:#1769a7}.user-edit-btn:hover{background:#edf7ff;color:#125685}
    .user-delete-btn{border:1px solid #f0aaa5;color:#c64c44}.user-delete-btn:hover{background:#fff1f0;color:#a83d36}
    .user-delete-btn:disabled{border-color:#d8dee7;color:#9ca8b7;background:#f4f6f8;cursor:not-allowed}
    .user-empty{padding:30px!important;text-align:center;color:#7186a1}
    .user-modal .modal-content{border:0;border-radius:8px;box-shadow:0 20px 50px rgba(20,49,82,.22)}
    .user-modal .modal-header{border-bottom:1px solid #e2e8f0;background:#f8fafc}
    .user-modal .modal-title{color:#17375e;font-size:18px;font-weight:800}
    .user-modal label{color:#344f70;font-size:12px;font-weight:800}
    .user-modal .form-control{height:38px;border-color:#cbd8e6;border-radius:6px;font-size:13px}
    .user-modal .modal-footer{border-top:1px solid #e2e8f0}
    .user-modal .modal-dialog{max-width:620px}
    .user-modal .modal-body{max-height:calc(100vh - 180px);overflow-y:auto}
    .user-modal .user-extra-access{height:auto;min-height:96px}
    .user-modal .select2-selection--multiple{min-height:38px;border-color:#cbd8e6;font-size:13px}
    .user-modal .select2-selection__choice{max-width:100%;white-space:normal;overflow-wrap:anywhere}
    .user-modal .select2-search__field{max-width:100%}
    .user-access-summary{font-size:12px;color:#607899;margin-top:6px}
    .user-access-cell{font-size:12px;white-space:nowrap}
    @media(max-width:767.98px){
        .user-admin-page{padding-top:12px}.user-admin-head{display:block}.user-add-btn{width:100%;margin-top:14px}
        .user-admin-title:after{display:none}.user-search-bar{align-items:stretch;flex-wrap:wrap;padding:13px}.user-search-wrap{width:100%}.user-count{margin-left:0}
    }
</style>

<section class="user-admin-page">
    <div class="user-admin-head">
        <div>
            <h1 class="user-admin-title">Manajemen User</h1>
            <p class="user-admin-subtitle">Kelola akun dan hak akses pengguna aplikasi.</p>
        </div>
        <button type="button" class="user-add-btn" data-toggle="modal" data-target="#modalTambahUser">
            <span class="glyphicon glyphicon-plus" aria-hidden="true"></span>
            Tambah User
        </button>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="user-search-bar">
        <label class="user-search-label" for="userSearch">Cari user:</label>
        <div class="user-search-wrap">
            <span class="glyphicon glyphicon-search user-search-icon" aria-hidden="true"></span>
            <input type="search" id="userSearch" class="user-search-input" placeholder="Nama, username, jabatan, atau level" autocomplete="off">
        </div>
        <span class="user-count" id="userCount"><?php echo count($users); ?> user</span>
    </div>

    <div class="user-table-panel">
        <div class="user-table-title">
            <span class="glyphicon glyphicon-user" aria-hidden="true"></span>
            Daftar User APTD-RSPI
        </div>
        <div class="user-table-scroll">
            <table class="user-table" id="userAdminTable">
                <thead>
                    <tr>
                        <th style="width:62px">No</th>
                        <th>Nama Lengkap</th>
                        <th>Username</th>
                        <th>Jabatan</th>
                        <th style="width:120px">Level</th>
                        <th>Akses</th>
                        <th style="width:175px">Login Terakhir</th>
                        <th style="width:145px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="8" class="user-empty">Belum ada data user.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $index => $user): ?>
                            <?php
                            $isCurrentUser = (int) $user['id_users'] === $currentUserId;
                            $extraMenus = aptd_decode_extra_menus($user['akses_menu']);
                            $levelClass = in_array($user['level'], ['admin', 'moneta', 'direktur', 'keuangan', 'rekammedis', 'pemasaran', 'perawat'], true)
                                ? 'level-' . $user['level']
                                : 'level-default';
                            ?>
                            <tr data-user-row>
                                <td style="text-align:center"><?php echo $index + 1; ?></td>
                                <td class="user-name">
                                    <?php echo htmlspecialchars($user['nama_lengkap'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($isCurrentUser): ?><span class="user-you">Anda</span><?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($user['jabatan'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="text-align:center">
                                    <span class="user-level <?php echo htmlspecialchars($levelClass, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars(aptd_admin_user_level_label($user['level'], $levelLabels), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="user-access-cell"><?php echo $user['level'] === 'admin' ? 'Semua menu' : 'Bawaan level' . (count($extraMenus) ? ' + ' . count($extraMenus) . ' menu' : ''); ?></td>
                                <td><?php echo htmlspecialchars(aptd_admin_user_last_login($user['tgl_log'], $user['jam_log']), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <div class="user-actions">
                                        <button type="button"
                                                class="user-action-btn user-edit-btn"
                                                data-toggle="modal"
                                                data-target="#modalEditUser"
                                                data-id="<?php echo (int) $user['id_users']; ?>"
                                                data-nama="<?php echo htmlspecialchars($user['nama_lengkap'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-username="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-jabatan="<?php echo htmlspecialchars($user['jabatan'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-level="<?php echo htmlspecialchars($user['level'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-menus="<?php echo htmlspecialchars(json_encode($extraMenus), ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="glyphicon glyphicon-pencil" aria-hidden="true"></span>
                                            Edit
                                        </button>
                                        <form method="post" style="margin:0" onsubmit="return confirm('Hapus user ini?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['user_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id_users" value="<?php echo (int) $user['id_users']; ?>">
                                            <button type="submit" class="user-action-btn user-delete-btn" <?php echo $isCurrentUser ? 'disabled' : ''; ?>>
                                                <span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade user-modal" id="modalTambahUser" tabindex="-1" role="dialog" aria-labelledby="modalTambahUserLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" class="modal-content">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['user_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h2 class="modal-title" id="modalTambahUserLabel">Tambah User</h2>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label for="createNama">Nama Lengkap</label><input type="text" id="createNama" name="nama_lengkap" class="form-control" required></div>
                    <div class="form-group"><label for="createUsername">Username</label><input type="text" id="createUsername" name="username" class="form-control" minlength="4" autocomplete="off" required></div>
                    <div class="form-group"><label for="createPassword">Password</label><input type="password" id="createPassword" name="password" class="form-control" minlength="6" autocomplete="new-password" required></div>
                    <div class="form-group"><label for="createJabatan">Jabatan</label><input type="text" id="createJabatan" name="jabatan" class="form-control" required></div>
                    <div class="form-group mb-0">
                        <label for="createLevel">Level</label>
                        <select id="createLevel" name="level" class="form-control" required>
                            <?php foreach ($allowedLevels as $level): ?>
                                <option value="<?php echo htmlspecialchars($level, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(aptd_admin_user_level_label($level, $levelLabels), ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php aptd_admin_render_access_select('createAccess'); ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan User</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade user-modal" id="modalEditUser" tabindex="-1" role="dialog" aria-labelledby="modalEditUserLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" class="modal-content">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['user_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id_users" id="editUserId">
                <div class="modal-header">
                    <h2 class="modal-title" id="modalEditUserLabel">Edit User</h2>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label for="editNama">Nama Lengkap</label><input type="text" id="editNama" name="nama_lengkap" class="form-control" required></div>
                    <div class="form-group"><label for="editUsername">Username</label><input type="text" id="editUsername" name="username" class="form-control" minlength="4" autocomplete="off" required></div>
                    <div class="form-group"><label for="editPassword">Password Baru</label><input type="password" id="editPassword" name="password" class="form-control" minlength="6" autocomplete="new-password" placeholder="Kosongkan jika tidak diubah"></div>
                    <div class="form-group"><label for="editJabatan">Jabatan</label><input type="text" id="editJabatan" name="jabatan" class="form-control" required></div>
                    <div class="form-group mb-0">
                        <label for="editLevel">Level</label>
                        <select id="editLevel" name="level" class="form-control" required>
                            <?php foreach ($allowedLevels as $level): ?>
                                <option value="<?php echo htmlspecialchars($level, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(aptd_admin_user_level_label($level, $levelLabels), ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php aptd_admin_render_access_select('editAccess'); ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
    (function() {
        var searchInput = document.getElementById('userSearch');
        var countLabel = document.getElementById('userCount');
        var rows = Array.prototype.slice.call(document.querySelectorAll('[data-user-row]'));

        function filterUsers() {
            var keyword = searchInput ? searchInput.value.toLowerCase().trim() : '';
            var visible = 0;
            rows.forEach(function(row) {
                var matches = keyword === '' || row.textContent.toLowerCase().indexOf(keyword) !== -1;
                row.style.display = matches ? '' : 'none';
                if (matches) {
                    visible++;
                }
            });
            if (countLabel) {
                countLabel.textContent = visible + ' user';
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', filterUsers);
        }

        var roleMenus = <?php
            $roleMenus = [];
            foreach ($allowedLevels as $role) {
                $roleMenus[$role] = [];
                foreach (aptd_extra_menu_catalog() as $menu => $item) {
                    if (aptd_role_can_access($role, $menu)) { $roleMenus[$role][] = $menu; }
                }
            }
            echo json_encode($roleMenus, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>;
        function updateAccessSummary(prefix) {
            var level = document.getElementById(prefix + 'Level').value;
            var select = document.getElementById(prefix + 'Access');
            var base = roleMenus[level] || [];
            var extra = Array.prototype.filter.call(select.options, function(option) {
                return option.selected && base.indexOf(option.value) === -1;
            }).length;
            document.getElementById(prefix + 'AccessSummary').textContent = level === 'admin'
                ? 'Bawaan level: semua menu'
                : 'Bawaan level: ' + base.length + ' menu | Tambahan: ' + extra + ' menu';
        }
        ['create', 'edit'].forEach(function(prefix) {
            document.getElementById(prefix + 'Level').addEventListener('change', function() { updateAccessSummary(prefix); });
            document.getElementById(prefix + 'Access').addEventListener('change', function() { updateAccessSummary(prefix); });
            updateAccessSummary(prefix);
        });

        function setExtraMenus(prefix, menus) {
            var select = document.getElementById(prefix + 'Access');
            Array.prototype.forEach.call(select.options, function(option) { option.selected = menus.indexOf(option.value) !== -1; });
            if (window.jQuery && jQuery.fn.select2) { jQuery(select).trigger('change'); }
            updateAccessSummary(prefix);
        }

        Array.prototype.forEach.call(document.querySelectorAll('.user-edit-btn'), function(button) {
            button.addEventListener('click', function() {
                document.getElementById('editUserId').value = button.getAttribute('data-id') || '';
                document.getElementById('editNama').value = button.getAttribute('data-nama') || '';
                document.getElementById('editUsername').value = button.getAttribute('data-username') || '';
                document.getElementById('editJabatan').value = button.getAttribute('data-jabatan') || '';
                document.getElementById('editLevel').value = button.getAttribute('data-level') || '';
                document.getElementById('editPassword').value = '';
                setExtraMenus('edit', JSON.parse(button.getAttribute('data-menus') || '[]'));
            });
        });
        document.addEventListener('DOMContentLoaded', function() {
            if (window.jQuery && jQuery.fn.select2) {
                jQuery('.user-extra-access').each(function() {
                    jQuery(this).select2({width: '100%', placeholder: 'Pilih menu tambahan', closeOnSelect: false, dropdownParent: jQuery(this).closest('.modal')});
                    jQuery(this).on('change', function() { updateAccessSummary(this.id === 'createAccess' ? 'create' : 'edit'); });
                });
            }
            var submitted = <?php
                $submitted = null;
                if ($error !== '' && in_array(aptd_admin_post_string($_POST, 'action'), ['create', 'update'], true)) {
                    $submitted = [];
                    foreach (['action', 'id_users', 'nama_lengkap', 'username', 'jabatan', 'level'] as $key) {
                        $submitted[$key] = aptd_admin_post_string($_POST, $key);
                    }
                    $submitted['akses_menu'] = isset($_POST['akses_menu']) && is_array($_POST['akses_menu'])
                        ? array_values(array_filter($_POST['akses_menu'], 'is_string')) : [];
                }
                echo json_encode($submitted, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
            ?>;
            if (submitted && window.jQuery) {
                var prefix = submitted.action === 'create' ? 'create' : 'edit';
                ['Nama', 'Username', 'Jabatan', 'Level'].forEach(function(field) {
                    var key = field === 'Nama' ? 'nama_lengkap' : field.toLowerCase();
                    document.getElementById(prefix + field).value = submitted[key];
                });
                if (prefix === 'edit') { document.getElementById('editUserId').value = submitted.id_users; }
                setExtraMenus(prefix, submitted.akses_menu);
                jQuery(prefix === 'create' ? '#modalTambahUser' : '#modalEditUser').modal('show');
            }
        });
    })();
</script>
