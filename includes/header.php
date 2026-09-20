<?php
/**
 * Header Template - Modern Dark Glossy UI
 * Sistem Presensi Karyawan GPS
 */
require_once __DIR__ . '/../config/database.php';

// Ambil profil user segar dari database
$currentUser = getCurrentUser();
if (!$currentUser) {
    // Auto-login default demo jika belum ada sesi
    $db = getDBConnection();
    $stmt = $db->query("SELECT * FROM employees WHERE role = 'employee' LIMIT 1");
    $defaultUser = $stmt->fetch();
    if ($defaultUser) {
        $_SESSION['user_id'] = $defaultUser['id'];
        $currentUser = getCurrentUser();
    } else {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

$isAdmin = (($currentUser['role'] ?? '') === 'admin');
$officeSettings = getOfficeSettings();
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?><?= htmlspecialchars($officeSettings['office_name']) ?></title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            base: '#070a12',
                            surface: '#0c1220',
                            card: 'rgba(16, 24, 42, 0.7)',
                            border: 'rgba(255, 255, 255, 0.08)'
                        },
                        neon: {
                            cyan: '#06b6d4',
                            emerald: '#10b981',
                            indigo: '#6366f1',
                            rose: '#f43f5e',
                            amber: '#f59e0b'
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif']
                    }
                }
            }
        }
    </script>

    <!-- Compiled SCSS Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

    <!-- Leaflet Maps CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* Custom Global Variables Injection */
        :root {
            --office-lat: <?= json_encode((float)$officeSettings['latitude']) ?>;
            --office-lng: <?= json_encode((float)$officeSettings['longitude']) ?>;
            --office-radius: <?= json_encode((int)$officeSettings['radius_meters']) ?>;
        }
    </style>
