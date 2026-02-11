<?php
session_start();
require_once '../config/database.php';

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

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ../player.php");
    exit;
}

$player_id = (int)$_GET['id'];
$player = null;
$teams = [];

try {
    // Get player data
    $stmt = $conn->prepare("
        SELECT p.*, t.name as team_name, t.logo as team_logo 
        FROM players p 
        LEFT JOIN teams t ON p.team_id = t.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$player_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$player) {
        header("Location: ../player.php");
        exit;
    }
    
    // Get teams for dropdown
    $stmt = $conn->prepare("SELECT id, name FROM teams WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        // Get form data
        $name = $_POST['name'] ?? '';
        $birth_place = $_POST['birth_place'] ?? '';
        $birth_date = $_POST['birth_date'] ?? '';
        $sport_type = $_POST['sport_type'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $nik = $_POST['nik'] ?? '';
        $nisn = $_POST['nisn'] ?? '';
        $height = $_POST['height'] ?? 0;
        $weight = $_POST['weight'] ?? 0;
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $nationality = $_POST['nationality'] ?? 'Indonesia';
        $street = $_POST['street'] ?? '';
        $city = $_POST['city'] ?? '';
        $province = $_POST['province'] ?? '';
        $postal_code = $_POST['postal_code'] ?? '';
        $country = $_POST['country'] ?? 'Indonesia';
        
        // Football info
        $team_id = !empty($_POST['team_id']) ? $_POST['team_id'] : null;
        $jersey_number = !empty($_POST['jersey_number']) ? $_POST['jersey_number'] : null;
        $dominant_foot = $_POST['dominant_foot'] ?? '';
        $position = $_POST['position'] ?? '';
        
        // Skills
        $dribbling = $_POST['dribbling'] ?? 5;
        $technique = $_POST['technique'] ?? 5;
        $speed = $_POST['speed'] ?? 5;
        $juggling = $_POST['juggling'] ?? 5;
        $shooting = $_POST['shooting'] ?? 5;
        $setplay_position = $_POST['setplay_position'] ?? 5;
        $passing = $_POST['passing'] ?? 5;
        $control = $_POST['control'] ?? 5;
        
        // **VALIDASI NIK 16 DIGIT** - TAMBAHKAN DI SINI
        if (strlen($nik) != 16 || !is_numeric($nik)) {
            throw new Exception("NIK harus terdiri dari tepat 16 digit angka!");
        }
        
        // **VALIDASI KK** - WAJIB ADA FILE KK
        $kk_required = true;
        $kk_has_existing_file = !empty($player['kk_image']);
        $kk_new_file_uploaded = isset($_FILES['kk_image']) && $_FILES['kk_image']['error'] === UPLOAD_ERR_OK;
        $kk_delete_checked = isset($_POST['delete_kk_image']) && $_POST['delete_kk_image'] == '1';
        
        // Logika validasi KK:
        // 1. Jika ada file KK yang sudah ada di database DAN tidak dicentang hapus → OK
        // 2. Jika ada upload file KK baru → OK
        // 3. Jika tidak ada file yang sudah ada DAN tidak ada upload baru → ERROR
        // 4. Jika ada file yang sudah ada tapi dicentang hapus DAN tidak ada upload baru → ERROR
        
        if ($kk_has_existing_file && !$kk_delete_checked) {
            // Ada file KK di database dan tidak dihapus → OK
            $kk_required = false;
        } elseif ($kk_new_file_uploaded) {
            // Ada upload file KK baru → OK
            $kk_required = false;
        } elseif ($kk_has_existing_file && $kk_delete_checked && !$kk_new_file_uploaded) {
            // File KK ada di database, dicentang hapus, dan tidak ada upload baru → ERROR
            throw new Exception("File Kartu Keluarga (KK) wajib diupload!");
        } elseif (!$kk_has_existing_file && !$kk_new_file_uploaded) {
            // Tidak ada file KK di database dan tidak ada upload baru → ERROR
            throw new Exception("File Kartu Keluarga (KK) wajib diupload!");
        }
        
        // Konversi gender ke format database (L/P)
        $gender_db = ($gender == 'Laki-laki') ? 'L' : (($gender == 'Perempuan') ? 'P' : '');
        
        // Handle photo upload
        $photo = $player['photo']; // Keep existing photo
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../images/players/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Delete old photo if exists
            if (!empty($player['photo'])) {
                $old_photo_path = $upload_dir . $player['photo'];
                if (file_exists($old_photo_path)) {
                    @unlink($old_photo_path);
                }
            }
            
            $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $new_filename = 'player_' . time() . '_' . uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
                $photo = $new_filename;
            }
        }
        
        // Handle document uploads
        function handleDocumentUpload($field_name, $type, $existing_file = null) {
            global $player;
            $upload_dir = '../../images/players/';
            
            if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
                // Delete old file if exists
                if (!empty($existing_file)) {
                    $old_file_path = $upload_dir . $existing_file;
                    if (file_exists($old_file_path)) {
                        @unlink($old_file_path);
                    }
                }
                
                $file_extension = pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION);
                $new_filename = $type . '_' . time() . '_' . uniqid() . '.' . $file_extension;
                $target_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $target_path)) {
                    return $new_filename;
                }
            }
            
            // If delete checkbox is checked, remove the file
            if (isset($_POST['delete_' . $field_name])) {
                if (!empty($existing_file)) {
                    $old_file_path = $upload_dir . $existing_file;
                    if (file_exists($old_file_path)) {
                        @unlink($old_file_path);
                    }
                }
                return null;
            }
            
            // Keep existing file
            return $existing_file;
        }
        
        $ktp_image = handleDocumentUpload('ktp_image', 'ktp', $player['ktp_image'] ?? null);
        $kk_image = handleDocumentUpload('kk_image', 'kk', $player['kk_image'] ?? null);
        $birth_cert_image = handleDocumentUpload('birth_cert_image', 'akte', $player['birth_cert_image'] ?? null);
        $diploma_image = handleDocumentUpload('diploma_image', 'ijazah', $player['diploma_image'] ?? null);
        
        // Update player - PERBAIKAN: gunakan $gender_db
        $stmt = $conn->prepare("
            UPDATE players SET
                name = ?,
                birth_place = ?,
                birth_date = ?,
                sport_type = ?,
                gender = ?,
                nik = ?,
                nisn = ?,
                height = ?,
                weight = ?,
                email = ?,
                phone = ?,
                nationality = ?,
                street = ?,
                city = ?,
                province = ?,
                postal_code = ?,
                country = ?,
                team_id = ?,
                jersey_number = ?,
                dominant_foot = ?,
                position = ?,
                dribbling = ?,
                technique = ?,
                speed = ?,
                juggling = ?,
                shooting = ?,
                setplay_position = ?,
                passing = ?,
                control = ?,
                photo = ?,
                ktp_image = ?,
                kk_image = ?,
                birth_cert_image = ?,
                diploma_image = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $name, $birth_place, $birth_date, $sport_type, $gender_db,
            $nik, $nisn, $height, $weight, $email, $phone,
            $nationality, $street, $city, $province, $postal_code, $country,
            $team_id, $jersey_number, $dominant_foot, $position,
            $dribbling, $technique, $speed, $juggling, $shooting,
            $setplay_position, $passing, $control,
            $photo, $ktp_image, $kk_image, $birth_cert_image, $diploma_image,
            $player_id
        ]);
        
        $conn->commit();
        $_SESSION['success_message'] = "Player berhasil diperbarui!";
        header("Location: ../player.php");
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Player - FutScore</title>
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
    background: white;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    overflow: hidden;
}

