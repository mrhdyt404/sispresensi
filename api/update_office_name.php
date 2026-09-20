<?php
/**
 * API Quick Update Office Name (Admin Only)
 * Sistem Presensi Karyawan GPS - Dark Glossy
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUser = getCurrentUser();
if (!$currentUser || ($currentUser['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Akses ditolak. Hanya Administrator yang dapat mengubah teks nama kantor pada header.'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$officeName = trim($input['office_name'] ?? $_POST['office_name'] ?? '');

if (empty($officeName)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Teks nama kantor tidak boleh kosong.'
    ]);
    exit;
}

try {
    $db = getDBConnection();
    $stmt = $db->prepare("UPDATE settings SET office_name = :name WHERE id = 1");
    $stmt->execute([':name' => $officeName]);

    echo json_encode([
        'success' => true,
        'message' => 'Teks header kantor berhasil diperbarui!',
        'office_name' => $officeName
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menyimpan ke database: ' . $e->getMessage()
    ]);
}
