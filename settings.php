<?php
/**
 * Office Settings & GPS Radius Configuration
 * Sistem Presensi Karyawan GPS - Modern Dark Glossy
 */

$pageTitle = 'Pengaturan Radius & Lokasi Kantor';
require_once __DIR__ . '/config/database.php';
checkAuth('admin');

$db = getDBConnection();
$message = null;
$messageType = 'success';

// Proses Simpan Pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $officeName = trim($_POST['office_name'] ?? '');
    $officeAddress = trim($_POST['office_address'] ?? '');
    $latitude = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $radiusMeters = filter_var($_POST['radius_meters'] ?? null, FILTER_VALIDATE_INT);
    $workStartTime = trim($_POST['work_start_time'] ?? '08:00');
    $workEndTime = trim($_POST['work_end_time'] ?? '17:00');
    $lateTolerance = filter_var($_POST['late_tolerance_minutes'] ?? 15, FILTER_VALIDATE_INT);
    $enforceRadius = isset($_POST['enforce_radius']) ? 1 : 0;
    $requireSelfie = isset($_POST['require_selfie']) ? 1 : 0;
    $allowSimulation = isset($_POST['allow_simulation']) ? 1 : 0;

    if (!$officeName || $latitude === false || $longitude === false || !$radiusMeters) {
        $message = 'Harap lengkapi nama kantor, koordinat Latitude/Longitude, dan radius yang valid.';
        $messageType = 'danger';
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE settings SET
                    office_name = :name,
                    office_address = :address,
                    latitude = :lat,
                    longitude = :lng,
                    radius_meters = :radius,
                    work_start_time = :start_time,
                    work_end_time = :end_time,
                    late_tolerance_minutes = :late_tol,
                    enforce_radius = :enforce,
                    require_selfie = :selfie,
                    allow_simulation = :sim
                WHERE id = 1
            ");
            $stmt->execute([
                ':name' => $officeName,
                ':address' => $officeAddress,
                ':lat' => $latitude,
                ':lng' => $longitude,
                ':radius' => $radiusMeters,
                ':start_time' => $workStartTime,
                ':end_time' => $workEndTime,
                ':late_tol' => $lateTolerance,
                ':enforce' => $enforceRadius,
                ':selfie' => $requireSelfie,
                ':sim' => $allowSimulation
            ]);
            $message = 'Pengaturan radius lokasi kantor dan jam kerja berhasil disimpan!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Gagal menyimpan pengaturan: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

$settings = getOfficeSettings();
require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">

    <!-- Page Header Title -->
    <div class="glass-panel p-6 relative overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                    <span class="text-xs font-semibold text-cyan-400 uppercase tracking-wider">Konfigurasi Geofencing</span>
                </div>
                <h1 class="text-2xl font-black text-white tracking-tight">Pengaturan Lokasi & Radius Presensi</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                    Tentukan titik pusat koordinat kantor dan batas toleransi jarak (radius dalam meter) yang diperbolehkan untuk absensi karyawan.
                </p>
            </div>

            <a href="<?= BASE_URL ?>/index.php" class="btn-glossy btn-glossy-secondary text-xs py-2 px-4 flex items-center gap-2 self-start sm:self-auto">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Presensi
            </a>
        </div>
    </div>

    <!-- Alert Notification jika ada pesan -->
    <?php if ($message): ?>
        <div class="glass-card p-4 border <?= $messageType === 'success' ? 'border-emerald-500/30 bg-emerald-950/40 text-emerald-300' : 'border-rose-500/30 bg-rose-950/40 text-rose-300' ?> rounded-xl flex items-center gap-3">
            <i class="fa-solid <?= $messageType === 'success' ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400' ?> text-lg"></i>
            <span class="text-sm font-medium"><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>

    <!-- Main Settings Form & Interactive Map Grid -->
    <form action="<?= BASE_URL ?>/settings.php" method="POST" class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left Column: Form Settings (6 Cols) -->
        <div class="lg:col-span-5 space-y-6">
            
            <!-- Panel 1: Data Identitas Kantor -->
            <div class="glass-panel p-6 space-y-4">
                <div class="flex items-center gap-2 border-b border-white/10 pb-3">
                    <i class="fa-solid fa-building text-cyan-400"></i>
                    <h3 class="text-sm font-bold text-white">Identitas Kantor / Perusahaan</h3>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">
                        Nama Kantor / Teks Header <span class="text-rose-400">*</span>
                    </label>
                    <input type="text" name="office_name" id="office_name" value="<?= htmlspecialchars($settings['office_name']) ?>" required class="glass-input w-full text-xs" placeholder="Contoh: Head Office PT Digital Solusindo">
                    <span class="text-[10px] text-slate-400 mt-1 block">Teks ini tampil di bawah logo GeoPresensi pada &lt;header&gt; semua halaman, aplikasi mobile, dan kop laporan resmi.</span>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Alamat Lengkap Kantor</label>
                    <textarea name="office_address" rows="2" class="glass-input w-full text-xs" placeholder="Jl. Sudirman No. 123..."><?= htmlspecialchars($settings['office_address'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Panel 2: Koordinat & Radius GPS -->
            <div class="glass-panel p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-map-pin text-cyan-400"></i>
                        <h3 class="text-sm font-bold text-white">Titik Koordinat & Radius</h3>
                    </div>
                    <button type="button" id="btnUseCurrentLocation" class="btn-glossy btn-glossy-secondary text-[11px] py-1 px-2.5 text-cyan-300 border-cyan-500/30 flex items-center gap-1.5">
                        <i class="fa-solid fa-crosshairs"></i> Gunakan Lokasi GPS Saya
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-slate-400 mb-1">Latitude <span class="text-rose-400">*</span></label>
                        <input type="number" step="any" id="settingLatitude" name="latitude" value="<?= (float)$settings['latitude'] ?>" required class="glass-input w-full font-mono text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-slate-400 mb-1">Longitude <span class="text-rose-400">*</span></label>
                        <input type="number" step="any" id="settingLongitude" name="longitude" value="<?= (float)$settings['longitude'] ?>" required class="glass-input w-full font-mono text-xs">
                    </div>
                </div>

                <!-- Radius Slider Control -->
                <div class="pt-2">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-medium text-slate-300 flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-notch text-cyan-400 text-[10px]"></i> Radius Toleransi Absen
                        </label>
                        <span id="radiusValueDisplay" class="text-sm font-bold font-mono text-cyan-400 bg-cyan-500/10 px-2.5 py-0.5 rounded-full border border-cyan-500/30">
                            <?= (int)$settings['radius_meters'] ?> Meter
                        </span>
                    </div>

                    <input type="range" id="settingRadius" name="radius_meters" min="20" max="1000" step="10" value="<?= (int)$settings['radius_meters'] ?>" class="slider-glossy w-full">

                    <!-- Quick Preset Buttons -->
                    <div class="flex items-center gap-2 mt-3">
                        <span class="text-[11px] text-slate-500">Preset Cepat:</span>
                        <button type="button" data-radius="50" class="btn-radius-preset text-[11px] px-2 py-0.5 rounded bg-white/5 hover:bg-cyan-500/20 hover:text-cyan-300 text-slate-300 border border-white/10 transition-colors">50m</button>
                        <button type="button" data-radius="100" class="btn-radius-preset text-[11px] px-2 py-0.5 rounded bg-white/5 hover:bg-cyan-500/20 hover:text-cyan-300 text-slate-300 border border-white/10 transition-colors">100m</button>
                        <button type="button" data-radius="200" class="btn-radius-preset text-[11px] px-2 py-0.5 rounded bg-white/5 hover:bg-cyan-500/20 hover:text-cyan-300 text-slate-300 border border-white/10 transition-colors">200m</button>
                        <button type="button" data-radius="500" class="btn-radius-preset text-[11px] px-2 py-0.5 rounded bg-white/5 hover:bg-cyan-500/20 hover:text-cyan-300 text-slate-300 border border-white/10 transition-colors">500m</button>
                    </div>
                </div>
            </div>

            <!-- Panel 3: Jam Kerja & Kebijakan -->
            <div class="glass-panel p-6 space-y-4">
                <div class="flex items-center gap-2 border-b border-white/10 pb-3">
                    <i class="fa-solid fa-clock text-cyan-400"></i>
                    <h3 class="text-sm font-bold text-white">Jam Kerja & Validasi Presensi</h3>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-slate-400 mb-1">Jam Masuk Kerja</label>
                        <input type="time" step="1" name="work_start_time" value="<?= htmlspecialchars($settings['work_start_time']) ?>" required class="glass-input w-full text-xs font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-slate-400 mb-1">Jam Pulang Kerja</label>
                        <input type="time" step="1" name="work_end_time" value="<?= htmlspecialchars($settings['work_end_time']) ?>" required class="glass-input w-full text-xs font-mono">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-medium text-slate-400 mb-1">Toleransi Keterlambatan (Menit)</label>
                    <input type="number" name="late_tolerance_minutes" min="0" max="120" value="<?= (int)$settings['late_tolerance_minutes'] ?>" class="glass-input w-full text-xs font-mono">
                    <p class="text-[10px] text-slate-500 mt-1">Absen di atas jam masuk + toleransi akan tercatat status "Terlambat".</p>
                </div>

                <!-- Toggles -->
                <div class="space-y-3 pt-2">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="enforce_radius" value="1" <?= $settings['enforce_radius'] ? 'checked' : '' ?> class="w-4 h-4 rounded text-cyan-500 focus:ring-cyan-400 focus:ring-offset-slate-900">
                        <div>
                            <span class="text-xs font-semibold text-white">Ketat Radius GPS (Enforce Strict Radius)</span>
                            <p class="text-[10px] text-slate-400">Tolak / kunci tombol presensi jika jarak karyawan melebihi radius kantor.</p>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="require_selfie" value="1" <?= $settings['require_selfie'] ? 'checked' : '' ?> class="w-4 h-4 rounded text-cyan-500 focus:ring-cyan-400 focus:ring-offset-slate-900">
                        <div>
                            <span class="text-xs font-semibold text-white">Wajibkan Verifikasi Foto Selfie</span>
                            <p class="text-[10px] text-slate-400">Ambil bukti foto snapshot kamera setiap kali absen masuk dan pulang.</p>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="allow_simulation" value="1" <?= $settings['allow_simulation'] ? 'checked' : '' ?> class="w-4 h-4 rounded text-cyan-500 focus:ring-cyan-400 focus:ring-offset-slate-900">
                        <div>
                            <span class="text-xs font-semibold text-white">Aktifkan Simulator Koordinat (Testing Mode)</span>
                            <p class="text-[10px] text-slate-400">Tampilkan panel tombol simulasi di halaman presensi untuk demo & pengujian.</p>
                        </div>
                    </label>
                </div>

                <!-- Submit Button -->
                <div class="pt-4 border-t border-white/10">
                    <button type="submit" class="btn-glossy btn-glossy-cyan w-full py-3 flex items-center justify-center gap-2 text-sm">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan Pengaturan Radius
                    </button>
                </div>

            </div>

        </div>

        <!-- Right Column: Interactive Map Picker (7 Cols) -->
        <div class="lg:col-span-7">
            <div class="glass-panel p-6 h-full flex flex-col">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-crosshairs text-cyan-400"></i>
                        <h3 class="text-sm font-bold text-white">Peta Pemilihan Titik Kantor & Radius Live</h3>
                    </div>
                    <span class="text-[11px] text-slate-400 flex items-center gap-1">
                        <i class="fa-solid fa-hand-pointer text-cyan-400"></i> Geser pin atau klik di peta
                    </span>
                </div>

                <div class="p-2.5 rounded-lg bg-cyan-950/30 border border-cyan-500/20 text-xs text-cyan-300 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Lingkaran biru muda menandakan zona radius absen. Sesuaikan slider untuk memperbesar / memperkecil area.</span>
                </div>

                <!-- Leaflet Interactive Map -->
                <div id="settingsMap" class="w-full rounded-xl border border-white/10 z-0" style="height: 520px; min-height: 480px; width: 100%;"></div>

                <div class="mt-3 text-[11px] text-slate-500 flex justify-between items-center">
                    <span>*Koordinat diperbarui secara otomatis saat marker digeser</span>
                    <span class="font-mono text-slate-400">Peta OpenStreetMap Leaflet</span>
                </div>
            </div>
        </div>

    </form>

</div>

<!-- Settings Map Script -->
<script src="<?= BASE_URL ?>/assets/js/settings-map.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
