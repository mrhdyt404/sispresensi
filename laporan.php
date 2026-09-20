<?php
/**
 * Laporan Presensi Karyawan & Rekapitulasi GPS
 * Sistem Presensi Karyawan GPS - Modern Dark Glossy
 */

require_once __DIR__ . '/config/database.php';

// Jika karyawan biasa membuka halaman ini, alihkan ke riwayat presensi pribadi
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'employee') {
    header('Location: ' . BASE_URL . '/riwayat.php');
    exit;
}
checkAuth('admin');

$db = getDBConnection();
$settings = getOfficeSettings();

// Preset Filter Periode: today, week, month, custom
$preset = $_GET['preset'] ?? 'month';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

if ($preset === 'today') {
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d');
} elseif ($preset === 'week') {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
} elseif ($preset === 'month') {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
} elseif ($preset === 'custom' && $startDate && $endDate) {
    // Gunakan custom
} else {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
    $preset = 'month';
}

$filterEmp = filter_var($_GET['employee_id'] ?? '', FILTER_VALIDATE_INT);
$filterStatus = trim($_GET['status'] ?? '');

// Handle Ekspor CSV Langsung jika diminta
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $whereSql = "WHERE a.date BETWEEN :start AND :end";
    $params = [':start' => $startDate, ':end' => $endDate];

    if ($filterEmp) {
        $whereSql .= " AND a.employee_id = :emp_id";
        $params[':emp_id'] = $filterEmp;
    }
    if ($filterStatus && in_array($filterStatus, ['on_time', 'late', 'out_of_radius'])) {
        $whereSql .= " AND a.check_in_status = :status";
        $params[':status'] = $filterStatus;
    }

    $exportQuery = "
        SELECT a.date, e.nip, e.name, e.department, e.position,
               a.check_in_time, a.check_out_time, a.check_in_distance, a.check_in_status,
               a.check_in_lat, a.check_in_lng, a.notes, a.ip_address
        FROM attendances a
        JOIN employees e ON a.employee_id = e.id
        $whereSql
        ORDER BY a.date DESC, a.check_in_time DESC
    ";
    $stmtExp = $db->prepare($exportQuery);
    $stmtExp->execute($params);
    $recordsExp = $stmtExp->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_Presensi_' . $startDate . '_sd_' . $endDate . '.csv');

    $output = fopen('php://output', 'w');
    // Output BOM untuk Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header Kolom CSV
    fputcsv($output, [
        'Tanggal', 'NIP', 'Nama Karyawan', 'Departemen', 'Jabatan',
        'Jam Masuk', 'Jam Pulang', 'Jarak GPS (m)', 'Status Presensi',
        'Latitude', 'Longitude', 'Catatan', 'IP Address'
    ]);

    foreach ($recordsExp as $r) {
        $statusStr = ($r['check_in_status'] === 'on_time') ? 'Tepat Waktu' : (($r['check_in_status'] === 'late') ? 'Terlambat' : 'Di Luar Radius');
        fputcsv($output, [
            $r['date'],
            $r['nip'],
            $r['name'],
            $r['department'],
            $r['position'],
            $r['check_in_time'] ?: '-',
            $r['check_out_time'] ?: '-',
            $r['check_in_distance'] ?: 0,
            $statusStr,
            $r['check_in_lat'] ?: '-',
            $r['check_in_lng'] ?: '-',
            $r['notes'] ?: '-',
            $r['ip_address'] ?: '-'
        ]);
    }
    fclose($output);
    exit;
}

// Query Data Laporan Terfilter
$whereClause = "WHERE a.date BETWEEN :start AND :end";
$queryParams = [':start' => $startDate, ':end' => $endDate];

if ($filterEmp) {
    $whereClause .= " AND a.employee_id = :emp_id";
    $queryParams[':emp_id'] = $filterEmp;
}
if ($filterStatus && in_array($filterStatus, ['on_time', 'late', 'out_of_radius'])) {
    $whereClause .= " AND a.check_in_status = :status";
    $queryParams[':status'] = $filterStatus;
}

$query = "
    SELECT a.*, e.name, e.nip, e.department, e.position, e.photo as emp_photo
    FROM attendances a
    JOIN employees e ON a.employee_id = e.id
    $whereClause
    ORDER BY a.date DESC, a.check_in_time DESC
";
$stmt = $db->prepare($query);
$stmt->execute($queryParams);
$attendances = $stmt->fetchAll();

