<?php
/**
 * Status Kehadiran Rekan Kerja Hari Ini
 * Memantau apakah rekan kantor sudah masuk kerja atau belum
 * Sistem Presensi Karyawan GPS - Modern Dark Glossy
 */

$pageTitle = 'Status Rekan Kerja Hari Ini';
require_once __DIR__ . '/config/database.php';
checkAuth();

$db = getDBConnection();
$today = date('Y-m-d');
$settings = getOfficeSettings();

// Filter & Pencarian
$search = trim($_GET['search'] ?? '');
$deptFilter = trim($_GET['dept'] ?? '');
$statusFilter = trim($_GET['status'] ?? ''); // 'hadir', 'belum', ''

// Ambil seluruh departemen yang ada untuk dropdown filter
$departments = $db->query("SELECT DISTINCT department FROM employees WHERE is_active = 1 ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);

// Query seluruh karyawan aktif digabungkan dengan data presensi hari ini
$sql = "
    SELECT e.id as employee_id, e.nip, e.name, e.department, e.position, e.photo, e.phone, e.email,
           a.id as attendance_id, a.check_in_time, a.check_out_time, a.check_in_status, a.check_in_distance, a.notes
    FROM employees e
    LEFT JOIN attendances a ON e.id = a.employee_id AND a.date = :today
    WHERE e.is_active = 1
";
$params = [':today' => $today];

if ($search) {
    $sql .= " AND (e.name LIKE :search OR e.nip LIKE :search OR e.position LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($deptFilter) {
    $sql .= " AND e.department = :dept";
    $params[':dept'] = $deptFilter;
}

$sql .= " ORDER BY (a.check_in_time IS NOT NULL) DESC, a.check_in_time ASC, e.name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$teamMembers = $stmt->fetchAll();

// Filter status hadir / belum di PHP
if ($statusFilter === 'hadir') {
    $teamMembers = array_filter($teamMembers, fn($m) => !empty($m['check_in_time']));
} elseif ($statusFilter === 'belum') {
    $teamMembers = array_filter($teamMembers, fn($m) => empty($m['check_in_time']));
}

// Hitung Statistik Hari Ini
$totalEmployees = $db->query("SELECT COUNT(*) FROM employees WHERE is_active = 1")->fetchColumn();
$totalHadir = $db->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendances WHERE date = :today AND check_in_time IS NOT NULL");
$totalHadir->execute([':today' => $today]);
$countHadir = $totalHadir->fetchColumn();
$countBelum = max(0, $totalEmployees - $countHadir);

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">

    <!-- Page Header Banner -->
    <div class="glass-panel p-6 sm:p-8 relative overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 relative z-10">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                    <span class="text-xs font-semibold text-cyan-400 uppercase tracking-wider">Live Status Kantor</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Status Kehadiran Rekan Kerja</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1 max-w-2xl">
                    Pantau siapa saja rekan kerja Anda yang sudah masuk kantor atau belum hadir pada hari ini, <strong class="text-slate-200"><?= formatIndonesianDate($today) ?></strong>.
                </p>
            </div>

            <!-- Jam Digital Kantor -->
            <div class="glass-card p-3 px-4 rounded-xl flex items-center gap-3 self-start sm:self-auto border border-white/10">
                <div class="w-9 h-9 rounded-lg bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div>
                    <div class="text-[10px] text-slate-400 font-medium">Batas Masuk Kerja</div>
                    <div class="text-xs font-bold font-mono text-white">
                        <?= substr($settings['work_start_time'], 0, 5) ?> WIB (+<?= (int)$settings['late_tolerance_minutes'] ?>m)
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Quick Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        
        <!-- Total Karyawan -->
        <div class="glass-card p-4 flex items-center justify-between">
            <div>
                <div class="text-xs text-slate-400 font-medium">Total Seluruh Karyawan</div>
                <div class="text-2xl font-black font-mono text-white mt-1"><?= $totalEmployees ?></div>
            </div>
            <div class="w-11 h-11 rounded-xl bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center text-indigo-400 text-lg">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>

        <!-- Sudah Masuk (Hadir) -->
        <div class="glass-card p-4 flex items-center justify-between">
            <div>
                <div class="text-xs text-slate-400 font-medium">Sudah Masuk Kerja</div>
                <div class="text-2xl font-black font-mono text-emerald-400 mt-1"><?= $countHadir ?></div>
            </div>
            <div class="w-11 h-11 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 text-lg">
                <i class="fa-solid fa-user-check"></i>
            </div>
        </div>

        <!-- Belum Masuk -->
        <div class="glass-card p-4 flex items-center justify-between">
            <div>
                <div class="text-xs text-slate-400 font-medium">Belum Hadir / Belum Absen</div>
                <div class="text-2xl font-black font-mono text-rose-400 mt-1"><?= $countBelum ?></div>
            </div>
            <div class="w-11 h-11 rounded-xl bg-rose-500/10 border border-rose-500/30 flex items-center justify-center text-rose-400 text-lg">
                <i class="fa-solid fa-user-xmark"></i>
            </div>
        </div>

    </div>

    <!-- Filter & Search Bar -->
    <div class="glass-panel p-5">
        <form action="<?= BASE_URL ?>/tim.php" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3.5 items-end">
            
            <!-- Filter Status Tab (4 Cols) -->
            <div class="lg:col-span-4">
                <label class="block text-[11px] font-medium text-slate-400 mb-1.5">Filter Kehadiran</label>
                <div class="grid grid-cols-3 gap-1 bg-slate-900/60 p-1 rounded-xl border border-white/5">
                    <a href="<?= BASE_URL ?>/tim.php?status=&dept=<?= urlencode($deptFilter) ?>&search=<?= urlencode($search) ?>" 
                       class="text-center py-1.5 text-xs font-semibold rounded-lg transition-all <?= empty($statusFilter) ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 shadow-sm' : 'text-slate-400 hover:text-white' ?>">
                        Semua
                    </a>
                    <a href="<?= BASE_URL ?>/tim.php?status=hadir&dept=<?= urlencode($deptFilter) ?>&search=<?= urlencode($search) ?>" 
                       class="text-center py-1.5 text-xs font-semibold rounded-lg transition-all <?= $statusFilter === 'hadir' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 shadow-sm' : 'text-slate-400 hover:text-white' ?>">
                        🟢 Hadir (<?= $countHadir ?>)
                    </a>
                    <a href="<?= BASE_URL ?>/tim.php?status=belum&dept=<?= urlencode($deptFilter) ?>&search=<?= urlencode($search) ?>" 
                       class="text-center py-1.5 text-xs font-semibold rounded-lg transition-all <?= $statusFilter === 'belum' ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30 shadow-sm' : 'text-slate-400 hover:text-white' ?>">
                        🔴 Belum (<?= $countBelum ?>)
                    </a>
                </div>
            </div>

            <!-- Filter Departemen (4 Cols) -->
            <div class="lg:col-span-4">
                <label class="block text-[11px] font-medium text-slate-400 mb-1">Departemen</label>
                <select name="dept" class="glass-input w-full text-xs">
                    <option value="">Semua Departemen</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept) ?>" <?= $deptFilter === $dept ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Search Box (3 Cols) -->
            <div class="lg:col-span-3">
                <label class="block text-[11px] font-medium text-slate-400 mb-1">Cari Nama / NIP</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Ketik nama atau jabatan..." class="glass-input w-full text-xs">
            </div>

            <!-- Submit Button (1 Col) -->
            <div class="lg:col-span-1 flex gap-2">
                <button type="submit" class="btn-glossy btn-glossy-cyan text-xs py-2 px-3 w-full flex items-center justify-center" title="Terapkan Filter">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </div>

        </form>
    </div>

    <!-- Team Member Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php if (empty($teamMembers)): ?>
            <div class="col-span-full glass-panel p-12 text-center text-slate-500">
                <i class="fa-solid fa-user-slash text-4xl mb-2 block text-slate-600"></i>
                <div class="text-sm font-semibold text-slate-400">Tidak ada rekan kerja yang cocok dengan filter pencarian.</div>
                <a href="<?= BASE_URL ?>/tim.php" class="text-xs text-cyan-400 mt-2 inline-block hover:underline">Reset Filter</a>
            </div>
        <?php else: ?>
            <?php foreach ($teamMembers as $member): ?>
                <?php 
                    $hasEntered = !empty($member['check_in_time']);
                    $isSelf = ($member['employee_id'] == $_SESSION['user_id']);
                ?>
                <div class="glass-card p-4 relative group transition-all duration-300 <?= $isSelf ? 'ring-2 ring-cyan-400/50 bg-slate-900/80' : '' ?>">
                    
                    <div class="flex items-start gap-3.5">
                        <div class="relative flex-shrink-0">
                            <img src="<?= htmlspecialchars($member['photo'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150') ?>" 
                                 alt="<?= htmlspecialchars($member['name']) ?>" 
                                 class="w-12 h-12 rounded-xl object-cover ring-2 <?= $hasEntered ? 'ring-emerald-400/60' : 'ring-white/10' ?>">
                            <span class="absolute -bottom-1 -right-1 w-3.5 h-3.5 rounded-full border-2 border-slate-900 <?= $hasEntered ? 'bg-emerald-400' : 'bg-slate-600' ?>"></span>
                        </div>

                        <div class="flex-grow min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <div class="text-xs font-bold text-white truncate group-hover:text-cyan-300 transition-colors">
                                    <?= htmlspecialchars($member['name']) ?>
                                    <?php if ($isSelf): ?>
                                        <span class="text-[10px] text-cyan-400 font-normal">(Anda)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-[10px] text-slate-400 font-mono">
                                <?= htmlspecialchars($member['nip']) ?>
                            </div>
                            <div class="text-[11px] text-slate-300 font-medium truncate mt-0.5">
                                <?= htmlspecialchars($member['position']) ?>
                            </div>
                            <div class="text-[10px] text-slate-500 truncate">
                                <?= htmlspecialchars($member['department']) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Status Pill -->
                    <div class="mt-3.5 pt-3 border-t border-white/5 flex items-center justify-between text-xs">
                        <?php if ($hasEntered): ?>
                            <div class="flex items-center gap-1.5 text-emerald-400 font-semibold font-mono">
                                <i class="fa-solid fa-circle-check text-xs"></i>
                                <span>Masuk: <?= substr($member['check_in_time'], 0, 5) ?> WIB</span>
                            </div>

                            <?php if ($member['check_in_status'] === 'on_time'): ?>
                                <span class="badge-glossy badge-glossy-success text-[10px] py-0.5 px-2">Tepat Waktu</span>
                            <?php elseif ($member['check_in_status'] === 'late'): ?>
                                <span class="badge-glossy badge-glossy-warning text-[10px] py-0.5 px-2">Terlambat</span>
                            <?php else: ?>
                                <span class="badge-glossy badge-glossy-danger text-[10px] py-0.5 px-2">Luar Radius</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="flex items-center gap-1.5 text-slate-500">
                                <i class="fa-regular fa-clock text-xs"></i>
                                <span>Belum Masuk</span>
                            </div>
                            <span class="badge-glossy text-[10px] py-0.5 px-2 bg-slate-800 text-slate-400 border border-white/5">Belum Absen</span>
                        <?php endif; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
