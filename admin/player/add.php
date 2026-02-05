<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// Mendapatkan nama file saat ini untuk penanda menu 'Active'
$current_page = basename($_SERVER['PHP_SELF']);

// --- DATA MENU DROPDOWN ---
$menu_items = [
    'dashboard' => [
        'icon' => '🏠',
        'name' => 'Dashboard',
        'url' => '../dashboard.php',
        'submenu' => false
    ],
    'master' => [
        'icon' => '📊',
        'name' => 'Master Data',
        'submenu' => true,
        'items' => [
            'player' => '../player.php',
            'team' => '../team.php',
            'team_staff' => '../team_staff.php',
            'transfer' => '../transfer.php',
        ]
    ],
    'Event' => [
        'icon' => '🏆',
        'name' => 'Event',
        'url' => '../challenge.php',
        'submenu' => false
    ],
    'Venue' => [
        'icon' => '📍',
        'name' => 'Venue',
        'url' => '../venue.php',
        'submenu' => false
    ],
    'Pelatih' => [
        'icon' => '👨‍🏫',
        'name' => 'Pelatih',
        'url' => '../pelatih.php',
        'submenu' => false
    ],
    'Berita' => [
        'icon' => '📰',
        'name' => 'Berita',
        'url' => '../berita.php',
        'submenu' => false
    ]
];

$academy_name = "Hi, Welcome...";
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';
$email = $admin_email;

// DEBUG: Tampilkan semua data POST untuk melihat apa yang dikirim
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("=== DEBUG PLAYER ADD ===");
    error_log("POST Data: " . print_r($_POST, true));
    error_log("FILES Data: " . print_r($_FILES, true));
    error_log("Gender: " . ($_POST['gender'] ?? 'NOT SET'));
    error_log("=== END DEBUG ===");
}