/* Header */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px 30px;
    background: linear-gradient(135deg, var(--primary), #1a365d);
    color: white;
}

.back-btn {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.3);
    padding: 10px 20px;
    border-radius: 10px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
}

.back-btn:hover {
    background: var(--secondary);
    color: var(--primary);
    border-color: var(--secondary);
}

.page-title {
    font-size: 24px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-title i {
    color: var(--secondary);
}

/* Form Container */
.form-container {
    padding: 30px;
}

/* Tabs */
.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    border-bottom: 2px solid #f0f0f0;
    padding-bottom: 10px;
}

.tab-btn {
    padding: 12px 25px;
    background: #f8f9fa;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    color: var(--gray);
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 10px;
}

.tab-btn:hover {
    background: var(--primary);
    color: white;
}

.tab-btn.active {
    background: var(--primary);
    color: white;
    box-shadow: 0 5px 15px rgba(10, 36, 99, 0.2);
}

.tab-content {
    display: none;
    animation: fadeIn 0.5s ease-out;
}

.tab-content.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Form Grid */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--dark);
    font-size: 14px;
}

.form-label .required {
    color: var(--danger);
    margin-left: 3px;
}

/* Label khusus untuk KK wajib */
.form-label.kk-required {
    color: var(--danger);
}

.kk-required-label .required-field::after {
    content: " *";
    color: var(--danger);
    font-weight: bold;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 15px;
    transition: var(--transition);
    background: #f8f9fa;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.1);
}

select.form-control {
    cursor: pointer;
}

/* Radio Group */
.radio-group {
    display: flex;
    gap: 15px;
    margin-top: 10px;
}

.radio-option {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 8px 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    transition: var(--transition);
}

.radio-option:hover {
    border-color: var(--primary);
    background: #f8f9ff;
}

.radio-option input[type="radio"] {
    accent-color: var(--primary);
}

/* File Upload */
.file-upload {
    border: 2px dashed #e0e0e0;
    border-radius: 12px;
    padding: 25px;
    text-align: center;
    transition: var(--transition);
    cursor: pointer;
    background: #fafbff;
    margin-bottom: 15px;
}

.file-upload:hover {
    border-color: var(--primary);
    background: #f0f4ff;
}

/* Khusus untuk KK yang wajib */
.file-upload.kk-required {
    border-color: var(--danger) !important;
    border-style: dashed !important;
    border-width: 2px !important;
}

.kk-required-label .required-field::after {
    content: " *";
    color: var(--danger);
    font-weight: bold;
}

.kk-warning {
    color: var(--danger);
    font-size: 11px;
    font-weight: bold;
    margin-top: 5px;
}

.file-upload input[type="file"] {
    display: none;
}

.file-upload i {
    font-size: 40px;
    color: var(--primary);
    margin-bottom: 10px;
    opacity: 0.7;
}

/* Icon khusus untuk KK */
.kk-required i {
    color: var(--danger) !important;
}

/* Photo Preview */
.photo-preview {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    overflow: hidden;
    border: 4px solid white;
    box-shadow: var(--card-shadow);
    margin: 0 auto 20px;
}

.photo-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.default-photo {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, var(--secondary), #FFEC8B);
    display: flex;
    align-items: center;
    justify-content: center;
}

.default-photo i {
    font-size: 60px;
    color: var(--primary);
}

/* Document Preview */
.document-preview {
    margin-top: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 1px solid #e0e0e0;
}

.document-preview img {
    max-width: 100%;
    height: 120px;
    object-fit: contain;
    display: block;
    margin: 0 auto 15px;
    border-radius: 8px;
    border: 2px solid #e0e0e0;
    cursor: pointer;
    transition: var(--transition);
}

.document-preview img:hover {
    transform: scale(1.05);
    border-color: var(--primary);
}

.delete-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    font-size: 14px;
    color: var(--danger);
}

.delete-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--danger);
}

/* Skills */
.skills-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.skill-item {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
    border-left: 4px solid var(--primary);
}

.skill-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.skill-name {
    font-weight: 600;
    color: var(--dark);
}

.skill-value {
    color: var(--primary);
    font-weight: 700;
    font-size: 16px;
}

