<?php
/**
 * Database Configuration and Global Helpers
 * Sistem Presensi Karyawan GPS - Dark Glossy
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'sispresensi_db');
define('DB_USER', 'sispresensi_user');
define('DB_PASS', 'presensi2026');
define('DB_PORT', 3306);

// Base URL detection dinamis (Mendukung VirtualHost root maupun Subdirektori)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$protocol = $isHttps ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html');
$appRoot = realpath(dirname(__DIR__));

$subDir = '';
if ($docRoot && $appRoot && strpos($appRoot, $docRoot) === 0) {
    $subDir = substr($appRoot, strlen($docRoot));
    $subDir = str_replace('\\', '/', $subDir);
    $subDir = rtrim($subDir, '/');
}

if (strpos($requestUri, '/sispresensi') === 0 && empty($subDir)) {
    $subDir = '/sispresensi';
}

define('BASE_URL', $protocol . $host . $subDir);

/**
 * Mendapatkan koneksi PDO Database
 */
function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Koneksi database gagal: " . htmlspecialchars($e->getMessage()));
        }
    }
    return $pdo;
}

/**
 * Mengambil data pengaturan kantor
 */
function getOfficeSettings() {
    $db = getDBConnection();
    $stmt = $db->query("SELECT * FROM settings LIMIT 1");
    $settings = $stmt->fetch();
    if (!$settings) {
        // Default settings jika belum ada di database
        return [
            'id' => 1,
            'office_name' => 'PT Antigravity Digital Solusindo',
            'office_address' => 'Jl. Jenderal Sudirman Kav. 52-53, Senayan, Jakarta Selatan',
            'latitude' => -6.224168,
            'longitude' => 106.809675,
            'radius_meters' => 100,
            'work_start_time' => '08:00:00',
            'work_end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'enforce_radius' => 1,
            'require_selfie' => 1,
            'allow_simulation' => 1
        ];
    }
    return $settings;
}

/**
 * Menghitung jarak antara 2 koordinat (Latitude & Longitude) dalam satuan Meter
 * Menggunakan rumus Haversine
 */
function calculateHaversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Radius bumi dalam meter

    $latDelta = deg2rad($lat2 - $lat1);
    $lonDelta = deg2rad($lon2 - $lon1);

    $a = sin($latDelta / 2) * sin($latDelta / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lonDelta / 2) * sin($lonDelta / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return round($earthRadius * $c, 1);
}

/**
 * Helper untuk memeriksa apakah user sudah login
 */
function checkAuth($roleRequired = null) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    if ($roleRequired && ($_SESSION['user_role'] ?? '') !== $roleRequired && ($_SESSION['user_role'] ?? '') !== 'admin') {
        header('Location: ' . BASE_URL . '/index.php?error=unauthorized');
        exit;
    }
}

/**
 * Mengambil data profil user yang sedang aktif langsung dari DB
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM employees WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user) {
        // Sinkronisasi session
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_nip'] = $user['nip'];
        $_SESSION['user_photo'] = $user['photo'];
        $_SESSION['user_dept'] = $user['department'];
        $_SESSION['user_position'] = $user['position'];
        return $user;
    }
    return null;
}

/**
 * Format tanggal dalam bahasa Indonesia
 */
function formatIndonesianDate($dateStr) {
    if (!$dateStr) return '-';
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $days = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
    ];

    $timestamp = strtotime($dateStr);
    $dayName = $days[date('l', $timestamp)] ?? '';
    $day = date('d', $timestamp);
    $month = $months[(int)date('m', $timestamp)] ?? '';
    $year = date('Y', $timestamp);

    return "$dayName, $day $month $year";
}