// Include database connection
require_once '../config/database.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Profile data
        $name = trim($_POST['name'] ?? '');
        $place_of_birth = trim($_POST['place_of_birth'] ?? '');
        $date_of_birth = trim($_POST['date_of_birth'] ?? '');
        $sport = trim($_POST['sport'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $nik = trim($_POST['nik'] ?? '');
        $nisn = trim($_POST['nisn'] ?? '');
        $height = trim($_POST['height'] ?? '');
        $weight = trim($_POST['weight'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $nationality = trim($_POST['nationality'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $country = trim($_POST['country'] ?? '');
        
        // Team data
        $team_id = trim($_POST['team_id'] ?? '');
        $jersey_number = trim($_POST['jersey_number'] ?? '');
        $dominant_foot = trim($_POST['dominant_foot'] ?? '');
        $position = trim($_POST['position'] ?? '');
        
        // Skills data - PERBAIKAN DI SINI
        $dribbling = isset($_POST['dribbling']) ? (int)$_POST['dribbling'] : 5;
        $technique = isset($_POST['technique']) ? (int)$_POST['technique'] : 5;
        $speed = isset($_POST['speed']) ? (int)$_POST['speed'] : 5;
        $juggling = isset($_POST['juggling']) ? (int)$_POST['juggling'] : 5;
        $shooting = isset($_POST['shooting']) ? (int)$_POST['shooting'] : 5;
        $setplay_position = isset($_POST['setplay_position']) ? (int)$_POST['setplay_position'] : 5;
        $passing = isset($_POST['passing']) ? (int)$_POST['passing'] : 5;
        $control = isset($_POST['control']) ? (int)$_POST['control'] : 5;
        
        // Validate required fields
        if (empty($name) || empty($place_of_birth) || empty($date_of_birth) || empty($sport) || 
            empty($gender) || empty($nik) || empty($team_id) || empty($jersey_number) || empty($dominant_foot) || empty($position)) {
            $error = "Semua field yang wajib harus diisi!";
        } else {
            // Konversi gender ke format database (L/P)
            $gender_db = ($gender == 'Laki-laki') ? 'L' : 'P';
            
            // Generate slug from name
            $slug = generateSlug($name);
            
            // Create upload directory if not exists
            $upload_dir = '../../images/players/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Initialize file names
            $photo_file = '';
            $ktp_file = '';
            $kk_file = '';
            $akte_file = '';
            $ijazah_file = '';
            
            // Upload Photo Profile
            if (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] == 0) {
                $photo_file = uploadFile($_FILES['photo_file'], $upload_dir, 'player_');
            }
            
            // Upload KTP/KIA/Kartu Pelajar
            if (isset($_FILES['ktp_file']) && $_FILES['ktp_file']['error'] == 0) {
                $ktp_file = uploadFile($_FILES['ktp_file'], $upload_dir, 'ktp_');
            }
            
            // Upload Kartu Keluarga
            if (isset($_FILES['kk_file']) && $_FILES['kk_file']['error'] == 0) {
                $kk_file = uploadFile($_FILES['kk_file'], $upload_dir, 'kk_');
            }
            
            // Upload Akta Lahir
            if (isset($_FILES['akte_file']) && $_FILES['akte_file']['error'] == 0) {
                $akte_file = uploadFile($_FILES['akte_file'], $upload_dir, 'akte_');
            }
            
            // Upload Ijazah/Biodata
            if (isset($_FILES['ijazah_file']) && $_FILES['ijazah_file']['error'] == 0) {
                $ijazah_file = uploadFile($_FILES['ijazah_file'], $upload_dir, 'ijazah_');
            }
            
            // Insert player data - PERBAIKAN: gunakan gender_db
            $query = "INSERT INTO players (
                team_id, name, slug, position, jersey_number, birth_date, height, weight,
                birth_place, gender, nisn, nik, sport_type, email, phone, nationality,
                street, city, province, postal_code, country, dominant_foot, position_detail,
                dribbling, technique, speed, juggling, shooting, setplay_position, passing, control,
                photo, ktp_image, kk_image, birth_cert_image, diploma_image,
                created_at, updated_at, status
            ) VALUES (
                :team_id, :name, :slug, :position, :jersey_number, :birth_date, :height, :weight,
                :birth_place, :gender, :nisn, :nik, :sport_type, :email, :phone, :nationality,
                :street, :city, :province, :postal_code, :country, :dominant_foot, :position_detail,
                :dribbling, :technique, :speed, :juggling, :shooting, :setplay_position, :passing, :control,
                :photo, :ktp_image, :kk_image, :birth_cert_image, :diploma_image,
                NOW(), NOW(), 'active'
            )";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':team_id' => $team_id,
                ':name' => $name,
                ':slug' => $slug,
                ':position' => $position,
                ':jersey_number' => $jersey_number,
                ':birth_date' => $date_of_birth,
                ':height' => $height ?: null,
                ':weight' => $weight ?: null,
                ':birth_place' => $place_of_birth,
                ':gender' => $gender_db, // Menggunakan $gender_db bukan $gender
                ':nisn' => $nisn ?: null,
                ':nik' => $nik,
                ':sport_type' => $sport,
                ':email' => $email ?: null,
                ':phone' => $phone ?: null,
                ':nationality' => $nationality ?: 'Indonesia',
                ':street' => $address ?: null,
                ':city' => $city ?: null,
                ':province' => $province ?: null,
                ':postal_code' => $postal_code ?: null,
                ':country' => $country ?: 'Indonesia',
                ':dominant_foot' => $dominant_foot,
                ':position_detail' => $position,
                ':dribbling' => $dribbling,
                ':technique' => $technique,
                ':speed' => $speed,
                ':juggling' => $juggling,
                ':shooting' => $shooting,
                ':setplay_position' => $setplay_position,
                ':passing' => $passing,
                ':control' => $control,
                ':photo' => $photo_file,
                ':ktp_image' => $ktp_file,
                ':kk_image' => $kk_file,
                ':birth_cert_image' => $akte_file,
                ':diploma_image' => $ijazah_file
            ]);
            
            $player_id = $conn->lastInsertId();
            
            $_SESSION['success_message'] = "Player berhasil ditambahkan!";
            header("Location: ../player.php");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Terjadi kesalahan: " . $e->getMessage();
        error_log("Database Error: " . $e->getMessage());
    } catch (Exception $e) {
        $error = "Terjadi kesalahan: " . $e->getMessage();
        error_log("General Error: " . $e->getMessage());
    }
}

// Function to generate slug from name
function generateSlug($name) {
    // Convert to lowercase
    $slug = strtolower($name);
    // Replace spaces with hyphens
    $slug = str_replace(' ', '-', $slug);
    // Remove special characters except hyphens and alphanumeric
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    // Replace multiple hyphens with single hyphen
    $slug = preg_replace('/-+/', '-', $slug);
    // Remove leading/trailing hyphens
    $slug = trim($slug, '-');
    // Add timestamp to ensure uniqueness
    $slug = $slug . '-' . time();
    return $slug;
}

// Function to upload files
function uploadFile($file, $upload_dir, $prefix) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception("Format file tidak didukung. Harap upload file gambar (JPEG, PNG, GIF).");
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception("Ukuran file terlalu besar. Maksimal 5MB.");
    }
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . time() . '_' . uniqid() . '.' . $file_extension;
    $target_path = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        throw new Exception("Gagal mengupload file.");
    }
    
    return $filename;
}

