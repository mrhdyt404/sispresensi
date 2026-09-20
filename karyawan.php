<?php
/**
 * Master Data Karyawan (Admin Panel)
 * Tambah Karyawan, Edit Data, Reset Password, & Hapus Karyawan
 * Sistem Presensi Karyawan GPS - Modern Dark Glossy
 */

$pageTitle = 'Kelola Data Karyawan';
require_once __DIR__ . '/config/database.php';
checkAuth('admin');

$db = getDBConnection();
$msg = null;
$msgType = 'success';

// Handle Tambah Karyawan Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $nip = trim($_POST['nip'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['admin', 'employee']) ? $_POST['role'] : 'employee';
    $department = trim($_POST['department'] ?? 'Teknologi Informasi');
    $position = trim($_POST['position'] ?? 'Staff');
    $phone = trim($_POST['phone'] ?? '');
    $photo = trim($_POST['photo'] ?? '');
    $customPass = trim($_POST['password'] ?? 'password123');

    if (!$photo) {
        $photo = 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150';
    }

    if (!$nip || !$name || !$email) {
        $msg = 'Harap lengkapi NIP, Nama Lengkap, dan Email karyawan.';
        $msgType = 'danger';
    } else {
        try {
            $check = $db->prepare("SELECT id FROM employees WHERE nip = :nip OR email = :email LIMIT 1");
            $check->execute([':nip' => $nip, ':email' => $email]);
            if ($check->fetch()) {
                $msg = 'NIP atau Email sudah digunakan oleh karyawan lain.';
                $msgType = 'danger';
            } else {
                $hashedPass = password_hash($customPass ?: 'password123', PASSWORD_BCRYPT);
                $ins = $db->prepare("
                    INSERT INTO employees (nip, name, email, password, role, department, position, phone, photo)
                    VALUES (:nip, :name, :email, :pass, :role, :dept, :pos, :phone, :photo)
                ");
                $ins->execute([
                    ':nip' => $nip,
                    ':name' => $name,
                    ':email' => $email,
                    ':pass' => $hashedPass,
                    ':role' => $role,
                    ':dept' => $department,
                    ':pos' => $position,
                    ':phone' => $phone,
                    ':photo' => $photo
                ]);
                $msg = "Karyawan $name berhasil ditambahkan!";
                $msgType = 'success';
            }
        } catch (Exception $e) {
            $msg = 'Gagal menyimpan data: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// Handle Update / Edit Data Karyawan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $empId = filter_var($_POST['employee_id'] ?? 0, FILTER_VALIDATE_INT);
    $nip = trim($_POST['nip'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['admin', 'employee']) ? $_POST['role'] : 'employee';
    $department = trim($_POST['department'] ?? 'Teknologi Informasi');
    $position = trim($_POST['position'] ?? 'Staff');
    $phone = trim($_POST['phone'] ?? '');
    $photo = trim($_POST['photo'] ?? '');

    if (!$empId || !$nip || !$name || !$email) {
        $msg = 'Harap isi semua kolom wajib untuk memperbarui karyawan.';
        $msgType = 'danger';
    } else {
        try {
            $check = $db->prepare("SELECT id FROM employees WHERE (nip = :nip OR email = :email) AND id != :id LIMIT 1");
            $check->execute([':nip' => $nip, ':email' => $email, ':id' => $empId]);
            if ($check->fetch()) {
                $msg = 'NIP atau Email sudah digunakan karyawan lain.';
                $msgType = 'danger';
            } else {
                $upd = $db->prepare("
                    UPDATE employees SET
                        nip = :nip, name = :name, email = :email, role = :role,
                        department = :dept, position = :pos, phone = :phone, photo = :photo
                    WHERE id = :id
                ");
                $upd->execute([
                    ':nip' => $nip,
                    ':name' => $name,
                    ':email' => $email,
                    ':role' => $role,
                    ':dept' => $department,
                    ':pos' => $position,
                    ':phone' => $phone,
                    ':photo' => $photo,
                    ':id' => $empId
                ]);
                $msg = "Data karyawan $name berhasil diperbarui!";
                $msgType = 'success';
            }
        } catch (Exception $e) {
            $msg = 'Gagal memperbarui data: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// Handle Reset Password Karyawan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $empId = filter_var($_POST['employee_id'] ?? 0, FILTER_VALIDATE_INT);
    $newPass = trim($_POST['new_password'] ?? '');

    if (!$empId || !$newPass) {
        $msg = 'Password baru wajib diisi.';
        $msgType = 'danger';
    } else {
        $hashed = password_hash($newPass, PASSWORD_BCRYPT);
        $updPass = $db->prepare("UPDATE employees SET password = :pass WHERE id = :id");
        $updPass->execute([':pass' => $hashed, ':id' => $empId]);
        $msg = 'Kata sandi karyawan berhasil direset!';
        $msgType = 'success';
    }
}

// Handle Hapus Karyawan
if (isset($_GET['delete_id'])) {
    $delId = filter_var($_GET['delete_id'], FILTER_VALIDATE_INT);
    if ($delId && $delId !== (int)($_SESSION['user_id'] ?? 0)) {
        $del = $db->prepare("DELETE FROM employees WHERE id = :id");
        $del->execute([':id' => $delId]);
        $msg = 'Data karyawan berhasil dihapus dari sistem.';
        $msgType = 'success';
    } elseif ($delId === (int)($_SESSION['user_id'] ?? 0)) {
        $msg = 'Anda tidak dapat menghapus akun Anda sendiri.';
        $msgType = 'danger';
    }
}

// Filter Pencarian & Departemen
$search = trim($_GET['search'] ?? '');
$deptFilter = trim($_GET['dept'] ?? '');

$sql = "SELECT * FROM employees WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (name LIKE :search OR nip LIKE :search OR email LIKE :search OR position LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($deptFilter) {
    $sql .= " AND department = :dept";
    $params[':dept'] = $deptFilter;
}
$sql .= " ORDER BY role ASC, name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// List Departemen
$departments = $db->query("SELECT DISTINCT department FROM employees ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);

// Stats Ringkasan
$totalAll = count($employees);
$totalAdmin = 0;
$totalStaff = 0;
foreach ($employees as $e) {
    if ($e['role'] === 'admin') $totalAdmin++;
    else $totalStaff++;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">

    <!-- Page Header Banner -->
    <div class="glass-panel p-6 sm:p-8 relative overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 relative z-10">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                    <span class="text-xs font-semibold text-cyan-400 uppercase tracking-wider">Panel Administrasi SDM</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Manajemen Data Karyawan</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1 max-w-2xl">
                    Tambah karyawan baru, perbarui data jabatan & departemen, reset kata sandi, dan kelola hak akses sistem.
                </p>
            </div>

            <button type="button" onclick="openAddModal()" 
                    class="btn-glossy btn-glossy-cyan text-xs py-2.5 px-4 flex items-center gap-2 self-start sm:self-auto">
                <i class="fa-solid fa-user-plus text-sm"></i> Tambah Karyawan Baru
            </button>
        </div>
    </div>

    <!-- Alert Notifikasi -->
    <?php if ($msg): ?>
        <div class="glass-card p-4 border <?= $msgType === 'success' ? 'border-emerald-500/30 bg-emerald-950/40 text-emerald-300' : 'border-rose-500/30 bg-rose-950/40 text-rose-300' ?> rounded-xl flex items-center gap-3">
            <i class="fa-solid <?= $msgType === 'success' ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400' ?> text-lg"></i>
            <span class="text-xs sm:text-sm font-medium"><?= htmlspecialchars($msg) ?></span>
        </div>
    <?php endif; ?>

    <!-- Filter & Search Bar -->
    <div class="glass-panel p-5">
        <form action="<?= BASE_URL ?>/karyawan.php" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
            
            <div class="lg:col-span-5">
                <label class="block text-[11px] font-medium text-slate-400 mb-1">Cari Karyawan</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Ketik nama, NIP, email, atau jabatan..." class="glass-input w-full text-xs">
            </div>

            <div class="lg:col-span-4">
                <label class="block text-[11px] font-medium text-slate-400 mb-1">Filter Departemen</label>
                <select name="dept" class="glass-input w-full text-xs">
                    <option value="">Semua Departemen</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept) ?>" <?= $deptFilter === $dept ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lg:col-span-3 flex gap-2">
                <button type="submit" class="btn-glossy btn-glossy-cyan text-xs py-2 px-4 w-full flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-magnifying-glass"></i> Cari
                </button>
                <a href="<?= BASE_URL ?>/karyawan.php" class="btn-glossy btn-glossy-secondary text-xs py-2 px-3 text-slate-400 hover:text-white" title="Reset Filter">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>

        </form>
    </div>

    <!-- Employee Grid Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php if (empty($employees)): ?>
            <div class="col-span-full glass-panel p-12 text-center text-slate-500">
                <i class="fa-solid fa-users-slash text-4xl mb-2 block text-slate-600"></i>
                <div class="text-sm font-semibold text-slate-400">Tidak ada data karyawan yang sesuai dengan kriteria pencarian.</div>
            </div>
        <?php else: ?>
            <?php foreach ($employees as $emp): ?>
                <?php $isSelf = ($emp['id'] == $_SESSION['user_id']); ?>
                <div class="glass-panel p-5 relative group transition-all duration-300 hover:border-cyan-500/40 <?= $isSelf ? 'ring-2 ring-cyan-500/50 bg-slate-900/90' : '' ?>">
                    
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3.5">
                            <img src="<?= htmlspecialchars($emp['photo'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150') ?>" 
                                 alt="<?= htmlspecialchars($emp['name']) ?>" 
                                 class="w-13 h-13 sm:w-14 sm:h-14 rounded-2xl object-cover ring-2 ring-white/10 shadow-lg group-hover:ring-cyan-400 transition-all flex-shrink-0">
                            
                            <div>
                                <div class="text-sm font-bold text-white group-hover:text-cyan-300 transition-colors">
                                    <?= htmlspecialchars($emp['name']) ?>
                                </div>
                                <div class="text-xs text-slate-400 font-mono mt-0.5">
                                    <?= htmlspecialchars($emp['nip']) ?>
                                </div>
                                <div class="mt-1">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= $emp['role'] === 'admin' ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' ?>">
                                        <?= strtoupper($emp['role']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <?php if ($isSelf): ?>
                            <span class="badge-glossy badge-glossy-info text-[9px] py-0.5 px-2">Anda</span>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 pt-3 border-t border-white/10 space-y-1.5 text-xs text-slate-400">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Jabatan:</span>
                            <span class="font-medium text-slate-300 truncate max-w-[160px]"><?= htmlspecialchars($emp['position']) ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Departemen:</span>
                            <span class="font-medium text-slate-300 truncate max-w-[160px]"><?= htmlspecialchars($emp['department']) ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Email:</span>
                            <span class="font-mono text-cyan-400 truncate max-w-[160px]"><?= htmlspecialchars($emp['email']) ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Telepon:</span>
                            <span class="font-mono text-slate-300"><?= htmlspecialchars($emp['phone'] ?: '-') ?></span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-4 pt-3 border-t border-white/5 grid grid-cols-3 gap-2">
                        <!-- Edit Button -->
                        <button type="button" 
                                onclick="openEditModal(<?= htmlspecialchars(json_encode($emp), ENT_QUOTES, 'UTF-8') ?>)"
                                class="btn-glossy btn-glossy-secondary text-xs py-1.5 px-2 text-cyan-300 border-cyan-500/20 hover:border-cyan-400 flex items-center justify-center gap-1">
                            <i class="fa-solid fa-pen-to-square text-xs"></i> Edit
                        </button>

                        <!-- Reset Password Button -->
                        <button type="button" 
                                onclick="openResetModal(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($emp['name'])) ?>')"
                                class="btn-glossy btn-glossy-secondary text-xs py-1.5 px-2 text-amber-300 border-amber-500/20 hover:border-amber-400 flex items-center justify-center gap-1">
                            <i class="fa-solid fa-key text-xs"></i> Sandi
                        </button>

                        <!-- Delete Button -->
                        <?php if (!$isSelf): ?>
                            <a href="<?= BASE_URL ?>/karyawan.php?delete_id=<?= $emp['id'] ?>" 
                               onclick="return confirm('Apakah Anda yakin ingin menghapus karyawan <?= htmlspecialchars(addslashes($emp['name'])) ?> beserta seluruh riwayat presensinya?')"
                               class="btn-glossy btn-glossy-secondary text-xs py-1.5 px-2 text-rose-400 border-rose-500/20 hover:border-rose-400 flex items-center justify-center gap-1">
                                <i class="fa-regular fa-trash-can text-xs"></i> Hapus
                            </a>
                        <?php else: ?>
                            <span class="text-[10px] text-slate-600 flex items-center justify-center italic">Aktif</span>
                        <?php endif; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- Modal 1: Tambah Karyawan Baru -->
<div id="addModal" class="hidden fixed inset-0 z-50 overflow-y-auto glass-modal-backdrop flex items-center justify-center p-4">
    <div class="glass-modal max-w-lg w-full p-6 space-y-4 modal-enter relative">
        <div class="flex items-center justify-between border-b border-white/10 pb-3">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-cyan-400"></i> Tambah Karyawan Baru
            </h3>
            <button type="button" onclick="closeAddModal()" class="text-slate-400 hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form action="<?= BASE_URL ?>/karyawan.php" method="POST" class="space-y-3.5">
            <input type="hidden" name="action" value="create">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-medium text-slate-400 mb-1">NIP Pegawai <span class="text-rose-400">*</span></label>
                    <input type="text" name="nip" required placeholder="EMP-2026-..." class="glass-input w-full text-xs font-mono">
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-slate-400 mb-1">Peran Akun</label>
                    <select name="role" class="glass-input w-full text-xs">
                        <option value="employee">Karyawan</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-medium text-slate-400 mb-1">Nama Lengkap <span class="text-rose-400">*</span></label>
                <input type="text" name="name" required placeholder="Nama lengkap karyawan..." class="glass-input w-full text-xs">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-medium text-slate-400 mb-1">Email <span class="text-rose-400">*</span></label>
                    <input type="email" name="email" required placeholder="email@perusahaan.com" class="glass-input w-full text-xs">
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-slate-400 mb-1">No. WhatsApp / HP</label>
                    <input type="text" name="phone" placeholder="08xxxxxxxxxx" class="glass-input w-full text-xs font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-medium text-slate-400 mb-1">Departemen</label>
                    <input type="text" name="department" required placeholder="Operasional / IT / Sales" class="glass-input w-full text-xs">
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-slate-400 mb-1">Jabatan</label>
                    <input type="text" name="position" required placeholder="Staff Officer" class="glass-input w-full text-xs">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-medium text-slate-400 mb-1">Kata Sandi Awal (Default: password123)</label>
                <input type="text" name="password" placeholder="password123" class="glass-input w-full text-xs font-mono">
            </div>

            <div>
                <label class="block text-[11px] font-medium text-slate-400 mb-1">URL Foto Profil (Opsional)</label>
                <input type="url" name="photo" placeholder="https://..." class="glass-input w-full text-xs">
            </div>

            <div class="pt-3 flex justify-end gap-2 border-t border-white/10">
                <button type="button" onclick="closeAddModal()" class="btn-glossy btn-glossy-secondary text-xs py-2 px-4">
                    Batal
                </button>
                <button type="submit" class="btn-glossy btn-glossy-cyan text-xs py-2 px-4">
                    Simpan Karyawan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Edit / Ubah Data Karyawan -->
<div id="editModal" class="hidden fixed inset-0 z-50 overflow-y-auto glass-modal-backdrop flex items-center justify-center p-4">
    <div class="glass-modal max-w-lg w-full p-6 space-y-4 modal-enter relative">
        <div class="flex items-center justify-between border-b border-white/10 pb-3">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-user-pen text-cyan-400"></i> Edit Data Karyawan
            </h3>
            <button type="button" onclick="closeEditModal()" class="text-slate-400 hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form action="<?= BASE_URL ?>/karyawan.php" method="POST" class="space-y-3.5">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="employee_id" id="editEmpId">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-medium text-slate-400 mb-1">NIP Pegawai <span class="text-rose-400">*</span></label>
                    <input type="text" name="nip" id="editNip" required class="glass-input w-full text-xs font-mono">
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-slate-400 mb-1">Peran Akun</label>
                    <select name="role" id="editRole" class="glass-input w-full text-xs">
                        <option value="employee">Karyawan</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-medium text-slate-400 mb-1">Nama Lengkap <span class="text-rose-400">*</span></label>
                <input type="text" name="name" id="editName" required class="glass-input w-full text-xs">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-medium text-slate-400 mb-1">Email <span class="text-rose-400">*</span></label>
                    <input type="email" name="email" id="editEmail" required class="glass-input w-full text-xs">
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-slate-400 mb-1">No. WhatsApp / HP</label>
                    <input type="text" name="phone" id="editPhone" class="glass-input w-full text-xs font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-medium text-slate-400 mb-1">Departemen</label>
                    <input type="text" name="department" id="editDept" required class="glass-input w-full text-xs">
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-slate-400 mb-1">Jabatan</label>
                    <input type="text" name="position" id="editPosition" required class="glass-input w-full text-xs">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-medium text-slate-400 mb-1">URL Foto Profil</label>
                <input type="url" name="photo" id="editPhoto" class="glass-input w-full text-xs">
            </div>

            <div class="pt-3 flex justify-end gap-2 border-t border-white/10">
                <button type="button" onclick="closeEditModal()" class="btn-glossy btn-glossy-secondary text-xs py-2 px-4">
                    Batal
                </button>
                <button type="submit" class="btn-glossy btn-glossy-cyan text-xs py-2 px-4">
                    Perbarui Data
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Reset Kata Sandi -->
<div id="resetModal" class="hidden fixed inset-0 z-50 overflow-y-auto glass-modal-backdrop flex items-center justify-center p-4">
    <div class="glass-modal max-w-sm w-full p-6 space-y-4 modal-enter relative">
        <div class="flex items-center justify-between border-b border-white/10 pb-3">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-key text-amber-400"></i> Reset Password
            </h3>
            <button type="button" onclick="closeResetModal()" class="text-slate-400 hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form action="<?= BASE_URL ?>/karyawan.php" method="POST" class="space-y-3.5">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="employee_id" id="resetEmpId">

            <p class="text-xs text-slate-300">
                Setel ulang kata sandi untuk <strong class="text-white" id="resetEmpName">...</strong>:
            </p>

            <div>
                <label class="block text-[11px] font-medium text-slate-400 mb-1">Kata Sandi Baru</label>
                <input type="text" name="new_password" required value="password123" class="glass-input w-full text-xs font-mono">
                <span class="text-[10px] text-slate-500">Default: password123</span>
            </div>

            <div class="pt-2 flex justify-end gap-2">
                <button type="button" onclick="closeResetModal()" class="btn-glossy btn-glossy-secondary text-xs py-1.5 px-3">
                    Batal
                </button>
                <button type="submit" class="btn-glossy btn-glossy-cyan text-xs py-1.5 px-3">
                    Reset Sekarang
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('addModal').classList.remove('hidden');
    }
    function closeAddModal() {
        document.getElementById('addModal').classList.add('hidden');
    }

    function openEditModal(emp) {
        document.getElementById('editEmpId').value = emp.id;
        document.getElementById('editNip').value = emp.nip;
        document.getElementById('editName').value = emp.name;
        document.getElementById('editEmail').value = emp.email;
        document.getElementById('editRole').value = emp.role;
        document.getElementById('editDept').value = emp.department;
        document.getElementById('editPosition').value = emp.position;
        document.getElementById('editPhone').value = emp.phone || '';
        document.getElementById('editPhoto').value = emp.photo || '';
        document.getElementById('editModal').classList.remove('hidden');
    }
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    function openResetModal(id, name) {
        document.getElementById('resetEmpId').value = id;
        document.getElementById('resetEmpName').textContent = name;
        document.getElementById('resetModal').classList.remove('hidden');
    }
    function closeResetModal() {
        document.getElementById('resetModal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
