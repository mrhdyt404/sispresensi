<?php
/**
 * Dashboard & Live Presensi Page
 * Sistem Presensi Karyawan GPS - Modern Dark Glossy
 */

$pageTitle = 'Presensi Live & Validasi GPS';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$settings = getOfficeSettings();
$userId = $currentUser['id'];
$today = date('Y-m-d');

// Cek status presensi user hari ini
$stmtToday = $db->prepare("SELECT * FROM attendances WHERE employee_id = :emp_id AND date = :today LIMIT 1");
$stmtToday->execute([':emp_id' => $userId, ':today' => $today]);
$todayRecord = $stmtToday->fetch();

$hasCheckedIn = !empty($todayRecord['check_in_time']);
$hasCheckedOut = !empty($todayRecord['check_out_time']);

// Ambil riwayat presensi hari ini dari seluruh rekan kantor untuk live feed
$stmtRecent = $db->prepare("
    SELECT a.*, e.name, e.nip, e.position, e.photo as emp_photo
    FROM attendances a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.date = :today
    ORDER BY a.created_at DESC
    LIMIT 6
");
$stmtRecent->execute([':today' => $today]);
$recentToday = $stmtRecent->fetchAll();

// Hitung kehadiran rekan kerja hari ini
$totalEmployeesCount = $db->query("SELECT COUNT(*) FROM employees WHERE is_active = 1")->fetchColumn();
$stmtHadirCount = $db->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendances WHERE date = :today AND check_in_time IS NOT NULL");
$stmtHadirCount->execute([':today' => $today]);
$todayHadirCount = $stmtHadirCount->fetchColumn();

// Format Tanggal Indonesia Lengkap
$dayNames = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$todayDayName = $dayNames[date('l')] ?? date('l');
$formattedTodayDate = $todayDayName . ', ' . formatIndonesianDate($today);
?>

<!-- Pass Office Config to JS -->
<script>
    window.BASE_URL = <?= json_encode(BASE_URL) ?>;
    window.OFFICE_CONFIG = {
        office_name: <?= json_encode($settings['office_name']) ?>,
        latitude: <?= (float)$settings['latitude'] ?>,
        longitude: <?= (float)$settings['longitude'] ?>,
        radius_meters: <?= (int)$settings['radius_meters'] ?>,
        enforce_radius: <?= (int)$settings['enforce_radius'] ?>,
        require_selfie: <?= (int)$settings['require_selfie'] ?>
    };
</script>

<div class="space-y-6">

    <!-- Top Greeting & Status Banner (With Integrated Live Digital Clock) -->
    <div class="glass-panel p-6 sm:p-8 relative overflow-hidden">
        <!-- Ambient decorative ambient orbs -->
        <div class="absolute -right-24 -top-24 w-80 h-80 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -left-24 -bottom-24 w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 relative z-10">
            <!-- Left: Salutation & Navigation Pills -->
            <div class="space-y-3.5 max-w-2xl">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 shadow-sm shadow-emerald-500/10">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        Sistem Presensi Aktif
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-slate-900/60 text-slate-300 border border-white/10">
                        <i class="fa-regular fa-clock text-cyan-400 text-[11px]"></i>
                        Jam Kerja: <strong class="text-white font-mono"><?= substr($settings['work_start_time'], 0, 5) ?> - <?= substr($settings['work_end_time'], 0, 5) ?> WIB</strong>
                    </span>
                </div>

                <div>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight text-white leading-tight">
                        Halo, <span class="bg-gradient-to-r from-cyan-400 via-sky-300 to-indigo-300 bg-clip-text text-transparent"><?= htmlspecialchars($currentUser['name']) ?></span>! 👋
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-400 mt-1.5 font-medium">
                        <?= htmlspecialchars($currentUser['nip']) ?> &bull; <?= htmlspecialchars($currentUser['position']) ?> &bull; <span class="text-slate-300"><?= htmlspecialchars($currentUser['department']) ?></span>
                    </p>
                </div>

                <!-- Quick Action Navigation Pills -->
                <div class="flex flex-wrap items-center gap-2.5 pt-1">
                    <a href="<?= BASE_URL ?>/tim.php" class="btn-glossy btn-glossy-secondary text-xs py-1.5 px-3.5 flex items-center gap-2 text-cyan-300 border-cyan-500/30 hover:border-cyan-400 hover:text-white transition-all shadow-sm">
                        <i class="fa-solid fa-users text-xs"></i>
                        <span>Status Rekan: <strong class="text-white"><?= $todayHadirCount ?>/<?= $totalEmployeesCount ?> Hadir</strong></span>
                    </a>

                    <?php if (!$isAdmin): ?>
                        <a href="<?= BASE_URL ?>/riwayat.php" class="btn-glossy btn-glossy-secondary text-xs py-1.5 px-3.5 flex items-center gap-2 text-indigo-300 border-indigo-500/30 hover:border-indigo-400 hover:text-white transition-all shadow-sm">
                            <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                            <span>Riwayat Presensi Saya</span>
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/laporan.php" class="btn-glossy btn-glossy-secondary text-xs py-1.5 px-3.5 flex items-center gap-2 text-purple-300 border-purple-500/30 hover:border-purple-400 hover:text-white transition-all shadow-sm">
                            <i class="fa-solid fa-chart-pie text-xs"></i>
                            <span>Rekap Laporan Absensi</span>
                        </a>
                        <a href="<?= BASE_URL ?>/karyawan.php" class="btn-glossy btn-glossy-secondary text-xs py-1.5 px-3.5 flex items-center gap-2 text-emerald-300 border-emerald-500/30 hover:border-emerald-400 hover:text-white transition-all shadow-sm">
                            <i class="fa-solid fa-user-plus text-xs"></i>
                            <span>Kelola Karyawan</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Live Digital Clock Showcase & Today Status Cards -->
            <div class="flex flex-col sm:flex-row lg:flex-col xl:flex-row items-stretch gap-3.5 shrink-0">
                
                <!-- 1. Live Digital Clock Showcase Card -->
                <div class="glass-card p-4 sm:p-5 rounded-2xl border border-cyan-500/30 bg-slate-900/80 shadow-xl shadow-cyan-950/40 flex items-center gap-4 relative overflow-hidden group">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-cyan-500/20 via-indigo-500/20 to-emerald-500/20 border border-cyan-500/40 flex items-center justify-center text-cyan-300 shrink-0 shadow-inner">
                        <i class="fa-solid fa-clock text-xl animate-pulse"></i>
                    </div>
                    <div class="min-w-[170px]">
                        <div class="flex items-center gap-1.5 text-[10px] font-bold text-cyan-400 uppercase tracking-widest">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                            Waktu Real-Time
                        </div>
                        <div id="liveClock" class="live-digital-clock text-2xl sm:text-3xl font-black font-mono text-white tracking-widest my-0.5 drop-shadow-[0_0_12px_rgba(6,182,212,0.4)]">
                            --:--:-- WIB
                        </div>
                        <div class="text-[11px] text-slate-400 font-medium flex items-center gap-1.5">
                            <i class="fa-regular fa-calendar text-slate-500"></i>
                            <?= $formattedTodayDate ?>
                        </div>
                    </div>
                </div>

                <!-- 2. Today's Check-in & Check-out Status Grid -->
                <div class="grid grid-cols-2 gap-2.5 sm:gap-3">
                    <!-- Absen Masuk Card -->
                    <div class="glass-card p-3 sm:p-3.5 rounded-2xl flex items-center gap-3 border <?= $hasCheckedIn ? 'border-emerald-500/30 bg-emerald-950/20' : 'border-white/10 bg-slate-900/60' ?>">
                        <div class="w-10 h-10 rounded-xl <?= $hasCheckedIn ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40' : 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' ?> flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-arrow-right-to-bracket text-base"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-[10px] uppercase font-bold tracking-wider text-slate-400">Absen Masuk</div>
                            <div class="text-xs sm:text-sm font-black font-mono text-white truncate">
                                <?= $hasCheckedIn ? substr($todayRecord['check_in_time'], 0, 5) . ' WIB' : '<span class="text-slate-500 font-normal">Belum Absen</span>' ?>
                            </div>
                            <?php if ($hasCheckedIn): ?>
                                <span class="text-[9px] font-semibold text-emerald-400 flex items-center gap-1">
                                    <i class="fa-solid fa-check text-[8px]"></i> Tercatat
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Absen Pulang Card -->
                    <div class="glass-card p-3 sm:p-3.5 rounded-2xl flex items-center gap-3 border <?= $hasCheckedOut ? 'border-rose-500/30 bg-rose-950/20' : 'border-white/10 bg-slate-900/60' ?>">
                        <div class="w-10 h-10 rounded-xl <?= $hasCheckedOut ? 'bg-rose-500/20 text-rose-400 border border-rose-500/40' : 'bg-rose-500/10 text-rose-400 border border-rose-500/30' ?> flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-arrow-right-from-bracket text-base"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-[10px] uppercase font-bold tracking-wider text-slate-400">Absen Pulang</div>
                            <div class="text-xs sm:text-sm font-black font-mono text-white truncate">
                                <?= $hasCheckedOut ? substr($todayRecord['check_out_time'], 0, 5) . ' WIB' : '<span class="text-slate-500 font-normal">Belum Pulang</span>' ?>
                            </div>
                            <?php if ($hasCheckedOut): ?>
                                <span class="text-[9px] font-semibold text-rose-400 flex items-center gap-1">
                                    <i class="fa-solid fa-check text-[8px]"></i> Tercatat
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Quick GPS Telemetry & Radius Indicators (4-Column Harmonized Grid) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Indicator 1: Radius Status -->
        <div class="glass-card p-4 rounded-2xl flex flex-col justify-between hover:border-cyan-500/30 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Status Geofence</span>
                <span class="p-1.5 rounded-lg bg-cyan-500/10 text-cyan-400 border border-cyan-500/20"><i class="fa-solid fa-bullseye text-sm"></i></span>
            </div>
            <div class="my-2.5">
                <span id="radiusStatusBadge" class="badge-glossy badge-glossy-warning text-xs">
                    <i class="fa-solid fa-circle-notch fa-spin text-xs"></i> Mendeteksi Lokasi...
                </span>
            </div>
            <div class="text-[11px] text-slate-500 flex items-center justify-between border-t border-white/5 pt-2">
                <span>Toleransi Radius:</span>
                <span class="font-bold text-slate-200"><?= (int)$settings['radius_meters'] ?> Meter</span>
            </div>
        </div>

        <!-- Indicator 2: Live Distance -->
        <div class="glass-card p-4 rounded-2xl flex flex-col justify-between hover:border-emerald-500/30 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Jarak ke Kantor</span>
                <span class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"><i class="fa-solid fa-route text-sm"></i></span>
            </div>
            <div class="my-1.5 flex items-baseline gap-1.5">
                <span id="distanceIndicatorText" class="text-2xl sm:text-3xl font-black font-mono text-white tracking-tight">--</span>
            </div>
            <div class="text-[11px] text-slate-500 flex items-center justify-between border-t border-white/5 pt-2">
                <span>Kalkulasi GPS:</span>
                <span class="text-emerald-400 font-mono font-medium">Haversine</span>
            </div>
        </div>

        <!-- Indicator 3: User Coordinates -->
        <div class="glass-card p-4 rounded-2xl flex flex-col justify-between hover:border-indigo-500/30 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Koordinat Anda</span>
                <span class="p-1.5 rounded-lg bg-indigo-500/10 text-indigo-400 border border-indigo-500/20"><i class="fa-solid fa-location-dot text-sm"></i></span>
            </div>
            <div class="my-2.5">
                <div id="userCoordsText" class="text-xs font-mono font-bold text-slate-200 truncate">Mencari sinyal GPS...</div>
            </div>
            <div class="text-[11px] text-slate-500 flex items-center justify-between border-t border-white/5 pt-2">
                <span>Akurasi Sinyal:</span>
                <span id="accuracyText" class="text-cyan-300 font-mono font-medium">--</span>
            </div>
        </div>

        <!-- Indicator 4: Strict Radius Policy -->
        <div class="glass-card p-4 rounded-2xl flex flex-col justify-between hover:border-amber-500/30 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Kebijakan Presensi</span>
                <span class="p-1.5 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/20"><i class="fa-solid fa-shield-halved text-sm"></i></span>
            </div>
            <div class="my-2.5">
                <span class="badge-glossy <?= $settings['enforce_radius'] ? 'badge-glossy-info' : 'badge-glossy-warning' ?> text-xs">
                    <i class="fa-solid <?= $settings['enforce_radius'] ? 'fa-lock' : 'fa-unlock' ?> text-xs"></i>
                    <?= $settings['enforce_radius'] ? 'Mode Ketat (Wajib Radius)' : 'Fleksibel' ?>
                </span>
            </div>
            <div class="text-[11px] text-slate-500 flex items-center justify-between border-t border-white/5 pt-2">
                <span>Toleransi Terlambat:</span>
                <span class="text-slate-200 font-bold"><?= (int)$settings['late_tolerance_minutes'] ?> Menit</span>
            </div>
        </div>

    </div>

    <!-- Main Attendance Workspace (Split 2-Column: Camera Viewfinder & Map) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Column Left: Camera Viewfinder & Actions (5 Cols) -->
        <div class="lg:col-span-5 space-y-5">
            <div class="glass-panel p-5 sm:p-6">
                
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400">
                            <i class="fa-solid fa-camera"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white">Verifikasi Foto Kehadiran</h3>
                            <p class="text-[11px] text-slate-400">Bukti wajah real-time saat presensi</p>
                        </div>
                    </div>
                    
                    <span class="px-2 py-1 rounded bg-slate-800 text-[10px] text-cyan-400 font-mono border border-white/5">
                        <i class="fa-solid fa-circle text-[7px] text-red-500 animate-pulse mr-1"></i> LIVE
                    </span>
                </div>

                <!-- Camera Viewfinder Box -->
                <div class="camera-viewfinder relative">
                    <!-- HUD Corner Brackets -->
                    <div class="hud-corner hud-corner--tl"></div>
                    <div class="hud-corner hud-corner--tr"></div>
                    <div class="hud-corner hud-corner--bl"></div>
                    <div class="hud-corner hud-corner--br"></div>
                    
                    <!-- Scanner beam -->
                    <div class="hud-scanner"></div>

                    <!-- WebRTC Video Element -->
                    <video id="cameraVideo" autoplay playsinline muted class="hidden"></video>
                    
                    <!-- Captured Photo Preview -->
                    <img id="photoPreview" alt="Snapshot Bukti" class="hidden w-full h-full object-cover">
                    
                    <!-- Hidden Canvas for frame snapshot -->
                    <canvas id="cameraCanvas" class="hidden"></canvas>

                    <!-- Camera Placeholder when loading or no permission -->
                    <div id="cameraPlaceholder" class="w-full h-full flex items-center justify-center bg-slate-900/90">
                        <div class="text-center p-4">
                            <i class="fa-solid fa-spinner fa-spin text-3xl text-cyan-400 mb-2"></i>
                            <div class="text-xs text-slate-300 font-medium">Mengaktifkan Kamera...</div>
                            <div class="text-[10px] text-slate-500 mt-1">Pastikan izin kamera diizinkan di browser</div>
                        </div>
                    </div>
                </div>

                <!-- Snapshot Controls -->
                <div class="mt-3 flex items-center justify-between">
                    <button type="button" id="btnCapturePhoto" class="btn-glossy btn-glossy-secondary text-xs py-1.5 px-3 rounded-lg flex items-center gap-1.5">
                        <i class="fa-solid fa-camera-retro text-cyan-400"></i> Ambil Foto Manual
                    </button>
                    <button type="button" id="btnRetakePhoto" class="hidden btn-glossy btn-glossy-secondary text-xs py-1.5 px-3 rounded-lg text-amber-400 flex items-center gap-1.5">
                        <i class="fa-solid fa-rotate-left"></i> Foto Ulang
                    </button>
                    <span class="text-[11px] text-slate-500 italic">
                        *Foto otomatis tersimpan saat klik absen
                    </span>
                </div>

                <!-- Notes Input -->
                <div class="mt-4">
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">
                        Catatan Kehadiran (Opsional)
                    </label>
                    <input type="text" id="attendanceNotes" 
                           placeholder="Contoh: Bekerja di kantor, Tugas presentasi, dll." 
                           class="glass-input w-full text-xs">
                </div>

                <!-- Main Action Buttons (Absen Masuk & Pulang) -->
                <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    
                    <!-- Button Absen Masuk -->
                    <button type="button" id="btnCheckIn" 
                            <?= $hasCheckedIn ? 'data-done="1" disabled' : '' ?>
                            class="btn-glossy btn-glossy-emerald w-full text-sm py-3.5 flex items-center justify-center gap-2 <?= $hasCheckedIn ? 'opacity-50 cursor-not-allowed' : '' ?>">
                        <i class="fa-solid fa-circle-check text-base"></i>
                        <span><?= $hasCheckedIn ? 'Sudah Masuk' : 'Absen Masuk' ?></span>
                    </button>

                    <!-- Button Absen Pulang -->
                    <button type="button" id="btnCheckOut" 
                            <?= ($hasCheckedOut || !$hasCheckedIn) ? 'data-done="1" disabled' : '' ?>
                            class="btn-glossy btn-glossy-rose w-full text-sm py-3.5 flex items-center justify-center gap-2 <?= ($hasCheckedOut || !$hasCheckedIn) ? 'opacity-50 cursor-not-allowed' : '' ?>">
                        <i class="fa-solid fa-person-walking-arrow-right text-base"></i>
                        <span><?= $hasCheckedOut ? 'Sudah Pulang' : 'Absen Pulang' ?></span>
                    </button>

                </div>

                <!-- Demo & Testing GPS Simulator Panel -->
                <div class="mt-5 p-3.5 rounded-xl bg-slate-900/60 border border-white/5">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-bold text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="fa-solid fa-flask text-cyan-400"></i> Simulator Koordinat (Demo)
                        </span>
                        <span class="text-[10px] text-slate-500">Uji Coba Radius</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mb-2.5">
                        Gunakan tombol di bawah ini untuk menguji reaksi sistem saat Anda berada di dalam atau di luar area radius kantor:
                    </p>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" id="simInRadius" class="btn-glossy btn-glossy-secondary text-[11px] py-1.5 px-2 text-emerald-400 hover:text-emerald-300 border-emerald-500/30 flex items-center justify-center gap-1">
                            <i class="fa-solid fa-location-dot"></i> Di Kantor
                        </button>
                        <button type="button" id="simOutRadius" class="btn-glossy btn-glossy-secondary text-[11px] py-1.5 px-2 text-rose-400 hover:text-rose-300 border-rose-500/30 flex items-center justify-center gap-1">
                            <i class="fa-solid fa-ban"></i> Luar Radius
                        </button>
                        <button type="button" id="simRealGps" class="btn-glossy btn-glossy-secondary text-[11px] py-1.5 px-2 text-cyan-400 hover:text-cyan-300 border-cyan-500/30 flex items-center justify-center gap-1">
                            <i class="fa-solid fa-satellite"></i> GPS Nyata
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- Column Right: Interactive Dark Map (7 Cols) -->
        <div class="lg:col-span-7">
            <div class="glass-panel p-5 sm:p-6 h-full flex flex-col">
                
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center text-indigo-400">
                            <i class="fa-solid fa-map-location-dot"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white">Visualisasi Radar & Radius Presensi</h3>
                            <p class="text-[11px] text-slate-400">Peta geolokasi Leaflet interaktif</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 text-xs">
                        <span class="inline-flex items-center gap-1 text-[11px] text-slate-400">
                            <span class="w-2.5 h-2.5 rounded-full bg-cyan-400"></span> Radius Kantor (<?= (int)$settings['radius_meters'] ?>m)
                        </span>
                        <span class="inline-flex items-center gap-1 text-[11px] text-slate-400">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span> Posisi Anda
                        </span>
                    </div>
                </div>

                <!-- Leaflet Map Container -->
                <div id="liveAttendanceMap" class="w-full rounded-xl border border-white/10 z-0" style="height: 480px; min-height: 450px; width: 100%;"></div>

                <!-- Map Footer Info -->
                <div class="mt-3 flex flex-col sm:flex-row items-start sm:items-center justify-between text-xs text-slate-400 gap-2">
                    <div class="flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-info text-cyan-400"></i>
                        <span>Pusat: <strong><?= htmlspecialchars($settings['office_name']) ?></strong></span>
                    </div>
                    <div class="font-mono text-[11px] text-slate-400">
                        Lat: <?= (float)$settings['latitude'] ?>, Lng: <?= (float)$settings['longitude'] ?>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- Today's Attendance Activity Table (Feed Rekan Kerja Hari Ini) -->
    <div class="glass-panel p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                    <i class="fa-solid fa-clipboard-user"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Aktivitas Presensi Hari Ini</h3>
                    <p class="text-xs text-slate-400"><?= formatIndonesianDate($today) ?></p>
                </div>
            </div>

            <?php if ($isAdmin): ?>
                <a href="<?= BASE_URL ?>/laporan.php" class="text-xs font-semibold text-cyan-400 hover:text-cyan-300 flex items-center gap-1 hover:underline">
                    Rekap Seluruh Laporan <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/tim.php" class="text-xs font-semibold text-cyan-400 hover:text-cyan-300 flex items-center gap-1 hover:underline">
                    Status Semua Teman (<?= $todayHadirCount ?>/<?= $totalEmployeesCount ?>) <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            <?php endif; ?>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-white/10 text-slate-400 font-semibold uppercase tracking-wider">
                        <th class="py-3 px-3">Karyawan</th>
                        <th class="py-3 px-3">Jam Masuk</th>
                        <th class="py-3 px-3">Jam Pulang</th>
                        <th class="py-3 px-3">Jarak GPS</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if (empty($recentToday)): ?>
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-500">
                                <i class="fa-regular fa-folder-open text-2xl mb-1 block"></i>
                                Belum ada riwayat presensi yang tercatat hari ini. Jadilah yang pertama!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentToday as $rec): ?>
                            <tr class="hover:bg-white/5 transition-colors">
                                <td class="py-3 px-3">
                                    <div class="flex items-center gap-2.5">
                                        <img src="<?= htmlspecialchars($rec['emp_photo'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150') ?>" 
                                             alt="Avatar" class="w-7 h-7 rounded-full object-cover ring-1 ring-white/20">
                                        <div>
                                            <div class="font-bold text-slate-200"><?= htmlspecialchars($rec['name']) ?></div>
                                            <div class="text-[10px] text-slate-400"><?= htmlspecialchars($rec['nip']) ?> &bull; <?= htmlspecialchars($rec['position']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3 font-mono font-semibold text-emerald-400">
                                    <?= $rec['check_in_time'] ? substr($rec['check_in_time'], 0, 5) . ' WIB' : '-' ?>
                                </td>
                                <td class="py-3 px-3 font-mono font-semibold text-rose-400">
                                    <?= $rec['check_out_time'] ? substr($rec['check_out_time'], 0, 5) . ' WIB' : '-' ?>
                                </td>
                                <td class="py-3 px-3 font-mono text-slate-300">
                                    <?= $rec['check_in_distance'] ? $rec['check_in_distance'] . ' m' : '-' ?>
                                </td>
                                <td class="py-3 px-3">
                                    <?php if ($rec['check_in_status'] === 'on_time'): ?>
                                        <span class="badge-glossy badge-glossy-success">Tepat Waktu</span>
                                    <?php elseif ($rec['check_in_status'] === 'late'): ?>
                                        <span class="badge-glossy badge-glossy-warning">Terlambat</span>
                                    <?php else: ?>
                                        <span class="badge-glossy badge-glossy-danger">Luar Radius</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 text-slate-400 truncate max-w-xs">
                                    <?= htmlspecialchars($rec['notes'] ?: '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Attendance JavaScript Bundle -->
<script src="<?= BASE_URL ?>/assets/js/attendance.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