// Get teams for dropdown
$teams = [];
try {
    $team_query = "SELECT id, name FROM teams WHERE is_active = 1 ORDER BY name";
    $team_stmt = $conn->prepare($team_query);
    $team_stmt->execute();
    $teams = $team_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error
    error_log("Error getting teams: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Player - FutScore Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS styles for sidebar and layout */
        :root {
            --primary: #0A2463;
            --secondary: #FFD700;
            --accent: #4CC9F0;
            --success: #2E7D32;
            --warning: #F9A826;
            --danger: #D32F2F;
            --light: #F8F9FA;
            --dark: #1A1A2E;
            --gray: #6C757D;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            color: var(--dark);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--primary) 0%, #1a365d 100%);
            color: white;
            padding: 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            box-shadow: 5px 0 25px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }

        .sidebar-header {
            padding: 30px 25px;
            text-align: center;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 2px solid var(--secondary);
        }

        .logo-container {
            position: relative;
            display: inline-block;
        }

        .logo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary) 0%, #FFEC8B 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border: 4px solid white;
            box-shadow: 0 0 25px rgba(255, 215, 0, 0.3);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }

        .logo:hover {
            transform: rotate(15deg) scale(1.05);
            box-shadow: 0 0 35px rgba(255, 215, 0, 0.5);
        }

        .logo::before {
            content: "⚽";
            font-size: 48px;
            color: var(--primary);
        }

        .academy-info {
            text-align: center;
            animation: fadeIn 0.8s ease-out;
        }

        .academy-name {
            font-size: 22px;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .academy-email {
            font-size: 14px;
            opacity: 0.9;
            color: rgba(255, 255, 255, 0.8);
        }

        /* Menu */
        .menu {
            padding: 25px 15px;
        }

        .menu-item {
            margin-bottom: 8px;
            border-radius: 12px;
            overflow: hidden;
        }

        .menu-link {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: var(--transition);
            position: relative;
            border-left: 4px solid transparent;
        }

        .menu-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: var(--secondary);
            padding-left: 25px;
        }

        .menu-link.active {
            background: rgba(255, 215, 0, 0.15);
            color: var(--secondary);
            border-left-color: var(--secondary);
            font-weight: 600;
        }

        .menu-icon {
            font-size: 22px;
            margin-right: 15px;
            width: 30px;
            text-align: center;
        }

        .menu-text {
            flex: 1;
            font-size: 16px;
        }

        .menu-arrow {
            font-size: 12px;
            transition: var(--transition);
        }

        .menu-arrow.rotate {
            transform: rotate(90deg);
        }

        /* Submenu */
        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-in-out;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 0 0 12px 12px;
        }

        .submenu.open {
            max-height: 300px;
        }

        .submenu-item {
            padding: 5px 15px 5px 70px;
        }

        .submenu-link {
            display: block;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            border-radius: 8px;
            transition: var(--transition);
            position: relative;
            font-size: 14px;
        }

        .submenu-link:hover {
            background: rgba(255, 215, 0, 0.1);
            color: var(--secondary);
            padding-left: 20px;
        }

        .submenu-link.active {
            background: rgba(255, 215, 0, 0.1);
            color: var(--secondary);
            padding-left: 20px;
        }

        .submenu-link::before {
            content: "•";
            position: absolute;
            left: 0;
            color: var(--secondary);
            font-size: 18px;
        }

        /* ===== MAIN CONTENT ===== */
        .main {
            flex: 1;
            padding: 30px;
            margin-left: 280px;
            transition: var(--transition);
        }

        /* Topbar */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding: 20px 25px;
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            animation: slideDown 0.5s ease-out;
        }

        .greeting h1 {
            font-size: 28px;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .greeting p {
            color: var(--gray);
            font-size: 14px;
        }

        .logout-btn {
            background: linear-gradient(135deg, var(--danger) 0%, #B71C1C 100%);
            color: white;
            padding: 12px 28px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
            box-shadow: 0 5px 15px rgba(211, 47, 47, 0.2);
        }

        .logout-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(211, 47, 47, 0.3);
        }

        /* Form Container Styles */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .form-container {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            padding: 30px;
            margin-bottom: 30px;
        }

        .form-section {
            margin-bottom: 40px;
            border-bottom: 2px solid #eee;
            padding-bottom: 30px;
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .section-title {
            font-size: 20px;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e1e5eb;
            border-radius: 12px;
            font-size: 15px;
            transition: var(--transition);
            background-color: #fff;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.1);
        }

        .date-input {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .date-input input {
            text-align: center;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 8px 12px;
            border: 2px solid #e1e5eb;
            border-radius: 8px;
            transition: var(--transition);
        }

        .radio-option:hover {
            border-color: var(--primary);
            background: #f8f9ff;
        }

        .file-upload {
            border: 2px dashed #e1e5eb;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            background: #fafbff;
        }

        .file-upload:hover {
            border-color: var(--primary);
            background: #f0f4ff;
        }

        .file-upload.dragover {
            border-color: var(--secondary);
            background: #fff9e6;
        }

        .file-upload input[type="file"] {
            display: none;
        }

        .file-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .file-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-item img {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            object-fit: cover;
        }

        .skill-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .skill-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .skill-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .skill-name {
            font-weight: 600;
            color: var(--dark);
        }

        .skill-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
        }

        .slider-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .slider {
            flex: 1;
            -webkit-appearance: none;
            appearance: none;
            height: 6px;
            background: #ddd;
            border-radius: 3px;
            outline: none;
        }

        .slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary);
            cursor: pointer;
            transition: var(--transition);
        }

        .slider::-webkit-slider-thumb:hover {
            transform: scale(1.2);
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #eee;
        }

        .btn {
            padding: 14px 28px;
            border-radius: 12px;
            border: none;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, #1a365d 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(10, 36, 99, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(10, 36, 99, 0.3);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: var(--dark);
            border: 2px solid #e1e5eb;
        }

        .btn-secondary:hover {
            background: #e9ecef;
            border-color: #ced4da;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideDown 0.3s ease-out;
        }

        .alert-error {
            background: linear-gradient(135deg, #FFE5E5 0%, #FFCCCC 100%);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .alert-success {
            background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .note {
            font-size: 12px;
            color: var(--gray);
            margin-top: 5px;
            font-style: italic;
        }

        .required-field::after {
            content: " *";
            color: var(--danger);
            font-weight: bold;
        }


        /* =========================================
           MOBILE RESPONSIVE DESIGN
           ========================================= */

        /* Default: Hide mobile-only elements on desktop */
        .menu-toggle {
            display: none;
        }

        .menu-overlay {
            display: none;
        }

        /* ===== TABLET (max-width: 1024px) ===== */
        @media screen and (max-width: 1024px) {
            .sidebar {
                width: 240px;
            }

            .main {
                margin-left: 240px;
            }
        }

        /* ===== MOBILE LANDSCAPE (max-width: 768px) ===== */
        @media screen and (max-width: 768px) {
            
            /* Show Mobile Menu Toggle Button */
            .menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
                position: fixed;
                bottom: 25px;
                right: 25px;
                width: 60px;
                height: 60px;
                background: linear-gradient(135deg, var(--secondary), #FFEC8B);
                color: var(--primary);
                border: none;
                border-radius: 50%;
                box-shadow: 0 5px 20px rgba(255, 215, 0, 0.4);
                z-index: 1001;
                font-size: 24px;
                cursor: pointer;
                transition: var(--transition);
            }

            .menu-toggle:hover {
                transform: scale(1.1);
                box-shadow: 0 8px 25px rgba(255, 215, 0, 0.6);
            }

            .menu-toggle:active {
                transform: scale(0.95);
            }

            /* Sidebar: Hidden by default on mobile */
            .sidebar {
                transform: translateX(-100%);
                box-shadow: none;
                width: 280px;
            }

            /* Sidebar: Show when active */
            .sidebar.active {
                transform: translateX(0);
                box-shadow: 10px 0 40px rgba(0, 0, 0, 0.3);
            }

            /* Overlay: Show when menu is open */
            .menu-overlay {
                display: block;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.6);
                z-index: 99;
                opacity: 0;
                visibility: hidden;
                transition: var(--transition);
                backdrop-filter: blur(2px);
            }

            body.menu-open .menu-overlay {
                opacity: 1;
                visibility: visible;
            }

            /* Main Content: Full width on mobile */
            .main {
                margin-left: 0;
                padding: 20px 15px;
                width: 100%;
                max-width: 100vw;
            }

            /* Topbar: Stack vertically */
            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
                padding: 20px;
            }

            .greeting h1 {
                font-size: 24px;
            }

            .user-actions {
                width: 100%;
                display: flex;
                justify-content: flex-end;
            }

            /* Header */
            .header {
                flex-direction: column;
                gap: 15px;
                align-items: center;
                text-align: center;
                padding: 20px;
            }

            .header h1 {
                font-size: 24px;
                justify-content: center;
            }
            
            /* Form Layout */
            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .date-input {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 15px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .skill-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ===== MOBILE PORTRAIT (max-width: 480px) ===== */
        @media screen and (max-width: 480px) {
            
            /* Reduce font sizes */
            .greeting h1 {
                font-size: 20px;
            }
            
            .greeting p {
                font-size: 13px;
            }

            /* Compact sidebar */
            .sidebar {
                width: 260px;
            }

            .sidebar-header {
                padding: 20px 15px;
            }

            .logo {
                width: 80px;
                height: 80px;
            }

            .logo::before {
                font-size: 36px;
            }

            .academy-name {
                font-size: 18px;
            }
            
            /* Compact menu */
            .menu {
                padding: 20px 10px;
            }

            .menu-link {
                padding: 14px 15px;
                font-size: 15px;
            }

            .menu-icon {
                font-size: 20px;
                width: 28px;
            }

            /* Smaller mobile toggle button */
            .menu-toggle {
                width: 55px;
                height: 55px;
                font-size: 22px;
                bottom: 20px;
                right: 20px;
            }
            
            .section-title {
                font-size: 18px;
            }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>


<!-- Mobile Menu Components (hidden by default via CSS) -->
<div class="menu-overlay"></div>
<button class="menu-toggle" id="menuToggle">
    <i class="fas fa-bars"></i>
</button>

<div class="wrapper">
    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo"></div>
            </div>
            <div class="academy-info">
                <div class="academy-name"><?php echo htmlspecialchars($academy_name ?? ''); ?></div>
                <div class="academy-email"><?php echo htmlspecialchars($email ?? ''); ?></div>
            </div>
        </div>

        <div class="menu">
            <?php foreach ($menu_items as $key => $item): ?>
            <?php 
                // Cek apakah menu ini aktif berdasarkan URL saat ini
                $isActive = false;
                $isSubmenuOpen = false;
                
                if ($item['submenu']) {
                    // Cek jika salah satu sub-item ada yang aktif
                    foreach($item['items'] as $subUrl) {
                        // Karena kita di dalam folder 'player', perlu relative path
                        if($current_page === 'add.php' && $subUrl === '../player.php') {
                            $isActive = true;
                            $isSubmenuOpen = true;
                            break;
                        }
                    }
                } else {
                    // Untuk menu tanpa submenu
                    if ($current_page === 'add.php' && $item['url'] === '../player.php') {
                        $isActive = true;
                    }
                }
            ?>
            <div class="menu-item">
                <a href="<?php echo $item['submenu'] ? '#' : $item['url']; ?>" 
                   class="menu-link <?php echo $isActive ? 'active' : ''; ?>" 
                   data-menu="<?php echo $key; ?>">
                        <span class="menu-icon"><?php echo $item['icon']; ?></span>
                        <span class="menu-text"><?php echo $item['name']; ?></span>
                        <?php if ($item['submenu']): ?>
                        <span class="menu-arrow <?php echo $isSubmenuOpen ? 'rotate' : ''; ?>">›</span>
                        <?php endif; ?>
                </a>
                
                <?php if ($item['submenu']): ?>
                <div class="submenu <?php echo $isSubmenuOpen ? 'open' : ''; ?>" id="submenu-<?php echo $key; ?>">
                    <?php foreach ($item['items'] as $subKey => $subUrl): ?>
                    <div class="submenu-item">
                        <a href="<?php echo $subUrl; ?>" 
                           class="submenu-link <?php echo ($current_page === 'add.php' && $subUrl === '../player.php') ? 'active' : ''; ?>">
                           <?php echo ucwords(str_replace('_', ' ', $subKey)); ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar">
            <div class="greeting">
                <h1>Selamat Datang, <?php echo htmlspecialchars($admin_name ?? ''); ?> ! 👋</h1>
                <p>Tambah Player Baru - Sistem manajemen pemain futsal</p>
            </div>
            
            <div class="user-actions">
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>

        <div class="container">
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-user-plus"></i> Tambah Player Baru</h1>
            </div>

            <!-- Alerts -->
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <span class="alert-icon">⚠</span>
                    <span><?php echo htmlspecialchars($error ?? ''); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <span class="alert-icon">✓</span>
                    <span><?php echo htmlspecialchars($_SESSION['success_message'] ?? ''); 
                    unset($_SESSION['success_message']); ?></span>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form action="" method="POST" enctype="multipart/form-data" id="playerForm">
                <!-- Profile Section -->
                <div class="form-container">
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-user-circle"></i>
                            Profile
                        </h2>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">
                                    <span class="required-field">Nama</span>
                                    <span class="note">Wajib diisi</span>
                                </label>
                                <input type="text" name="name" class="form-control" placeholder="Masukkan nama lengkap" required
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <span class="required-field">Tempat/Tgl Lahir</span>
                                    <span class="note">Wajib diisi</span>
                                </label>
                                <div class="date-input">
                                    <input type="text" name="place_of_birth" class="form-control" placeholder="Tempat lahir" required
                                           value="<?php echo isset($_POST['place_of_birth']) ? htmlspecialchars($_POST['place_of_birth']) : ''; ?>">
                                    <input type="date" name="date_of_birth" class="form-control" required
                                           value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <span class="required-field">Cabor</span>
                                    <span class="note">Wajib diisi</span>
                                </label>
                                <select name="sport" class="form-control" required>
                                    <option value="">Pilih Cabor</option>
                                    <?php 
                                    $sports = ['Futsal', 'Sepakbola', 'Panahan', 'Karate', 'Angkat Besi', 'Atletik', 'Dayung', 
                                              'Pencak Silat', 'Taekwondo', 'Sepak Takraw', 'Bola Voli', 'Cricket', 
                                              'Mini Soccer/Mini Football', 'Basket'];
                                    foreach ($sports as $sport_option): 
                                    ?>
                                        <option value="<?php echo $sport_option; ?>" 
                                            <?php echo (isset($_POST['sport']) && $_POST['sport'] == $sport_option) ? 'selected' : ''; ?>>
                                            <?php echo $sport_option; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <span class="required-field">Jenis Kelamin</span>
                                    <span class="note">Wajib diisi</span>
                                </label>
                                <div class="radio-group">
                                    <label class="radio-option">
                                        <input type="radio" name="gender" value="Laki-laki" required
                                            <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Laki-laki') ? 'checked' : ''; ?>>
                                        <span>Laki-laki</span>
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="gender" value="Perempuan" required
                                            <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Perempuan') ? 'checked' : ''; ?>>
                                        <span>Perempuan</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <span class="required-field">NIK</span>
                                    <span class="note">Wajib diisi</span>
                                </label>
                                <input type="text" name="nik" class="form-control" placeholder="Masukkan NIK" required
                                       value="<?php echo isset($_POST['nik']) ? htmlspecialchars($_POST['nik']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <span>NISN</span>
                                    <span class="note">Opsional</span>
                                </label>
                                <input type="text" name="nisn" class="form-control" placeholder="Masukkan NISN"
                                       value="<?php echo isset($_POST['nisn']) ? htmlspecialchars($_POST['nisn']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <span>Tinggi/Berat</span>
                                    <span class="note">Opsional</span>
                                </label>
                                <div class="date-input">
                                    <input type="number" name="height" class="form-control" placeholder="Tinggi (cm)"
                                           value="<?php echo isset($_POST['height']) ? htmlspecialchars($_POST['height']) : ''; ?>">
                                    <input type="number" name="weight" class="form-control" placeholder="Berat (kg)"
                                           value="<?php echo isset($_POST['weight']) ? htmlspecialchars($_POST['weight']) : ''; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <span>Email</span>
                                    <span class="note">Opsional</span>
                                </label>
                                <input type="email" name="email" class="form-control" placeholder="Masukkan email"
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <span>Telpon</span>
                                    <span class="note">Opsional</span>
                                </label>
                                <input type="tel" name="phone" class="form-control" placeholder="Masukkan nomor telepon"
                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <span>Kewarganegaraan</span>
                                    <span class="note">Opsional</span>
                                </label>
                                <input type="text" name="nationality" class="form-control" placeholder="Masukkan kewarganegaraan" 
                                       value="<?php echo isset($_POST['nationality']) ? htmlspecialchars($_POST['nationality']) : 'Indonesia'; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <span>Alamat</span>
                                    <span class="note">Opsional</span>
                                </label>
                                <input type="text" name="address" class="form-control" placeholder="Jalan/No"
                                       value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <span>Kota</span>
                                    <span class="note">Opsional</span>
                                </label>
                                <input type="text" name="city" class="form-control" placeholder="Masukkan kota"
                                       value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <span>Provinsi</span>
                                    <span class="note">Opsional</span>
                                </label>
                                <input type="text" name="province" class="form-control" placeholder="Masukkan provinsi"
                                       value="<?php echo isset($_POST['province']) ? htmlspecialchars($_POST['province']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <span>Kode Pos</span>
                                    <span class="note">Opsional</span>
                                </label>
                                <input type="text" name="postal_code" class="form-control" placeholder="Masukkan kode pos"
                                       value="<?php echo isset($_POST['postal_code']) ? htmlspecialchars($_POST['postal_code']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <span>Negara</span>
                                    <span class="note">Opsional</span>
                                </label>
                                <input type="text" name="country" class="form-control" placeholder="Masukkan negara" 
                                       value="<?php echo isset($_POST['country']) ? htmlspecialchars($_POST['country']) : 'Indonesia'; ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Photo Section -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-camera"></i>
                            Foto Profile
                        </h2>
                        <p class="note" style="margin-bottom: 20px; color: var(--gray);">
                            Note! Foto profile digunakan untuk menampilkan identitas player. 
                            Pastikan foto jelas, terang, dan mudah dikenali. 
                            Hanya file berformat gambar yang diterima.
                        </p>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Foto Profile</label>
                                <div class="file-upload" id="photoUpload">
                                    <div>
                                        <i class="fas fa-cloud-upload-alt" style="font-size: 24px; color: var(--primary); margin-bottom: 10px;"></i>
                                        <p style="margin: 0; color: var(--gray);">Klik untuk upload atau drag & drop</p>
                                        <p style="margin: 5px 0 0 0; font-size: 12px; color: var(--gray);">Maksimal 5MB</p>
                                    </div>
                                    <input type="file" name="photo_file" id="photoFile" accept="image/*">
                                </div>
                                <div class="file-preview" id="photoPreview"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Documents Section -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-file-alt"></i>
                            Dokumen
                        </h2>
                        <p class="note" style="margin-bottom: 20px; color: var(--gray);">
                            Note! Dokumen digunakan untuk memverifikasi keaslian data yang diberikan. 
                            Pastikan file yang diunggah asli, relevan, dan mudah dibaca. 
                            Hanya file berformat gambar yang diterima.
                        </p>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">KTP / KIA / Kartu Pelajar / Kartu Identitas</label>
                                <div class="file-upload" id="ktpUpload">
                                    <div>
                                        <i class="fas fa-cloud-upload-alt" style="font-size: 24px; color: var(--primary); margin-bottom: 10px;"></i>
                                        <p style="margin: 0; color: var(--gray);">Klik untuk upload atau drag & drop</p>
                                        <p style="margin: 5px 0 0 0; font-size: 12px; color: var(--gray);">Maksimal 5MB</p>
                                    </div>
                                    <input type="file" name="ktp_file" id="ktpFile" accept="image/*">
                                </div>
                                <div class="file-preview" id="ktpPreview"></div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Kartu Keluarga</label>
                                <div class="file-upload" id="kkUpload">
                                    <div>
                                        <i class="fas fa-cloud-upload-alt" style="font-size: 24px; color: var(--primary); margin-bottom: 10px;"></i>
                                        <p style="margin: 0; color: var(--gray);">Klik untuk upload atau drag & drop</p>
                                        <p style="margin: 5px 0 0 0; font-size: 12px; color: var(--gray);">Maksimal 5MB</p>
                                    </div>
                                    <input type="file" name="kk_file" id="kkFile" accept="image/*">
                                </div>
                                <div class="file-preview" id="kkPreview"></div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Akta Lahir / Surat Ket. Lahir</label>
                                <div class="file-upload" id="akteUpload">
                                    <div>
                                        <i class="fas fa-cloud-upload-alt" style="font-size: 24px; color: var(--primary); margin-bottom: 10px;"></i>
                                        <p style="margin: 0; color: var(--gray);">Klik untuk upload atau drag & drop</p>
                                        <p style="margin: 5px 0 0 0; font-size: 12px; color: var(--gray);">Maksimal 5MB</p>
                                    </div>
                                    <input type="file" name="akte_file" id="akteFile" accept="image/*">
                                </div>
                                <div class="file-preview" id="aktePreview"></div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Ijazah / Biodata Raport / Kartu NISN</label>
                                <div class="file-upload" id="ijazahUpload">
                                    <div>
                                        <i class="fas fa-cloud-upload-alt" style="font-size: 24px; color: var(--primary); margin-bottom: 10px;"></i>
                                        <p style="margin: 0; color: var(--gray);">Klik untuk upload atau drag & drop</p>
                                        <p style="margin: 5px 0 0 0; font-size: 12px; color: var(--gray);">Maksimal 5MB</p>
                                    </div>
                                    <input type="file" name="ijazah_file" id="ijazahFile" accept="image/*">
                                </div>
                                <div class="file-preview" id="ijazahPreview"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Team & Skills Section -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-tshirt"></i>
                            Team & Skills
                        </h2>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">
                                    <span class="required-field">Team</span>
                                    <span class="note">Wajib diisi</span>
                                </label>
                                <select name="team_id" class="form-control" required>
                                    <option value="">Pilih Team</option>
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?php echo $team['id']; ?>"
                                            <?php echo (isset($_POST['team_id']) && $_POST['team_id'] == $team['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($team['name'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <span class="required-field">No Punggung</span>
                                    <span class="note">Wajib diisi</span>
                                </label>
                                <input type="number" name="jersey_number" class="form-control" placeholder="Masukkan nomor punggung" required
                                       value="<?php echo isset($_POST['jersey_number']) ? htmlspecialchars($_POST['jersey_number']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <span class="required-field">Kaki Dominan</span>
                                    <span class="note">Wajib diisi</span>
                                </label>
                                <div class="radio-group">
                                    <label class="radio-option">
                                        <input type="radio" name="dominant_foot" value="Kanan" required
                                            <?php echo (isset($_POST['dominant_foot']) && $_POST['dominant_foot'] == 'Kanan') ? 'checked' : ''; ?>>
                                        <span>Kanan</span>
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="dominant_foot" value="Kiri" required
                                            <?php echo (isset($_POST['dominant_foot']) && $_POST['dominant_foot'] == 'Kiri') ? 'checked' : ''; ?>>
                                        <span>Kiri</span>
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="dominant_foot" value="Kedua" required
                                            <?php echo (isset($_POST['dominant_foot']) && $_POST['dominant_foot'] == 'Kedua') ? 'checked' : ''; ?>>
                                        <span>Kedua</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <span class="required-field">Posisi</span>
                                    <span class="note">Wajib diisi</span>
                                </label>
                                <select name="position" class="form-control" required>
                                    <option value="">Pilih Posisi</option>
                                    <option value="GK" <?php echo (isset($_POST['position']) && $_POST['position'] == 'GK') ? 'selected' : ''; ?>>Kiper (GK)</option>
                                    <option value="DF" <?php echo (isset($_POST['position']) && $_POST['position'] == 'DF') ? 'selected' : ''; ?>>Bek (DF)</option>
                                    <option value="MF" <?php echo (isset($_POST['position']) && $_POST['position'] == 'MF') ? 'selected' : ''; ?>>Gelandang (MF)</option>
                                    <option value="FW" <?php echo (isset($_POST['position']) && $_POST['position'] == 'FW') ? 'selected' : ''; ?>>Penyerang (FW)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Skills Section -->
                        <div class="form-group">
                            <label class="form-label">
                                <span>Skill (Range: 0-10)</span>
                                <span class="note">Nilai default: 5</span>
                            </label>
                            <div class="skill-grid">
                                <?php
                                $skills = [
                                    'dribbling' => 'Dribbling',
                                    'technique' => 'Technique',
                                    'speed' => 'Speed',
                                    'juggling' => 'Juggling',
                                    'shooting' => 'Shooting',
                                    'setplay_position' => 'Setplay Position',
                                    'passing' => 'Passing',
                                    'control' => 'Control'
                                ];
                                
                                foreach ($skills as $key => $label):
                                    $value = isset($_POST[$key]) ? (int)$_POST[$key] : 5;
                                ?>
                                <div class="skill-item">
                                    <div class="skill-header">
                                        <span class="skill-name"><?php echo $label; ?></span>
                                        <span class="skill-value" id="<?php echo $key; ?>Value"><?php echo $value; ?></span>
                                    </div>
                                    <div class="slider-container">
                                        <input type="range" name="<?php echo $key; ?>" class="slider" min="0" max="10" 
                                               value="<?php echo $value; ?>" id="<?php echo $key; ?>Slider"
                                               oninput="document.getElementById('<?php echo $key; ?>Value').textContent = this.value">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <a href="../player.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Kembali ke Daftar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Simpan Player
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>

    // Mobile Menu Toggle Functionality
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.menu-overlay');

        if (menuToggle && sidebar && overlay) {
            // Toggle menu when clicking hamburger button
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                document.body.classList.toggle('menu-open');
            });

            // Close menu when clicking overlay
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                document.body.classList.remove('menu-open');
            });

            // Close menu when clicking a menu link (better UX on mobile)
            const menuLinks = document.querySelectorAll('.menu-link');
            menuLinks.forEach(function(link) {
                // Only close if it's not a submenu toggle
                if (!link.querySelector('.menu-arrow')) {
                    link.addEventListener('click', function() {
                        sidebar.classList.remove('active');
                        document.body.classList.remove('menu-open');
                    });
                }
            });
        }
        
        // Menu toggle functionality (untuk Submenu)
        document.querySelectorAll('.menu-link').forEach(link => {
            if (link.querySelector('.menu-arrow')) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const submenu = this.nextElementSibling;
                    const arrow = this.querySelector('.menu-arrow');
                    
                    if (submenu) {
                        submenu.classList.toggle('open');
                        arrow.classList.toggle('rotate');
                    }
                });
            }
        });
    });

    // File upload functionality
    function setupFileUpload(uploadElement, fileInput, previewElement) {
        const uploadArea = uploadElement;
        const fileInputField = fileInput;
        const previewContainer = previewElement;

        // Click to upload
        uploadArea.addEventListener('click', () => {
            fileInputField.click();
        });

        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                fileInputField.files = e.dataTransfer.files;
                handleFileSelect(e.dataTransfer.files[0], previewContainer);
            }
        });

        // File selection
        fileInputField.addEventListener('change', (e) => {
            if (e.target.files.length) {
                handleFileSelect(e.target.files[0], previewContainer);
            }
        });
    }

    function handleFileSelect(file, previewContainer) {
        if (!file.type.startsWith('image/')) {
            alert('Harap pilih file gambar!');
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            previewContainer.innerHTML = `
                <div class="file-item">
                    <img src="${e.target.result}" alt="Preview">
                    <div>
                        <div><strong>${file.name}</strong></div>
                        <div style="font-size: 12px; color: var(--gray);">${formatFileSize(file.size)}</div>
                    </div>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Initialize file uploads
    setupFileUpload(document.getElementById('photoUpload'), document.getElementById('photoFile'), document.getElementById('photoPreview'));
    setupFileUpload(document.getElementById('ktpUpload'), document.getElementById('ktpFile'), document.getElementById('ktpPreview'));
    setupFileUpload(document.getElementById('kkUpload'), document.getElementById('kkFile'), document.getElementById('kkPreview'));
    setupFileUpload(document.getElementById('akteUpload'), document.getElementById('akteFile'), document.getElementById('aktePreview'));
    setupFileUpload(document.getElementById('ijazahUpload'), document.getElementById('ijazahFile'), document.getElementById('ijazahPreview'));

    // Initialize skill sliders
    const skills = ['dribbling', 'technique', 'speed', 'juggling', 'shooting', 'setplay_position', 'passing', 'control'];
    skills.forEach(skill => {
        const slider = document.getElementById(skill + 'Slider');
        const value = document.getElementById(skill + 'Value');
        
        if (slider && value) {
            slider.addEventListener('input', () => {
                value.textContent = slider.value;
            });
        }
    });

    // Form validation
    const playerForm = document.getElementById('playerForm');
    if (playerForm) {
        playerForm.addEventListener('submit', function(e) {
            const name = document.querySelector('input[name="name"]').value.trim();
            const placeOfBirth = document.querySelector('input[name="place_of_birth"]').value.trim();
            const dateOfBirth = document.querySelector('input[name="date_of_birth"]').value.trim();
            const sport = document.querySelector('select[name="sport"]').value;
            const gender = document.querySelector('input[name="gender"]:checked');
            const nik = document.querySelector('input[name="nik"]').value.trim();
            const teamId = document.querySelector('select[name="team_id"]').value;
            const jerseyNumber = document.querySelector('input[name="jersey_number"]').value.trim();
            const dominantFoot = document.querySelector('input[name="dominant_foot"]:checked');
            const position = document.querySelector('select[name="position"]').value;

            if (!name || !placeOfBirth || !dateOfBirth || !sport || !gender || !nik || !teamId || !jerseyNumber || !dominantFoot || !position) {
                e.preventDefault();
                alert('Harap lengkapi semua field yang wajib diisi!');
                return false;
            }
        });
    }

    // Auto-fill date of birth format hint
    const dateInput = document.querySelector('input[name="date_of_birth"]');
    if (dateInput) {
        const today = new Date();
        const maxDate = today.toISOString().split('T')[0];
        dateInput.max = maxDate;
        
        // Set default to 18 years ago if empty
        if (!dateInput.value) {
            const defaultDate = new Date();
            defaultDate.setFullYear(defaultDate.getFullYear() - 18);
            dateInput.value = defaultDate.toISOString().split('T')[0];
        }
    }
</script>
</body>
</html>