// Ambil List Karyawan untuk Dropdown Filter
$employeesList = $db->query("SELECT id, nip, name, department FROM employees ORDER BY name ASC")->fetchAll();

// Kalkulasi Statistik KPI Ringkasan
$totalCount = count($attendances);
$onTimeCount = 0;
$lateCount = 0;
$outOfRadiusCount = 0;
$totalDistance = 0;

foreach ($attendances as $row) {
    if ($row['check_in_status'] === 'on_time') $onTimeCount++;
    elseif ($row['check_in_status'] === 'late') $lateCount++;
    elseif ($row['check_in_status'] === 'out_of_radius') $outOfRadiusCount++;

    $totalDistance += (float)($row['check_in_distance'] ?? 0);
}
$avgDistance = $totalCount > 0 ? round($totalDistance / $totalCount, 1) : 0;

$pageTitle = 'Laporan Presensi Karyawan';
require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">

    <!-- Top Report Header Banner -->
    <div class="glass-panel p-6 sm:p-8 relative overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 relative z-10">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                    <span class="text-xs font-semibold text-cyan-400 uppercase tracking-wider">Rekapitulasi Kehadiran & Audit Geolocation</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Laporan Presensi Karyawan</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1 max-w-2xl">
                    Pantau riwayat absensi masuk & pulang, akurasi jarak GPS, bukti foto selfie, dan kepatuhan radius toleransi <?= (int)$settings['radius_meters'] ?> meter kantor.
                </p>
            </div>

            <!-- Export & Print Actions -->
            <div class="flex flex-wrap items-center gap-2.5 self-start sm:self-auto no-print">
                <a href="<?= BASE_URL ?>/laporan.php?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" 
                   class="btn-glossy btn-glossy-secondary text-xs py-2 px-3.5 flex items-center gap-2 text-emerald-400 border-emerald-500/30 hover:border-emerald-500/50">
                    <i class="fa-solid fa-file-excel text-sm"></i>
                    <span>Ekspor CSV / Excel</span>
                </a>
                <button type="button" onclick="window.print()" 
                        class="btn-glossy btn-glossy-secondary text-xs py-2 px-3.5 flex items-center gap-2 text-cyan-400 border-cyan-500/30 hover:border-cyan-500/50">
                    <i class="fa-solid fa-print text-sm"></i>
                    <span>Cetak Laporan</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Print Header Only (Visible on window.print) -->
    <div class="hidden print:block mb-6 text-black border-b border-black pb-4">
        <h2 class="text-2xl font-bold"><?= htmlspecialchars($settings['office_name']) ?></h2>
        <p class="text-sm"><?= htmlspecialchars($settings['office_address']) ?></p>
        <h3 class="text-lg font-semibold mt-2">REKAPITULASI PRESENSI KEHADIRAN KARYAWAN</h3>
        <p class="text-xs">Periode: <?= formatIndonesianDate($startDate) ?> s/d <?= formatIndonesianDate($endDate) ?></p>
    </div>

    <!-- KPI Statistics Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
        
        <!-- Total Absensi -->
        <div class="glass-card p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs text-slate-400 font-medium">Total Kehadiran</span>
                <span class="p-1 rounded bg-indigo-500/10 text-indigo-400"><i class="fa-solid fa-calendar-check text-xs"></i></span>
            </div>
            <div class="mt-2 flex items-baseline gap-1">
                <span class="text-2xl font-black font-mono text-white"><?= $totalCount ?></span>
                <span class="text-[11px] text-slate-400">Presensi</span>
            </div>
        </div>

        <!-- Tepat Waktu -->
        <div class="glass-card p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs text-slate-400 font-medium">Tepat Waktu</span>
                <span class="p-1 rounded bg-emerald-500/10 text-emerald-400"><i class="fa-solid fa-clock-check text-xs"></i></span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-black font-mono text-emerald-400"><?= $onTimeCount ?></span>
                <span class="text-[10px] text-emerald-500 font-bold"><?= $totalCount > 0 ? round(($onTimeCount / $totalCount) * 100) : 0 ?>%</span>
            </div>
        </div>

        <!-- Terlambat -->
        <div class="glass-card p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs text-slate-400 font-medium">Terlambat</span>
                <span class="p-1 rounded bg-amber-500/10 text-amber-400"><i class="fa-solid fa-hourglass-half text-xs"></i></span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-black font-mono text-amber-400"><?= $lateCount ?></span>
                <span class="text-[10px] text-amber-500 font-bold"><?= $totalCount > 0 ? round(($lateCount / $totalCount) * 100) : 0 ?>%</span>
            </div>
        </div>

        <!-- Di Luar Radius -->
        <div class="glass-card p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs text-slate-400 font-medium">Di Luar Radius</span>
                <span class="p-1 rounded bg-rose-500/10 text-rose-400"><i class="fa-solid fa-ban text-xs"></i></span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-black font-mono text-rose-400"><?= $outOfRadiusCount ?></span>
                <span class="text-[10px] text-rose-500 font-bold"><?= $totalCount > 0 ? round(($outOfRadiusCount / $totalCount) * 100) : 0 ?>%</span>
            </div>
        </div>

        <!-- Rata-rata Jarak GPS -->
        <div class="glass-card p-4 col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between">
                <span class="text-xs text-slate-400 font-medium">Rata-rata Jarak</span>
                <span class="p-1 rounded bg-cyan-500/10 text-cyan-400"><i class="fa-solid fa-location-crosshairs text-xs"></i></span>
            </div>
            <div class="mt-2 flex items-baseline gap-1">
                <span class="text-2xl font-black font-mono text-cyan-400"><?= $avgDistance ?></span>
                <span class="text-[11px] text-slate-400">Meter</span>
            </div>
        </div>

    </div>

