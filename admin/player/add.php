<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

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
        /* CSS styles remain the same as in your original file */
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
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
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
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
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

        .header .breadcrumb {
            display: flex;
            gap: 10px;
            font-size: 14px;
            color: var(--gray);
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .breadcrumb .separator {
            color: var(--gray);
        }

        .form-container {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
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

        .required {
            color: var(--danger);
            font-weight: bold;
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

        .form-control.error {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.1);
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

        .radio-option input[type="radio"] {
            accent-color: var(--primary);
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

        .slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary);
            cursor: pointer;
            border: none;
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

        .alert-icon {
            font-size: 20px;
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

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .date-input {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 15px;
            }
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
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-user-plus"></i> Tambah Player Baru</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span class="separator">/</span>
                <a href="../player.php">Player</a>
                <span class="separator">/</span>
                <span>Tambah</span>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <span class="alert-icon">⚠</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✓</span>
                <span><?php echo htmlspecialchars($_SESSION['success_message']); 
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
                                        <?php echo htmlspecialchars($team['name']); ?>
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

    <script>
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