</head>
<body class="bg-mesh-pattern min-h-screen text-slate-100 flex flex-col relative antialiased selection:bg-cyan-500 selection:text-white pb-16 md:pb-0">

    <!-- Ambient Glowing Orbs Background -->
    <div class="ambient-glow ambient-glow--cyan -top-24 -left-20"></div>
    <div class="ambient-glow ambient-glow--purple top-1/3 -right-28"></div>
    <div class="ambient-glow ambient-glow--emerald bottom-10 left-1/4"></div>

    <!-- Glassmorphic Navbar -->
    <header class="glass-navbar no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                
                <!-- Logo & Brand -->
                <div class="flex items-center space-x-3">
                    <a href="<?= BASE_URL ?>/index.php" class="flex items-center space-x-3 group">
                        <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-cyan-600 via-indigo-500 to-emerald-400 p-[1.5px] shadow-lg shadow-cyan-500/20 group-hover:shadow-cyan-500/40 transition-all duration-300">
                            <div class="w-full h-full bg-slate-950/90 rounded-[10px] flex items-center justify-center backdrop-blur-md">
                                <i class="fa-solid fa-location-crosshairs text-cyan-400 text-lg group-hover:scale-110 transition-transform"></i>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xl font-bold tracking-tight bg-gradient-to-r from-white via-slate-100 to-cyan-300 bg-clip-text text-transparent">
                                    GeoPresensi
                                </span>
                                <span class="px-2 py-0.5 text-[10px] uppercase font-bold tracking-wider rounded <?= $isAdmin ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' ?>">
                                    <?= $isAdmin ? 'PANEL ADMIN' : 'KARYAWAN' ?>
                                </span>
                            </div>
                        </a>
                        <div class="flex items-center gap-1.5">
                            <p id="headerOfficeNameText" class="text-xs text-slate-400 truncate max-w-[170px] sm:max-w-xs font-medium">
                                <?= htmlspecialchars($officeSettings['office_name']) ?>
                            </p>
                            <?php if ($isAdmin): ?>
                                <button type="button" 
                                        onclick="event.preventDefault(); event.stopPropagation(); openEditOfficeModal();" 
                                        title="Ubah teks nama kantor pada header" 
                                        class="inline-flex items-center justify-center w-4 h-4 rounded bg-white/5 hover:bg-cyan-500/20 text-slate-400 hover:text-cyan-300 border border-white/10 hover:border-cyan-500/30 transition-all text-[10px]">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                </div>

                <!-- Center Navigation (Role Based) -->
                <nav class="hidden md:flex items-center space-x-1 glass-panel px-3 py-1.5 border border-white/5 rounded-full">
                    <!-- Menu Umum -->
                    <a href="<?= BASE_URL ?>/index.php" 
                       class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all duration-200 flex items-center gap-1.5 <?= $currentScript === 'index.php' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 shadow-sm shadow-cyan-500/20' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                        <i class="fa-solid fa-fingerprint <?= $currentScript === 'index.php' ? 'text-cyan-400' : 'text-slate-400' ?>"></i>
                        Presensi Live
                    </a>

                    <?php if ($isAdmin): ?>
                        <!-- Menu Khusus Administrator -->
                        <a href="<?= BASE_URL ?>/laporan.php" 
                           class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all duration-200 flex items-center gap-1.5 <?= $currentScript === 'laporan.php' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 shadow-sm shadow-cyan-500/20' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                            <i class="fa-solid fa-chart-pie <?= $currentScript === 'laporan.php' ? 'text-cyan-400' : 'text-slate-400' ?>"></i>
                            Rekap Laporan
                        </a>
                        <a href="<?= BASE_URL ?>/karyawan.php" 
                           class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all duration-200 flex items-center gap-1.5 <?= $currentScript === 'karyawan.php' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 shadow-sm shadow-cyan-500/20' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                            <i class="fa-solid fa-users-gear <?= $currentScript === 'karyawan.php' ? 'text-cyan-400' : 'text-slate-400' ?>"></i>
                            Kelola Karyawan
                        </a>
                        <a href="<?= BASE_URL ?>/settings.php" 
                           class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all duration-200 flex items-center gap-1.5 <?= $currentScript === 'settings.php' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 shadow-sm shadow-cyan-500/20' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                            <i class="fa-solid fa-sliders <?= $currentScript === 'settings.php' ? 'text-cyan-400' : 'text-slate-400' ?>"></i>
                            Setting Radius
                        </a>
                    <?php else: ?>
                        <!-- Menu Khusus Karyawan -->
                        <a href="<?= BASE_URL ?>/riwayat.php" 
                           class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all duration-200 flex items-center gap-1.5 <?= $currentScript === 'riwayat.php' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 shadow-sm shadow-cyan-500/20' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                            <i class="fa-solid fa-clock-rotate-left <?= $currentScript === 'riwayat.php' ? 'text-cyan-400' : 'text-slate-400' ?>"></i>
                            Riwayat Saya
                        </a>
                        <a href="<?= BASE_URL ?>/tim.php" 
                           class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all duration-200 flex items-center gap-1.5 <?= $currentScript === 'tim.php' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 shadow-sm shadow-cyan-500/20' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                            <i class="fa-solid fa-users <?= $currentScript === 'tim.php' ? 'text-cyan-400' : 'text-slate-400' ?>"></i>
                            Rekan Kerja
                        </a>
                    <?php endif; ?>

                    <a href="<?= BASE_URL ?>/profile.php" 
                       class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all duration-200 flex items-center gap-1.5 <?= $currentScript === 'profile.php' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 shadow-sm shadow-cyan-500/20' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                        <i class="fa-solid fa-user-gear <?= $currentScript === 'profile.php' ? 'text-cyan-400' : 'text-slate-400' ?>"></i>
                        Profil Saya
                    </a>
                </nav>

                <!-- Right Side: Realtime Clock & Profile Menu -->
                <div class="flex items-center space-x-3 sm:space-x-4">
                    

                    <!-- User Pill (Click to Profile) -->
                    <div class="relative flex items-center">
                        <a href="<?= BASE_URL ?>/profile.php" title="Buka Profil & Pengaturan Akun" class="glass-card flex items-center p-1.5 pr-3 rounded-full space-x-2.5 border border-white/10 hover:border-cyan-500/40 transition-colors">
                            <img src="<?= htmlspecialchars($currentUser['photo'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150') ?>" 
                                 alt="Avatar" 
                                 class="w-8 h-8 rounded-full object-cover ring-2 ring-cyan-500/40">
                            <div class="text-left hidden sm:block">
                                <div class="text-xs font-semibold text-white truncate max-w-[110px]">
                                    <?= htmlspecialchars($currentUser['name']) ?>
                                </div>
                                <div class="text-[10px] text-cyan-400 uppercase font-mono flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $isAdmin ? 'bg-purple-400' : 'bg-emerald-400' ?>"></span>
                                    <?= strtoupper($currentUser['role']) ?>
                                </div>
                            </div>
                        </a>

                        <!-- Logout Button -->
                        <a href="<?= BASE_URL ?>/logout.php" title="Keluar / Logout" class="ml-2 text-slate-400 hover:text-rose-400 p-2 rounded-full hover:bg-white/10 transition-colors">
                            <i class="fa-solid fa-arrow-right-from-bracket text-sm"></i>
                        </a>
                    </div>

                </div>

            </div>
        </div>
        
        <!-- Mobile Bottom Nav Bar (Ergonomic Phone Navigation) -->
        <div class="md:hidden fixed bottom-0 left-0 right-0 z-50 flex items-center justify-around py-2 border-t border-white/10 bg-slate-950/90 backdrop-blur-xl shadow-2xl">
            <a href="<?= BASE_URL ?>/index.php" class="flex flex-col items-center text-[10px] font-medium py-1 px-2 rounded-lg transition-colors <?= $currentScript === 'index.php' ? 'text-cyan-400' : 'text-slate-400 hover:text-slate-200' ?>">
                <i class="fa-solid fa-fingerprint text-lg mb-0.5"></i>
                <span>Presensi</span>
            </a>

            <?php if ($isAdmin): ?>
                <a href="<?= BASE_URL ?>/laporan.php" class="flex flex-col items-center text-[10px] font-medium py-1 px-2 rounded-lg transition-colors <?= $currentScript === 'laporan.php' ? 'text-cyan-400' : 'text-slate-400 hover:text-slate-200' ?>">
                    <i class="fa-solid fa-chart-pie text-lg mb-0.5"></i>
                    <span>Laporan</span>
                </a>
                <a href="<?= BASE_URL ?>/karyawan.php" class="flex flex-col items-center text-[10px] font-medium py-1 px-2 rounded-lg transition-colors <?= $currentScript === 'karyawan.php' ? 'text-cyan-400' : 'text-slate-400 hover:text-slate-200' ?>">
                    <i class="fa-solid fa-users-gear text-lg mb-0.5"></i>
                    <span>Karyawan</span>
                </a>
                <a href="<?= BASE_URL ?>/settings.php" class="flex flex-col items-center text-[10px] font-medium py-1 px-2 rounded-lg transition-colors <?= $currentScript === 'settings.php' ? 'text-cyan-400' : 'text-slate-400 hover:text-slate-200' ?>">
                    <i class="fa-solid fa-sliders text-lg mb-0.5"></i>
                    <span>Radius</span>
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/riwayat.php" class="flex flex-col items-center text-[10px] font-medium py-1 px-2 rounded-lg transition-colors <?= $currentScript === 'riwayat.php' ? 'text-cyan-400' : 'text-slate-400 hover:text-slate-200' ?>">
                    <i class="fa-solid fa-clock-rotate-left text-lg mb-0.5"></i>
                    <span>Riwayat</span>
                </a>
                <a href="<?= BASE_URL ?>/tim.php" class="flex flex-col items-center text-[10px] font-medium py-1 px-2 rounded-lg transition-colors <?= $currentScript === 'tim.php' ? 'text-cyan-400' : 'text-slate-400 hover:text-slate-200' ?>">
                    <i class="fa-solid fa-users text-lg mb-0.5"></i>
                    <span>Teman</span>
                </a>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/profile.php" class="flex flex-col items-center text-[10px] font-medium py-1 px-2 rounded-lg transition-colors <?= $currentScript === 'profile.php' ? 'text-cyan-400' : 'text-slate-400 hover:text-slate-200' ?>">
                <i class="fa-solid fa-user text-lg mb-0.5"></i>
                <span>Profil</span>
            </a>
        </div>

        <?php if ($isAdmin): ?>
            <!-- Modal Cepat Ubah Nama Kantor / Teks Header (Khusus Admin) -->
            <div id="modalEditOfficeName" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md hidden transition-all">
                <div class="glass-card max-w-md w-full p-6 space-y-4 border border-cyan-500/30 shadow-2xl relative">
                    <div class="flex items-center justify-between border-b border-white/10 pb-3">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-building-circle-check text-cyan-400 text-base"></i>
                            <h3 class="text-sm font-bold text-white">Ubah Nama Kantor / Teks Header</h3>
                        </div>
                        <button type="button" onclick="closeEditOfficeModal()" class="text-slate-400 hover:text-white p-1">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <form id="formEditOfficeName" onsubmit="handleSaveOfficeName(event)" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">
                                Nama Kantor / Instansi <span class="text-rose-400">*</span>
                            </label>
                            <input type="text" id="inputOfficeNameModal" required class="glass-input w-full text-xs font-medium" placeholder="Contoh: Kantor Pusat PT Solusindo" value="<?= htmlspecialchars($officeSettings['office_name']) ?>">
                            <span class="text-[10px] text-slate-400 mt-1.5 block leading-relaxed">
                                Teks ini langsung diperbarui dan ditampilkan di bawah logo GeoPresensi pada &lt;header&gt; semua halaman, perangkat mobile, dan kop cetak laporan.
                            </span>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-white/5">
                            <button type="button" onclick="closeEditOfficeModal()" class="btn-glossy btn-glossy-secondary text-xs py-1.5 px-3">
                                Batal
                            </button>
                            <button type="submit" id="btnSaveOfficeName" class="btn-glossy btn-glossy-cyan text-xs py-1.5 px-3.5 flex items-center gap-1.5">
                                <i class="fa-solid fa-floppy-disk"></i> Simpan Teks
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                function openEditOfficeModal() {
                    const modal = document.getElementById('modalEditOfficeName');
                    if (modal) {
                        modal.classList.remove('hidden');
                        const inp = document.getElementById('inputOfficeNameModal');
                        if (inp) {
                            inp.focus();
                            inp.select();
                        }
                    }
                }

                function closeEditOfficeModal() {
                    const modal = document.getElementById('modalEditOfficeName');
                    if (modal) modal.classList.add('hidden');
                }

                async function handleSaveOfficeName(e) {
                    e.preventDefault();
                    const input = document.getElementById('inputOfficeNameModal');
                    const btn = document.getElementById('btnSaveOfficeName');
                    const newName = input.value.trim();
                    if (!newName) return;

                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Menyimpan...';

                    try {
                        const res = await fetch('<?= BASE_URL ?>/api/update_office_name.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ office_name: newName })
                        });
                        const data = await res.json();
                        if (data.success) {
                            // Update teks langsung di header
                            const headerText = document.getElementById('headerOfficeNameText');
                            if (headerText) headerText.textContent = data.office_name;

                            // Jika user sedang berada di settings.php, perbarui inputnya
                            const settingsInput = document.querySelector('input[name="office_name"]');
                            if (settingsInput) settingsInput.value = data.office_name;

                            closeEditOfficeModal();
                        } else {
                            alert(data.message || 'Gagal memperbarui nama kantor.');
                        }
                    } catch (err) {
                        alert('Terjadi kesalahan koneksi: ' + err.message);
                    } finally {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1"></i> Simpan Teks';
                    }
                }
            </script>
        <?php endif; ?>
    </header>

    <!-- Flash Alert Unauthorized jika karyawan mencoba akses menu admin -->
    <?php if (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="p-4 rounded-xl bg-rose-950/50 border border-rose-500/40 text-rose-300 flex items-center justify-between text-xs sm:text-sm shadow-lg shadow-rose-950/40">
                <div class="flex items-center gap-2.5">
                    <i class="fa-solid fa-shield-halved text-rose-400 text-lg"></i>
                    <span><strong>Akses Ditolak:</strong> Halaman yang Anda tuju hanya diperuntukkan bagi Administrator.</span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-rose-400 hover:text-white p-1">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Content Container -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 z-10">
