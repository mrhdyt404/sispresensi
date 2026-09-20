<?php
/**
 * Riwayat Presensi Pribadi Karyawan
 * Menampilkan catatan kehadiran khusus untuk akun yang sedang login
 * Sistem Presensi Karyawan GPS - Modern Dark Glossy
 */

$pageTitle = 'Riwayat Presensi Saya';
require_once __DIR__ . '/config/database.php';
checkAuth();

$db = getDBConnection();
$userId = $_SESSION['user_id'];
$user = getCurrentUser();
$settings = getOfficeSettings();

// Preset Tanggal Filter
$preset = $_GET['preset'] ?? 'month'; // 'today', 'week', 'month', 'custom'
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

if ($preset === 'today') {
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d');
} elseif ($preset === 'week') {
    // Senin s/d Minggu minggu ini
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
} elseif ($preset === 'month') {
    // Awal bulan s/d akhir bulan ini
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
} elseif ($preset === 'custom' && $startDate && $endDate) {
    // Gunakan custom input
} else {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
    $preset = 'month';
}

// Handle Export CSV Pribadi
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportStmt = $db->prepare("
        SELECT date, check_in_time, check_out_time, check_in_distance, check_in_status, check_in_lat, check_in_lng, notes
        FROM attendances
        WHERE employee_id = :emp_id AND date BETWEEN :start AND :end
        ORDER BY date DESC, check_in_time DESC
    ");
    $exportStmt->execute([':emp_id' => $userId, ':start' => $startDate, ':end' => $endDate]);
    $recordsExp = $exportStmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Riwayat_Presensi_' . $user['nip'] . '_' . $startDate . '_sd_' . $endDate . '.csv');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['Tanggal', 'Jam Masuk', 'Jam Pulang', 'Jarak GPS (m)', 'Status', 'Latitude', 'Longitude', 'Catatan']);

    foreach ($recordsExp as $r) {
        $statusStr = ($r['check_in_status'] === 'on_time') ? 'Tepat Waktu' : (($r['check_in_status'] === 'late') ? 'Terlambat' : 'Di Luar Radius');
        fputcsv($output, [
            $r['date'],
            $r['check_in_time'] ?: '-',
            $r['check_out_time'] ?: '-',
            $r['check_in_distance'] ?: 0,
            $statusStr,
            $r['check_in_lat'] ?: '-',
            $r['check_in_lng'] ?: '-',
            $r['notes'] ?: '-'
        ]);
    }
    fclose($output);
    exit;
}