<?php
    // Hitung Rekap per Departemen
    $deptStats = [];
    foreach ($attendances as $row) {
        $d = $row['department'] ?: 'Umum';
        if (!isset($deptStats[$d])) {
            $deptStats[$d] = ['total' => 0, 'on_time' => 0, 'late' => 0, 'out_of_radius' => 0];
        }
        $deptStats[$d]['total']++;
        if ($row['check_in_status'] === 'on_time') $deptStats[$d]['on_time']++;
        elseif ($row['check_in_status'] === 'late') $deptStats[$d]['late']++;
        elseif ($row['check_in_status'] === 'out_of_radius') $deptStats[$d]['out_of_radius']++;
    }
?>

    <!-- Preset Filter & Control Card -->
    <div class="glass-panel p-5 no-print space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 pb-3">
            <div class="flex items-center gap-1.5">
                <span class="text-xs text-slate-400 font-medium">Pilihan Rekap Cepat:</span>
            </div>
            <div class="flex flex-wrap items-center gap-2 bg-slate-900/80 p-1 rounded-xl border border-white/5">
                <a href="<?= BASE_URL ?>/laporan.php?preset=today" 
                   class="px-3 py-1 rounded-lg text-xs font-semibold transition-all <?= $preset === 'today' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'text-slate-400 hover:text-white' ?>">
                    <i class="fa-solid fa-calendar-day mr-1"></i> Hari Ini
                </a>
                <a href="<?= BASE_URL ?>/laporan.php?preset=week" 
                   class="px-3 py-1 rounded-lg text-xs font-semibold transition-all <?= $preset === 'week' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'text-slate-400 hover:text-white' ?>">
                    <i class="fa-solid fa-calendar-week mr-1"></i> Rekap Mingguan
                </a>
                <a href="<?= BASE_URL ?>/laporan.php?preset=month" 
                   class="px-3 py-1 rounded-lg text-xs font-semibold transition-all <?= $preset === 'month' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'text-slate-400 hover:text-white' ?>">
                    <i class="fa-solid fa-calendar mr-1"></i> Rekap Bulanan (<?= date('F Y') ?>)
                </a>
            </div>
        </div>

        <form action="<?= BASE_URL ?>/laporan.php" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3.5 items-end">
            <input type="hidden" name="preset" value="custom">

            <!-- Tanggal Mulai -->
            <div class="lg:col-span-3">
                <label class="block text-[11px] font-medium text-slate-400 mb-1">Dari Tanggal</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="glass-input w-full text-xs font-mono">
            </div>

            <!-- Tanggal Akhir -->
            <div class="lg:col-span-3">
                <label class="block text-[11px] font-medium text-slate-400 mb-1">Sampai Tanggal</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="glass-input w-full text-xs font-mono">
            </div>

            <!-- Filter Karyawan -->
            <div class="lg:col-span-3">
                <label class="block text-[11px] font-medium text-slate-400 mb-1">Karyawan</label>
                <select name="employee_id" class="glass-input w-full text-xs">
                    <option value="">Semua Karyawan</option>
                    <?php foreach ($employeesList as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= $filterEmp == $emp['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['nip']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Status -->
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-medium text-slate-400 mb-1">Status</label>
                <select name="status" class="glass-input w-full text-xs">
                    <option value="">Semua Status</option>
                    <option value="on_time" <?= $filterStatus === 'on_time' ? 'selected' : '' ?>>Tepat Waktu</option>
                    <option value="late" <?= $filterStatus === 'late' ? 'selected' : '' ?>>Terlambat</option>
                    <option value="out_of_radius" <?= $filterStatus === 'out_of_radius' ? 'selected' : '' ?>>Di Luar Radius</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="lg:col-span-1 flex gap-1.5">
                <button type="submit" class="btn-glossy btn-glossy-cyan text-xs py-2 px-3 w-full flex items-center justify-center" title="Filter">
                    <i class="fa-solid fa-filter"></i>
                </button>
                <a href="<?= BASE_URL ?>/laporan.php" class="btn-glossy btn-glossy-secondary text-xs py-2 px-2.5 text-slate-400 hover:text-white" title="Reset Filter">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>

        </form>
    </div>

    <!-- Rekapitulasi per Departemen -->
    <?php if (!empty($deptStats)): ?>
        <div class="glass-panel p-5">
            <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                <i class="fa-solid fa-sitemap text-cyan-400"></i> Rekapitulasi Kehadiran per Departemen
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <?php foreach ($deptStats as $deptName => $ds): ?>
                    <div class="glass-card p-3 rounded-xl border border-white/5 space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-white truncate"><?= htmlspecialchars($deptName) ?></span>
                            <span class="text-xs font-mono font-bold text-cyan-400"><?= $ds['total'] ?> Hadir</span>
                        </div>
                        <div class="flex items-center gap-2 text-[10px] text-slate-400 pt-1">
                            <span class="text-emerald-400"><i class="fa-solid fa-check text-[9px]"></i> <?= $ds['on_time'] ?> Tepat</span>
                            &bull;
                            <span class="text-amber-400"><i class="fa-solid fa-clock text-[9px]"></i> <?= $ds['late'] ?> Telat</span>
                            <?php if ($ds['out_of_radius'] > 0): ?>
                                &bull; <span class="text-rose-400"><?= $ds['out_of_radius'] ?> Luar</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Data Table Presensi -->
    <div class="glass-panel p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-list-check text-cyan-400"></i>
                Daftar Catatan Kehadiran (<?= $totalCount ?> Data)
            </h3>
            <span class="text-xs text-slate-400">
                Maks. Radius Kantor: <strong class="text-cyan-400"><?= (int)$settings['radius_meters'] ?> m</strong>
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-white/10 text-slate-400 font-semibold uppercase tracking-wider">
                        <th class="py-3 px-3">Tanggal</th>
                        <th class="py-3 px-3">Karyawan</th>
                        <th class="py-3 px-3">Foto Bukti</th>
                        <th class="py-3 px-3">Masuk</th>
                        <th class="py-3 px-3">Pulang</th>
                        <th class="py-3 px-3">Jarak GPS</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Catatan</th>
                        <th class="py-3 px-3 text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if (empty($attendances)): ?>
                        <tr>
                            <td colspan="9" class="py-12 text-center text-slate-500">
                                <i class="fa-regular fa-folder-open text-3xl mb-2 block"></i>
                                Tidak ada data presensi yang sesuai dengan kriteria filter yang dipilih.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attendances as $row): ?>
                            <?php 
                                $isOutside = (float)$row['check_in_distance'] > (int)$settings['radius_meters'];
                                $photoSrc = $row['check_in_photo'] ?: $row['emp_photo'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150';
                                if ($row['check_in_photo'] && !filter_var($row['check_in_photo'], FILTER_VALIDATE_URL)) {
                                    $photoSrc = BASE_URL . '/' . $row['check_in_photo'];
                                }
                            ?>
                            <tr class="hover:bg-white/5 transition-colors group">
                                
                                <!-- Tanggal -->
                                <td class="py-3 px-3 whitespace-nowrap font-mono text-slate-300">
                                    <div class="font-bold text-slate-200"><?= date('d/m/Y', strtotime($row['date'])) ?></div>
                                    <div class="text-[10px] text-slate-500"><?= date('l', strtotime($row['date'])) ?></div>
                                </td>

                                <!-- Karyawan -->
                                <td class="py-3 px-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2.5">
                                        <img src="<?= htmlspecialchars($photoSrc) ?>" alt="Thumb" class="w-8 h-8 rounded-full object-cover ring-1 ring-white/20">
                                        <div>
                                            <div class="font-bold text-slate-200"><?= htmlspecialchars($row['name']) ?></div>
                                            <div class="text-[10px] text-slate-400"><?= htmlspecialchars($row['nip']) ?> &bull; <?= htmlspecialchars($row['department']) ?></div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Foto Selfie Thumbnail -->
                                <td class="py-3 px-3">
                                    <button type="button" 
                                            onclick="viewDetailModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)"
                                            class="w-9 h-9 rounded-lg overflow-hidden border border-white/10 hover:border-cyan-400 transition-all hover:scale-110 shadow-sm inline-block">
                                        <img src="<?= htmlspecialchars($photoSrc) ?>" alt="Selfie" class="w-full h-full object-cover">
                                    </button>
                                </td>

                                <!-- Masuk -->
                                <td class="py-3 px-3 whitespace-nowrap font-mono font-semibold text-emerald-400">
                                    <?= $row['check_in_time'] ? substr($row['check_in_time'], 0, 5) . ' WIB' : '-' ?>
                                </td>

                                <!-- Pulang -->
                                <td class="py-3 px-3 whitespace-nowrap font-mono font-semibold text-rose-400">
                                    <?= $row['check_out_time'] ? substr($row['check_out_time'], 0, 5) . ' WIB' : '-' ?>
                                </td>

                                <!-- Jarak GPS -->
                                <td class="py-3 px-3 whitespace-nowrap font-mono">
                                    <?php if ($row['check_in_distance'] !== null): ?>
                                        <span class="font-bold <?= $isOutside ? 'text-rose-400' : 'text-emerald-400' ?>">
                                            <?= (float)$row['check_in_distance'] ?> m
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-500">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-3 px-3 whitespace-nowrap">
                                    <?php if ($row['check_in_status'] === 'on_time'): ?>
                                        <span class="badge-glossy badge-glossy-success">Tepat Waktu</span>
                                    <?php elseif ($row['check_in_status'] === 'late'): ?>
                                        <span class="badge-glossy badge-glossy-warning">Terlambat</span>
                                    <?php else: ?>
                                        <span class="badge-glossy badge-glossy-danger">Luar Radius</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Catatan -->
                                <td class="py-3 px-3 text-slate-400 truncate max-w-xs">
                                    <?= htmlspecialchars($row['notes'] ?: '-') ?>
                                </td>

                                <!-- Detail Modal Action -->
                                <td class="py-3 px-3 text-center no-print">
                                    <button type="button" 
                                            onclick="viewDetailModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)"
                                            class="btn-glossy btn-glossy-secondary text-xs py-1 px-2.5 text-cyan-400 hover:text-cyan-300 border-cyan-500/20">
                                        <i class="fa-solid fa-eye mr-1"></i> Detail
                                    </button>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal Detail Presensi (Photo + Mini GPS Map) -->
<div id="detailModal" class="hidden fixed inset-0 z-50 overflow-y-auto glass-modal-backdrop flex items-center justify-center p-4">
    <div class="glass-modal max-w-2xl w-full p-6 space-y-5 modal-enter relative">
        
        <!-- Close Button -->
        <button type="button" onclick="closeDetailModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white p-2 rounded-full hover:bg-white/10 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-3 border-b border-white/10 pb-4">
            <div class="w-10 h-10 rounded-xl bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400">
                <i class="fa-solid fa-fingerprint text-lg"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-white" id="modalEmpName">Detail Bukti Presensi</h3>
                <p class="text-xs text-slate-400" id="modalEmpSub">NIP & Jabatan</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            
            <!-- Selfie Photo Preview -->
            <div>
                <div class="text-xs font-semibold text-slate-400 mb-1.5 flex items-center gap-1.5">
                    <i class="fa-solid fa-camera text-cyan-400"></i> Foto Bukti Kamera
                </div>
                <div class="rounded-xl overflow-hidden border border-white/10 aspect-video sm:aspect-square bg-slate-950 flex items-center justify-center">
                    <img id="modalSelfieImg" src="" alt="Bukti Foto" class="w-full h-full object-cover">
                </div>
            </div>

            <!-- Mini Map & Geo Telemetry -->
            <div class="space-y-3">
                <div class="text-xs font-semibold text-slate-400 flex items-center gap-1.5">
                    <i class="fa-solid fa-map-location-dot text-cyan-400"></i> Titik Lokasi Presensi GPS
                </div>
                <div id="modalMiniMap" class="w-full h-44 rounded-xl border border-white/10 bg-slate-900"></div>

                <div class="p-3 bg-slate-900/70 rounded-xl border border-white/5 space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Jarak dari Kantor:</span>
                        <span id="modalDistance" class="font-mono font-bold text-white">--</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Koordinat GPS:</span>
                        <span id="modalCoords" class="font-mono text-cyan-400">--</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Status Kehadiran:</span>
                        <span id="modalStatusBadge">--</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">IP Address:</span>
                        <span id="modalIp" class="font-mono text-slate-300">--</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Notes -->
        <div>
            <div class="text-xs font-semibold text-slate-400 mb-1">Catatan Karyawan:</div>
            <div id="modalNotes" class="p-3 bg-slate-900/60 rounded-lg border border-white/5 text-xs text-slate-300 italic">
                -
            </div>
        </div>

        <div class="pt-2 flex justify-end">
            <button type="button" onclick="closeDetailModal()" class="btn-glossy btn-glossy-secondary text-xs py-2 px-4">
                Tutup
            </button>
        </div>

    </div>
</div>

<script>
    let miniMapInstance = null;

    function viewDetailModal(data) {
        document.getElementById('modalEmpName').textContent = data.name;
        document.getElementById('modalEmpSub').textContent = `${data.nip} • ${data.department} • ${data.date}`;
        
        // Foto
        let photo = data.check_in_photo || data.emp_photo || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300';
        if (data.check_in_photo && !data.check_in_photo.startsWith('http')) {
            photo = '<?= BASE_URL ?>/' + data.check_in_photo;
        }
        document.getElementById('modalSelfieImg').src = photo;

        // Jarak & Koordinat
        document.getElementById('modalDistance').textContent = (data.check_in_distance || '0') + ' meter';
        document.getElementById('modalCoords').textContent = (data.check_in_lat && data.check_in_lng) ? `${parseFloat(data.check_in_lat).toFixed(6)}, ${parseFloat(data.check_in_lng).toFixed(6)}` : 'N/A';
        document.getElementById('modalIp').textContent = data.ip_address || '127.0.0.1';
        document.getElementById('modalNotes').textContent = data.notes || 'Tidak ada catatan khusus.';

        // Status Badge
        const badgeEl = document.getElementById('modalStatusBadge');
        if (data.check_in_status === 'on_time') {
            badgeEl.className = 'badge-glossy badge-glossy-success text-[10px]';
            badgeEl.textContent = 'Tepat Waktu';
        } else if (data.check_in_status === 'late') {
            badgeEl.className = 'badge-glossy badge-glossy-warning text-[10px]';
            badgeEl.textContent = 'Terlambat';
        } else {
            badgeEl.className = 'badge-glossy badge-glossy-danger text-[10px]';
            badgeEl.textContent = 'Di Luar Radius';
        }

        // Tampilkan Modal
        document.getElementById('detailModal').classList.remove('hidden');

        // Render Leaflet Mini Map
        setTimeout(() => {
            const lat = parseFloat(data.check_in_lat) || <?= (float)$settings['latitude'] ?>;
            const lng = parseFloat(data.check_in_lng) || <?= (float)$settings['longitude'] ?>;

            if (!miniMapInstance) {
                miniMapInstance = L.map('modalMiniMap', {
                    zoomControl: false,
                    attributionControl: false
                }).setView([lat, lng], 17);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    className: 'map-dark-tiles'
                }).addTo(miniMapInstance);
            } else {
                miniMapInstance.setView([lat, lng], 17);
                miniMapInstance.invalidateSize();
            }

            // Clear previous markers
            miniMapInstance.eachLayer(layer => {
                if (layer instanceof L.Marker || layer instanceof L.Circle) {
                    miniMapInstance.removeLayer(layer);
                }
            });

            // Add office radius circle
            L.circle([<?= (float)$settings['latitude'] ?>, <?= (float)$settings['longitude'] ?>], {
                color: '#06b6d4',
                fillColor: '#06b6d4',
                fillOpacity: 0.15,
                radius: <?= (int)$settings['radius_meters'] ?>
            }).addTo(miniMapInstance);

            // Add employee position marker
            L.marker([lat, lng]).addTo(miniMapInstance).bindPopup(data.name).openPopup();
        }, 200);
    }

    function closeDetailModal() {
        document.getElementById('detailModal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