.skill-range {
    width: 100%;
    height: 10px;
    -webkit-appearance: none;
    background: #e0e0e0;
    border-radius: 5px;
    outline: none;
}

.skill-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--primary);
    cursor: pointer;
    border: 3px solid white;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #f0f0f0;
}

.btn {
    padding: 12px 25px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
    font-size: 15px;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: white;
    box-shadow: 0 5px 15px rgba(10, 36, 99, 0.2);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(10, 36, 99, 0.3);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-3px);
}

/* Alert */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 15px;
    animation: slideDown 0.3s ease-out;
}

.alert-danger {
    background: rgba(211, 47, 47, 0.1);
    border-left: 4px solid var(--danger);
    color: var(--danger);
}

/* NIK Feedback Styles - TAMBAHKAN INI */
.nik-feedback {
    margin-top: 5px;
    font-size: 12px;
    font-style: italic;
    padding: 3px 5px;
    border-radius: 3px;
    transition: all 0.3s ease;
}

.nik-feedback.error {
    color: var(--danger);
    background-color: rgba(211, 47, 47, 0.1);
}

.nik-feedback.warning {
    color: var(--warning);
    background-color: rgba(249, 168, 38, 0.1);
}

.nik-feedback.success {
    color: var(--success);
    background-color: rgba(46, 125, 50, 0.1);
}

