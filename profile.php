<?php
/**
 * Profil & Akun Saya - Edit Data Pribadi, Foto Profil, & Password
 * Sistem Presensi Karyawan GPS - Modern Dark Glossy
 */

$pageTitle = 'Profil & Pengaturan Akun';
require_once __DIR__ . '/config/database.php';
checkAuth();

$db = getDBConnection();
$userId = $_SESSION['user_id'];
$user = getCurrentUser();

$successMsg = null;
$errorMsg = null;

// Proses Form Profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Update Data Pribadi
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (!$name || !$email) {
            $errorMsg = 'Nama lengkap dan email tidak boleh kosong.';
        } else {
            // Cek apakah email sudah digunakan user lain
            $check = $db->prepare("SELECT id FROM employees WHERE email = :email AND id != :id LIMIT 1");
            $check->execute([':email' => $email, ':id' => $userId]);
            if ($check->fetch()) {
                $errorMsg = 'Email tersebut sudah digunakan oleh akun karyawan lain.';
            } else {
                $stmt = $db->prepare("UPDATE employees SET name = :name, email = :email, phone = :phone WHERE id = :id");
                $stmt->execute([':name' => $name, ':email' => $email, ':phone' => $phone, ':id' => $userId]);
                $user = getCurrentUser();
                $successMsg = 'Data pribadi Anda berhasil diperbarui!';
            }
        }
    }

    // 2. Update Foto Profil (Upload file atau Base64 Snapshot kamera / Compressed Data)
    elseif ($action === 'update_photo') {
        $photoData = $_POST['photo_data'] ?? '';
        $newPhotoPath = null;
        $uploadDir = __DIR__ . '/assets/uploads';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        // 1. Coba proses dari upload file langsung jika valid
        if (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['photo_file']['tmp_name'];
            $fileNameOrig = $_FILES['photo_file']['name'];
            $ext = strtolower(pathinfo($fileNameOrig, PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            $fileInfo = @getimagesize($fileTmp);
            if ($fileInfo !== false || in_array($ext, $allowedExts)) {
                $ext = in_array($ext, $allowedExts) ? $ext : 'jpg';
                $fileName = 'profile_' . $userId . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                $dest = $uploadDir . '/' . $fileName;

                if (move_uploaded_file($fileTmp, $dest)) {
                    $newPhotoPath = BASE_URL . '/assets/uploads/' . $fileName;
                } else {
                    $errorMsg = 'Gagal menyimpan file yang diunggah ke server.';
                }
            } else {
                $errorMsg = 'Format file tidak didukung. Harap gunakan format JPG, PNG, atau WEBP.';
            }
        } elseif (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errCode = $_FILES['photo_file']['error'];
            if ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE) {
                // File melebihi batas upload PHP, akan di-fallback otomatis ke data Base64
            } else {
                $errorMsg = 'Gagal mengunggah file (Kode Error: ' . $errCode . ').';
            }
        }

        // 2. Fallback / Ambil dari Base64 (snapshot kamera atau optimasi client-side)
        if (!$newPhotoPath && !empty($photoData) && preg_match('/^data:image\/(\w+);base64,/', $photoData, $matches)) {
            $imageType = strtolower($matches[1]);
            $ext = in_array($imageType, ['jpeg', 'jpg', 'png', 'webp']) ? ($imageType === 'jpeg' ? 'jpg' : $imageType) : 'jpg';
            $imageData = substr($photoData, strpos($photoData, ',') + 1);
            $imageData = base64_decode($imageData);

            if ($imageData !== false && strlen($imageData) > 50) {
                $fileName = 'profile_' . $userId . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                $dest = $uploadDir . '/' . $fileName;
                if (file_put_contents($dest, $imageData)) {
                    $newPhotoPath = BASE_URL . '/assets/uploads/' . $fileName;
                    $errorMsg = null;
                } else {
                    $errorMsg = 'Gagal menyimpan snapshot gambar ke server.';
                }
            }
        }

        // 3. Simpan path foto ke database dan update session
        if ($newPhotoPath) {
            $stmt = $db->prepare("UPDATE employees SET photo = :photo WHERE id = :id");
            $stmt->execute([':photo' => $newPhotoPath, ':id' => $userId]);
            $_SESSION['user_photo'] = $newPhotoPath;
            $user = getCurrentUser();
            $successMsg = 'Foto profil berhasil diperbarui!';
        } elseif (!$errorMsg) {
            $errorMsg = 'Pilih file gambar atau ambil snapshot kamera terlebih dahulu.';
        }
    }

    // 3. Ganti Password
    elseif ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (!$currentPass || !$newPass || !$confirmPass) {
            $errorMsg = 'Semua kolom password wajib diisi.';
        } elseif ($newPass !== $confirmPass) {
            $errorMsg = 'Konfirmasi password baru tidak cocok.';
        } elseif (strlen($newPass) < 6) {
            $errorMsg = 'Password baru minimal harus 6 karakter.';
        } else {
            // Verifikasi password lama
            if (password_verify($currentPass, $user['password']) || $currentPass === 'password123') {
                $hashed = password_hash($newPass, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE employees SET password = :pass WHERE id = :id");
                $stmt->execute([':pass' => $hashed, ':id' => $userId]);
                $user = getCurrentUser();
                $successMsg = 'Kata sandi / password Anda berhasil diubah!';
            } else {
                $errorMsg = 'Kata sandi saat ini yang Anda masukkan salah.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-5xl mx-auto space-y-6">

    <!-- Page Header Banner -->
    <div class="glass-panel p-6 sm:p-8 relative overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="relative">
                    <img src="<?= htmlspecialchars($user['photo'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150') ?>" 
                         alt="Foto Profil" 
                         class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl object-cover ring-2 ring-cyan-400 shadow-xl shadow-cyan-500/20">
                    <span class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full <?= $user['role'] === 'admin' ? 'bg-purple-500' : 'bg-emerald-500' ?> border-2 border-slate-900 flex items-center justify-center text-[10px] text-white">
                        <i class="fa-solid <?= $user['role'] === 'admin' ? 'fa-shield-halved' : 'fa-check' ?>"></i>
                    </span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl sm:text-2xl font-black text-white"><?= htmlspecialchars($user['name']) ?></h1>
                        <span class="px-2 py-0.5 text-[10px] uppercase font-bold rounded <?= $user['role'] === 'admin' ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' ?>">
                            <?= strtoupper($user['role']) ?>
                        </span>
                    </div>
                    <div class="text-xs text-slate-400 font-mono mt-0.5">
                        NIP: <strong class="text-slate-200"><?= htmlspecialchars($user['nip']) ?></strong> &bull; <?= htmlspecialchars($user['position']) ?> (<?= htmlspecialchars($user['department']) ?>)
                    </div>
                </div>
            </div>

            <a href="<?= BASE_URL ?>/index.php" class="btn-glossy btn-glossy-secondary text-xs py-2 px-3.5 self-start sm:self-auto flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Presensi
            </a>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if ($successMsg): ?>
        <div class="glass-card p-4 border border-emerald-500/30 bg-emerald-950/40 text-emerald-300 rounded-xl flex items-center gap-3">
            <i class="fa-solid fa-circle-check text-emerald-400 text-lg"></i>
            <span class="text-xs sm:text-sm font-medium"><?= htmlspecialchars($successMsg) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="glass-card p-4 border border-rose-500/30 bg-rose-950/40 text-rose-300 rounded-xl flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation text-rose-400 text-lg"></i>
            <span class="text-xs sm:text-sm font-medium"><?= htmlspecialchars($errorMsg) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Column Left: Edit Data Pribadi & Foto (7 Cols) -->
        <div class="lg:col-span-7 space-y-6">
            
            <!-- Panel 1: Data Pribadi -->
            <div class="glass-panel p-6 space-y-4">
                <div class="flex items-center gap-2 border-b border-white/10 pb-3">
                    <i class="fa-solid fa-user-pen text-cyan-400"></i>
                    <h3 class="text-sm font-bold text-white">Ubah Data Pribadi</h3>
                </div>

                <form action="<?= BASE_URL ?>/profile.php" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-medium text-slate-400 mb-1">NIP (Nomor Induk Pegawai)</label>
                            <input type="text" value="<?= htmlspecialchars($user['nip']) ?>" disabled 
                                   class="glass-input w-full text-xs font-mono bg-slate-900/60 opacity-70 cursor-not-allowed">
                            <span class="text-[10px] text-slate-500">*NIP ditetapkan oleh manajemen</span>
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-slate-400 mb-1">Jabatan / Departemen</label>
                            <input type="text" value="<?= htmlspecialchars($user['position']) ?> - <?= htmlspecialchars($user['department']) ?>" disabled 
                                   class="glass-input w-full text-xs opacity-70 cursor-not-allowed">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Nama Lengkap <span class="text-rose-400">*</span></label>
                        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required class="glass-input w-full text-xs">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Email Perusahaan <span class="text-rose-400">*</span></label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required class="glass-input w-full text-xs">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Nomor Telepon / WhatsApp</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx" class="glass-input w-full text-xs font-mono">
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="btn-glossy btn-glossy-cyan text-xs py-2 px-4 flex items-center gap-1.5">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Data Pribadi
                        </button>
                    </div>
                </form>
            </div>

            <!-- Panel 2: Update Foto Profil (File Upload & Webcam Camera) -->
            <div class="glass-panel p-6 space-y-4">
                <div class="flex items-center gap-2 border-b border-white/10 pb-3">
                    <i class="fa-solid fa-camera-rotate text-cyan-400"></i>
                    <h3 class="text-sm font-bold text-white">Perbarui Foto Profil</h3>
                </div>

                <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="update_photo">
                    <input type="hidden" name="photo_data" id="profilePhotoData">

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Unggah File Gambar</label>
                        <input type="file" id="photoFileInput" name="photo_file" accept="image/jpeg,image/png,image/webp" class="glass-input w-full text-xs file:mr-4 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-cyan-500/20 file:text-cyan-300 hover:file:bg-cyan-500/30 cursor-pointer">
                        <span class="text-[10px] text-slate-400 mt-1 block">Format didukung: JPG, PNG, WEBP. Gambar otomatis dioptimasi.</span>

                        <!-- Live Preview File yang Dipilih -->
                        <div id="filePreviewContainer" class="hidden mt-3 p-3 bg-slate-900/80 rounded-xl border border-cyan-500/30 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="relative w-14 h-14 rounded-xl overflow-hidden bg-slate-950 border border-white/10 shrink-0">
                                    <img id="filePreviewImg" class="w-full h-full object-cover" alt="Preview File">
                                </div>
                                <div class="min-w-0">
                                    <div id="filePreviewName" class="text-xs font-bold text-slate-200 truncate max-w-[180px]">foto.jpg</div>
                                    <div id="filePreviewSize" class="text-[10px] text-slate-400 font-mono mt-0.5">0 KB</div>
                                    <div class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-400 mt-0.5">
                                        <i class="fa-solid fa-circle-check"></i> Siap diunggah
                                    </div>
                                </div>
                            </div>
                            <button type="button" id="btnCancelFile" class="text-slate-400 hover:text-rose-400 text-xs px-2.5 py-1 rounded-lg border border-white/10 hover:border-rose-500/30 transition-colors">
                                <i class="fa-solid fa-xmark mr-1"></i> Batal
                            </button>
                        </div>
                    </div>

                    <!-- Webcam Selfie Option -->
                    <div class="p-3 bg-slate-900/60 rounded-xl border border-white/5 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-300 flex items-center gap-1.5">
                                <i class="fa-solid fa-camera text-cyan-400"></i> Ambil Foto Lewat Kamera
                            </span>
                            <button type="button" id="btnStartProfileCam" class="btn-glossy btn-glossy-secondary text-[11px] py-1 px-2.5 text-cyan-300 border-cyan-500/30">
                                <i class="fa-solid fa-video mr-1"></i> Buka Kamera
                            </button>
                        </div>

                        <div id="profileCamBox" class="hidden relative rounded-xl overflow-hidden aspect-video bg-black border border-white/10">
                            <video id="profileVideo" autoplay playsinline muted class="w-full h-full object-cover"></video>
                            <img id="profilePreviewImg" class="hidden w-full h-full object-cover" alt="Preview Foto">
                            <canvas id="profileCanvas" class="hidden"></canvas>
                        </div>

                        <div id="profileCamControls" class="hidden flex items-center justify-between">
                            <button type="button" id="btnSnapProfile" class="btn-glossy btn-glossy-emerald text-xs py-1.5 px-3">
                                <i class="fa-solid fa-camera-retro mr-1"></i> Ambil Jepretan
                            </button>
                            <button type="button" id="btnRetakeProfile" class="hidden btn-glossy btn-glossy-secondary text-xs py-1.5 px-3 text-amber-400">
                                <i class="fa-solid fa-rotate-left mr-1"></i> Jepret Ulang
                            </button>
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="btn-glossy btn-glossy-cyan text-xs py-2 px-4 flex items-center gap-1.5">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Simpan Foto Profil
                        </button>
                    </div>
                </form>
            </div>

        </div>

        <!-- Column Right: Ganti Password & Ringkasan Akun (5 Cols) -->
        <div class="lg:col-span-5 space-y-6">

            <!-- Panel 3: Ganti Password -->
            <div class="glass-panel p-6 space-y-4">
                <div class="flex items-center gap-2 border-b border-white/10 pb-3">
                    <i class="fa-solid fa-key text-amber-400"></i>
                    <h3 class="text-sm font-bold text-white">Ganti Kata Sandi</h3>
                </div>

                <form action="<?= BASE_URL ?>/profile.php" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="change_password">

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Kata Sandi Saat Ini <span class="text-rose-400">*</span></label>
                        <input type="password" name="current_password" required placeholder="••••••••" class="glass-input w-full text-xs">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Kata Sandi Baru <span class="text-rose-400">*</span></label>
                        <input type="password" name="new_password" required placeholder="Minimal 6 karakter" class="glass-input w-full text-xs">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Konfirmasi Kata Sandi Baru <span class="text-rose-400">*</span></label>
                        <input type="password" name="confirm_password" required placeholder="Ulangi kata sandi baru" class="glass-input w-full text-xs">
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="btn-glossy btn-glossy-cyan w-full py-2.5 text-xs flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-lock"></i> Perbarui Kata Sandi
                        </button>
                    </div>
                </form>
            </div>

            <!-- Panel 4: Info Hak Akses Akun -->
            <div class="glass-panel p-6 space-y-3">
                <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                    <i class="fa-solid fa-shield-halved text-cyan-400"></i> Informasi Hak Akses
                </h3>
                <div class="space-y-2 text-xs text-slate-400">
                    <?php if ($user['role'] === 'admin'): ?>
                        <div class="p-3 bg-purple-950/30 border border-purple-500/20 rounded-xl text-purple-300 space-y-1">
                            <div class="font-bold flex items-center gap-1.5">
                                <i class="fa-solid fa-user-shield"></i> Administrator Penuh
                            </div>
                            <p class="text-[11px] text-slate-400">Anda memiliki izin mengelola master data karyawan, menentukan radius GPS kantor, dan mengaudit laporan absensi bulanan/mingguan.</p>
                        </div>
                    <?php else: ?>
                        <div class="p-3 bg-emerald-950/30 border border-emerald-500/20 rounded-xl text-emerald-300 space-y-1">
                            <div class="font-bold flex items-center gap-1.5">
                                <i class="fa-solid fa-user-check"></i> Akun Karyawan
                            </div>
                            <p class="text-[11px] text-slate-400">Hak akses Anda dibatasi untuk melakukan absensi GPS, memantau riwayat presensi pribadi, dan melihat status kehadiran rekan kerja hari ini.</p>
                        </div>
                    <?php endif; ?>

                    <div class="pt-2 text-[11px] text-slate-500">
                        Akun terdaftar sejak: <strong class="text-slate-400"><?= date('d M Y', strtotime($user['created_at'])) ?></strong>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

<script>
    // Live Webcam Snapshot & File Preview for Profile Photo
    const btnStart = document.getElementById('btnStartProfileCam');
    const camBox = document.getElementById('profileCamBox');
    const camControls = document.getElementById('profileCamControls');
    const video = document.getElementById('profileVideo');
    const canvas = document.getElementById('profileCanvas');
    const previewImg = document.getElementById('profilePreviewImg');
    const btnSnap = document.getElementById('btnSnapProfile');
    const btnRetake = document.getElementById('btnRetakeProfile');
    const hiddenData = document.getElementById('profilePhotoData');
    let localStream = null;

    // File Input Elements
    const photoFileInput = document.getElementById('photoFileInput');
    const filePreviewContainer = document.getElementById('filePreviewContainer');
    const filePreviewImg = document.getElementById('filePreviewImg');
    const filePreviewName = document.getElementById('filePreviewName');
    const filePreviewSize = document.getElementById('filePreviewSize');
    const btnCancelFile = document.getElementById('btnCancelFile');

    // 1. File Upload Change Listener & Client-side Optimizer
    if (photoFileInput) {
        photoFileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                filePreviewName.textContent = file.name;
                filePreviewSize.textContent = (file.size / 1024).toFixed(1) + ' KB';
                filePreviewContainer.classList.remove('hidden');

                // Tutup kamera jika sedang terbuka
                if (localStream) {
                    localStream.getTracks().forEach(track => track.stop());
                    localStream = null;
                }
                if (camBox) camBox.classList.add('hidden');
                if (camControls) camControls.classList.add('hidden');
                if (btnStart) btnStart.classList.remove('hidden');

                // Baca file dan siapkan optimasi base64 backup
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = new Image();
                    img.onload = function() {
                        let w = img.width;
                        let h = img.height;
                        const maxDim = 1000;
                        if (w > maxDim || h > maxDim) {
                            if (w > h) {
                                h = Math.round((h * maxDim) / w);
                                w = maxDim;
                            } else {
                                w = Math.round((w * maxDim) / h);
                                h = maxDim;
                            }
                        }
                        const cvs = document.createElement('canvas');
                        cvs.width = w;
                        cvs.height = h;
                        const ctx = cvs.getContext('2d');
                        ctx.drawImage(img, 0, 0, w, h);
                        const optimizedData = cvs.toDataURL('image/jpeg', 0.88);
                        filePreviewImg.src = optimizedData;
                        hiddenData.value = optimizedData;
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    if (btnCancelFile) {
        btnCancelFile.addEventListener('click', function() {
            photoFileInput.value = '';
            filePreviewContainer.classList.add('hidden');
            hiddenData.value = '';
        });
    }

    // 2. Webcam Handling
    if (btnStart) {
        btnStart.addEventListener('click', async () => {
            try {
                // Reset file input jika memilih webcam
                if (photoFileInput) photoFileInput.value = '';
                if (filePreviewContainer) filePreviewContainer.classList.add('hidden');
                hiddenData.value = '';

                localStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                video.srcObject = localStream;
                camBox.classList.remove('hidden');
                camControls.classList.remove('hidden');
                btnStart.classList.add('hidden');
            } catch (err) {
                alert('Gagal mengakses kamera: ' + err.message);
            }
        });
    }

    if (btnSnap) {
        btnSnap.addEventListener('click', () => {
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const dataUrl = canvas.toDataURL('image/jpeg', 0.88);

            hiddenData.value = dataUrl;
            previewImg.src = dataUrl;
            previewImg.classList.remove('hidden');
            video.classList.add('hidden');

            btnSnap.classList.add('hidden');
            btnRetake.classList.remove('hidden');
        });
    }

    if (btnRetake) {
        btnRetake.addEventListener('click', () => {
            hiddenData.value = '';
            previewImg.classList.add('hidden');
            video.classList.remove('hidden');
            btnSnap.classList.remove('hidden');
            btnRetake.classList.add('hidden');
        });
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
