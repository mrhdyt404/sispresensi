<?php
/**
 * Database Migration and Seeder Script
 * Sistem Presensi Karyawan GPS - Dark Glossy
 */

require_once __DIR__ . '/database.php';

echo "=== Memulai Migrasi Database Sispresensi ===\n";

try {
    $db = getDBConnection();

    // 1. Tabel settings (Pengaturan Kantor & Radius)
    $db->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            office_name VARCHAR(150) NOT NULL DEFAULT 'Kantor Pusat PT Digital Solusindo',
            office_address TEXT NULL,
            latitude DECIMAL(10, 8) NOT NULL DEFAULT -6.22416800,
            longitude DECIMAL(11, 8) NOT NULL DEFAULT 106.80967500,
            radius_meters INT NOT NULL DEFAULT 100,
            work_start_time TIME NOT NULL DEFAULT '08:00:00',
            work_end_time TIME NOT NULL DEFAULT '17:00:00',
            late_tolerance_minutes INT NOT NULL DEFAULT 15,
            enforce_radius TINYINT(1) NOT NULL DEFAULT 1,
            require_selfie TINYINT(1) NOT NULL DEFAULT 1,
            allow_simulation TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✓ Tabel 'settings' siap.\n";

    // Inisialisasi default settings jika kosong
    $stmt = $db->query("SELECT COUNT(*) FROM settings");
    if ($stmt->fetchColumn() == 0) {
        $db->exec("
            INSERT INTO settings (
                office_name, office_address, latitude, longitude, radius_meters, 
                work_start_time, work_end_time, late_tolerance_minutes, enforce_radius, require_selfie, allow_simulation
            ) VALUES (
                'Head Office PT Digital Solusindo',
                'Pacific Century Place Lt. 28, SCBD Kav. 52-53, Jl. Jend. Sudirman, Senayan, Jakarta Selatan',
                -6.22416800,
                106.80967500,
                150,
                '08:00:00',
                '17:00:00',
                15,
                1,
                1,
                1
            )
        ");
        echo "✓ Data awal 'settings' berhasil dibuat.\n";
    }

    // 2. Tabel employees (Karyawan)
    $db->exec("
        CREATE TABLE IF NOT EXISTS employees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nip VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'employee') NOT NULL DEFAULT 'employee',
            department VARCHAR(100) NOT NULL DEFAULT 'Teknologi Informasi',
            position VARCHAR(100) NOT NULL DEFAULT 'Software Engineer',
            phone VARCHAR(30) NULL,
            photo VARCHAR(255) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✓ Tabel 'employees' siap.\n";

    // 3. Tabel attendances (Data Presensi)
    $db->exec("
        CREATE TABLE IF NOT EXISTS attendances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            date DATE NOT NULL,
            check_in_time TIME NULL,
            check_out_time TIME NULL,
            check_in_lat DECIMAL(10, 8) NULL,
            check_in_lng DECIMAL(11, 8) NULL,
            check_in_distance DECIMAL(8, 2) NULL,
            check_in_photo TEXT NULL,
            check_in_status ENUM('on_time', 'late', 'out_of_radius') DEFAULT 'on_time',
            check_out_lat DECIMAL(10, 8) NULL,
            check_out_lng DECIMAL(11, 8) NULL,
            check_out_distance DECIMAL(8, 2) NULL,
            check_out_photo TEXT NULL,
            notes TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_emp_date (employee_id, date),
            INDEX idx_date (date),
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✓ Tabel 'attendances' siap.\n";

    // Buat folder uploads jika belum ada
    $uploadDir = dirname(__DIR__) . '/assets/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    // Seed data akun admin dan karyawan
    $stmtEmp = $db->query("SELECT COUNT(*) FROM employees");
    if ($stmtEmp->fetchColumn() == 0) {
        $defaultPassword = password_hash('password123', PASSWORD_BCRYPT);

        $employees = [
            [
                'nip' => 'ADM-001',
                'name' => 'Arya Pratama, M.Kom',
                'email' => 'admin@perusahaan.com',
                'role' => 'admin',
                'department' => 'Management & IT',
                'position' => 'Head of Technology & Ops',
                'phone' => '081234567890',
                'photo' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80'
            ],
            [
                'nip' => 'EMP-2026-001',
                'name' => 'Dimas Bagus Wicaksono',
                'email' => 'dimas.bagus@perusahaan.com',
                'role' => 'employee',
                'department' => 'Software Engineering',
                'position' => 'Senior Fullstack Developer',
                'phone' => '082198765432',
                'photo' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80'
            ],
            [
                'nip' => 'EMP-2026-002',
                'name' => 'Siti Nurhaliza Putri',
                'email' => 'siti.nurhaliza@perusahaan.com',
                'role' => 'employee',
                'department' => 'Product & Design',
                'position' => 'UI/UX Product Designer',
                'phone' => '081345678901',
                'photo' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&auto=format&fit=crop&q=80'
            ],
            [
                'nip' => 'EMP-2026-003',
                'name' => 'Reza Fahlevi',
                'email' => 'reza.fahlevi@perusahaan.com',
                'role' => 'employee',
                'department' => 'Marketing & Growth',
                'position' => 'Digital Marketing Lead',
                'phone' => '085712345678',
                'photo' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80'
            ],
            [
                'nip' => 'EMP-2026-004',
                'name' => 'Anisa Rahmawati',
                'email' => 'anisa.rahma@perusahaan.com',
                'role' => 'employee',
                'department' => 'Human Resources',
                'position' => 'HR & Talent Specialist',
                'phone' => '087812345678',
                'photo' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=150&auto=format&fit=crop&q=80'
            ],
            [
                'nip' => 'EMP-2026-005',
                'name' => 'Budi Santoso',
                'email' => 'budi.santoso@perusahaan.com',
                'role' => 'employee',
                'department' => 'Finance & Accounting',
                'position' => 'Senior Financial Analyst',
                'phone' => '081298761234',
                'photo' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=150&auto=format&fit=crop&q=80'
            ],
        ];

        $insEmp = $db->prepare("
            INSERT INTO employees (nip, name, email, password, role, department, position, phone, photo)
            VALUES (:nip, :name, :email, :password, :role, :department, :position, :phone, :photo)
        ");

        foreach ($employees as $emp) {
            $insEmp->execute([
                ':nip' => $emp['nip'],
                ':name' => $emp['name'],
                ':email' => $emp['email'],
                ':password' => $defaultPassword,
                ':role' => $emp['role'],
                ':department' => $emp['department'],
                ':position' => $emp['position'],
                ':phone' => $emp['phone'],
                ':photo' => $emp['photo']
            ]);
        }
        echo "✓ Berhasil menambahkan 6 akun demo (1 Admin, 5 Karyawan).\n";

        // Seed data riwayat kehadiran contoh (7 hari terakhir) agar laporan langsung terlihat kaya & realistis
        $empList = $db->query("SELECT id FROM employees WHERE role = 'employee'")->fetchAll(PDO::FETCH_COLUMN);
        $officeLat = -6.22416800;
        $officeLng = 106.80967500;

        $insAtt = $db->prepare("
            INSERT INTO attendances (
                employee_id, date, check_in_time, check_out_time, 
                check_in_lat, check_in_lng, check_in_distance, check_in_photo, check_in_status,
                check_out_lat, check_out_lng, check_out_distance, notes
            ) VALUES (
                :emp_id, :date, :in_time, :out_time,
                :in_lat, :in_lng, :in_dist, :photo, :status,
                :out_lat, :out_lng, :out_dist, :notes
            )
        ");

        for ($dayOffset = 6; $dayOffset >= 0; $dayOffset--) {
            $date = date('Y-m-d', strtotime("-$dayOffset days"));
            $dayOfWeek = date('N', strtotime($date));
            if ($dayOfWeek >= 6) continue; // Lewati akhir pekan (Sabtu & Minggu)

            foreach ($empList as $idx => $empId) {
                // Variasikan waktu masuk & jarak
                $randMinute = rand(45, 75); // 07:45 sd 08:15
                $hour = 7 + floor($randMinute / 60);
                $min = $randMinute % 60;
                $inTime = sprintf('%02d:%02d:%02d', $hour, $min, rand(10, 59));
                $outTime = sprintf('%02d:%02d:%02d', 17, rand(5, 45), rand(10, 59));

                $isLate = ($hour > 8 || ($hour == 8 && $min > 15));
                $distance = rand(15, 85); // Mayoritas dalam radius 100m
                $status = $isLate ? 'late' : 'on_time';

                // Buat 1 data out_of_radius untuk pengujian fitur laporan
                if ($dayOffset == 2 && $idx == 1) {
                    $distance = 420;
                    $status = 'out_of_radius';
                }

                $latVariation = $officeLat + (rand(-100, 100) / 1000000);
                $lngVariation = $officeLng + (rand(-100, 100) / 1000000);

                // Sample selfie avatar
                $selfieAvatar = 'https://images.unsplash.com/photo-' . (1500000000000 + ($idx * 35000)) . '?w=200&auto=format&fit=crop&q=80';

                $insAtt->execute([
                    ':emp_id' => $empId,
                    ':date' => $date,
                    ':in_time' => $inTime,
                    ':out_time' => $outTime,
                    ':in_lat' => $latVariation,
                    ':in_lng' => $lngVariation,
                    ':in_dist' => $distance,
                    ':photo' => $selfieAvatar,
                    ':status' => $status,
                    ':out_lat' => $officeLat,
                    ':out_lng' => $officeLng,
                    ':out_dist' => 25.0,
                    ':notes' => ($status === 'late' ? 'Trafik jalan tol padat' : ($status === 'out_of_radius' ? 'Kunjungan meeting vendor' : 'Kehadiran kantor normal'))
                ]);
            }
        }
        echo "✓ Berhasil membuat riwayat presensi realistis untuk laporan.\n";
    }

    echo "\n=== Migrasi Database Selesai dengan Sukses! ===\n";

} catch (Exception $e) {
    echo "ERROR MIGRASI: " . $e->getMessage() . "\n";
    exit(1);
}