/* KK Error Message */
.kk-error-message {
    display: none;
    background: linear-gradient(135deg, #FFE5E5 0%, #FFCCCC 100%);
    color: var(--danger);
    padding: 15px;
    border-radius: 10px;
    margin: 15px 0;
    border-left: 4px solid var(--danger);
    font-weight: 600;
    animation: slideDown 0.5s ease-out;
}

.kk-error-message.show {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Styling untuk scroll ke KK */
.kk-highlight {
    animation: kkBlink 1s ease-in-out 3;
    box-shadow: 0 0 20px rgba(211, 47, 47, 0.5) !important;
}

@keyframes kkBlink {
    0%, 100% { 
        box-shadow: 0 0 20px rgba(211, 47, 47, 0.5);
        border-color: var(--danger);
    }
    50% { 
        box-shadow: 0 0 30px rgba(211, 47, 47, 0.8);
        border-color: var(--danger);
        transform: scale(1.02);
    }
}

/* Note styling */
.note {
    font-size: 12px;
    color: var(--gray);
    margin-top: 5px;
    font-style: italic;
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
    
    .page-title {
        order: -1; /* Title first */
        font-size: 20px;
    }

    /* Tabs for Mobile */
    .tabs {
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .tab-btn {
        flex: 1;
        min-width: 120px;
        justify-content: center;
        padding: 8px 15px;
        font-size: 14px;
    }
    
    /* Form Layout */
    .form-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .form-actions {
        flex-direction: column;
        gap: 15px;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .skills-grid {
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
    
    .tab-btn {
        min-width: 100%;
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
                        if($current_page === 'edit.php' && $subUrl === '../player.php') {
                            $isActive = true;
                            $isSubmenuOpen = true;
                            break;
                        }
                    }
                } else {
                    // Untuk menu tanpa submenu
                    if ($current_page === 'edit.php' && $item['url'] === '../player.php') {
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
                           class="submenu-link <?php echo ($current_page === 'edit.php' && $subUrl === '../player.php') ? 'active' : ''; ?>">
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
                <p>Edit Player - Sistem manajemen pemain futsal</p>
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
                <a href="../player.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Kembali ke Players
                </a>
                <div class="page-title">
                    <i class="fas fa-edit"></i>
                    <span>Edit Player: <?php echo htmlspecialchars($player['name'] ?? ''); ?></span>
                </div>
                <div></div> <!-- Empty div for spacing -->
            </div>

            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error ?? ''); ?></span>
            </div>
            <?php endif; ?>

            <!-- Pesan error KK yang akan muncul jika KK belum ada -->
            <div class="kk-error-message" id="kkErrorMessage" style="display: none;">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>PERHATIAN!</strong> File Kartu Keluarga (KK) belum diupload.
                    <br><small>Jika menghapus file KK yang ada, harus upload file KK baru.</small>
                </div>
            </div>

            <!-- Form -->
            <form method="POST" action="" enctype="multipart/form-data" class="form-container" id="playerForm">
                <input type="hidden" name="player_id" value="<?php echo $player['id']; ?>">
                
                <!-- Tabs -->
                <div class="tabs">
                    <button type="button" class="tab-btn active" data-tab="profile">
                        <i class="fas fa-user-circle"></i>
                        Profile
                    </button>
                    <button type="button" class="tab-btn" data-tab="documents">
                        <i class="fas fa-file-alt"></i>
                        Dokumen
                    </button>
                    <button type="button" class="tab-btn" data-tab="skills">
                        <i class="fas fa-futbol"></i>
                        Info & Skills
                    </button>
                </div>

                <!-- Profile Tab -->
                <div class="tab-content active" id="profileTab">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <div class="photo-preview" id="photoPreview">
                                <?php if (!empty($player['photo'])): 
                                    $photo_path = '../../images/players/' . $player['photo'];
                                    if (file_exists($photo_path)): ?>
                                        <img src="<?php echo $photo_path; ?>" 
                                             alt="<?php echo htmlspecialchars($player['name'] ?? ''); ?>"
                                             id="currentPhoto">
                                    <?php else: ?>
                                        <div class="default-photo">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="default-photo">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="file-upload" onclick="document.getElementById('photo').click()">
                                <input type="file" id="photo" name="photo" accept="image/*" onchange="previewImage(this)">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Upload Photo Player Baru (Opsional)</p>
                                <small>Format: JPG, PNG | Maks: 5MB</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="name">Nama <span class="required">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?php echo htmlspecialchars($player['name'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="birth_place">Tempat Lahir <span class="required">*</span></label>
                            <input type="text" id="birth_place" name="birth_place" class="form-control" 
                                   value="<?php echo htmlspecialchars($player['birth_place'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="birth_date">Tanggal Lahir <span class="required">*</span></label>
                            <input type="date" id="birth_date" name="birth_date" class="form-control" 
                                   value="<?php echo $player['birth_date']; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="sport_type">Event <span class="required">*</span></label>
                            <?php
                            $selected_sport_type = $_POST['sport_type'] ?? ($player['sport_type'] ?? '');
                            ?>
                            <select id="sport_type" name="sport_type" class="form-control" required>
                                <option value="" <?php echo $selected_sport_type === '' ? 'selected' : ''; ?>>Pilih Event</option>
                                <?php 
                                $sports = [
                                        'LIGA AAFI BATAM U-13 PUTRA 2026',
                                        'LIGA AAFI BATAM U-16 PUTRA 2026',
                                        'LIGA AAFI BATAM U-16 PUTRI 2026'
                                    ];
                                if ($selected_sport_type !== '' && !in_array($selected_sport_type, $sports, true)) {
                                    $sports[] = $selected_sport_type;
                                }
                                foreach ($sports as $sport_option): 
                                ?>
                                    <option value="<?php echo htmlspecialchars($sport_option); ?>" <?php echo $selected_sport_type === $sport_option ? 'selected' : ''; ?>>
                                        <?php echo $sport_option; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="gender">Jenis Kelamin <span class="required">*</span></label>
                            <div class="radio-group">
                                <?php 
                                $gender_display = ($player['gender'] == 'L') ? 'Laki-laki' : 
                                                (($player['gender'] == 'P') ? 'Perempuan' : '');
                                ?>
                                <label class="radio-option">
                                    <input type="radio" name="gender" value="Laki-laki" 
                                           <?php echo $gender_display === 'Laki-laki' ? 'checked' : ''; ?> required>
                                    <span>Laki-laki</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="gender" value="Perempuan"
                                           <?php echo $gender_display === 'Perempuan' ? 'checked' : ''; ?> required>
                                    <span>Perempuan</span>
                                </label>
                            </div>
                        </div>

                        <!-- **INPUT NIK DENGAN VALIDASI 16 DIGIT** -->
                        <div class="form-group">
                            <label for="nik">NIK <span class="required">*</span></label>
                            <input type="text" 
                                   id="nik" 
                                   name="nik" 
                                   class="form-control" 
                                   value="<?php echo htmlspecialchars($player['nik'] ?? ''); ?>" 
                                   required 
                                   maxlength="16"
                                   pattern="[0-9]{16}"
                                   oninput="validateNIK(this.value)"
                                   title="NIK harus terdiri dari tepat 16 digit angka">
                            <div class="nik-feedback" id="nikFeedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="nisn">NISN</label>
                            <input type="text" id="nisn" name="nisn" class="form-control" 
                                   value="<?php echo htmlspecialchars($player['nisn'] ?? ''); ?>" maxlength="20">
                        </div>

                        <div class="form-group">
                            <label for="height">Tinggi (cm)</label>
                            <input type="number" id="height" name="height" class="form-control" 
                            value="<?php echo $player['height']; ?>">
                        </div>

                        <div class="form-group">
                            <label for="weight">Berat (kg)</label>
                            <input type="number" id="weight" name="weight" class="form-control" 
                            value="<?php echo $player['weight']; ?>">
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?php echo htmlspecialchars($player['email'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="phone">Telpon</label>
                            <input type="tel" id="phone" name="phone" class="form-control"
                                   value="<?php echo htmlspecialchars($player['phone'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="nationality">Kewarganegaraan</label>
                            <input type="text" id="nationality" name="nationality" class="form-control"
                                   value="<?php echo htmlspecialchars($player['nationality'] ?? ''); ?>">
                        </div>

                        <div class="form-group full-width">
                            <label for="street">Alamat - Jalan/No</label>
                            <input type="text" id="street" name="street" class="form-control"
                                   value="<?php echo htmlspecialchars($player['street'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="city">Kota</label>
                            <input type="text" id="city" name="city" class="form-control"
                                   value="<?php echo htmlspecialchars($player['city'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="province">Provinsi</label>
                            <input type="text" id="province" name="province" class="form-control"
                                   value="<?php echo htmlspecialchars($player['province'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="postal_code">Kode Pos</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-control"
                                   value="<?php echo htmlspecialchars($player['postal_code'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="country">Negara</label>
                            <input type="text" id="country" name="country" class="form-control"
                                   value="<?php echo htmlspecialchars($player['country'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Documents Tab -->
                <div class="tab-content" id="documentsTab">
                    <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 10px; border-left: 4px solid var(--primary);">
                        <strong><i class="fas fa-info-circle"></i> Note!</strong> Dokumen digunakan untuk memverifikasi keaslian data yang diberikan. 
                        Pastikan file yang diunggah asli, relevan, dan mudah dibaca. Hanya file berformat gambar yang diterima.
                    </div>

                    <div class="form-grid">
                        <!-- KTP -->
                        <div class="form-group">
                            <label class="form-label">KTP / KIA / Kartu Pelajar</label>
                            <div class="file-upload" onclick="document.getElementById('ktp_image').click()">
                                <input type="file" id="ktp_image" name="ktp_image" accept="image/*" onchange="previewKTP(this)">
                                <i class="fas fa-id-card"></i>
                                <p>Upload KTP/Kartu Identitas Baru</p>
                                <small>Format: JPG, PNG | Maks: 5MB</small>
                            </div>
                            
                            <?php if (!empty($player['ktp_image'])): 
                                $ktp_path = '../../images/players/' . $player['ktp_image'];
                                if (file_exists($ktp_path)): ?>
                                <div class="document-preview">
                                    <img src="<?php echo $ktp_path; ?>" 
                                         alt="KTP" onclick="viewDocument('<?php echo $ktp_path; ?>')"
                                         style="cursor: pointer;">
                                    <div class="delete-checkbox">
                                        <input type="checkbox" id="delete_ktp_image" name="delete_ktp_image" value="1">
                                        <label for="delete_ktp_image">Hapus dokumen ini</label>
                                    </div>
                                </div>
                            <?php endif; endif; ?>
                        </div>

                        <!-- KK - WAJIB DIUPLOAD -->
                        <div class="form-group">
                            <label class="form-label kk-required-label">
                                <span class="required-field">Kartu Keluarga</span>
                                <span class="note">Wajib diisi</span>
                            </label>
                            <div class="file-upload kk-required" id="kkUpload" onclick="document.getElementById('kk_image').click()">
                                <input type="file" id="kk_image" name="kk_image" accept="image/*" onchange="previewKK(this)">
                                <i class="fas fa-home"></i>
                                <p style="margin: 0; color: var(--danger); font-weight: bold;">Upload Kartu Keluarga Baru</p>
                                <p style="margin: 5px 0 0 0; font-size: 12px; color: var(--danger);">Maksimal 5MB</p>
                                <p class="kk-warning">* FILE KARTU KELUARGA WAJIB DIUPLOAD!</p>
                            </div>
                            
                            <?php if (!empty($player['kk_image'])): 
                                $kk_path = '../../images/players/' . $player['kk_image'];
                                if (file_exists($kk_path)): ?>
                                <div class="document-preview" id="kkPreview">
                                    <img src="<?php echo $kk_path; ?>" 
                                         alt="KK" onclick="viewDocument('<?php echo $kk_path; ?>')"
                                         style="cursor: pointer;">
                                    <div class="delete-checkbox">
                                        <input type="checkbox" id="delete_kk_image" name="delete_kk_image" value="1" onchange="checkKKRequirement()">
                                        <label for="delete_kk_image">Hapus dokumen ini</label>
                                    </div>
                                    <p class="note" style="color: var(--success); margin-top: 5px;">
                                        <i class="fas fa-check-circle"></i> File KK tersedia
                                    </p>
                                </div>
                            <?php endif; endif; ?>
                        </div>

                        <!-- Akta Lahir -->
                        <div class="form-group">
                            <label class="form-label">Akta Lahir / Surat Ket. Lahir</label>
                            <div class="file-upload" onclick="document.getElementById('birth_cert_image').click()">
                                <input type="file" id="birth_cert_image" name="birth_cert_image" accept="image/*" onchange="previewAkte(this)">
                                <i class="fas fa-baby"></i>
                                <p>Upload Akta Lahir Baru</p>
                                <small>Format: JPG, PNG | Maks: 5MB</small>
                            </div>
                            
                            <?php if (!empty($player['birth_cert_image'])): 
                                $akte_path = '../../images/players/' . $player['birth_cert_image'];
                                if (file_exists($akte_path)): ?>
                                <div class="document-preview">
                                    <img src="<?php echo $akte_path; ?>" 
                                         alt="Akta Lahir" onclick="viewDocument('<?php echo $akte_path; ?>')"
                                         style="cursor: pointer;">
                                    <div class="delete-checkbox">
                                        <input type="checkbox" id="delete_birth_cert_image" name="delete_birth_cert_image" value="1">
                                        <label for="delete_birth_cert_image">Hapus dokumen ini</label>
                                    </div>
                                </div>
                            <?php endif; endif; ?>
                        </div>

                        <!-- Ijazah -->
                        <div class="form-group">
                            <label class="form-label">Ijazah / Biodata Raport / Kartu NISN</label>
                            <div class="file-upload" onclick="document.getElementById('diploma_image').click()">
                                <input type="file" id="diploma_image" name="diploma_image" accept="image/*" onchange="previewIjazah(this)">
                                <i class="fas fa-graduation-cap"></i>
                                <p>Upload Ijazah/Raport Baru</p>
                                <small>Format: JPG, PNG | Maks: 5MB</small>
                            </div>
                            
                            <?php if (!empty($player['diploma_image'])): 
                                $ijazah_path = '../../images/players/' . $player['diploma_image'];
                                if (file_exists($ijazah_path)): ?>
                                <div class="document-preview">
                                    <img src="<?php echo $ijazah_path; ?>" 
                                         alt="Ijazah" onclick="viewDocument('<?php echo $ijazah_path; ?>')"
                                         style="cursor: pointer;">
                                    <div class="delete-checkbox">
                                        <input type="checkbox" id="delete_diploma_image" name="delete_diploma_image" value="1">
                                        <label for="delete_diploma_image">Hapus dokumen ini</label>
                                    </div>
                                </div>
                            <?php endif; endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Skills Tab -->
                <div class="tab-content" id="skillsTab">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="team_id">Team</label>
                            <select id="team_id" name="team_id" class="form-control">
                                <option value="">Pilih Team</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>"
                                        <?php echo $player['team_id'] == $team['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($team['name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="jersey_number">No Punggung</label>
                            <input type="number" id="jersey_number" name="jersey_number" class="form-control" 
                                   value="<?php echo $player['jersey_number']; ?>" min="1" max="99">
                        </div>

                        <div class="form-group">
                            <label for="dominant_foot">Kaki Dominan</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="dominant_foot" value="Kanan" 
                                           <?php echo strtolower($player['dominant_foot']) === 'kanan' ? 'checked' : ''; ?>>
                                    <span>Kanan</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="dominant_foot" value="Kiri"
                                           <?php echo strtolower($player['dominant_foot']) === 'kiri' ? 'checked' : ''; ?>>
                                    <span>Kiri</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="dominant_foot" value="Kedua"
                                           <?php echo strtolower($player['dominant_foot']) === 'kedua' ? 'checked' : ''; ?>>
                                    <span>Kedua-duanya</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="position">Posisi</label>
                            <select id="position" name="position" class="form-control">
                                <option value="">Pilih Posisi</option>
                                <option value="GK" <?php echo $player['position'] === 'GK' ? 'selected' : ''; ?>>Kiper (GK)</option>
                                <option value="DF" <?php echo $player['position'] === 'DF' ? 'selected' : ''; ?>>Bek (DF)</option>
                                <option value="MF" <?php echo $player['position'] === 'MF' ? 'selected' : ''; ?>>Gelandang (MF)</option>
                                <option value="FW" <?php echo $player['position'] === 'FW' ? 'selected' : ''; ?>>Penyerang (FW)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Skills Section -->
                    <div style="margin-top: 30px;">
                        <h3 style="color: var(--primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-chart-line"></i>
                            Player Skills (Range: 0-10)
                        </h3>
                        
                        <div class="skills-grid">
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
                                $value = $player[$key] ?? 5;
                            ?>
                            <div class="skill-item">
                                <div class="skill-header">
                                    <span class="skill-name"><?php echo $label; ?></span>
                                    <span class="skill-value" id="<?php echo $key; ?>Value"><?php echo $value; ?></span>
                                </div>
                                <input type="range" class="skill-range" id="<?php echo $key; ?>" name="<?php echo $key; ?>" 
                                       min="0" max="10" value="<?php echo $value; ?>" step="1"
                                       oninput="document.getElementById('<?php echo $key; ?>Value').textContent = this.value">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="../player.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Batal
                    </a>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i>
                        Update Player
                    </button>
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

// Tab Navigation
document.querySelectorAll('.tab-btn').forEach(button => {
    button.addEventListener('click', () => {
        const tabId = button.getAttribute('data-tab');
        
        // Update active tab button
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        button.classList.add('active');
        
        // Show selected tab content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(tabId + 'Tab').classList.add('active');
    });
});

// Photo Preview
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('photoPreview');
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Document Previews
function previewKTP(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const ktpContainer = input.closest('.form-group').querySelector('.document-preview') || 
                               createDocumentPreview(input, 'KTP');
            ktpContainer.innerHTML = `
                <img src="${e.target.result}" alt="KTP Preview">
                <div class="delete-checkbox">
                    <input type="checkbox" id="delete_ktp_image" name="delete_ktp_image" value="1">
                    <label for="delete_ktp_image">Hapus dokumen ini</label>
                </div>
            `;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function previewKK(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const kkContainer = input.closest('.form-group').querySelector('.document-preview') || 
                              createDocumentPreview(input, 'KK');
            kkContainer.innerHTML = `
                <img src="${e.target.result}" alt="KK Preview">
                <div class="delete-checkbox">
                    <input type="checkbox" id="delete_kk_image" name="delete_kk_image" value="1" onchange="checkKKRequirement()">
                    <label for="delete_kk_image">Hapus dokumen ini</label>
                </div>
            `;
            
            // Update tampilan upload area KK
            const kkUpload = document.getElementById('kkUpload');
            if (kkUpload) {
                kkUpload.classList.remove('kk-required');
                kkUpload.style.borderColor = 'var(--success)';
                kkUpload.style.borderStyle = 'solid';
            }
            
            // Sembunyikan pesan error KK
            hideKKErrorMessage();
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function previewAkte(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const akteContainer = input.closest('.form-group').querySelector('.document-preview') || 
                                createDocumentPreview(input, 'Akta Lahir');
            akteContainer.innerHTML = `
                <img src="${e.target.result}" alt="Akta Lahir Preview">
                <div class="delete-checkbox">
                    <input type="checkbox" id="delete_birth_cert_image" name="delete_birth_cert_image" value="1">
                    <label for="delete_birth_cert_image">Hapus dokumen ini</label>
                </div>
            `;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function previewIjazah(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const ijazahContainer = input.closest('.form-group').querySelector('.document-preview') || 
                                  createDocumentPreview(input, 'Ijazah');
            ijazahContainer.innerHTML = `
                <img src="${e.target.result}" alt="Ijazah Preview">
                <div class="delete-checkbox">
                    <input type="checkbox" id="delete_diploma_image" name="delete_diploma_image" value="1">
                    <label for="delete_diploma_image">Hapus dokumen ini</label>
                </div>
            `;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function createDocumentPreview(input, title) {
    const container = document.createElement('div');
    container.className = 'document-preview';
    container.innerHTML = `<h4>${title} Preview</h4>`;
    input.closest('.form-group').appendChild(container);
    return container;
}

// View Document
function viewDocument(imagePath) {
    window.open(imagePath, '_blank');
}

// **FUNGSI VALIDASI NIK 16 DIGIT**
function validateNIK(value) {
    const nikInput = document.getElementById('nik');
    const nikFeedback = document.getElementById('nikFeedback');
    const submitBtn = document.getElementById('submitBtn');
    
    // Hanya izinkan angka
    const numericValue = value.replace(/[^0-9]/g, '');
    nikInput.value = numericValue.slice(0, 16);
    
    const length = numericValue.length;
    
    // Update feedback text dan warna
    const nikFeedbackElement = document.getElementById('nikFeedback');
    
    if (length === 0) {
        nikFeedbackElement.textContent = 'NIK harus diisi - 16 digit angka';
        nikFeedbackElement.className = 'nik-feedback warning';
        nikInput.style.borderColor = '#e0e0e0';
        submitBtn.disabled = false;
    } else if (length < 16) {
        nikFeedbackElement.textContent = `Kurang ${16 - length} digit (${length}/16)`;
        nikFeedbackElement.className = 'nik-feedback warning';
        nikInput.style.borderColor = 'var(--warning)';
        submitBtn.disabled = false;
    } else if (length > 16) {
        nikFeedbackElement.textContent = 'Terlalu panjang! Maksimal 16 digit';
        nikFeedbackElement.className = 'nik-feedback error';
        nikInput.style.borderColor = 'var(--danger)';
        submitBtn.disabled = true;
    } else {
        // Cek apakah semua karakter adalah angka
        const isValid = /^[0-9]{16}$/.test(numericValue);
        if (isValid) {
            nikFeedbackElement.textContent = '✓ 16 digit valid';
            nikFeedbackElement.className = 'nik-feedback success';
            nikInput.style.borderColor = 'var(--success)';
            submitBtn.disabled = false;
        } else {
            nikFeedbackElement.textContent = 'Hanya boleh angka 0-9';
            nikFeedbackElement.className = 'nik-feedback error';
            nikInput.style.borderColor = 'var(--danger)';
            submitBtn.disabled = true;
        }
    }
    
    // Cegah input lebih dari 16 karakter
    if (numericValue.length > 16) {
        nikInput.value = numericValue.substring(0, 16);
    }
}

// **FUNGSI UNTUK VALIDASI KK**
function checkKKRequirement() {
    const deleteKKCheckbox = document.getElementById('delete_kk_image');
    const kkFileInput = document.getElementById('kk_image');
    const kkUpload = document.getElementById('kkUpload');
    const kkPreview = document.getElementById('kkPreview');
    
    // Cek apakah checkbox hapus KK dicentang
    const deleteChecked = deleteKKCheckbox ? deleteKKCheckbox.checked : false;
    
    // Cek apakah ada file yang diupload
    const hasFileUpload = kkFileInput && kkFileInput.files && kkFileInput.files.length > 0;
    
    // Cek apakah ada preview KK (berarti file sudah ada di database)
    const hasExistingFile = kkPreview ? true : false;
    
    // Logika validasi:
    // 1. Jika ada file yang sudah ada DAN tidak dicentang hapus → OK
    // 2. Jika ada upload file baru → OK
    // 3. Jika tidak ada file yang sudah ada DAN tidak ada upload baru → ERROR
    // 4. Jika ada file yang sudah ada tapi dicentang hapus DAN tidak ada upload baru → ERROR
    
    if (hasExistingFile && !deleteChecked) {
        // File KK ada di database dan tidak dihapus → OK
        hideKKErrorMessage();
        if (kkUpload) {
            kkUpload.classList.remove('kk-required');
            kkUpload.style.borderColor = 'var(--success)';
            kkUpload.style.borderStyle = 'solid';
        }
    } else if (hasFileUpload) {
        // Ada upload file KK baru → OK
        hideKKErrorMessage();
        if (kkUpload) {
            kkUpload.classList.remove('kk-required');
            kkUpload.style.borderColor = 'var(--success)';
            kkUpload.style.borderStyle = 'solid';
        }
    } else if (hasExistingFile && deleteChecked && !hasFileUpload) {
        // File KK ada di database, dicentang hapus, dan tidak ada upload baru → ERROR
        showKKErrorMessage();
        if (kkUpload) {
            kkUpload.classList.add('kk-required');
            kkUpload.style.borderColor = 'var(--danger)';
            kkUpload.style.borderStyle = 'dashed';
        }
    } else if (!hasExistingFile && !hasFileUpload) {
        // Tidak ada file KK di database dan tidak ada upload baru → ERROR
        showKKErrorMessage();
        if (kkUpload) {
            kkUpload.classList.add('kk-required');
            kkUpload.style.borderColor = 'var(--danger)';
            kkUpload.style.borderStyle = 'dashed';
        }
    }
}

// Fungsi untuk menampilkan pesan error KK
function showKKErrorMessage() {
    const errorMessage = document.getElementById('kkErrorMessage');
    if (errorMessage) {
        errorMessage.style.display = 'flex';
    }
}

// Fungsi untuk menyembunyikan pesan error KK
function hideKKErrorMessage() {
    const errorMessage = document.getElementById('kkErrorMessage');
    if (errorMessage) {
        errorMessage.style.display = 'none';
    }
}

// Set max date for birth date
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const birthDateInput = document.getElementById('birth_date');
    if (birthDateInput) {
        birthDateInput.max = today;
    }
    
    // **INISIALISASI VALIDASI NIK**
    const nikInput = document.getElementById('nik');
    if (nikInput) {
        // Validasi real-time saat mengetik
        nikInput.addEventListener('input', function(e) {
            validateNIK(e.target.value);
        });
        
        // Cegah karakter non-numeric
        nikInput.addEventListener('keypress', function(e) {
            const charCode = e.which ? e.which : e.keyCode;
            if (charCode < 48 || charCode > 57) {
                e.preventDefault();
            }
        });
        
        // Cegah paste karakter non-numeric
        nikInput.addEventListener('paste', function(e) {
            const pastedData = e.clipboardData.getData('text');
            if (!/^\d*$/.test(pastedData)) {
                e.preventDefault();
            }
        });
        
        // Validasi saat halaman dimuat
        if (nikInput.value) {
            validateNIK(nikInput.value);
        }
    }
    
    // Format NISN input (numbers only)
    const nisnInput = document.getElementById('nisn');
    if (nisnInput) {
        nisnInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 20) {
                value = value.substring(0, 20);
            }
            e.target.value = value;
        });
    }
    
    // File upload validation
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Max 5MB
                if (file.size > 5 * 1024 * 1024) {
                    alert(`File ${file.name} terlalu besar! Maksimal 5MB`);
                    this.value = '';
                    return;
                }
                
                // Validate image types
                const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!validTypes.includes(file.type)) {
                    alert(`Format file ${file.name} tidak didukung! Hanya JPG, PNG, GIF`);
                    this.value = '';
                    return;
                }
            }
        });
    });
    
    // **INISIALISASI VALIDASI KK**
    const deleteKKCheckbox = document.getElementById('delete_kk_image');
    if (deleteKKCheckbox) {
        deleteKKCheckbox.addEventListener('change', checkKKRequirement);
    }
    
    const kkFileInput = document.getElementById('kk_image');
    if (kkFileInput) {
        kkFileInput.addEventListener('change', function() {
            // Tunggu sebentar untuk memastikan file sudah diproses
            setTimeout(checkKKRequirement, 100);
        });
    }
    
    // Jalankan validasi KK saat halaman dimuat
    checkKKRequirement();
    
    // **FORM VALIDATION**
    const playerForm = document.getElementById('playerForm');
    if (playerForm) {
        playerForm.addEventListener('submit', function(e) {
            const nik = document.getElementById('nik').value;
            
            // Validasi NIK 16 digit sebelum submit
            const nikRegex = /^[0-9]{16}$/;
            if (!nikRegex.test(nik)) {
                e.preventDefault();
                alert('NIK harus terdiri dari tepat 16 digit angka!');
                document.getElementById('nik').focus();
                return false;
            }
            
            // **VALIDASI KK SEBELUM SUBMIT**
            const deleteKKCheckbox = document.getElementById('delete_kk_image');
            const kkFileInput = document.getElementById('kk_image');
            const kkPreview = document.getElementById('kkPreview');
            
            const deleteChecked = deleteKKCheckbox ? deleteKKCheckbox.checked : false;
            const hasFileUpload = kkFileInput && kkFileInput.files && kkFileInput.files.length > 0;
            const hasExistingFile = kkPreview ? true : false;
            
            // Logika validasi KK
            if (hasExistingFile && !deleteChecked) {
                // OK: Ada file di database dan tidak dihapus
            } else if (hasFileUpload) {
                // OK: Ada upload file baru
            } else if (hasExistingFile && deleteChecked && !hasFileUpload) {
                // ERROR: File dihapus tapi tidak ada upload baru
                e.preventDefault();
                
                // Tampilkan pesan error
                showKKErrorMessage();
                
                // Pindah ke tab dokumen
                document.querySelector('[data-tab="documents"]').click();
                
                // Scroll ke KK
                const kkUpload = document.getElementById('kkUpload');
                if (kkUpload) {
                    kkUpload.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center' 
                    });
                    
                    // Highlight dengan animasi
                    kkUpload.classList.add('kk-highlight');
                    
                    // Hapus highlight setelah 3 detik
                    setTimeout(() => {
                        kkUpload.classList.remove('kk-highlight');
                    }, 3000);
                }
                
                return false;
            } else if (!hasExistingFile && !hasFileUpload) {
                // ERROR: Tidak ada file sama sekali
                e.preventDefault();
                
                // Tampilkan pesan error
                showKKErrorMessage();
                
                // Pindah ke tab dokumen
                document.querySelector('[data-tab="documents"]').click();
                
                // Scroll ke KK
                const kkUpload = document.getElementById('kkUpload');
                if (kkUpload) {
                    kkUpload.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center' 
                    });
                    
                    // Highlight dengan animasi
                    kkUpload.classList.add('kk-highlight');
                    
                    // Hapus highlight setelah 3 detik
                    setTimeout(() => {
                        kkUpload.classList.remove('kk-highlight');
                    }, 3000);
                }
                
                alert('❌ PERHATIAN!\n\nFile Kartu Keluarga (KK) belum diupload!\n\nSilakan upload file KK terlebih dahulu untuk melanjutkan.\n\nFile KK wajib diisi untuk verifikasi data player.');
                return false;
            }
            
            // Validasi field wajib lainnya
            const requiredFields = ['name', 'birth_place', 'birth_date', 'sport_type', 'gender'];
            for (const field of requiredFields) {
                const element = document.querySelector(`[name="${field}"]`);
                if (element && !element.value.trim()) {
                    e.preventDefault();
                    alert('Harap lengkapi semua field yang wajib diisi!');
                    element.focus();
                    return false;
                }
            }
        });
    }
    
    // Tambahkan efek pulsating untuk menarik perhatian ke KK
    const kkUpload = document.getElementById('kkUpload');
    if (kkUpload) {
        setInterval(() => {
            // Cek jika KK masih dalam kondisi error (wajib diupload)
            const deleteKKCheckbox = document.getElementById('delete_kk_image');
            const kkFileInput = document.getElementById('kk_image');
            const kkPreview = document.getElementById('kkPreview');
            
            const deleteChecked = deleteKKCheckbox ? deleteKKCheckbox.checked : false;
            const hasFileUpload = kkFileInput && kkFileInput.files && kkFileInput.files.length > 0;
            const hasExistingFile = kkPreview ? true : false;
            
            const needsAttention = (!hasExistingFile && !hasFileUpload) || 
                                  (hasExistingFile && deleteChecked && !hasFileUpload);
            
            if (needsAttention) {
                kkUpload.style.boxShadow = kkUpload.style.boxShadow ? 
                    '' : '0 0 15px rgba(211, 47, 47, 0.3)';
            }
        }, 1500);
        
        // Tambahkan tooltip hover
        kkUpload.addEventListener('mouseenter', function() {
            this.title = "⚠ FILE KARTU KELUARGA WAJIB DIUPLOAD!\nKlik untuk memilih file KK";
        });
    }
    
    // Tambahkan event listener untuk tombol submit agar memberikan feedback
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            // Cek kondisi KK
            const deleteKKCheckbox = document.getElementById('delete_kk_image');
            const kkFileInput = document.getElementById('kk_image');
            const kkPreview = document.getElementById('kkPreview');
            
            const deleteChecked = deleteKKCheckbox ? deleteKKCheckbox.checked : false;
            const hasFileUpload = kkFileInput && kkFileInput.files && kkFileInput.files.length > 0;
            const hasExistingFile = kkPreview ? true : false;
            
            const kkError = (!hasExistingFile && !hasFileUpload) || 
                           (hasExistingFile && deleteChecked && !hasFileUpload);
            
            if (kkError) {
                // Berikan feedback visual pada tombol
                this.style.background = 'linear-gradient(135deg, var(--warning), #F9A826)';
                this.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Cek KK Terlebih Dahulu!';
                
                // Kembalikan setelah 2 detik
                setTimeout(() => {
                    this.style.background = 'linear-gradient(135deg, var(--primary), var(--accent))';
                    this.innerHTML = '<i class="fas fa-save"></i> Update Player';
                }, 2000);
            }
        });
    }
});
</script>
</body>
</html>
