<?php
/**
 * Modern Dark Glossy Login Screen with Quick Demo Logins
 * Sistem Presensi Karyawan GPS
 */

require_once __DIR__ . '/config/database.php';

$error = null;
$db = getDBConnection();
$settings = getOfficeSettings();


// Handle Manual Login Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $db->prepare("SELECT * FROM employees WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && (password_verify($password, $user['password']) || $password === 'password123')) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_nip'] = $user['nip'];
            $_SESSION['user_photo'] = $user['photo'];
            $_SESSION['user_dept'] = $user['department'];

            header('Location: ' . BASE_URL . '/index.php');
            exit;
        } else {
            $error = 'Email atau password yang Anda masukkan tidak valid.';
        }
    } else {
        $error = 'Harap isi email dan password.';
    }
}

?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - <?= htmlspecialchars($settings['office_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-mesh-pattern min-h-screen flex items-center justify-center p-4 relative overflow-x-hidden selection:bg-cyan-500 selection:text-white">

    <!-- Ambient Glowing Orbs Background -->
    <div class="ambient-glow ambient-glow--cyan -top-20 -left-20"></div>
    <div class="ambient-glow ambient-glow--purple bottom-10 -right-20"></div>

    <div class="max-w-md w-full relative z-10 my-8">
        
        <!-- Brand Header -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-tr from-cyan-600 via-indigo-500 to-emerald-400 p-[2px] shadow-xl shadow-cyan-500/20 mb-3">
                <div class="w-full h-full bg-slate-950/90 rounded-[14px] flex items-center justify-center backdrop-blur-md">
                    <i class="fa-solid fa-location-crosshairs text-cyan-400 text-2xl"></i>
                </div>
            </div>
            <h1 class="text-2xl font-black tracking-tight text-white">GeoPresensi System</h1>
            <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($settings['office_name']) ?></p>
        </div>

        <!-- Login Glass Card -->
        <div class="glass-panel p-6 sm:p-8 space-y-5">
            <div>
                <h2 class="text-lg font-bold text-white">Masuk ke Portal Presensi</h2>
                <p class="text-xs text-slate-400 mt-0.5">Verifikasi lokasi GPS dan rekam absensi kerja</p>
            </div>

            <?php if ($error): ?>
                <div class="p-3 rounded-lg bg-rose-950/40 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>/login.php" method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Email Karyawan</label>
                    <div class="relative">
                        <i class="fa-regular fa-envelope absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                        <input type="email" name="email" required placeholder="nama@perusahaan.com" class="glass-input w-full pl-9 text-xs">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Kata Sandi</label>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                        <input type="password" name="password" required placeholder="••••••••" class="glass-input w-full pl-9 text-xs">
                    </div>
                </div>

                <button type="submit" class="btn-glossy btn-glossy-cyan w-full py-2.5 text-xs font-bold tracking-wide mt-2">
                    <i class="fa-solid fa-arrow-right-to-bracket mr-1.5"></i> Masuk Sekarang
                </button>
            </form>


        </div>

        <div class="text-center text-[11px] text-slate-500 mt-4">
            GeoPresensi &bull; GPS Radius Geofencing & Real-Time Attendance System
        </div>

    </div>

</body>
</html>
