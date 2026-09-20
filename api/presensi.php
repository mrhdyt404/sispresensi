<?php
/**
 * API Presensi - Memproses Absensi Masuk & Pulang
 * Validasi GPS Haversine, Foto Selfie, dan Jam Kerja
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi login telah berakhir. Silakan refresh halaman.']);
    exit;
}

$employeeId = $_SESSION['user_id'];
$action = trim($_POST['action'] ?? '');
$userLat = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$userLng = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
$photoData = $_POST['photo'] ?? null;
$notes = trim($_POST['notes'] ?? '');
$isSimulation = !empty($_POST['is_simulation']);

if (!in_array($action, ['check_in', 'check_out'])) {
    echo json_encode(['success' => false, 'message' => 'Aksi presensi tidak valid.']);
    exit;
}

if ($userLat === false || $userLng === false) {
    echo json_encode(['success' => false, 'message' => 'Koordinat GPS tidak terdeteksi atau tidak valid.']);
    exit;
}

try {
    $db = getDBConnection();
    $settings = getOfficeSettings();

    // Hitung jarak menggunakan rumus Haversine
    $officeLat = (float)$settings['latitude'];
    $officeLng = (float)$settings['longitude'];
    $allowedRadius = (int)$settings['radius_meters'];
    $distance = calculateHaversineDistance($userLat, $userLng, $officeLat, $officeLng);

    $isInsideRadius = ($distance <= $allowedRadius);

    // Cek pembatasan radius ketat (enforce_radius)
    if (!$isInsideRadius && !empty($settings['enforce_radius']) && !$isSimulation) {
        echo json_encode([
            'success' => false,
            'distance' => $distance,
            'allowed_radius' => $allowedRadius,
            'message' => "Anda berada di luar radius kantor ($distance meter dari kantor). Batas toleransi maksimal adalah $allowedRadius meter."
        ]);
        exit;
    }

    // Proses Foto Selfie (Base64 atau Upload)
    $savedPhotoPath = null;
    if ($photoData && preg_match('/^data:image\/(\w+);base64,/', $photoData, $matches)) {
        $imageType = strtolower($matches[1]);
        $imageData = substr($photoData, strpos($photoData, ',') + 1);
        $imageData = base64_decode($imageData);

        if ($imageData !== false) {
            $uploadDir = dirname(__DIR__) . '/assets/uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $fileName = 'selfie_' . $action . '_' . $employeeId . '_' . date('Ymd_His') . '.' . ($imageType === 'png' ? 'png' : 'jpg');
            $filePath = $uploadDir . '/' . $fileName;
            file_put_contents($filePath, $imageData);
            $savedPhotoPath = 'assets/uploads/' . $fileName;
        }
    }

    $today = date('Y-m-d');
    $currentTime = date('H:i:s');
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);

    // Cari riwayat presensi hari ini
    $checkStmt = $db->prepare("SELECT * FROM attendances WHERE employee_id = :emp_id AND date = :today LIMIT 1");
    $checkStmt->execute([':emp_id' => $employeeId, ':today' => $today]);
    $existing = $checkStmt->fetch();

    if ($action === 'check_in') {
        if ($existing && !empty($existing['check_in_time'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Anda sudah melakukan Absen Masuk hari ini pada pukul ' . substr($existing['check_in_time'], 0, 5) . ' WIB.'
            ]);
            exit;
        }

        // Tentukan status ketepatan waktu
        $startTime = $settings['work_start_time'];
        $lateTolerance = (int)$settings['late_tolerance_minutes'];
        $maxToleranceTime = date('H:i:s', strtotime("+$lateTolerance minutes", strtotime($startTime)));

        $status = 'on_time';
        if (!$isInsideRadius) {
            $status = 'out_of_radius';
        } elseif ($currentTime > $maxToleranceTime) {
            $status = 'late';
        }

        if ($existing) {
            $update = $db->prepare("
                UPDATE attendances 
                SET check_in_time = :in_time, check_in_lat = :lat, check_in_lng = :lng,
                    check_in_distance = :dist, check_in_photo = :photo, check_in_status = :status,
                    notes = :notes, ip_address = :ip, user_agent = :ua
                WHERE id = :id
            ");
            $update->execute([
                ':in_time' => $currentTime,
                ':lat' => $userLat,
                ':lng' => $userLng,
                ':dist' => $distance,
                ':photo' => $savedPhotoPath ?: $existing['check_in_photo'],
                ':status' => $status,
                ':notes' => $notes ?: $existing['notes'],
                ':ip' => $clientIp,
                ':ua' => $userAgent,
                ':id' => $existing['id']
            ]);
        } else {
            $insert = $db->prepare("
                INSERT INTO attendances (
                    employee_id, date, check_in_time, check_in_lat, check_in_lng,
                    check_in_distance, check_in_photo, check_in_status, notes, ip_address, user_agent
                ) VALUES (
                    :emp_id, :today, :in_time, :lat, :lng,
                    :dist, :photo, :status, :notes, :ip, :ua
                )
            ");
            $insert->execute([
                ':emp_id' => $employeeId,
                ':today' => $today,
                ':in_time' => $currentTime,
                ':lat' => $userLat,
                ':lng' => $userLng,
                ':dist' => $distance,
                ':photo' => $savedPhotoPath,
                ':status' => $status,
                ':notes' => $notes,
                ':ip' => $clientIp,
                ':ua' => $userAgent
            ]);
        }

        $statusLabel = ($status === 'on_time') ? 'Tepat Waktu' : (($status === 'late') ? 'Terlambat' : 'Di Luar Radius');

        echo json_encode([
            'success' => true,
            'action' => 'check_in',
            'time' => substr($currentTime, 0, 5) . ' WIB',
            'distance' => $distance,
            'status' => $status,
            'status_label' => $statusLabel,
            'message' => "Absen Masuk Berhasil dicatat! Status: $statusLabel (Jarak: $distance m)."
        ]);
        exit;

    } elseif ($action === 'check_out') {
        if (!$existing || empty($existing['check_in_time'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Anda belum melakukan Absen Masuk hari ini. Silakan Absen Masuk terlebih dahulu.'
            ]);
            exit;
        }

        if (!empty($existing['check_out_time'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Anda sudah melakukan Absen Pulang hari ini pada pukul ' . substr($existing['check_out_time'], 0, 5) . ' WIB.'
            ]);
            exit;
        }

        $existingNotes = trim($existing['notes'] ?? '');
        $finalNotes = $existingNotes;
        if (!empty($notes)) {
            $finalNotes = !empty($existingNotes) ? ($existingNotes . ' | ' . $notes) : $notes;
        }

        $update = $db->prepare("
            UPDATE attendances 
            SET check_out_time = :out_time, check_out_lat = :lat, check_out_lng = :lng,
                check_out_distance = :dist, check_out_photo = :photo, notes = :notes
            WHERE id = :id
        ");
        $update->execute([
            ':out_time' => $currentTime,
            ':lat' => $userLat,
            ':lng' => $userLng,
            ':dist' => $distance,
            ':photo' => $savedPhotoPath ?: $existing['check_out_photo'],
            ':notes' => $finalNotes,
            ':id' => $existing['id']
        ]);

        echo json_encode([
            'success' => true,
            'action' => 'check_out',
            'time' => substr($currentTime, 0, 5) . ' WIB',
            'distance' => $distance,
            'message' => "Absen Pulang Berhasil dicatat pada pukul " . substr($currentTime, 0, 5) . " WIB (Jarak: $distance m). Selamat beristirahat!"
        ]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()]);
    exit;
}