// Query Data Presensi Pribadi
$stmt = $db->prepare("
    SELECT a.*, e.name, e.nip, e.department, e.position, e.photo as emp_photo
    FROM attendances a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.employee_id = :emp_id AND a.date BETWEEN :start AND :end
    ORDER BY a.date DESC, a.check_in_time DESC
");
$stmt->execute([':emp_id' => $userId, ':start' => $startDate, ':end' => $endDate]);
$myAttendances = $stmt->fetchAll();

// Kalkulasi KPI Pribadi
$totalMyCount = count($myAttendances);
$onTimeCount = 0;
$lateCount = 0;
$outOfRadiusCount = 0;

foreach ($myAttendances as $row) {
    if ($row['check_in_status'] === 'on_time') $onTimeCount++;
    elseif ($row['check_in_status'] === 'late') $lateCount++;
    elseif ($row['check_in_status'] === 'out_of_radius') $outOfRadiusCount++;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">

    <!-- Page Header Banner -->
    <div class="glass-panel p-6 sm:p-8 relative overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 relative z-10">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                    <span class="text-xs font-semibold text-cyan-400 uppercase tracking-wider">Rekap Kehadiran Pribadi</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Riwayat Presensi Saya</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1 max-w-2xl">
                    Pantau catatan absensi masuk, jam kepulangan, titik GPS, dan bukti foto selfie kehadiran Anda sendiri.
                </p>
            </div>

            <!-- Export CSV Button -->
            <div class="flex items-center gap-2 self-start sm:self-auto">
                <a href="<?= BASE_URL ?>/riwayat.php?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" 
                   class="btn-glossy btn-glossy-secondary text-xs py-2 px-3.5 flex items-center gap-2 text-emerald-400 border-emerald-500/30 hover:border-emerald-500/50">
                    <i class="fa-solid fa-file-excel text-sm"></i>
                    <span>Ekspor CSV</span>
                </a>
                <a href="<?= BASE_URL ?>/index.php" class="btn-glossy btn-glossy-cyan text-xs py-2 px-3.5 flex items-center gap-1.5">
                    <i class="fa-solid fa-fingerprint"></i> Absen Hari Ini
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Filter Preset Tabs -->
    <div class="glass-panel p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            
            <!-- Preset Buttons -->
            <div class="flex items-center gap-2 bg-slate-900/70 p-1 rounded-xl border border-white/5">
                <a href="<?= BASE_URL ?>/riwayat.php?preset=today" 
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all <?= $preset === 'today' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'text-slate-400 hover:text-white' ?>">
                    Hari Ini
                </a>
                <a href="<?= BASE_URL ?>/riwayat.php?preset=week" 
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all <?= $preset === 'week' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'text-slate-400 hover:text-white' ?>">
                    Minggu Ini
                </a>
                <a href="<?= BASE_URL ?>/riwayat.php?preset=month" 
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all <?= $preset === 'month' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'text-slate-400 hover:text-white' ?>">
                    Bulan Ini (<?= date('F Y') ?>)
                </a>
            </div>

            <!-- Custom Date Form -->
            <form action="<?= BASE_URL ?>/riwayat.php" method="GET" class="flex flex-wrap items-center gap-2 text-xs">
                <input type="hidden" name="preset" value="custom">
                <div class="flex items-center gap-1.5">
                    <span class="text-slate-400 text-[11px]">Dari:</span>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="glass-input text-xs py-1 px-2 font-mono">
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-slate-400 text-[11px]">Sampai:</span>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="glass-input text-xs py-1 px-2 font-mono">
                </div>
                <button type="submit" class="btn-glossy btn-glossy-secondary text-xs py-1.5 px-3">
                    <i class="fa-solid fa-filter mr-1"></i> Terapkan
                </button>
            </form>

        </div>
    </div>

    <!-- Personal KPI Statistics -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Total Hadir -->
        <div class="glass-card p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs text-slate-400 font-medium">Total Kehadiran</span>
                <span class="p-1 rounded bg-indigo-500/10 text-indigo-400"><i class="fa-solid fa-calendar-check text-xs"></i></span>
            </div>
            <div class="mt-2 flex items-baseline gap-1">
                <span class="text-2xl font-black font-mono text-white"><?= $totalMyCount ?></span>
                <span class="text-[11px] text-slate-400">Hari</span>
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
                <span class="text-[10px] text-emerald-500 font-bold"><?= $totalMyCount > 0 ? round(($onTimeCount / $totalMyCount) * 100) : 0 ?>%</span>
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
                <span class="text-[10px] text-amber-500 font-bold"><?= $totalMyCount > 0 ? round(($lateCount / $totalMyCount) * 100) : 0 ?>%</span>
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
                <span class="text-[10px] text-rose-500 font-bold"><?= $totalMyCount > 0 ? round(($outOfRadiusCount / $totalMyCount) * 100) : 0 ?>%</span>
            </div>
        </div>

    </div>

    <!-- Data Table Riwayat -->
    <div class="glass-panel p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-timeline text-cyan-400"></i>
                Catatan Presensi (<?= $totalMyCount ?> Data)
            </h3>
            <span class="text-xs text-slate-400">
                Periode: <strong class="text-slate-200"><?= date('d M Y', strtotime($startDate)) ?> &ndash; <?= date('d M Y', strtotime($endDate)) ?></strong>
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-white/10 text-slate-400 font-semibold uppercase tracking-wider">
                        <th class="py-3 px-3">Tanggal</th>
                        <th class="py-3 px-3">Bukti Foto</th>
                        <th class="py-3 px-3">Jam Masuk</th>
                        <th class="py-3 px-3">Jam Pulang</th>
                        <th class="py-3 px-3">Jarak GPS</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Catatan</th>
                        <th class="py-3 px-3 text-center">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if (empty($myAttendances)): ?>
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-500">
                                <i class="fa-regular fa-calendar-xmark text-3xl mb-2 block"></i>
                                Tidak ada catatan presensi Anda pada rentang tanggal yang dipilih.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($myAttendances as $row): ?>
                            <?php 
                                $isOutside = (float)$row['check_in_distance'] > (int)$settings['radius_meters'];
                                $photoSrc = $row['check_in_photo'] ?: $user['photo'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150';
                                if ($row['check_in_photo'] && !filter_var($row['check_in_photo'], FILTER_VALIDATE_URL)) {
                                    $photoSrc = BASE_URL . '/' . $row['check_in_photo'];
                                }
                            ?>
                            <tr class="hover:bg-white/5 transition-colors">
                                <td class="py-3 px-3 whitespace-nowrap font-mono">
                                    <div class="font-bold text-slate-200"><?= date('d/m/Y', strtotime($row['date'])) ?></div>
                                    <div class="text-[10px] text-slate-500"><?= date('l', strtotime($row['date'])) ?></div>
                                </td>

                                <td class="py-3 px-3">
                                    <button type="button" 
                                            onclick="viewDetailModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)"
                                            class="w-8 h-8 rounded-lg overflow-hidden border border-white/10 hover:border-cyan-400 transition-all shadow-sm block">
                                        <img src="<?= htmlspecialchars($photoSrc) ?>" alt="Selfie" class="w-full h-full object-cover">
                                    </button>
                                </td>

                                <td class="py-3 px-3 whitespace-nowrap font-mono font-semibold text-emerald-400">
                                    <?= $row['check_in_time'] ? substr($row['check_in_time'], 0, 5) . ' WIB' : '-' ?>
                                </td>

                                <td class="py-3 px-3 whitespace-nowrap font-mono font-semibold text-rose-400">
                                    <?= $row['check_out_time'] ? substr($row['check_out_time'], 0, 5) . ' WIB' : '-' ?>
                                </td>

                                <td class="py-3 px-3 whitespace-nowrap font-mono">
                                    <?php if ($row['check_in_distance'] !== null): ?>
                                        <span class="font-bold <?= $isOutside ? 'text-rose-400' : 'text-emerald-400' ?>">
                                            <?= (float)$row['check_in_distance'] ?> m
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-500">-</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3 px-3 whitespace-nowrap">
                                    <?php if ($row['check_in_status'] === 'on_time'): ?>
                                        <span class="badge-glossy badge-glossy-success">Tepat Waktu</span>
                                    <?php elseif ($row['check_in_status'] === 'late'): ?>
                                        <span class="badge-glossy badge-glossy-warning">Terlambat</span>
                                    <?php else: ?>
                                        <span class="badge-glossy badge-glossy-danger">Luar Radius</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3 px-3 text-slate-400 truncate max-w-xs">
                                    <?= htmlspecialchars($row['notes'] ?: '-') ?>
                                </td>

                                <td class="py-3 px-3 text-center">
                                    <button type="button" 
                                            onclick="viewDetailModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)"
                                            class="btn-glossy btn-glossy-secondary text-[11px] py-1 px-2.5 text-cyan-400 hover:text-cyan-300">
                                        <i class="fa-solid fa-eye mr-1"></i> Bukti
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

<!-- Modal Detail Bukti Presensi Pribadi -->
<div id="detailModal" class="hidden fixed inset-0 z-50 overflow-y-auto glass-modal-backdrop flex items-center justify-center p-4">
    <div class="glass-modal max-w-lg w-full p-6 space-y-4 modal-enter relative">
        <button type="button" onclick="closeDetailModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white p-2">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-3 border-b border-white/10 pb-3">
            <div class="w-9 h-9 rounded-xl bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400">
                <i class="fa-solid fa-fingerprint"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-white">Bukti Presensi Saya</h3>
                <p class="text-xs text-slate-400" id="modalDateStr">Tanggal Presensi</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div>
                <div class="text-[11px] font-semibold text-slate-400 mb-1 flex items-center gap-1.5">
                    <i class="fa-solid fa-camera text-cyan-400"></i> Foto Selfie Presensi
                </div>
                <div class="rounded-xl overflow-hidden border border-white/10 aspect-square bg-slate-950 flex items-center justify-center">
                    <img id="modalSelfieImg" src="" alt="Bukti Foto" class="w-full h-full object-cover">
                </div>
            </div>

            <div class="space-y-2">
                <div class="text-[11px] font-semibold text-slate-400 flex items-center gap-1.5">
                    <i class="fa-solid fa-map-location-dot text-cyan-400"></i> Peta Lokasi Anda
                </div>
                <div id="modalMiniMap" class="w-full h-36 rounded-xl border border-white/10 bg-slate-900"></div>

                <div class="p-2.5 bg-slate-900/80 rounded-xl border border-white/5 space-y-1.5 text-[11px]">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Jarak ke Kantor:</span>
                        <span id="modalDistance" class="font-mono font-bold text-white">--</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Status:</span>
                        <span id="modalStatusBadge">--</span>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="text-[11px] font-semibold text-slate-400 mb-1">Catatan Absen:</div>
            <div id="modalNotes" class="p-2.5 bg-slate-900/60 rounded-lg border border-white/5 text-xs text-slate-300 italic">
                -
            </div>
        </div>

        <div class="pt-2 flex justify-end">
            <button type="button" onclick="closeDetailModal()" class="btn-glossy btn-glossy-secondary text-xs py-1.5 px-4">
                Tutup
            </button>
        </div>
    </div>
</div>

<script>
    let miniMapInstance = null;

    function viewDetailModal(data) {
        document.getElementById('modalDateStr').textContent = data.date;
        let photo = data.check_in_photo || data.emp_photo || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300';
        if (data.check_in_photo && !data.check_in_photo.startsWith('http')) {
            photo = '<?= BASE_URL ?>/' + data.check_in_photo;
        }
        document.getElementById('modalSelfieImg').src = photo;
        document.getElementById('modalDistance').textContent = (data.check_in_distance || '0') + ' meter';
        document.getElementById('modalNotes').textContent = data.notes || 'Tidak ada catatan.';

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

        document.getElementById('detailModal').classList.remove('hidden');

        setTimeout(() => {
            const lat = parseFloat(data.check_in_lat) || <?= (float)$settings['latitude'] ?>;
            const lng = parseFloat(data.check_in_lng) || <?= (float)$settings['longitude'] ?>;

            if (!miniMapInstance) {
                miniMapInstance = L.map('modalMiniMap', { zoomControl: false, attributionControl: false }).setView([lat, lng], 17);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, className: 'map-dark-tiles' }).addTo(miniMapInstance);
            } else {
                miniMapInstance.setView([lat, lng], 17);
                miniMapInstance.invalidateSize();
            }

            miniMapInstance.eachLayer(layer => {
                if (layer instanceof L.Marker || layer instanceof L.Circle) miniMapInstance.removeLayer(layer);
            });

            L.circle([<?= (float)$settings['latitude'] ?>, <?= (float)$settings['longitude'] ?>], {
                color: '#06b6d4', fillColor: '#06b6d4', fillOpacity: 0.15, radius: <?= (int)$settings['radius_meters'] ?>
            }).addTo(miniMapInstance);

            L.marker([lat, lng]).addTo(miniMapInstance);
        }, 200);
    }

    function closeDetailModal() {
        document.getElementById('detailModal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
